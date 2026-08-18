<?php

namespace Tests\Feature\Learner;

use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\TestCase;

class LearnerCheckoutSummaryViewTest extends TestCase
{
    public function test_subscription_summary_page_shows_purchase_type_item_and_total_sections(): void
    {
        config()->set('paymongo.secret_key', 'sk_test_checkout_view');

        /** @var User $learner */
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Premium View Test',
            'slug' => 'premium-view-test',
            'description' => 'Plan for view test',
            'price' => 299,
            'features' => [],
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'user_id' => $learner->id,
            'plan_id' => $plan->id,
            'plan' => 'premium',
            'status' => 'pending',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'price_paid' => 299,
            'auto_renew' => true,
        ]);

        $this->actingAs($learner)
            ->get(route('payment.checkout.summary', $subscription))
            ->assertOk()
            ->assertSee('Checkout Summary')
            ->assertSee('Purchase Type')
            ->assertSee('Payment Summary')
            ->assertSee('Total Amount')
            ->assertSee('Payments are securely processed by PayMongo')
            ->assertDontSee('Select Payment Method')
            ->assertDontSee('Sandbox mode is active');
    }

    public function test_module_summary_page_shows_module_and_instructor_details(): void
    {
        config()->set('paymongo.secret_key', 'sk_test_checkout_module_view');
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        /** @var User $learner */
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'summary_view_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Summary view profile',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Instructor View Test']);
        $admin->assignRole('admin');

        $module = Module::factory()->create([
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
            'title' => 'Respect and Safety',
            'description' => 'Module summary details should be visible.',
            'is_published' => true,
            'access_type' => 'paid',
            'price_amount' => 499,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
            'current_review_status' => null,
        ]);

        $this->actingAs($learner)
            ->get(route('learner.modules.purchase.form', $module))
            ->assertOk()
            ->assertSee('Checkout Summary')
            ->assertSee('Module Name')
            ->assertSee('Instructor')
            ->assertSee('Respect and Safety')
            ->assertSee('Instructor View Test')
            ->assertSee('Proceed to GCash Checkout')
            ->assertDontSee('Select Payment Method')
            ->assertDontSee('Billing Information');
    }
}
