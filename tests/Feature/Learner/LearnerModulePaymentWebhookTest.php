<?php

namespace Tests\Feature\Learner;

use App\Enums\PaymentStatus;
use App\Http\Middleware\VerifyPayMongoWebhook;
use App\Models\CommissionPolicy;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\ModulePurchase;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LearnerModulePaymentWebhookTest extends TestCase
{
    public function test_paymongo_webhook_completes_module_purchase_and_enrollment(): void
    {
        Config::set('paymongo.webhook_secret', 'test_webhook_secret');
        $this->withoutMiddleware(VerifyPayMongoWebhook::class);

        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'webhook_learner',
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Profile',
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
            'transaction_id' => 'MOD-WEBHOOK-1',
            'payment_details' => [
                'payment_scope' => 'module_purchase',
                'module_id' => $module->id,
                'module_purchase_id' => $purchase->id,
            ],
        ]);

        $payload = [
            'data' => [
                'id' => 'evt_test_123',
                'attributes' => [
                    'type' => 'link.payment.paid',
                    'data' => [
                        'id' => 'pay_test_123',
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

        $this->postJson(
            route('webhook.paymongo'),
            $payload,
            [
                'Paymongo-Signature' => $signature,
            ]
        )->assertOk();

        $this->assertDatabaseHas('module_purchases', [
            'id' => $purchase->id,
            'status' => ModulePurchase::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('module_enrollments', [
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('module_sale_ledgers', [
            'payment_id' => $payment->id,
            'module_id' => $module->id,
            'learner_id' => $learner->id,
            'sale_status' => 'completed',
        ]);
    }
}
