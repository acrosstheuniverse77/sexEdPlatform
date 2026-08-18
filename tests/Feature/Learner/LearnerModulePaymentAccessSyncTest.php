<?php

namespace Tests\Feature\Learner;

use App\Enums\PaymentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\CommissionPolicy;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\ModulePurchase;
use App\Models\Payment;
use App\Models\User;
use App\Services\PayMongoPaymentLinkService;
use Mockery\MockInterface;
use Tests\TestCase;

class LearnerModulePaymentAccessSyncTest extends TestCase
{
    public function test_payment_success_route_completes_pending_module_payment_and_unlocks_access(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $module, $payment, $purchase] = $this->createPendingModuleCheckoutState();

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'cs_test_paid_1',
                        'attributes' => [
                            'status' => 'completed',
                            'payments' => [
                                ['id' => 'pay_test_paid_1'],
                            ],
                        ],
                    ],
                ]);

            $mock->shouldReceive('getActualPaymentIdFromCheckoutSession')
                ->once()
                ->andReturn('pay_test_paid_1');
        });

        $this->actingAs($learner)
            ->get(route('payment.success', [
                'scope' => 'module_purchase',
                'module_id' => $module->id,
                'payment_id' => $payment->id,
            ]))
            ->assertOk()
            ->assertSee('Payment Successful');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('module_purchases', [
            'id' => $purchase->id,
            'status' => ModulePurchase::STATUS_COMPLETED,
            'payment_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('module_enrollments', [
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('module_sale_ledgers', [
            'payment_id' => $payment->id,
            'module_id' => $module->id,
            'instructor_id' => $module->created_by,
            'learner_id' => $learner->id,
            'sale_status' => 'completed',
        ]);
    }

    public function test_repeated_module_checkout_uses_single_pending_payment_record(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'dedupe_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Duplicate prevention profile',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

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

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')
                ->twice()
                ->andReturn(
                    [
                        'data' => [
                            'id' => 'cs_dedupe_1',
                            'attributes' => [
                                'checkout_url' => 'https://checkout.test/dedupe-1',
                            ],
                        ],
                    ],
                    [
                        'data' => [
                            'id' => 'cs_dedupe_2',
                            'attributes' => [
                                'checkout_url' => 'https://checkout.test/dedupe-2',
                            ],
                        ],
                    ]
                );
        });

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase.process', $module), [
                'accept_terms' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase.process', $module), [
                'accept_terms' => '1',
            ])
            ->assertRedirect();

        $pendingPayments = Payment::query()
            ->where('user_id', $learner->id)
            ->where('status', PaymentStatus::Pending)
            ->where('payment_details->payment_scope', 'module_purchase')
            ->where('payment_details->module_id', $module->id)
            ->count();

        $pendingPurchases = ModulePurchase::query()
            ->where('user_id', $learner->id)
            ->where('module_id', $module->id)
            ->where('status', ModulePurchase::STATUS_PENDING)
            ->count();

        $this->assertSame(1, $pendingPayments);
        $this->assertSame(1, $pendingPurchases);
    }

    private function createPendingModuleCheckoutState(): array
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'access_sync_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Access sync profile',
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
            'transaction_id' => 'MOD-SUCCESS-SYNC-1',
            'payment_details' => [
                'payment_scope' => 'module_purchase',
                'module_id' => $module->id,
                'module_purchase_id' => $purchase->id,
                'paymongo_checkout_session_id' => 'cs_test_paid_1',
            ],
        ]);

        return [$learner, $module, $payment, $purchase];
    }
}
