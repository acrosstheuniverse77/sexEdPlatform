<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ModuleFeedback;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;

class ModuleFeedbackFlowTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public function test_learner_cannot_submit_feedback_before_full_completion(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $module] = $this->createLearnerAndModule();

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'rating' => 5,
                'review_content' => '<p>Great module</p>',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('module_feedback', 0);
    }

    public function test_learner_can_submit_or_update_feedback_after_completion(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $module, $lesson] = $this->createLearnerAndModule();

        UserProgress::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'rating' => 4,
                'review_content' => '<p>Helpful and clear.</p>',
            ])
            ->assertSessionHas('success');

        $feedback = ModuleFeedback::query()->first();
        $this->assertNotNull($feedback);
        $this->assertSame(4, (int) $feedback->rating);

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'rating' => 5,
                'review_content' => '<p>Updated review after finishing again.</p>',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('module_feedback', 1);
        $this->assertDatabaseHas('module_feedback', [
            'module_id' => $module->id,
            'learner_id' => $learner->id,
            'rating' => 5,
        ]);
    }

    public function test_completed_enrollment_can_submit_feedback_even_when_old_lesson_quiz_state_is_stale(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $module, $lesson] = $this->createLearnerAndModule();

        $lessonQuiz = Quiz::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'is_active' => true,
            'attempt_limit' => 3,
        ]);

        QuizAttempt::query()->create([
            'user_id' => $learner->id,
            'quiz_id' => $lessonQuiz->id,
            'score' => 50,
            'passed' => false,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        ModuleEnrollment::query()
            ->where('user_id', $learner->id)
            ->where('module_id', $module->id)
            ->update([
                'completed_at' => now(),
                'completion_percentage' => 100,
            ]);

        UserProgress::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'rating' => 5,
                'review_content' => 'Completed module should be reviewable.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('module_feedback', [
            'module_id' => $module->id,
            'learner_id' => $learner->id,
            'rating' => 5,
        ]);
    }

    public function test_learner_can_submit_feedback_after_completing_lesson_quiz_attempt_without_passing(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $module, $lesson] = $this->createLearnerAndModule();

        $lessonQuiz = Quiz::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'is_active' => true,
            'attempt_limit' => 3,
        ]);

        UserProgress::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        QuizAttempt::query()->create([
            'user_id' => $learner->id,
            'quiz_id' => $lessonQuiz->id,
            'score' => 67,
            'passed' => false,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'feedback_type' => 'instructor',
                'rating' => 4,
                'review_content' => 'I finished the module and can leave instructor feedback.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('instructor_feedback', [
            'learner_id' => $learner->id,
            'rating' => 4,
        ]);
    }

    /**
     * @return array{0: User, 1: Module, 2: Lesson}
     */
    private function createLearnerAndModule(): array
    {
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'status' => 'active',
        ]);
        $instructor->assignRole('instructor');

        $learner = User::factory()->create([
            'role' => 'learner',
            'status' => 'active',
        ]);
        $learner->assignRole('learner');

        LearnerProfile::query()->create([
            'user_id' => $learner->id,
            'username' => 'feedback_' . $learner->id,
            'birthdate' => now()->subYears(15)->toDateString(),
            'age_range' => 'teens_13_17',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Test bio',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'is_published' => true,
            'current_review_status' => null,
            'min_age' => 13,
            'max_age' => 17,
            'enrollment_mode' => 'auto',
            'final_quiz_id' => null,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'is_published' => true,
            'order' => 1,
        ]);

        ModuleEnrollment::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        return [$learner, $module, $lesson];
    }
}
