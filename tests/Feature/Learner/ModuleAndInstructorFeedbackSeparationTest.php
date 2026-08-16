<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ModuleFeedback;
use App\Models\User;
use App\Models\UserProgress;
use Tests\TestCase;

class ModuleAndInstructorFeedbackSeparationTest extends TestCase
{
    public function test_completed_learner_can_submit_module_and_instructor_feedback_independently(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $instructor, $module, $lesson] = $this->completedModuleFixture();

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'feedback_type' => 'instructor',
                'rating' => 5,
                'review_content' => '',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('module_feedback', 0);
        $this->assertDatabaseHas('instructor_feedback', [
            'instructor_id' => $instructor->id,
            'learner_id' => $learner->id,
            'source_module_id' => $module->id,
            'rating' => 5,
        ]);

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'feedback_type' => 'module',
                'rating' => 4,
                'review_content' => 'Clear lesson structure.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('module_feedback', [
            'module_id' => $module->id,
            'learner_id' => $learner->id,
            'rating' => 4,
        ]);

        $this->assertSame(1, ModuleFeedback::query()->count());
        $this->assertDatabaseCount('instructor_feedback', 1);

        $this->actingAs($learner)
            ->post(route('learner.modules.feedback.store', $module), [
                'feedback_type' => 'module',
                'rating' => 5,
                'review_content' => 'Updated attempt.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('module_feedback', [
            'module_id' => $module->id,
            'learner_id' => $learner->id,
            'rating' => 5,
        ]);
        $this->assertSame(1, ModuleFeedback::query()->count());
    }

    /**
     * @return array{0: User, 1: User, 2: Module, 3: Lesson}
     */
    private function completedModuleFixture(): array
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        LearnerProfile::query()->create([
            'user_id' => $learner->id,
            'username' => 'feedback_split_' . $learner->id,
            'birthdate' => now()->subYears(15)->toDateString(),
            'age_range' => 'teens_13_17',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Bio',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'is_published' => true,
            'current_review_status' => null,
            'min_age' => 13,
            'max_age' => 17,
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

        return [$learner, $instructor, $module, $lesson];
    }
}
