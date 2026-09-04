<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\InteractiveCheckpointProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointProgressIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_lesson_completion_ignores_uncompleted_between_topic_checkpoint(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 1]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'order' => 2]);

        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);
        LessonTopicProgress::create([
            'user_id' => $learner->id,
            'lesson_topic_id' => $topic->id,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $this->assertTrue($lesson->allTopicsCompletedBy($learner->id));
        $this->assertSame(100, $lesson->getTopicCompletionPercentage($learner->id));
    }

    public function test_skipped_checkpoint_does_not_block_default_navigation_or_required_progress(): void
    {
        [$learner, $lesson, $previousTopic, $checkpoint, $question, $nextTopic] = $this->orderedCheckpointFixture();
        LessonTopicProgress::create([
            'user_id' => $learner->id,
            'lesson_topic_id' => $previousTopic->id,
            'completed' => true,
            'completed_at' => now(),
        ]);
        InteractiveCheckpointProgress::create([
            'user_id' => $learner->id,
            'lesson_topic_id' => $checkpoint->id,
            'quiz_question_id' => $question->id,
            'status' => 'skipped',
            'skipped_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($learner)
            ->get(route('learner.lessons.show', $lesson))
            ->assertOk()
            ->assertViewHas('currentTopic', fn (LessonTopic $topic) => $topic->is($nextTopic));

        $this->assertSame(50, $lesson->getTopicCompletionPercentage($learner->id));
    }

    public function test_checkpoint_topic_cannot_be_completed_through_topic_endpoint(): void
    {
        [$learner, , , $checkpoint] = $this->orderedCheckpointFixture();

        $this->actingAs($learner)
            ->post(route('learner.topics.complete', $checkpoint))
            ->assertNotFound();

        $this->assertDatabaseMissing('lesson_topic_progress', [
            'user_id' => $learner->id,
            'lesson_topic_id' => $checkpoint->id,
        ]);
    }

    private function orderedCheckpointFixture(): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $previousTopic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 1]);
        $checkpoint = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive_checkpoint',
            'order' => 2,
            'duration' => 0,
        ]);
        $nextTopic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 3]);
        $question = QuizQuestion::create([
            'checkpoint_topic_id' => $checkpoint->id,
            'question_text' => 'Optional question',
            'question_type' => 'multiple_choice',
            'points' => 1,
            'order' => 1,
        ]);
        $question->options()->create(['option_text' => 'Correct', 'is_correct' => true, 'order' => 0]);
        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        return [$learner, $lesson, $previousTopic, $checkpoint, $question, $nextTopic];
    }
}
