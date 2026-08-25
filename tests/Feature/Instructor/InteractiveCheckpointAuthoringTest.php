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
    public function test_topic_create_page_shows_checkpoint_authoring_controls(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id, 'content_owner_type' => 'instructor']);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $this->actingAs($instructor)
            ->get(route('instructor.topics.create', ['lesson' => $lesson->id]))
            ->assertOk()
            ->assertSee('Interactive Checkpoint')
            ->assertSee('Inside Topic')
            ->assertSee('Between Topics')
            ->assertSee('Question Type')
            ->assertSee('Explanation');
    }

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

    public function test_admin_can_create_multiple_choice_checkpoint_when_hidden_acceptable_answer_field_is_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $module = Module::factory()->create([
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
        ]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $this->actingAs($admin)
            ->post(route('admin.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Admin MC Checkpoint',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'between_topics',
                'question_text' => 'Which answer is clear consent?',
                'question_type' => 'multiple_choice',
                'points' => 1,
                'options' => ['Yes', 'Silence', 'Pressure', 'Maybe'],
                'correct_options' => [0],
                'acceptable_answers' => [''],
            ])
            ->assertRedirect(route('admin.lessons.show', $lesson));

        $this->assertDatabaseHas('quiz_questions', [
            'question_text' => 'Which answer is clear consent?',
            'question_type' => 'multiple_choice',
            'acceptable_answers' => null,
        ]);
    }

    public function test_admin_can_create_identification_checkpoint_when_hidden_option_fields_are_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $module = Module::factory()->create([
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
        ]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $this->actingAs($admin)
            ->post(route('admin.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Admin Identification Checkpoint',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'between_topics',
                'question_text' => 'Name the concept.',
                'question_type' => 'identification',
                'points' => 1,
                'options' => ['', '', '', ''],
                'correct_options' => [],
                'acceptable_answers' => ['Consent'],
            ])
            ->assertRedirect(route('admin.lessons.show', $lesson));

        $question = QuizQuestion::where('question_text', 'Name the concept.')->firstOrFail();
        $this->assertSame('identification', $question->question_type);
        $this->assertSame('Consent', $question->acceptable_answers);
        $this->assertCount(0, $question->options);
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
