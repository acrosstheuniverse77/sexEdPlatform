<?php

namespace Tests\Feature\Seeders;

use App\Models\InstructorApplication;
use App\Models\ParentChildAccount;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\TestUserSeeder;
use Tests\TestCase;

class TestUserSeederTest extends TestCase
{
    public function test_it_seeds_role_specific_test_accounts_with_profile_and_relationship_coverage(): void
    {
        $this->seed(TestUserSeeder::class);

        $admin = User::query()->where('email', 'admin@test.local')->first();
        $approvedInstructor = User::query()->where('email', 'instructor@test.local')->first();
        $paidInstructor = User::query()->where('email', 'instructor.paid@test.local')->first();
        $pendingInstructor = User::query()->where('email', 'instructor.pending@test.local')->first();
        $kidLearner = User::query()->where('email', 'kid@test.local')->first();
        $teenLearner = User::query()->where('email', 'teen@test.local')->first();
        $adultLearner = User::query()->where('email', 'adult@test.local')->first();
        $premiumLearner = User::query()->where('email', 'premium.learner@test.local')->first();
        $parent = User::query()->where('email', 'guardian@test.local')->first();
        $linkedChild = User::query()->where('email', 'linked.child@test.local')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($approvedInstructor);
        $this->assertNotNull($paidInstructor);
        $this->assertNotNull($pendingInstructor);
        $this->assertNotNull($kidLearner);
        $this->assertNotNull($teenLearner);
        $this->assertNotNull($adultLearner);
        $this->assertNotNull($premiumLearner);
        $this->assertNotNull($parent);
        $this->assertNotNull($linkedChild);

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertSame(User::ACCOUNT_TYPE_ADMIN, $admin->account_type);

        $this->assertTrue($approvedInstructor->hasRole('instructor'));
        $this->assertNotNull($approvedInstructor->instructorProfile);
        $this->assertSame(User::ACCOUNT_TYPE_INSTRUCTOR, $approvedInstructor->account_type);

        $this->assertTrue($paidInstructor->hasRole('instructor'));
        $this->assertNotNull($paidInstructor->instructorProfile);
        $this->assertTrue($paidInstructor->isPremium());
        $this->assertSame(User::ACCOUNT_TYPE_INSTRUCTOR, $paidInstructor->account_type);

        $this->assertTrue($pendingInstructor->hasRole('learner'));
        $this->assertFalse($pendingInstructor->hasRole('instructor'));
        $this->assertSame('pending', $pendingInstructor->instructorApplication?->status);

        $this->assertTrue($kidLearner->hasRole('learner'));
        $this->assertTrue($kidLearner->hasCompletedProfile());
        $this->assertSame('kids', $kidLearner->age_bracket_cached);
        $this->assertSame(User::ACCOUNT_TYPE_LEARNER_CHILD, $kidLearner->account_type);

        $this->assertTrue($teenLearner->hasRole('learner'));
        $this->assertTrue($teenLearner->hasCompletedProfile());
        $this->assertSame('teens', $teenLearner->age_bracket_cached);
        $this->assertSame(User::ACCOUNT_TYPE_LEARNER_TEEN, $teenLearner->account_type);

        $this->assertTrue($adultLearner->hasRole('learner'));
        $this->assertTrue($adultLearner->hasCompletedProfile());
        $this->assertSame('adults', $adultLearner->age_bracket_cached);
        $this->assertSame(User::ACCOUNT_TYPE_LEARNER_ADULT, $adultLearner->account_type);

        $this->assertTrue($premiumLearner->hasRole('learner'));
        $this->assertTrue($premiumLearner->hasCompletedProfile());
        $this->assertSame('adults', $premiumLearner->age_bracket_cached);
        $this->assertTrue($premiumLearner->isPremium());
        $this->assertSame(User::ACCOUNT_TYPE_LEARNER_ADULT, $premiumLearner->account_type);

        $this->assertTrue($parent->hasRole('learner'));
        $this->assertSame('Guardian Account Test', $parent->name);
        $this->assertTrue($parent->hasRole('parent'));
        $this->assertTrue($parent->isParentRegistration());
        $this->assertTrue($parent->isParentVerificationApproved());
        $this->assertTrue($parent->hasCompletedProfile());
        $this->assertSame(User::ACCOUNT_TYPE_PARENT, $parent->account_type);

        $this->assertTrue($linkedChild->hasRole('learner'));
        $this->assertTrue($linkedChild->hasCompletedProfile());
        $this->assertSame('kids', $linkedChild->age_bracket_cached);

        $parentChildLink = ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $linkedChild->id)
            ->first();

        $this->assertNotNull($parentChildLink);
        $this->assertSame('approved', $parentChildLink->verification_status);
        $this->assertNotNull($parentChildLink->relationship_verified_at);

        $approvedApplication = InstructorApplication::query()->where('user_id', $approvedInstructor->id)->first();
        $paidInstructorApplication = InstructorApplication::query()->where('user_id', $paidInstructor->id)->first();
        $pendingApplication = InstructorApplication::query()->where('user_id', $pendingInstructor->id)->first();

        $this->assertNotNull($approvedApplication);
        $this->assertNotNull($paidInstructorApplication);
        $this->assertNotNull($pendingApplication);
        $this->assertSame('approved', $approvedApplication->status);
        $this->assertSame('approved', $paidInstructorApplication->status);
        $this->assertSame('pending', $pendingApplication->status);

        $premiumSubscriptionCount = Subscription::query()
            ->where('source_provider', 'test-seed')
            ->whereIn('user_id', [$paidInstructor->id, $premiumLearner->id])
            ->where('status', 'active')
            ->whereNotNull('plan_id')
            ->count();

        $this->assertSame(2, $premiumSubscriptionCount);
    }

    public function test_it_is_safe_to_run_repeatedly_without_duplicate_test_accounts(): void
    {
        $this->seed(TestUserSeeder::class);
        $this->seed(TestUserSeeder::class);

        $emails = [
            'admin@test.local',
            'instructor@test.local',
            'instructor.paid@test.local',
            'instructor.pending@test.local',
            'kid@test.local',
            'teen@test.local',
            'adult@test.local',
            'premium.learner@test.local',
            'guardian@test.local',
            'linked.child@test.local',
        ];

        $this->assertSame(10, User::query()->whereIn('email', $emails)->count());

        $parent = User::query()->where('email', 'guardian@test.local')->firstOrFail();
        $linkedChild = User::query()->where('email', 'linked.child@test.local')->firstOrFail();

        $this->assertSame(
            1,
            ParentChildAccount::query()
                ->where('parent_user_id', $parent->id)
                ->where('child_user_id', $linkedChild->id)
                ->count(),
        );

        $instructorIds = User::query()
            ->whereIn('email', ['instructor@test.local', 'instructor.paid@test.local', 'instructor.pending@test.local'])
            ->pluck('id');

        $this->assertSame(
            3,
            InstructorApplication::query()
                ->whereIn('user_id', $instructorIds)
                ->count(),
        );

        $premiumUserIds = User::query()
            ->whereIn('email', ['instructor.paid@test.local', 'premium.learner@test.local'])
            ->pluck('id');

        $this->assertSame(
            2,
            Subscription::query()
                ->whereIn('user_id', $premiumUserIds)
                ->where('source_provider', 'test-seed')
                ->whereIn('source_reference', ['seed-paid-instructor', 'seed-premium-learner'])
                ->count(),
        );
    }
}
