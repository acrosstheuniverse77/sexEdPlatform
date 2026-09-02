<?php

declare(strict_types=1);

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\User;
use Tests\TestCase;

class InteractiveActivityProgressIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_legacy_interactive_topics_are_excluded_from_completion_and_prerequisites(): void
    {
        [$learner, $lesson, $required, $activity, $following] = $this->fixture();
        LessonTopicProgress::create(['user_id' => $learner->id, 'lesson_topic_id' => $required->id, 'completed' => true, 'completed_at' => now()]);
        LessonTopicProgress::create(['user_id' => $learner->id, 'lesson_topic_id' => $following->id, 'completed' => true, 'completed_at' => now()]);

        $this->assertTrue($lesson->allTopicsCompletedBy($learner->id));
        $this->assertSame(100, $lesson->getTopicCompletionPercentage($learner->id));
        $this->assertTrue($activity->isOptionalInteraction());

        $this->actingAs($learner)
            ->get(route('learner.lessons.show', $lesson))
            ->assertOk()
            ->assertViewHas('lockedTopicIds', fn (array $ids) => ! in_array($following->id, $ids, true));
    }

    public function test_legacy_interactive_topic_cannot_create_progress_or_award_completion(): void
    {
        [$learner, , , $activity] = $this->fixture();
        $scoreBefore = (int) $learner->gamification->score;

        $this->actingAs($learner)
            ->post(route('learner.topics.complete', $activity))
            ->assertNotFound();

        $this->assertDatabaseMissing('lesson_topic_progress', ['user_id' => $learner->id, 'lesson_topic_id' => $activity->id]);
        $this->assertSame($scoreBefore, (int) $learner->fresh()->gamification->score);
        $this->assertDatabaseMissing('user_progress', ['user_id' => $learner->id, 'lesson_id' => $activity->lesson_id]);
    }

    public function test_legacy_interactive_topic_cannot_remove_progress(): void
    {
        [$learner, , , $activity] = $this->fixture();

        $this->actingAs($learner)
            ->post(route('learner.topics.uncomplete', $activity))
            ->assertNotFound();

        $this->assertDatabaseMissing('lesson_topic_progress', ['user_id' => $learner->id, 'lesson_topic_id' => $activity->id]);
    }

    /** @return array{User, Lesson, LessonTopic, LessonTopic, LessonTopic} */
    private function fixture(): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $required = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 1, 'is_prerequisite' => true]);
        $activity = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive', 'order' => 2, 'is_prerequisite' => true]);
        $following = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 3, 'is_prerequisite' => true]);
        ModuleEnrollment::create(['user_id' => $learner->id, 'module_id' => $module->id, 'status' => EnrollmentStatus::Approved, 'enrolled_at' => now()]);

        return [$learner, $lesson, $required, $activity, $following];
    }
}
