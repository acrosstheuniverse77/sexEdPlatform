<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ModulePurchase;
use App\Models\Payment;
use App\Models\User;
use App\Services\Instructor\InstructorPlanCapabilityService;
use App\Services\Monetization\ModuleSaleLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModulePurchaseService
{
    public function __construct(
        private readonly PayMongoPaymentLinkService $payMongoPaymentLinkService,
        private readonly ModuleSaleLedgerService $moduleSaleLedgerService,
        private readonly InstructorPlanCapabilityService $instructorPlanCapabilityService,
    ) {
    }

    public function hasCompletedPurchase(User $user, Module $module): bool
    {
        return ModulePurchase::query()
            ->where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('status', ModulePurchase::STATUS_COMPLETED)
            ->exists();
    }

    public function getCompletedPurchase(User $user, Module $module): ?ModulePurchase
    {
        return ModulePurchase::query()
            ->where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('status', ModulePurchase::STATUS_COMPLETED)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{status:string, checkout_url?:string, payment_id?:int, message:string}
     */
    public function createCheckout(User $user, Module $module, ?string $paymentMethod, array $billing = []): array
    {
        if ($module->isOwnedBy($user)) {
            return [
                'status' => 'owned_by_user',
                'message' => 'You cannot purchase a module that you own.',
            ];
        }

        if (!$this->canReceivePaidEnrollments($module)) {
            return [
                'status' => 'paid_enrollment_unavailable',
                'message' => 'Paid enrollment is currently unavailable for this module.',
            ];
        }

        if ($this->hasCompletedPurchase($user, $module)) {
            return [
                'status' => 'already_purchased',
                'message' => 'You have already purchased this module.',
            ];
        }

        try {
            return DB::transaction(function () use ($user, $module, $paymentMethod, $billing) {
                $billingName = (string) ($billing['name'] ?? $user->name);
                $billingEmail = (string) ($billing['email'] ?? $user->email ?? '');
                $billingPhone = (string) ($billing['phone'] ?? '');

                $storedPaymentMethod = $paymentMethod ?: 'gcash';

                $purchase = ModulePurchase::query()
                    ->firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'module_id' => $module->id,
                            'status' => ModulePurchase::STATUS_PENDING,
                        ],
                        [
                            'amount' => (float) ($module->price_amount ?? 0),
                            'currency' => strtoupper((string) ($module->price_currency ?? 'PHP')),
                            'metadata' => [],
                        ]
                    );

                $payment = Payment::query()
                    ->where('user_id', $user->id)
                    ->whereNull('subscription_id')
                    ->where('status', PaymentStatus::Pending)
                    ->where('payment_details->payment_scope', 'module_purchase')
                    ->where('payment_details->module_purchase_id', $purchase->id)
                    ->latest('id')
                    ->first();

                if (!$payment) {
                    $payment = Payment::query()->create([
                        'user_id' => $user->id,
                        'subscription_id' => null,
                        'amount' => (float) ($module->price_amount ?? 0),
                        'method' => $storedPaymentMethod,
                        'status' => PaymentStatus::Pending,
                        'transaction_id' => 'MOD-' . strtoupper(uniqid()),
                        'payment_details' => [
                            'payment_scope' => 'module_purchase',
                            'payment_method' => $storedPaymentMethod,
                            'module_id' => $module->id,
                            'module_title' => $module->title,
                            'module_purchase_id' => $purchase->id,
                            'billing' => [
                                'name' => $billingName,
                                'email' => $billingEmail,
                                'phone' => $billingPhone,
                            ],
                        ],
                    ]);
                } else {
                    $payment->update([
                        'amount' => (float) ($module->price_amount ?? 0),
                        'method' => $storedPaymentMethod,
                        'payment_details' => array_merge($payment->payment_details ?? [], [
                            'payment_scope' => 'module_purchase',
                            'payment_method' => $storedPaymentMethod,
                            'module_id' => $module->id,
                            'module_title' => $module->title,
                            'module_purchase_id' => $purchase->id,
                            'billing' => [
                                'name' => $billingName,
                                'email' => $billingEmail,
                                'phone' => $billingPhone,
                            ],
                        ]),
                    ]);
                }

                $response = $this->payMongoPaymentLinkService->createCheckoutSession(
                    amount: (float) ($module->price_amount ?? 0),
                    description: 'Module Purchase: ' . $module->title,
                    remarks: 'Module purchase for ' . $user->name,
                    metadata: [
                        'payment_scope' => 'module_purchase',
                        'module_id' => $module->id,
                        'module_purchase_id' => $purchase->id,
                        'user_id' => $user->id,
                        'payment_id' => $payment->id,
                        'billing_name' => $billingName,
                        'billing_email' => $billingEmail,
                    ],
                    successUrl: route('payment.success', [
                        'scope' => 'module_purchase',
                        'module_id' => $module->id,
                        'payment_id' => $payment->id,
                    ]),
                    cancelUrl: route('payment.cancel', [
                        'scope' => 'module_purchase',
                        'module_id' => $module->id,
                        'payment_id' => $payment->id,
                    ]),
                    preferredPaymentMethod: $paymentMethod,
                    lineItemName: $module->title,
                );

                $checkoutUrl = (string) data_get($response, 'data.attributes.checkout_url', '');
                $checkoutSessionId = data_get($response, 'data.id');

                if ($checkoutUrl === '') {
                    throw new \RuntimeException('PayMongo did not return checkout URL.');
                }

                $payment->update([
                    'payment_details' => array_merge($payment->payment_details ?? [], [
                        'paymongo_checkout_session_id' => $checkoutSessionId,
                        'checkout_url' => $checkoutUrl,
                        'payment_method' => $storedPaymentMethod,
                        'billing' => [
                            'name' => $billingName,
                            'email' => $billingEmail,
                            'phone' => $billingPhone,
                        ],
                    ]),
                ]);

                $purchase->update([
                    'payment_id' => $payment->id,
                    'metadata' => array_merge($purchase->metadata ?? [], [
                        'paymongo_checkout_session_id' => $checkoutSessionId,
                    ]),
                ]);

                return [
                    'status' => 'checkout_created',
                    'checkout_url' => $checkoutUrl,
                    'payment_id' => $payment->id,
                    'message' => 'Redirecting to secure checkout.',
                ];
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'uq_module_purchases_user_module_status')) {
                $pending = ModulePurchase::query()
                    ->where('user_id', $user->id)
                    ->where('module_id', $module->id)
                    ->where('status', ModulePurchase::STATUS_PENDING)
                    ->latest('id')
                    ->first();

                $checkoutUrl = data_get($pending, 'payment.payment_details.checkout_url');
                if (is_string($checkoutUrl) && $checkoutUrl !== '') {
                    return [
                        'status' => 'checkout_created',
                        'checkout_url' => $checkoutUrl,
                        'payment_id' => (int) data_get($pending, 'payment.id'),
                        'message' => 'Continuing your pending checkout session.',
                    ];
                }
            }

            throw $exception;
        }
    }

    public function completePayment(Payment $payment, ?string $paymentMethod = null, ?string $paymongoPaymentId = null): bool
    {
        $scope = (string) data_get($payment->payment_details, 'payment_scope');
        if ($scope !== 'module_purchase') {
            return false;
        }

        $moduleId = (int) data_get($payment->payment_details, 'module_id');
        $purchaseId = data_get($payment->payment_details, 'module_purchase_id');

        if ($moduleId <= 0) {
            return false;
        }

        $module = Module::query()->find($moduleId);
        if ($module && $module->isOwnedBy($payment->user)) {
            return false;
        }

        return DB::transaction(function () use ($payment, $paymentMethod, $paymongoPaymentId, $moduleId, $purchaseId, $module) {
            if ($payment->status !== PaymentStatus::Completed) {
                $payment->update([
                    'status' => PaymentStatus::Completed,
                    'method' => $paymentMethod ?? $payment->method,
                    'paid_at' => now(),
                    'payment_details' => array_merge($payment->payment_details ?? [], [
                        'paymongo_payment_id' => $paymongoPaymentId,
                        'completed_via' => 'module_purchase_service',
                    ]),
                ]);
            }

            $purchaseQuery = ModulePurchase::query()
                ->where('user_id', $payment->user_id)
                ->where('module_id', $moduleId);

            if ($purchaseId) {
                $purchaseQuery->where('id', (int) $purchaseId);
            }

            $purchase = $purchaseQuery->latest('id')->first();
            if (!$purchase) {
                $purchase = ModulePurchase::query()->create([
                    'user_id' => $payment->user_id,
                    'module_id' => $moduleId,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => 'PHP',
                    'status' => ModulePurchase::STATUS_PENDING,
                ]);
            }

            $alreadyCompleted = $purchase->isCompleted();

            if (!$alreadyCompleted) {
                $purchase->update([
                    'payment_id' => $payment->id,
                    'status' => ModulePurchase::STATUS_COMPLETED,
                    'purchased_at' => now(),
                    'metadata' => array_merge($purchase->metadata ?? [], [
                        'payment_id' => $payment->id,
                    ]),
                ]);
            }

            if ($module) {
                $this->ensureEnrollmentAfterPurchase($payment->user_id, $module);
            }

            try {
                $this->moduleSaleLedgerService->createForCompletedModulePayment($payment->fresh(['user']));
            } catch (\Throwable $e) {
                Log::warning('Module sale ledger creation skipped', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        });
    }

    public function verifyAndCompletePendingPayment(Payment $payment): bool
    {
        $scope = (string) data_get($payment->payment_details, 'payment_scope');
        if ($scope !== 'module_purchase') {
            return false;
        }

        $sessionId = (string) data_get($payment->payment_details, 'paymongo_checkout_session_id', '');
        $linkId = (string) data_get($payment->payment_details, 'paymongo_link_id', '');

        if ($sessionId === '' && $linkId === '') {
            return false;
        }

        $response = $sessionId !== ''
            ? $this->payMongoPaymentLinkService->retrieveCheckoutSession($sessionId)
            : $this->payMongoPaymentLinkService->retrievePaymentLink($linkId);

        $status = strtolower((string) data_get($response, 'data.attributes.status', ''));
        $hasPayments = !empty(data_get($response, 'data.attributes.payments', []));

        if ($status !== 'paid' && $status !== 'completed' && !$hasPayments) {
            return false;
        }

        $actualPaymentId = $sessionId !== ''
            ? $this->payMongoPaymentLinkService->getActualPaymentIdFromCheckoutSession($sessionId)
            : $this->payMongoPaymentLinkService->getActualPaymentIdFromLink($linkId);

        return $this->completePayment($payment, $payment->method, $actualPaymentId);
    }

    private function ensureEnrollmentAfterPurchase(int $userId, Module $module): void
    {
        if (!$this->canReceivePaidEnrollments($module)) {
            return;
        }

        $enrollment = ModuleEnrollment::query()
            ->where('user_id', $userId)
            ->where('module_id', $module->id)
            ->first();

        if (!$enrollment) {
            $status = $module->enrollment_mode === 'manual'
                ? EnrollmentStatus::Pending
                : EnrollmentStatus::Approved;

            ModuleEnrollment::query()->create([
                'user_id' => $userId,
                'module_id' => $module->id,
                'status' => $status,
                'enrolled_at' => $status === EnrollmentStatus::Approved ? now() : null,
            ]);

            return;
        }

        if ($module->enrollment_mode !== 'manual' && $enrollment->status === EnrollmentStatus::Pending) {
            $enrollment->update([
                'status' => EnrollmentStatus::Approved,
                'enrolled_at' => now(),
            ]);
        }
    }

    private function canReceivePaidEnrollments(Module $module): bool
    {
        if (!$module->isPaidAccess()) {
            return true;
        }

        if ((string) ($module->content_owner_type ?? '') !== 'instructor') {
            return true;
        }

        $module->loadMissing('creator');
        $instructor = $module->creator;

        if (!$instructor) {
            return false;
        }

        return $this->instructorPlanCapabilityService->canReceivePaidEnrollments($instructor);
    }
}
