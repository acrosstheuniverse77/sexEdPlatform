<?php

namespace Tests\Feature\Instructor;

use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointAuthoringTest extends TestCase
{
    public function test_instructor_can_create_between_topic_checkpoint(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id, 'content_owner_type' => 'instructor']);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Understanding Consent',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'between_topics',
                'question_text' => 'Consent requires free agreement.',
                'question_type' => 'true_false',
                'points' => 1,
                'options' => ['True', 'False'],
                'correct_options' => [0],
                'explanation' => 'Consent cannot be pressured.',
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $topic = LessonTopic::where('lesson_id', $lesson->id)->where('type', 'interactive_checkpoint')->firstOrFail();
        $this->assertSame('between_topics', $topic->interactive_config['placement']);
        $this->assertDatabaseHas('quiz_questions', [
            'checkpoint_topic_id' => $topic->id,
            'question_type' => 'true_false',
            'explanation' => 'Consent cannot be pressured.',
        ]);
    }

    public function test_instructor_can_add_inside_topic_checkpoint_block(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id, 'content_owner_type' => 'instructor']);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $parentTopic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'text_content' => '<p>First</p><p>Second</p>',
        ]);

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Inline Check',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'inside_topic',
                'parent_topic_id' => $parentTopic->id,
                'insert_after_block' => 0,
                'question_text' => 'Pick two safe actions.',
                'question_type' => 'multiple_select',
                'points' => 1,
                'options' => ['Ask', 'Pressure', 'Pause'],
                'correct_options' => [0, 2],
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $parentTopic->refresh();
        $this->assertNotNull($parentTopic->content_blocks);
        $this->assertSame('checkpoint', $parentTopic->content_blocks[1]['type']);
        $this->assertSame(1, QuizQuestion::where('checkpoint_topic_id', $parentTopic->id)->checkpoint()->count());
        $this->assertSame(0, LessonTopic::where('lesson_id', $lesson->id)->where('type', 'interactive_checkpoint')->count());
    }
}
