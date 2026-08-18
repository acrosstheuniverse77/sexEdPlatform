<?php

namespace Tests\Feature\Learner;

use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LearnerCheckoutPayloadContractTest extends TestCase
{
    public function test_module_checkout_without_local_method_selection_still_sends_full_method_list(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        Config::set('paymongo.secret_key', 'sk_test_payload_contract');
        Config::set('paymongo.public_key', 'pk_test_payload_contract');
        Config::set('paymongo.api_base_url', 'https://api.paymongo.com/v1');
        Config::set('paymongo.payment_link.allowed_payment_method_types', ['gcash', 'paymaya', 'grab_pay', 'card']);

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_module_payload_contract_default',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.test/module-payload-contract-default',
                    ],
                ],
            ], 200),
        ]);

        /** @var User $learner */
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'payload_learner_default_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Payload contract default method profile',
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

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase.process', $module), [
                'accept_terms' => '1',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            $types = data_get($request->data(), 'data.attributes.payment_method_types', []);
            $lineItems = data_get($request->data(), 'data.attributes.line_items', []);
            $successUrl = (string) data_get($request->data(), 'data.attributes.success_url', '');
            $cancelUrl = (string) data_get($request->data(), 'data.attributes.cancel_url', '');

            return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
                && $types === ['card', 'gcash', 'paymaya', 'grab_pay']
                && data_get($lineItems, '0.amount') === 49900
                && str_contains($successUrl, '/payment/success')
                && str_contains($cancelUrl, '/payment/cancel');
        });
    }

    public function test_subscription_checkout_sends_full_method_list_with_preferred_first(): void
    {
        Config::set('paymongo.secret_key', 'sk_test_payload_contract');
        Config::set('paymongo.public_key', 'pk_test_payload_contract');
        Config::set('paymongo.api_base_url', 'https://api.paymongo.com/v1');
        Config::set('paymongo.payment_link.allowed_payment_method_types', ['gcash', 'paymaya', 'grab_pay', 'card']);

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_subscription_payload_contract',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.test/subscription-payload-contract',
                    ],
                ],
            ], 200),
        ]);

        /** @var User $learner */
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        $subscription = Subscription::query()->create([
            'user_id' => $learner->id,
            'plan' => 'premium',
            'status' => 'pending',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'price_paid' => 349,
            'auto_renew' => true,
        ]);

        $this->actingAs($learner)
            ->post(route('payment.checkout.proceed', $subscription), [
                'payment_method' => 'grab_pay',
                'accept_terms' => '1',
                'billing_name' => 'Payload Learner',
                'billing_email' => 'payload.learner@example.test',
                'billing_phone' => '09170000001',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            $types = data_get($request->data(), 'data.attributes.payment_method_types', []);
            $metadata = data_get($request->data(), 'data.attributes.metadata', []);
            $lineItems = data_get($request->data(), 'data.attributes.line_items', []);
            $successUrl = (string) data_get($request->data(), 'data.attributes.success_url', '');
            $cancelUrl = (string) data_get($request->data(), 'data.attributes.cancel_url', '');

            return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
                && $types === ['grab_pay', 'gcash', 'paymaya', 'card']
                && data_get($metadata, 'payment_scope') === 'subscription'
                && data_get($lineItems, '0.amount') === 34900
                && str_contains($successUrl, '/payment/paymongo/success/')
                && str_contains($cancelUrl, '/payment/paymongo/failed/');
        });
    }

    public function test_module_checkout_sends_full_method_list_with_preferred_first(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        Config::set('paymongo.secret_key', 'sk_test_payload_contract');
        Config::set('paymongo.public_key', 'pk_test_payload_contract');
        Config::set('paymongo.api_base_url', 'https://api.paymongo.com/v1');
        Config::set('paymongo.payment_link.allowed_payment_method_types', ['gcash', 'paymaya', 'grab_pay', 'card']);

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_module_payload_contract',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.test/module-payload-contract',
                    ],
                ],
            ], 200),
        ]);

        /** @var User $learner */
        $learner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $learner->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $learner->id,
            'username' => 'payload_learner_' . $learner->id,
            'birthdate' => $learner->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Payload contract test profile',
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

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase.process', $module), [
                'payment_method' => 'paymaya',
                'accept_terms' => '1',
                'billing_name' => 'Payload Learner',
                'billing_email' => 'payload.learner@example.test',
                'billing_phone' => '09170000002',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            $types = data_get($request->data(), 'data.attributes.payment_method_types', []);
            $metadata = data_get($request->data(), 'data.attributes.metadata', []);
            $lineItems = data_get($request->data(), 'data.attributes.line_items', []);
            $successUrl = (string) data_get($request->data(), 'data.attributes.success_url', '');
            $cancelUrl = (string) data_get($request->data(), 'data.attributes.cancel_url', '');

            return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
                && $types === ['paymaya', 'gcash', 'grab_pay', 'card']
                && data_get($metadata, 'payment_scope') === 'module_purchase'
                && data_get($lineItems, '0.amount') === 49900
                && str_contains($successUrl, '/payment/success')
                && str_contains($cancelUrl, '/payment/cancel');
        });
    }
}
