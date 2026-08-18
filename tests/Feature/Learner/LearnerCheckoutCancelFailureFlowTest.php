<?php

namespace Tests\Feature\Learner;

use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\User;
use Tests\TestCase;

class LearnerCheckoutCancelFailureFlowTest extends TestCase
{
    public function test_module_failure_redirects_back_to_module_checkout_summary_with_retry_message(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearnerWithProfile();
        $module = $this->createPaidModule();

        $this->actingAs($learner)
            ->get(route('learner.modules.purchase.failed', $module))
            ->assertRedirect(route('learner.modules.purchase.form', $module))
            ->assertSessionHas('error');
    }

    public function test_subscription_failure_redirects_back_to_subscription_checkout_summary_with_retry_message(): void
    {
        /** @var User $learner */
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

        $this->actingAs($learner)
            ->get(route('payment.paymongo.failed', $subscription))
            ->assertRedirect(route('payment.checkout.summary', $subscription))
            ->assertSessionHas('error');
    }

    public function test_module_failure_flow_keeps_retry_context_available_on_summary_page(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearnerWithProfile();
        $module = $this->createPaidModule();

        $response = $this->actingAs($learner)
            ->followingRedirects()
            ->get(route('learner.modules.purchase.failed', $module));

        $response->assertOk();
        $response->assertSee('Checkout Summary');
        $response->assertSee($module->title);
    }

    public function test_payment_cancel_page_for_module_shows_return_and_retry_actions(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearnerWithProfile();
        $module = $this->createPaidModule();

        $this->actingAs($learner)
            ->get(route('payment.cancel', [
                'scope' => 'module_purchase',
                'module_id' => $module->id,
            ]))
            ->assertOk()
            ->assertSee('Payment Cancelled')
            ->assertSee('Return to Module')
            ->assertSee('Retry Payment');
    }

    public function test_payment_success_page_for_module_shows_learning_and_module_actions(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearnerWithProfile();
        $module = $this->createPaidModule();

        $this->actingAs($learner)
            ->get(route('payment.success', [
                'scope' => 'module_purchase',
                'module_id' => $module->id,
            ]))
            ->assertOk()
            ->assertSee('Payment Successful')
            ->assertSee('Module access has been granted.')
            ->assertSee('Go to My Learning')
            ->assertSee('View Module');
    }

    private function createLearnerWithProfile(): User
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'failure_retry_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Checkout failure retry test profile',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        return $learner;
    }

    private function createPaidModule(): Module
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        return Module::factory()->create([
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
            'is_published' => true,
            'access_type' => 'paid',
            'price_amount' => 450,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
            'current_review_status' => null,
        ]);
    }
}
