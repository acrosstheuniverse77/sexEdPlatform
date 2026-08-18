<?php

namespace Tests\Feature\Learner;

use App\Enums\PaymentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PayMongoPaymentLinkService;
use Mockery\MockInterface;
use Tests\TestCase;

class LearnerCheckoutRoutingFlowTest extends TestCase
{
    public function test_module_payment_entry_redirects_to_module_summary_page(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearner();
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

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase', $module))
            ->assertRedirect(route('learner.modules.purchase.form', $module));
    }

    public function test_subscription_entry_redirects_to_summary_checkout_when_feature_flag_is_enabled(): void
    {
        config()->set('billing.features.learner_checkout_refinement', true);

        /** @var User $learner */
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Premium Routing Test',
            'slug' => 'premium-routing-test',
            'description' => 'Premium plan for routing test',
            'price' => 299,
            'features' => [],
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($learner)
            ->post(route('subscription.subscribe'), ['plan_id' => $plan->id])
            ->assertRedirect();

        $subscription = Subscription::query()->where('user_id', $learner->id)->latest('id')->first();
        $this->assertNotNull($subscription);

        $this->actingAs($learner)
            ->get(route('payment.create', $subscription))
            ->assertRedirect(route('payment.checkout.summary', $subscription));
    }

    public function test_subscription_summary_submit_creates_pending_checkout_and_redirects_to_pending_page(): void
    {
        config()->set('billing.features.learner_checkout_refinement', true);

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

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'cs_test_checkout_routing',
                        'attributes' => [
                            'checkout_url' => 'https://checkout.test/subscription-routing',
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($learner)
            ->post(route('payment.checkout.proceed', $subscription), [
                'payment_method' => 'card',
                'accept_terms' => '1',
                'billing_name' => 'Learner One',
                'billing_email' => 'learner.one@example.test',
                'billing_phone' => '09171234567',
            ]);

        $payment = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        $this->assertNotNull($payment);

        $response->assertRedirect(route('payment.pending', ['payment' => $payment->id]));
        $response->assertSessionHas('paymongo_checkout_url', 'https://checkout.test/subscription-routing');
    }

    private function createLearner(): User
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'routing_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Routing flow test profile',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        return $learner;
    }
}
