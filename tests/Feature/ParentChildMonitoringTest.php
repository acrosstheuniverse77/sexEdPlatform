<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\ParentChildAccount;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RewardLog;
use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentChildMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_enrollments_accepts_pending_parent_approval_status(): void
    {
        $parent = User::factory()->create(['email_verified_at' => now()]);
        $parent->assignRole('learner');

        $child = User::factory()->create(['email_verified_at' => now()]);
        $child->assignRole('learner');

        $module = Module::factory()->create();

        $enrollment = ModuleEnrollment::create([
            'user_id'    => $child->id,
            'module_id'  => $module->id,
            'status'     => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->assertDatabaseHas('module_enrollments', [
            'id'     => $enrollment->id,
            'status' => 'pending_parent_approval',
        ]);
    }

    private function createParentWithChild(): array
    {
        $parent = User::factory()->create(['email_verified_at' => now()]);
        $parent->assignRole('learner');

        $child = User::factory()->create(['email_verified_at' => now()]);
        $child->assignRole('learner');

        ParentChildAccount::create([
            'parent_user_id'        => $parent->id,
            'child_user_id'         => $child->id,
            'can_view_progress'     => true,
            'can_view_quiz_answers' => true,
            'can_approve_content'   => true,
            'verification_status'   => 'approved',
            'relationship_verified_at' => now(),
        ]);

        UserGamification::create([
            'user_id'      => $child->id,
            'level'        => 1,
            'score'        => 0,
            'total_points' => 0,
            'streak_count' => 0,
        ]);

        return [$parent, $child];
    }

    public function test_parent_can_view_own_childs_detail_page(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $this->actingAs($parent)
             ->get(route('parent.children.show', $child))
             ->assertOk();
    }

    public function test_parent_can_view_quiz_attempt_details_for_owned_child(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $quiz = Quiz::factory()->create();
        $attempt = QuizAttempt::create([
            'user_id' => $child->id,
            'quiz_id' => $quiz->id,
            'score' => 85,
            'passed' => true,
            'answers' => [],
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->actingAs($parent)
            ->get(route('parent.children.quiz-attempts.show', [$child, $attempt]))
            ->assertOk()
            ->assertSee('Quiz Attempt Details');
    }

    public function test_parent_can_view_pending_enrollment_details_for_owned_child(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create([
            'title' => 'Body Safety Basics',
            'enrollment_mode' => 'auto',
            'access_type' => 'free',
        ]);

        $enrollment = ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Lesson Parent Review',
            'order' => 1,
            'text_content' => '<p>Lesson overview for parent review.</p>',
            'is_published' => true,
        ]);

        LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Topic Parent Review',
            'type' => 'text',
            'text_content' => '<p>Topic details visible to parent.</p>',
            'order' => 1,
        ]);

        $quiz = Quiz::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'title' => 'Quiz Parent Review',
            'is_active' => true,
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What is consent? ',
            'question_type' => 'multiple_choice',
            'points' => 1,
            'order' => 1,
        ]);

        QuizOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'Consent is clear agreement.',
            'is_correct' => true,
            'order' => 1,
        ]);

        $this->actingAs($parent)
            ->get(route('parent.children.enrollments.show', [$child, $enrollment]))
            ->assertOk()
            ->assertSee('Lesson Details')
            ->assertSee('Body Safety Basics')
            ->assertSee('Learning Content Review')
            ->assertSee('Lesson Parent Review')
            ->assertSee('Topic Parent Review')
            ->assertSee('Review Topic')
            ->assertSee('Lesson Quizzes');
    }

    public function test_parent_dashboard_shows_pending_parent_approval_label_and_detail_link(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create(['title' => 'Body Boundaries']);
        $enrollment = ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $response = $this->actingAs($parent)
            ->get(route('parent.children.show', $child));

        $response->assertOk()
            ->assertSee('Pending Guardian Approval')
            ->assertSee(route('parent.children.enrollments.show', [$child, $enrollment]), false);
    }

    public function test_parent_enrollment_detail_shows_notification_context_when_opened_from_notification(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create(['title' => 'Consent Basics']);
        $enrollment = ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
            ->get(route('parent.children.enrollments.show', [$child, $enrollment, 'from' => 'notification']))
            ->assertOk()
            ->assertSee('Opened from notification')
            ->assertSee('Return to notifications');
    }

    public function test_parent_without_content_approval_cannot_view_pending_enrollment_details(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->update(['can_approve_content' => false]);

        $module = Module::factory()->create();
        $enrollment = ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
            ->get(route('parent.children.enrollments.show', [$child, $enrollment]))
            ->assertForbidden();
    }

    public function test_parent_cannot_view_quiz_attempt_details_when_attempt_belongs_to_another_child(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $otherChild = User::factory()->create(['email_verified_at' => now()]);
        $otherChild->assignRole('learner');

        $quiz = Quiz::factory()->create();
        $attempt = QuizAttempt::create([
            'user_id' => $otherChild->id,
            'quiz_id' => $quiz->id,
            'score' => 70,
            'passed' => true,
            'answers' => [],
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->actingAs($parent)
            ->get(route('parent.children.quiz-attempts.show', [$child, $attempt]))
            ->assertNotFound();
    }

    public function test_parent_cannot_view_another_users_child(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        /** @var User $stranger */
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $stranger->assignRole('learner');

        $this->actingAs($stranger)
             ->get(route('parent.children.show', $child))
             ->assertForbidden();
    }

    public function test_parent_cannot_view_child_when_relationship_is_archived(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->delete();

        $this->actingAs($parent)
            ->get(route('parent.children.show', $child))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_parent_routes(): void
    {
        $child = User::factory()->create();

        $this->get(route('parent.children.show', $child))
             ->assertRedirect('/login');
    }

    public function test_get_progress_returns_approved_enrollments_with_progress(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create(['title' => 'Test Module']);
        ModuleEnrollment::create([
            'user_id'     => $child->id,
            'module_id'   => $module->id,
            'status'      => 'approved',
            'enrolled_at' => now(),
        ]);

        $service = app(\App\Services\ParentChildService::class);
        $progress = $service->getProgress($child);

        $this->assertCount(1, $progress);
        $this->assertEquals('Test Module', $progress->first()->module->title);
    }

    public function test_get_quiz_results_returns_attempts_newest_first(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $quiz = Quiz::factory()->create();

        QuizAttempt::create([
            'user_id'      => $child->id,
            'quiz_id'      => $quiz->id,
            'score'        => 80,
            'passed'       => true,
            'answers'      => json_encode([]),
            'started_at'   => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $service = app(\App\Services\ParentChildService::class);
        $results = $service->getQuizResults($child);

        $this->assertCount(1, $results);
        $this->assertEquals(80, $results->first()->score);
    }

    public function test_get_achievements_returns_gamification_and_reward_logs(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $service = app(\App\Services\ParentChildService::class);
        $achievements = $service->getAchievements($child);

        $this->assertArrayHasKey('gamification', $achievements);
        $this->assertArrayHasKey('rewardLogs', $achievements);
        $this->assertEquals(1, $achievements['gamification']->level);
    }

    public function test_get_pending_enrollments_returns_only_pending_parent_approval(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create();
        ModuleEnrollment::create([
            'user_id'     => $child->id,
            'module_id'   => $module->id,
            'status'      => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $service = app(\App\Services\ParentChildService::class);
        $pending = $service->getPendingEnrollments($child);

        $this->assertCount(1, $pending);
        $this->assertEquals('pending_parent_approval', $pending->first()->status->value);
    }

    public function test_parent_can_approve_pending_enrollment_auto_module(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create(['enrollment_mode' => 'auto']);
        $enrollment = ModuleEnrollment::create([
            'user_id'     => $child->id,
            'module_id'   => $module->id,
            'status'      => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
             ->post(route('parent.children.enrollments.approve', [$child, $enrollment]))
             ->assertRedirect(route('parent.children.show', $child));

        $this->assertDatabaseHas('module_enrollments', [
            'id'     => $enrollment->id,
            'status' => 'approved',
        ]);
    }

    public function test_parent_can_approve_pending_enrollment_manual_module(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create(['enrollment_mode' => 'manual']);
        $enrollment = ModuleEnrollment::create([
            'user_id'     => $child->id,
            'module_id'   => $module->id,
            'status'      => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
             ->post(route('parent.children.enrollments.approve', [$child, $enrollment]))
             ->assertRedirect(route('parent.children.show', $child));

        $this->assertDatabaseHas('module_enrollments', [
            'id'     => $enrollment->id,
            'status' => 'pending',
        ]);
    }

    public function test_parent_without_content_approval_cannot_approve_pending_enrollment(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->update(['can_approve_content' => false]);

        $module = Module::factory()->create(['enrollment_mode' => 'auto']);
        $enrollment = ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
            ->post(route('parent.children.enrollments.approve', [$child, $enrollment]))
            ->assertForbidden();

        $this->assertDatabaseHas('module_enrollments', [
            'id' => $enrollment->id,
            'status' => 'pending_parent_approval',
        ]);
    }

    public function test_parent_can_reject_pending_enrollment(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        $module = Module::factory()->create();
        $enrollment = ModuleEnrollment::create([
            'user_id'     => $child->id,
            'module_id'   => $module->id,
            'status'      => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
             ->post(route('parent.children.enrollments.reject', [$child, $enrollment]), [
                 'reason_code' => 'not_ready_for_topic',
             ])
             ->assertRedirect(route('parent.children.show', $child));

        $this->assertDatabaseHas('module_enrollments', [
            'id'     => $enrollment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_parent_without_content_approval_cannot_reject_pending_enrollment(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->update(['can_approve_content' => false]);

        $module = Module::factory()->create();
        $enrollment = ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        $this->actingAs($parent)
            ->post(route('parent.children.enrollments.reject', [$child, $enrollment]), [
                'reason_code' => 'not_ready_for_topic',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('module_enrollments', [
            'id' => $enrollment->id,
            'status' => 'pending_parent_approval',
        ]);
    }

    public function test_parent_cannot_approve_enrollment_for_another_childs_enrollment(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        // A stranger's child
        $otherChild = User::factory()->create(['email_verified_at' => now()]);
        $otherChild->assignRole('learner');

        $module = Module::factory()->create();
        $enrollment = ModuleEnrollment::create([
            'user_id'     => $otherChild->id,
            'module_id'   => $module->id,
            'status'      => 'pending_parent_approval',
            'enrolled_at' => null,
        ]);

        // Parent tries to approve an enrollment that belongs to a child they don't own
        $this->actingAs($parent)
             ->post(route('parent.children.enrollments.approve', [$otherChild, $enrollment]))
             ->assertForbidden();
    }

    public function test_child_enrollment_is_gated_when_parent_has_content_approval_enabled(): void
    {
        [$parent, $child] = $this->createParentWithChild();

        // Give the child a complete-enough learner profile for the controller
        LearnerProfile::updateOrCreate(
            ['user_id' => $child->id],
            [
                'username'                  => 'testchild',
                'birthdate'                 => now()->subYears(8),
                'requires_parental_consent' => true,
            ]
        );

        $module = Module::factory()->create([
            'enrollment_mode' => 'auto',
            'is_published'    => true,
            'min_age'         => 5,
            'max_age'         => 12,
        ]);

        // Bypass the profile-completion redirect so we hit the enroll() logic
        $this->withoutMiddleware(EnsureProfileCompleted::class)
             ->actingAs($child)
             ->post(route('learner.modules.enroll', $module))
             ->assertRedirect();

        $this->assertDatabaseHas('module_enrollments', [
            'user_id'   => $child->id,
            'module_id' => $module->id,
            'status'    => 'pending_parent_approval',
        ]);
    }
}
