<?php

namespace Tests\Feature\Instructor;

use App\Models\FeatureCatalog;
use App\Models\Module;
use App\Models\PlanFeatureEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\TestCase;

class InstructorModuleConfigValidationTest extends TestCase
{
    public function test_paid_module_requires_price_amount(): void
    {
        config()->set('subscription_features.instructor_rollout_mode', 'soft');

        /** @var User $instructor */
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $this->actingAs($instructor)
            ->post(route('instructor.modules.store'), [
                'title' => 'Paid module',
                'description' => 'desc',
                'age_bracket' => 'teens',
                'enrollment_mode' => 'auto',
                'access_type' => 'paid',
                'price_currency' => 'PHP',
                'enrollment_limit' => 50,
            ])
            ->assertSessionHasErrors(['price_amount']);
    }

    public function test_free_module_stores_null_price_amount(): void
    {
        config()->set('subscription_features.instructor_rollout_mode', 'soft');

        /** @var User $instructor */
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $this->actingAs($instructor)
            ->post(route('instructor.modules.store'), [
                'title' => 'Free module',
                'description' => 'desc',
                'age_bracket' => 'teens',
                'enrollment_mode' => 'auto',
                'access_type' => 'free',
                'price_amount' => 100,
                'price_currency' => 'PHP',
                'enrollment_limit' => 50,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('modules', [
            'title' => 'Free module',
            'access_type' => 'free',
            'price_amount' => null,
        ]);
    }

    public function test_enrollment_limit_accepts_null_or_up_to_plan_cap(): void
    {
        $planCap = 30;

        /** @var User $instructor */
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $this->createInstructorBaselineWithFreeLearnerCap($planCap);

        $this->actingAs($instructor)
            ->post(route('instructor.modules.store'), [
                'title' => 'No cap module',
                'description' => 'desc',
                'age_bracket' => 'kids',
                'enrollment_mode' => 'manual',
                'access_type' => 'free',
                'enrollment_limit' => null,
            ])
            ->assertRedirect();

        $module = Module::where('title', 'No cap module')->firstOrFail();

        $this->actingAs($instructor)
            ->put(route('instructor.modules.update', $module), [
                'title' => 'No cap module',
                'description' => 'desc updated',
                'age_bracket' => 'kids',
                'enrollment_mode' => 'manual',
                'access_type' => 'free',
                'enrollment_limit' => $planCap,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'enrollment_limit' => $planCap,
        ]);
    }

    public function test_enrollment_limit_above_plan_cap_is_rejected(): void
    {
        $planCap = 30;

        /** @var User $instructor */
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $this->createInstructorBaselineWithFreeLearnerCap($planCap);

        $this->actingAs($instructor)
            ->post(route('instructor.modules.store'), [
                'title' => 'Over cap module',
                'description' => 'desc',
                'age_bracket' => 'kids',
                'enrollment_mode' => 'manual',
                'access_type' => 'free',
                'enrollment_limit' => $planCap + 1,
            ])
            ->assertSessionHasErrors(['enrollment_limit']);

        $this->assertDatabaseMissing('modules', [
            'title' => 'Over cap module',
        ]);
    }

    private function createInstructorBaselineWithFreeLearnerCap(int $cap): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => 'Instructor Free Baseline',
            'slug' => 'instructor-baseline-' . uniqid(),
            'description' => 'Baseline for validation tests',
            'price' => 0,
            'features' => [],
            'plan_audience' => 'instructor',
            'billing_mode' => 'monthly',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $feature = FeatureCatalog::query()->updateOrCreate(
            ['key' => 'instructor_max_learners_per_free_module'],
            [
                'name' => 'Free Module Learner Cap',
                'description' => 'Free Module Learner Cap',
                'value_type' => 'quota',
                'unit_label' => 'learners',
                'category' => 'instructor',
                'is_active' => true,
            ]
        );

        PlanFeatureEntitlement::query()->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'is_enabled' => true,
            'quota_value' => $cap,
            'is_unlimited' => false,
        ]);

        return $plan;
    }
}
