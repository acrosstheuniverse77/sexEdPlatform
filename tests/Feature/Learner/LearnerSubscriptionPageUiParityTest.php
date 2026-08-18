<?php

namespace Tests\Feature\Learner;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerSubscriptionPageUiParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_page_hides_entitlement_snapshot_and_shows_explicit_free_plan_copy(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPlan('Premium Explorer', renewalWarningDays: null);

        $this->actingAs($user)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertDontSee('Current entitlement snapshot', false)
            ->assertSee('Access to core age-appropriate learning modules', false)
            ->assertSee('3 quiz shields per day', false)
            ->assertSee('Username change every 7 days', false)
                ->assertSee('All Free Plan Features', false)
                ->assertDontSee('Current premium controls', false)
            ->assertDontSee('Free forever', false)
            ->assertDontSee('PHP 0.00', false)
            ->assertDontSee('Certificate downloads unlock on supported premium plans.', false);
    }

    public function test_subscription_page_shows_renewal_notice_for_expired_subscription(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $plan = $this->createPlan('Premium Expired', renewalWarningDays: 7);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan' => 'premium',
            'status' => 'expired',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subDay(),
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'price_paid' => 199,
            'auto_renew' => false,
        ]);

        $this->actingAs($user)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertSee('Your premium access has expired', false)
            ->assertSee('Renew via Checkout', false);
    }

    public function test_subscription_page_shows_renewal_notice_for_expiring_subscription(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $plan = $this->createPlan('Premium Expiring', renewalWarningDays: 5);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan' => 'premium',
            'status' => 'active',
            'start_date' => now()->subDays(20),
            'end_date' => now()->addDays(2),
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->addDays(2),
            'price_paid' => 199,
            'auto_renew' => true,
        ]);

        $this->actingAs($user)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertSee('Your premium access is expiring soon', false)
            ->assertSee('Renew via Checkout', false);
    }

    private function createPlan(string $name, ?int $renewalWarningDays): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $name,
            'slug' => str($name)->slug() . '-' . uniqid(),
            'description' => 'UI parity plan',
            'price' => 199,
            'features' => [],
            'renewal_warning_days' => $renewalWarningDays,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
