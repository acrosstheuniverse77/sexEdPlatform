<?php

namespace Database\Seeders;

use App\Models\InstructorApplication;
use App\Models\InstructorProfile;
use App\Models\LearnerProfile;
use App\Models\PlanPrice;
use App\Models\ParentChildAccount;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Admin\RoleSyncService;
use App\Services\InstructorApplicationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class TestUserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureRolesExist();

        $roleSyncService = app(RoleSyncService::class);
        $location = $this->resolveLocationData();
        [$learnerPaidPlan, $learnerPaidPlanPrice] = $this->resolvePaidPlanAndPrice(
            slug: 'seed-premium-learner-monthly',
            name: 'Seed Premium Learner Monthly',
            audience: 'learner',
            price: 129.00,
        );
        [$instructorPaidPlan, $instructorPaidPlanPrice] = $this->resolvePaidPlanAndPrice(
            slug: 'seed-premium-instructor-monthly',
            name: 'Seed Premium Instructor Monthly',
            audience: 'instructor',
            price: 299.00,
        );

        $admin = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'admin@test.local',
            primaryRole: 'admin',
            attributes: [
                'name' => 'Test Administrator',
                'first_name' => 'Test',
                'last_name' => 'Administrator',
                'birthdate' => $this->birthdateForAge(34),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertUserProfile($admin, [
            'bio' => 'Administrative test account for QA and thesis demonstrations.',
            'birthdate' => $admin->birthdate,
            'gender' => 'prefer_not_to_say',
            'location' => 'Cavite, Philippines',
            'contact' => '09910000001',
        ]);

        $approvedInstructor = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'instructor@test.local',
            primaryRole: 'instructor',
            attributes: [
                'name' => 'Test Instructor Approved',
                'first_name' => 'Test',
                'last_name' => 'Instructor',
                'birthdate' => $this->birthdateForAge(29),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertUserProfile($approvedInstructor, [
            'bio' => 'Approved instructor account for module and governance workflows.',
            'birthdate' => $approvedInstructor->birthdate,
            'gender' => 'female',
            'location' => 'Bacoor, Cavite',
            'contact' => '09910000002',
        ]);

        $this->upsertInstructorProfile($approvedInstructor, [
            'bio' => 'Licensed educator focused on age-appropriate sexual health instruction.',
            'educational_background' => 'college_graduate',
            'professional_background' => 'Former school facilitator and youth trainer.',
            'specialization' => 'Adolescent Sexual Health',
            'primary_expertise' => 'Sexual Health Education',
            'expertise_tags' => ['puberty', 'boundaries', 'consent'],
            'years_experience' => 7,
            'certifications' => ['Youth Counseling Basics', 'Comprehensive Sexuality Education'],
            'credentials' => [
                'government_id_path' => 'seed/instructor-approved/government-id.pdf',
                'clearance_path' => 'seed/instructor-approved/clearance.pdf',
                'cv_resume_path' => 'seed/instructor-approved/cv.pdf',
            ],
        ]);

        $this->upsertInstructorApplication($approvedInstructor, 'approved', $admin);

        $paidInstructor = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'instructor.paid@test.local',
            primaryRole: 'instructor',
            attributes: [
                'name' => 'Test Instructor Paid',
                'first_name' => 'Paid',
                'last_name' => 'Instructor',
                'birthdate' => $this->birthdateForAge(31),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertUserProfile($paidInstructor, [
            'bio' => 'Paid instructor account for monetization and entitlement testing.',
            'birthdate' => $paidInstructor->birthdate,
            'gender' => 'male',
            'location' => 'Imus, Cavite',
            'contact' => '09910000009',
        ]);

        $this->upsertInstructorProfile($paidInstructor, [
            'bio' => 'Seeded paid instructor account for active-plan instructor billing checks.',
            'educational_background' => 'postgraduate',
            'professional_background' => 'Senior facilitator and curriculum specialist.',
            'specialization' => 'Instructor Professional Development',
            'primary_expertise' => 'Adult Learning and Program Delivery',
            'expertise_tags' => ['instructional design', 'mentorship', 'evaluation'],
            'years_experience' => 10,
            'certifications' => ['National Trainer Certification'],
            'credentials' => [
                'government_id_path' => 'seed/instructor-paid/government-id.pdf',
                'clearance_path' => 'seed/instructor-paid/clearance.pdf',
                'cv_resume_path' => 'seed/instructor-paid/cv.pdf',
            ],
        ]);

        $this->upsertInstructorApplication($paidInstructor, 'approved', $admin);
        $this->upsertActivePaidSubscription(
            user: $paidInstructor,
            plan: $instructorPaidPlan,
            planPrice: $instructorPaidPlanPrice,
            sourceReference: 'seed-paid-instructor',
        );

        $pendingInstructor = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'instructor.pending@test.local',
            primaryRole: 'learner',
            attributes: [
                'name' => 'Test Instructor Pending',
                'first_name' => 'Pending',
                'last_name' => 'Instructor',
                'birthdate' => $this->birthdateForAge(26),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($pendingInstructor, [
            'username' => 'seed_pending_instructor',
            'birthdate' => $pendingInstructor->birthdate,
            'gender' => 'male',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Pending instructor applicant profile for moderation testing.',
            'requires_parental_consent' => false,
            'is_parent_account' => false,
        ]);

        $this->upsertInstructorApplication($pendingInstructor, 'pending');

        $kidLearner = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'kid@test.local',
            primaryRole: 'learner',
            attributes: [
                'name' => 'Kid Learner Test',
                'first_name' => 'Kid',
                'last_name' => 'Learner',
                'birthdate' => $this->birthdateForAge(10),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($kidLearner, [
            'username' => 'seed_kid_learner',
            'birthdate' => $kidLearner->birthdate,
            'gender' => 'male',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Kid learner account for age-bracket module visibility checks.',
            'requires_parental_consent' => true,
            'is_parent_account' => false,
        ]);

        $teenLearner = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'teen@test.local',
            primaryRole: 'learner',
            attributes: [
                'name' => 'Teen Learner Test',
                'first_name' => 'Teen',
                'last_name' => 'Learner',
                'birthdate' => $this->birthdateForAge(15),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($teenLearner, [
            'username' => 'seed_teen_learner',
            'birthdate' => $teenLearner->birthdate,
            'gender' => 'female',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Teen learner account for age-bracket and quiz-flow checks.',
            'requires_parental_consent' => false,
            'is_parent_account' => false,
        ]);

        $adultLearner = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'adult@test.local',
            primaryRole: 'learner',
            attributes: [
                'name' => 'Adult Learner Test',
                'first_name' => 'Adult',
                'last_name' => 'Learner',
                'birthdate' => $this->birthdateForAge(24),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($adultLearner, [
            'username' => 'seed_adult_learner',
            'birthdate' => $adultLearner->birthdate,
            'gender' => 'prefer_not_to_say',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Adult learner account for entitlement and subscription scenario validation.',
            'requires_parental_consent' => false,
            'is_parent_account' => false,
        ]);

        $premiumLearner = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'premium.learner@test.local',
            primaryRole: 'learner',
            attributes: [
                'name' => 'Premium Learner Test',
                'first_name' => 'Premium',
                'last_name' => 'Learner',
                'birthdate' => $this->birthdateForAge(27),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($premiumLearner, [
            'username' => 'seed_premium_learner',
            'birthdate' => $premiumLearner->birthdate,
            'gender' => 'female',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Premium learner account with active paid plan for entitlement validation.',
            'requires_parental_consent' => false,
            'is_parent_account' => false,
        ]);

        $this->upsertActivePaidSubscription(
            user: $premiumLearner,
            plan: $learnerPaidPlan,
            planPrice: $learnerPaidPlanPrice,
            sourceReference: 'seed-premium-learner',
        );

        $this->renameSeedEmail('parent@test.local', 'guardian@test.local');

        $parent = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'guardian@test.local',
            primaryRole: 'learner',
            additionalRoles: ['parent'],
            attributes: [
                'name' => 'Guardian Account Test',
                'first_name' => 'Guardian',
                'last_name' => 'Tester',
                'birthdate' => $this->birthdateForAge(38),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
                'is_parent_registration' => true,
                'parent_verification_status' => 'approved',
                'parent_verification_rejection_reason' => null,
                'parent_verification_reviewed_by' => $admin->id,
                'parent_verification_reviewed_at' => now(),
                'parent_verification_approved_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($parent, [
            'username' => 'seed_guardian_account',
            'birthdate' => $parent->birthdate,
            'gender' => 'female',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Guardian test account for monitoring and approvals.',
            'requires_parental_consent' => false,
            'is_parent_account' => true,
        ]);

        $linkedChild = $this->upsertUser(
            roleSyncService: $roleSyncService,
            email: 'linked.child@test.local',
            primaryRole: 'learner',
            attributes: [
                'name' => 'Linked Child Test',
                'first_name' => 'Linked',
                'last_name' => 'Child',
                'birthdate' => $this->birthdateForAge(11),
                'status' => User::STATUS_ACTIVE,
                'verified' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->upsertLearnerProfile($linkedChild, [
            'username' => 'seed_linked_child',
            'birthdate' => $linkedChild->birthdate,
            'gender' => 'male',
            'province_code' => $location['province_code'],
            'city_code' => $location['city_code'],
            'barangay_code' => $location['barangay_code'],
            'barangay' => $location['barangay_name'],
            'bio' => 'Linked child account for parent-child workflow tests.',
            'requires_parental_consent' => true,
            'is_parent_account' => false,
        ]);

        ParentChildAccount::updateOrCreate(
            [
                'parent_user_id' => $parent->id,
                'child_user_id' => $linkedChild->id,
            ],
            [
                'can_view_progress' => true,
                'can_view_quiz_answers' => true,
                'can_approve_content' => true,
                'verification_status' => 'approved',
                'verification_document_path' => 'seed/guardian-child/linked-child-verification.pdf',
                'verification_rejection_reason' => null,
                'verification_reviewed_by' => $admin->id,
                'verification_reviewed_at' => now(),
                'verification_approved_at' => now(),
                'relationship_verified_at' => now(),
            ],
        );

        foreach ([
            $admin,
            $approvedInstructor,
            $paidInstructor,
            $pendingInstructor,
            $kidLearner,
            $teenLearner,
            $adultLearner,
            $premiumLearner,
            $parent,
            $linkedChild,
        ] as $user) {
            $user->refreshClassificationCache();
        }

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('Test user accounts seeded successfully.');
            $this->command->line('Password for all test users: ' . self::DEFAULT_PASSWORD);
            $this->command->line('Admin: admin@test.local');
            $this->command->line('Instructor (approved): instructor@test.local');
            $this->command->line('Instructor (paid active plan): instructor.paid@test.local');
            $this->command->line('Instructor (pending applicant): instructor.pending@test.local');
            $this->command->line('Learner (kid): kid@test.local');
            $this->command->line('Learner (teen): teen@test.local');
            $this->command->line('Learner (adult): adult@test.local');
            $this->command->line('Learner (premium active plan): premium.learner@test.local');
            $this->command->line('Guardian: guardian@test.local');
            $this->command->line('Linked child: linked.child@test.local');
        }
    }

    private function ensureRolesExist(): void
    {
        foreach (['admin', 'instructor', 'learner', 'parent'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    /**
     * @return array{province_code:string, city_code:string, barangay_code:string, barangay_name:string}
     */
    private function resolveLocationData(): array
    {
        $city = DB::table('cities')
            ->where('province_code', '402100000')
            ->orderBy('name')
            ->first(['code', 'province_code']);

        if (! $city) {
            $this->call(CavitePSGCSeeder::class);

            $city = DB::table('cities')
                ->where('province_code', '402100000')
                ->orderBy('name')
                ->first(['code', 'province_code']);
        }

        if (! $city) {
            $city = DB::table('cities')
                ->orderBy('name')
                ->first(['code', 'province_code']);
        }

        if (! $city) {
            throw new RuntimeException('No city records found. Seed PSGC data before running TestUserSeeder.');
        }

        $barangay = DB::table('barangays')
            ->where('city_code', $city->code)
            ->orderBy('name')
            ->first(['code', 'name']);

        if (! $barangay) {
            $barangay = DB::table('barangays')
                ->orderBy('name')
                ->first(['code', 'name', 'city_code']);

            if ($barangay && property_exists($barangay, 'city_code')) {
                $fallbackCity = DB::table('cities')
                    ->where('code', $barangay->city_code)
                    ->first(['code', 'province_code']);

                if ($fallbackCity) {
                    $city = $fallbackCity;
                }
            }
        }

        if (! $barangay) {
            throw new RuntimeException('No barangay records found. Seed PSGC data before running TestUserSeeder.');
        }

        return [
            'province_code' => (string) ($city->province_code ?? '402100000'),
            'city_code' => (string) $city->code,
            'barangay_code' => (string) $barangay->code,
            'barangay_name' => (string) $barangay->name,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string> $additionalRoles
     */
    private function upsertUser(
        RoleSyncService $roleSyncService,
        string $email,
        string $primaryRole,
        array $attributes,
        array $additionalRoles = [],
    ): User {
        $normalizedEmail = strtolower($email);

        $user = User::updateOrCreate(
            ['email' => $normalizedEmail],
            array_merge($attributes, [
                'email' => $normalizedEmail,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
            ]),
        );

        $roleSyncService->assignPrimaryRole($user, $primaryRole);

        foreach ($additionalRoles as $roleName) {
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }

        return $user->refresh();
    }

    private function renameSeedEmail(string $from, string $to): void
    {
        if (User::query()->where('email', $to)->exists()) {
            return;
        }

        User::query()
            ->where('email', $from)
            ->update(['email' => $to]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertUserProfile(User $user, array $attributes): UserProfile
    {
        return UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $attributes,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertLearnerProfile(User $user, array $attributes): LearnerProfile
    {
        return LearnerProfile::updateOrCreate(
            ['user_id' => $user->id],
            $attributes,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertInstructorProfile(User $user, array $attributes): InstructorProfile
    {
        return InstructorProfile::updateOrCreate(
            ['user_id' => $user->id],
            $attributes,
        );
    }

    private function upsertInstructorApplication(User $user, string $status, ?User $approvedBy = null): InstructorApplication
    {
        $isApproved = $status === 'approved';

        return InstructorApplication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'educational_background' => 'college_graduate',
                'government_id_path' => 'seed/instructor-applications/' . $user->id . '/government-id.pdf',
                'clearance_path' => 'seed/instructor-applications/' . $user->id . '/clearance.pdf',
                'cv_resume_path' => 'seed/instructor-applications/' . $user->id . '/cv.pdf',
                'bio' => 'Seeded instructor application record for governance QA workflows.',
                'teaching_credential_path' => 'seed/instructor-applications/' . $user->id . '/teaching-credential.pdf',
                'sexed_certificate_path' => 'seed/instructor-applications/' . $user->id . '/sexed-certificate.pdf',
                'professional_license_path' => 'seed/instructor-applications/' . $user->id . '/professional-license.pdf',
                'approved_by' => $isApproved ? $approvedBy?->id : null,
                'approved_at' => $isApproved ? now() : null,
                'rejection_reason' => null,
                'rejection_reason_code' => null,
                'rejection_reason_note' => null,
                'review_message' => $isApproved
                    ? InstructorApplicationService::defaultApprovalMessage()
                    : null,
                'application_metadata' => [
                    'seeded_by' => self::class,
                    'seeded_at' => now()->toIso8601String(),
                ],
            ],
        );
    }

    /**
     * @return array{SubscriptionPlan, PlanPrice}
     */
    private function resolvePaidPlanAndPrice(
        string $slug,
        string $name,
        string $audience,
        float $price,
    ): array {
        $plan = SubscriptionPlan::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => 'Seeded paid test plan for workflow verification.',
                'price' => $price,
                'features' => [
                    'learning' => [
                        'module_access' => 'unlimited',
                        'full_course_access' => true,
                        'age_based_content_filtering' => true,
                    ],
                    'assessment' => [
                        'quiz_attempts' => 'unlimited',
                        'certificates' => true,
                    ],
                ],
                'plan_audience' => $audience,
                'billing_mode' => 'monthly',
                'renewal_warning_days' => 7,
                'availability_starts_on' => null,
                'availability_ends_on' => null,
                'admin_preview_starts_on' => null,
                'admin_preview_ends_on' => null,
                'trial_days' => 0,
                'max_users' => 1,
                'max_modules' => null,
                'is_active' => true,
                'archived_at' => null,
                'sort_order' => 90,
            ],
        );

        $planPrice = PlanPrice::updateOrCreate(
            [
                'plan_id' => $plan->id,
                'duration_mode' => 'preset',
                'duration_unit' => 'month',
                'duration_count' => 1,
            ],
            [
                'duration_label' => 'Monthly',
                'amount_minor' => (int) round($price * 100),
                'currency' => 'PHP',
                'compare_at_minor' => null,
                'is_default' => true,
                'is_active' => true,
            ],
        );

        PlanPrice::query()
            ->where('plan_id', $plan->id)
            ->where('id', '!=', $planPrice->id)
            ->update(['is_default' => false]);

        return [$plan, $planPrice->fresh()];
    }

    private function upsertActivePaidSubscription(
        User $user,
        SubscriptionPlan $plan,
        PlanPrice $planPrice,
        string $sourceReference,
    ): Subscription {
        $startsAt = CarbonImmutable::now()->subDays(5);
        $endsAt = $startsAt->addMonth();

        return Subscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'source_provider' => 'test-seed',
                'source_reference' => $sourceReference,
            ],
            [
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'plan' => 'premium',
                'status' => 'active',
                'start_date' => $startsAt->toDateString(),
                'end_date' => $endsAt->toDateString(),
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
                'price_paid' => ((int) $planPrice->amount_minor) / 100,
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'auto_renew' => true,
                'grace_period_ends' => null,
                'grace_ends_at' => null,
                'cancel_at' => null,
                'canceled_at' => null,
                'next_billing_at' => $endsAt->toDateTimeString(),
            ],
        );
    }

    private function birthdateForAge(int $age): string
    {
        return CarbonImmutable::now()->subYears($age)->toDateString();
    }
}
