<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\FeatureCatalog;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\ModuleRevision;
use App\Models\ModuleEnrollment;
use App\Models\Payment;
use App\Models\PlanFeatureEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PayMongoPaymentLinkService;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class LearnerPaidModulePurchaseFlowTest extends TestCase
{
    public function test_eligible_paid_module_redirects_to_paymongo_checkout(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearner();
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');
        $this->createInstructorBaselinePlanWithPaidEnrollmentAccess();

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
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
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'link_test_123',
                        'attributes' => [
                            'checkout_url' => 'https://checkout.test/pay/123',
                        ],
                    ],
                ]);
        });

        $this->actingAs($learner)
            ->get(route('learner.modules.purchase.form', $module))
            ->assertOk()
            ->assertSee('Confirm Module Purchase')
            ->assertSee('Proceed to GCash Checkout');

        $response = $this->actingAs($learner)
            ->post(route('learner.modules.purchase.process', $module), [
                'payment_method' => 'gcash',
                'accept_terms' => '1',
                'billing_name' => 'Learner One',
                'billing_email' => 'learner.one@example.test',
                'billing_phone' => '09171234567',
            ]);

        $payment = Payment::query()->where('user_id', $learner->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame('module_purchase', data_get($payment->payment_details, 'payment_scope'));
        $this->assertSame('gcash', $payment->method);

        $response->assertRedirect(route('payment.pending', ['payment' => $payment->id]));
        $response->assertSessionHas('paymongo_checkout_url', 'https://checkout.test/pay/123');

        $this->assertDatabaseHas('module_purchases', [
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'pending',
        ]);
    }

    public function test_paid_module_requires_parent_approval_before_checkout(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearner();
        $parent = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(35)->toDateString(),
        ]);
        $parent->assignRole('learner');
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');
        $this->createInstructorBaselinePlanWithPaidEnrollmentAccess();

        DB::table('parent_child_accounts')->insert([
            'parent_user_id' => $parent->id,
            'child_user_id' => $learner->id,
            'verification_status' => 'approved',
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'approved',
            'relationship_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => true,
            'access_type' => 'paid',
            'price_amount' => 299,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
            'current_review_status' => null,
        ]);

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createPaymentLink');
        });

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase', $module))
            ->assertRedirect(route('learner.modules.show', $module))
            ->assertSessionHas('info');

        $this->assertDatabaseHas('module_enrollments', [
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::PendingParentApproval->value,
        ]);

        $this->assertDatabaseMissing('module_purchases', [
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'pending',
        ]);
    }

    public function test_paid_module_checkout_is_blocked_when_module_is_full(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $existingLearner = $this->createLearner('existing_paid_capacity');
        $newLearner = $this->createLearner('new_paid_capacity');
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');
        $this->createInstructorBaselinePlanWithPaidEnrollmentAccess();

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => true,
            'access_type' => 'paid',
            'price_amount' => 499,
            'price_currency' => 'PHP',
            'enrollment_limit' => 1,
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
            'current_review_status' => null,
        ]);

        ModuleEnrollment::query()->create([
            'user_id' => $existingLearner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createPaymentLink');
        });

        $this->actingAs($newLearner)
            ->post(route('learner.modules.purchase', $module))
            ->assertRedirect(route('learner.modules.show', $module))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('module_purchases', [
            'user_id' => $newLearner->id,
            'module_id' => $module->id,
        ]);
    }

    public function test_paid_module_checkout_is_blocked_when_instructor_plan_cannot_receive_paid_enrollments(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearner('paid_entitlement_blocked');
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');

        $this->createInstructorBaselinePlanWithoutReceivePaidEntitlement();

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => true,
            'current_review_status' => null,
            'access_type' => 'paid',
            'price_amount' => 399,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
        ]);

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createCheckoutSession');
        });

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase', $module))
            ->assertRedirect(route('learner.modules.show', $module))
            ->assertSessionHas('error', 'Paid enrollment is currently unavailable for this module.');

        $this->assertDatabaseMissing('module_purchases', [
            'user_id' => $learner->id,
            'module_id' => $module->id,
        ]);
    }

    public function test_paid_module_show_page_displays_checkout_block_reason_when_paid_enrollment_is_disabled(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearner('paid_block_reason');
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');

        $this->createInstructorBaselinePlanWithoutReceivePaidEntitlement();

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => true,
            'current_review_status' => null,
            'access_type' => 'paid',
            'price_amount' => 399,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
        ]);

        $this->actingAs($learner)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertSeeText('Paid enrollment is not enabled for this module yet.');
    }

    public function test_purchase_does_not_404_when_snapshot_has_stale_unpublished_flag(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $learner = $this->createLearner('stale_snapshot_purchase');
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');
        $this->createInstructorBaselinePlanWithPaidEnrollmentAccess();

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => true,
            'current_review_status' => 'approved',
            'access_type' => 'paid',
            'price_amount' => 499,
            'price_currency' => 'PHP',
            'enrollment_mode' => 'auto',
            'min_age' => 18,
            'max_age' => 25,
        ]);

        $revision = ModuleRevision::query()->create([
            'module_id' => $module->id,
            'revision_number' => 1,
            'snapshot_payload' => [
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'thumbnail' => $module->thumbnail,
                    'min_age' => $module->min_age,
                    'max_age' => $module->max_age,
                    'age_specific_content' => $module->age_specific_content,
                    'order' => $module->order,
                    'duration_minutes' => $module->duration_minutes,
                    'is_published' => false,
                    'is_premium' => $module->is_premium,
                    'access_type' => 'paid',
                    'price_amount' => $module->price_amount,
                    'price_currency' => $module->price_currency,
                    'enrollment_mode' => $module->enrollment_mode,
                    'final_quiz_id' => $module->final_quiz_id,
                    'certificate_pass_score' => $module->certificate_pass_score,
                    'created_by' => $instructor->id,
                    'content_owner_type' => 'instructor',
                ],
                'lessons' => [],
                'quizzes' => [],
            ],
            'submitted_by' => $instructor->id,
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
            'reviewed_by' => $instructor->id,
        ]);

        $module->update([
            'published_revision_id' => $revision->id,
            'published_by_admin_id' => $instructor->id,
        ]);

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'link_test_stale_snapshot',
                        'attributes' => [
                            'checkout_url' => 'https://checkout.test/pay/stale-snapshot',
                        ],
                    ],
                ]);
        });

        $this->actingAs($learner)
            ->post(route('learner.modules.purchase', $module))
            ->assertRedirect(route('learner.modules.purchase.form', $module));

        $response = $this->actingAs($learner)
            ->post(route('learner.modules.purchase.process', $module), [
                'payment_method' => 'gcash',
                'accept_terms' => '1',
                'billing_name' => 'Learner Snapshot',
                'billing_email' => 'learner.snapshot@example.test',
                'billing_phone' => '09179876543',
            ]);

        $payment = Payment::query()->where('user_id', $learner->id)->latest('id')->first();

        $this->assertNotNull($payment);
        $response->assertRedirect(route('payment.pending', ['payment' => $payment->id]));
        $response->assertSessionHas('paymongo_checkout_url', 'https://checkout.test/pay/stale-snapshot');
    }

    private function createLearner(string $username = null): User
    {
        $user = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(20)->toDateString(),
        ]);
        $user->assignRole('learner');

        LearnerProfile::create([
            'user_id' => $user->id,
            'username' => $username ?: ('learner_' . $user->id),
            'birthdate' => $user->birthdate,
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Profile',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        return $user;
    }

    private function createInstructorBaselinePlanWithoutReceivePaidEntitlement(): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Instructor Free Baseline',
            'slug' => 'instructor-free-baseline-' . uniqid(),
            'description' => 'Instructor baseline',
            'price' => 0,
            'features' => [],
            'plan_audience' => 'instructor',
            'billing_mode' => 'monthly',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $feature = FeatureCatalog::updateOrCreate(
            ['key' => 'instructor_can_publish_paid_modules'],
            [
                'name' => 'Instructor Can Publish Paid Modules',
                'value_type' => 'boolean',
                'category' => 'instructor',
                'is_active' => true,
            ]
        );

        PlanFeatureEntitlement::create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'is_enabled' => true,
            'is_unlimited' => true,
        ]);

        return $plan;
    }

    private function createInstructorBaselinePlanWithPaidEnrollmentAccess(): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Instructor Free Baseline',
            'slug' => 'instructor-free-baseline-' . uniqid(),
            'description' => 'Instructor baseline',
            'price' => 0,
            'features' => [],
            'plan_audience' => 'instructor',
            'billing_mode' => 'monthly',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        foreach (['instructor_can_publish_paid_modules', 'instructor_can_receive_paid_enrollments'] as $key) {
            $feature = FeatureCatalog::updateOrCreate(
                ['key' => $key],
                [
                    'name' => str($key)->headline()->toString(),
                    'value_type' => 'boolean',
                    'category' => 'instructor',
                    'is_active' => true,
                ]
            );

            PlanFeatureEntitlement::updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'feature_id' => $feature->id,
                ],
                [
                    'is_enabled' => true,
                    'is_unlimited' => true,
                ]
            );
        }

        return $plan;
    }
}
