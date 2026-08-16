<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\Quiz;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;

class ModuleOverviewLayoutTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public function test_module_show_page_uses_expected_right_rail_order_and_hides_module_assessment_section(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $instructor, $module] = $this->createLearnerAndModule();

        Quiz::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => null,
            'title' => 'Final Module Checkpoint',
            'is_active' => true,
        ]);

        $this->actingAs($learner)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertDontSee('Module Assessment', false)
            ->assertDontSee('sticky top-6', false)
            ->assertSeeInOrder([
                'Your Progress',
                'Instructor Information',
                'Module Info',
                'Module Reviews',
            ], false);
    }

    public function test_module_show_page_displays_quiz_markers_inside_curriculum_hierarchy(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $instructor, $module] = $this->createLearnerAndModule();

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Lesson One',
            'order' => 1,
            'is_published' => true,
        ]);

        Quiz::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'title' => 'Lesson 1 Quiz',
            'is_active' => true,
        ]);

        $this->actingAs($learner)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertSee('Module Curriculum', false)
            ->assertSee('data-lesson-breakdown', false)
            ->assertSee('data-lesson-quiz-indicator', false)
            ->assertSee('Lesson 1 Quiz', false)
            ->assertDontSee('Module Assessment', false);
    }

    public function test_module_show_uses_review_modal_trigger_instead_of_inline_form(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $instructor, $module] = $this->createLearnerAndModule();

        $this->actingAs($learner)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertSee('Write Review', false)
            ->assertDontSee('Your Review</label>', false);
    }

    public function test_module_show_uses_report_icon_and_dual_target_modal_actions(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $instructor, $module] = $this->createLearnerAndModule();

        $this->actingAs($learner)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertSee('aria-label="Report module or instructor"', false)
            ->assertSee('Report Module', false)
            ->assertSee('Report Instructor', false);
    }

    public function test_instructor_card_exposes_message_icon_with_module_chat_context_payload(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        [$learner, $instructor, $module] = $this->createLearnerAndModule();

        $this->actingAs($learner)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertSee('open-global-chat', false)
            ->assertSee('module_chat', false);
    }

    /**
     * @return array{0: User, 1: User, 2: Module}
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
            'username' => 'layout_' . $learner->id,
            'birthdate' => now()->subYears(16)->toDateString(),
            'age_range' => 'teens_13_17',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Layout profile',
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
            'title' => 'Default Lesson',
            'order' => 1,
            'is_published' => true,
        ]);

        ModuleEnrollment::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        UserProgress::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        return [$learner, $instructor, $module];
    }
}
