<?php

namespace Tests\Feature\Learner;

use App\Enums\PaymentStatus;
use App\Events\PaymentSuccessful;
use App\Http\Middleware\VerifyPayMongoWebhook;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendPaymentReceiptEmail;
use App\Listeners\HandlePaymentSuccessful;
use App\Models\CommissionPolicy;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\ModulePurchase;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ModulePurchaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LearnerCheckoutCompletionIdempotencyTest extends TestCase
{
    public function test_duplicate_webhook_event_does_not_duplicate_module_completion_side_effects(): void
    {
        Config::set('paymongo.webhook_secret', 'test_webhook_secret');
        $this->withoutMiddleware(VerifyPayMongoWebhook::class);

        [$learner, $module, $purchase, $payment] = $this->createModuleCheckoutState();

        $payload = [
            'data' => [
                'id' => 'evt_duplicate_same_id',
                'attributes' => [
                    'type' => 'link.payment.paid',
                    'data' => [
                        'id' => 'pay_duplicate_001',
                        'attributes' => [
                            'metadata' => [
                                'payment_scope' => 'module_purchase',
                                'module_id' => $module->id,
                                'module_purchase_id' => $purchase->id,
                                'user_id' => $learner->id,
                                'payment_id' => $payment->id,
                            ],
                            'source' => [
                                'type' => 'gcash',
                            ],
                            'amount' => 49900,
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawPayload, 'test_webhook_secret');

        $this->postJson(route('webhook.paymongo'), $payload, [
            'Paymongo-Signature' => $signature,
        ])->assertOk();

        $this->postJson(route('webhook.paymongo'), $payload, [
            'Paymongo-Signature' => $signature,
        ])->assertOk()->assertJson([
            'success' => true,
            'already_processed' => true,
        ]);

        $this->assertDatabaseHas('module_purchases', [
            'id' => $purchase->id,
            'status' => ModulePurchase::STATUS_COMPLETED,
        ]);

        $this->assertSame(1, (int) DB::table('module_enrollments')
            ->where('user_id', $learner->id)
            ->where('module_id', $module->id)
            ->count());

        $this->assertSame(1, (int) DB::table('module_sale_ledgers')
            ->where('payment_id', $payment->id)
            ->count());
    }

    public function test_pending_verification_then_webhook_keeps_module_completion_idempotent(): void
    {
        Config::set('paymongo.webhook_secret', 'test_webhook_secret');
        $this->withoutMiddleware(VerifyPayMongoWebhook::class);

        [$learner, $module, $purchase, $payment] = $this->createModuleCheckoutState();

        app(ModulePurchaseService::class)->completePayment($payment, 'gcash', 'pay_verify_then_webhook_001');

        $payload = [
            'data' => [
                'id' => 'evt_pending_then_webhook',
                'attributes' => [
                    'type' => 'link.payment.paid',
                    'data' => [
                        'id' => 'pay_pending_then_webhook_001',
                        'attributes' => [
                            'metadata' => [
                                'payment_scope' => 'module_purchase',
                                'module_id' => $module->id,
                                'module_purchase_id' => $purchase->id,
                                'user_id' => $learner->id,
                                'payment_id' => $payment->id,
                            ],
                            'source' => [
                                'type' => 'gcash',
                            ],
                            'amount' => 49900,
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawPayload, 'test_webhook_secret');

        $this->postJson(route('webhook.paymongo'), $payload, [
            'Paymongo-Signature' => $signature,
        ])->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame(1, (int) DB::table('module_purchases')
            ->where('user_id', $learner->id)
            ->where('module_id', $module->id)
            ->where('status', ModulePurchase::STATUS_COMPLETED)
            ->count());

        $this->assertSame(1, (int) DB::table('module_sale_ledgers')
            ->where('payment_id', $payment->id)
            ->count());
    }

    public function test_payment_success_listener_queues_invoice_and_receipt_once_per_payment(): void
    {
        Queue::fake();

        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        $subscription = Subscription::query()->create([
            'user_id' => $learner->id,
            'plan' => 'premium',
            'status' => 'pending',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'price_paid' => 299,
            'auto_renew' => true,
        ]);

        $payment = Payment::query()->create([
            'user_id' => $learner->id,
            'subscription_id' => $subscription->id,
            'amount' => 299,
            'method' => 'paymongo',
            'status' => PaymentStatus::Completed,
            'transaction_id' => 'SUB-QUEUE-IDEMPOTENT-1',
            'payment_details' => [
                'payment_scope' => 'subscription',
            ],
            'paid_at' => now(),
        ]);

        $listener = app(HandlePaymentSuccessful::class);
        $event = new PaymentSuccessful($payment);

        $listener->handle($event);
        $listener->handle($event);

        Queue::assertPushed(GenerateInvoiceJob::class, 1);
        Queue::assertPushed(SendPaymentReceiptEmail::class, 1);
    }

    private function createModuleCheckoutState(): array
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'idempotency_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Idempotency profile',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        CommissionPolicy::query()->create([
            'scope_type' => CommissionPolicy::SCOPE_GLOBAL,
            'scope_id' => null,
            'commission_percent' => 10,
            'tax_basis' => 'gross',
            'refund_policy' => 'disabled',
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'effective_to' => null,
            'updated_by' => $admin->id,
        ]);

        $module = Module::factory()->create([
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
            'is_published' => true,
            'access_type' => 'paid',
            'price_amount' => 499,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
            'current_review_status' => null,
        ]);

        $purchase = ModulePurchase::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'amount' => 499,
            'currency' => 'PHP',
            'status' => ModulePurchase::STATUS_PENDING,
        ]);

        $payment = Payment::query()->create([
            'user_id' => $learner->id,
            'subscription_id' => null,
            'amount' => 499,
            'method' => 'paymongo',
            'status' => PaymentStatus::Pending,
            'transaction_id' => 'MOD-IDEMPOTENCY-' . strtoupper(uniqid()),
            'payment_details' => [
                'payment_scope' => 'module_purchase',
                'module_id' => $module->id,
                'module_purchase_id' => $purchase->id,
            ],
        ]);

        return [$learner, $module, $purchase, $payment];
    }
}
