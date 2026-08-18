<?php

namespace Tests\Feature\Learner;

use App\Enums\PaymentStatus;
use App\Models\CommissionPolicy;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\ModulePurchase;
use App\Models\User;
use App\Services\ModulePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerModulePurchaseMonetizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_completion_still_unlocks_enrollment_and_writes_sale_ledger(): void
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'monetization_regression_learner',
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

        $payment = $learner->payments()->create([
            'subscription_id' => null,
            'amount' => 499,
            'method' => 'paymongo',
            'status' => PaymentStatus::Pending,
            'transaction_id' => 'MOD-REG-1',
            'payment_details' => [
                'payment_scope' => 'module_purchase',
                'module_id' => $module->id,
                'module_purchase_id' => $purchase->id,
            ],
        ]);

        /** @var ModulePurchaseService $modulePurchaseService */
        $modulePurchaseService = app(ModulePurchaseService::class);

        $completed = $modulePurchaseService->completePayment($payment);

        $this->assertTrue($completed);

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
}
