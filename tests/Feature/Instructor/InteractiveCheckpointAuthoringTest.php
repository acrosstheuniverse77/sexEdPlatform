<?php

namespace Tests\Feature\Instructor;

use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_inactive_checkpoint_fields_are_disabled_in_generic_topic_form(): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');

        $this->actingAs($instructor)
            ->get(route('instructor.topics.create', ['lesson' => $lesson]))
            ->assertOk()
            ->assertSee('id="checkpointQuestionFields" disabled', false)
            ->assertSee("checkpointQuestionFields.disabled = type !== 'interactive_checkpoint';", false);
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
                'points' => 37,
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
            'points' => 1,
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

    #[DataProvider('checkpointPayloadProvider')]
    public function test_instructor_can_create_each_checkpoint_question_type(string $type, array $question): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), array_merge([
                'lesson_id' => $lesson->id,
                'title' => "{$type} checkpoint",
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'between_topics',
                'question_type' => $type,
                'question_text' => str_starts_with($type, 'fill_blank') ? '_____ follows _____.' : '<p>Question text</p>',
                'points' => 1,
                'explanation' => 'Optional learner feedback.',
            ], $question))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $saved = QuizQuestion::query()->where('question_type', $type)->latest('id')->firstOrFail();
        $this->assertSame(1, $saved->points);
        $this->assertSame('Optional learner feedback.', $saved->explanation);
    }

    public static function checkpointPayloadProvider(): array
    {
        return [
            'multiple choice' => ['multiple_choice', ['options' => ['A', 'B'], 'correct_options' => [0]]],
            'true false' => ['true_false', ['options' => ['stale', 'values'], 'correct_options' => [1]]],
            'identification' => ['identification', ['acceptable_answers' => ['Consent', 'Permission'], 'case_sensitive' => 1]],
            'fill blank text' => ['fill_blank_text', ['acceptable_answers' => ['alpha|Alpha', 'beta']]],
            'fill blank word bank' => ['fill_blank_select', ['word_bank' => 'alpha, beta, extra', 'acceptable_answers' => ['alpha', 'beta']]],
            'multiple select' => ['multiple_select', ['options' => ['A', 'B', 'C'], 'correct_options' => [0, 2]]],
        ];
    }

    public function test_invalid_checkpoint_writes_no_topic_question_or_option(): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');
        $topicCount = LessonTopic::count();
        $questionCount = QuizQuestion::count();

        $this->actingAs($instructor)
            ->from(route('instructor.topics.create', ['lesson' => $lesson]))
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Invalid checkpoint',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'between_topics',
                'question_type' => 'multiple_choice',
                'question_text' => '<p><br></p>',
                'points' => 1,
                'options' => ['Only one'],
                'correct_options' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['question_text', 'options', 'correct_options']);

        $this->assertSame($topicCount, LessonTopic::count());
        $this->assertSame($questionCount, QuizQuestion::count());
    }

    public function test_between_topic_checkpoint_edit_updates_same_question_and_keeps_placement(): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'title' => 'Original title', 'duration' => 1, 'interactive_config' => ['placement' => 'between_topics']]);
        $question = QuizQuestion::create(['quiz_id' => null, 'checkpoint_topic_id' => $topic->id, 'checkpoint_block_uuid' => null, 'question_text' => 'Original question', 'question_type' => 'true_false', 'points' => 1, 'order' => 1]);
        $question->options()->createMany([
            ['option_text' => 'True', 'is_correct' => true, 'order' => 0],
            ['option_text' => 'False', 'is_correct' => false, 'order' => 1],
        ]);

        $this->actingAs($instructor)->get(route('instructor.topics.edit', $topic))->assertOk()->assertSee('Between Topics')->assertSee('Original question');
        $this->actingAs($instructor)->put(route('instructor.topics.update', $topic), [
            'title' => 'Updated title', 'duration' => 2, 'question_type' => 'identification',
            'question_text' => '<p>Name the concept.</p>', 'points' => 1,
            'acceptable_answers' => ['Consent'], 'explanation' => 'Updated explanation.',
            'checkpoint_placement' => 'inside_topic',
        ])->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame($question->id, $topic->checkpointQuestion()->value('id'));
        $this->assertSame('between_topics', $topic->refresh()->interactive_config['placement']);
        $this->assertSame('identification', $question->refresh()->question_type);
        $this->assertNull($question->checkpoint_block_uuid);
    }

    public function test_validation_rerender_preserves_identification_image_removal_intent(): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive_checkpoint',
            'interactive_config' => ['placement' => 'between_topics'],
        ]);
        $question = QuizQuestion::create([
            'checkpoint_topic_id' => $topic->id,
            'question_text' => 'Name it.',
            'question_type' => 'identification',
            'acceptable_answers' => 'Consent',
            'image_path' => 'quiz-images/user-1/prompt.png',
            'points' => 1,
            'order' => 1,
        ]);
        $editUrl = route('instructor.topics.edit', $topic);

        $this->actingAs($instructor)
            ->from($editUrl)
            ->put(route('instructor.topics.update', $topic), [
                'title' => $topic->title,
                'duration' => 1,
                'question_type' => 'identification',
                'question_text' => 'Name it.',
                'acceptable_answers' => [''],
                'remove_existing_image' => 1,
            ])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('acceptable_answers.0');

        $this->get($editUrl)
            ->assertOk()
            ->assertDontSee($question->image_url);
    }

    public function test_inside_topic_checkpoint_edit_preserves_block_uuid_and_position(): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id, 'type' => 'text',
            'content_blocks' => [
                ['type' => 'rich_text', 'html' => '<p>Before</p>'],
                ['type' => 'checkpoint', 'uuid' => 'block-uuid', 'question_id' => 999],
                ['type' => 'rich_text', 'html' => '<p>After</p>'],
            ],
        ]);
        $question = QuizQuestion::create(['quiz_id' => null, 'checkpoint_topic_id' => $topic->id, 'checkpoint_block_uuid' => 'block-uuid', 'question_text' => 'Old question', 'question_type' => 'multiple_choice', 'points' => 1, 'order' => 1]);
        $blocks = $topic->content_blocks;
        $blocks[1]['question_id'] = $question->id;
        $topic->update(['content_blocks' => $blocks]);
        $question->options()->createMany([
            ['option_text' => 'A', 'is_correct' => true, 'order' => 0],
            ['option_text' => 'B', 'is_correct' => false, 'order' => 1],
        ]);

        $this->actingAs($instructor)->put(route('instructor.topics.checkpoints.update', [$topic, $question]), [
            'question_type' => 'multiple_select', 'question_text' => '<p>Choose two.</p>', 'points' => 1,
            'options' => ['A', 'B', 'C'], 'correct_options' => [0, 2],
            'explanation' => 'Two answers are correct.', 'checkpoint_placement' => 'between_topics',
        ])->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame($question->id, $question->refresh()->id);
        $this->assertSame('block-uuid', $question->checkpoint_block_uuid);
        $this->assertSame($blocks, $topic->refresh()->content_blocks);
        $this->assertCount(2, $question->options()->where('is_correct', true)->get());
    }

    public function test_inside_checkpoint_route_rejects_a_question_from_another_topic(): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text']);
        $otherTopic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text']);
        $question = QuizQuestion::create(['quiz_id' => null, 'checkpoint_topic_id' => $otherTopic->id, 'checkpoint_block_uuid' => 'other-block', 'question_text' => 'Other question', 'question_type' => 'identification', 'acceptable_answers' => 'answer', 'points' => 1, 'order' => 1]);

        $this->actingAs($instructor)->get(route('instructor.topics.checkpoints.edit', [$topic, $question]))->assertNotFound();
    }

    public function test_admin_can_edit_an_admin_owned_checkpoint_through_admin_routes(): void
    {
        [$admin, $lesson] = $this->authoringFixture('admin');
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'interactive_config' => ['placement' => 'between_topics']]);
        $question = QuizQuestion::create(['quiz_id' => null, 'checkpoint_topic_id' => $topic->id, 'question_text' => 'Admin checkpoint', 'question_type' => 'true_false', 'points' => 1, 'order' => 1]);
        $question->options()->createMany([
            ['option_text' => 'True', 'is_correct' => true, 'order' => 0],
            ['option_text' => 'False', 'is_correct' => false, 'order' => 1],
        ]);

        $this->actingAs($admin)->get(route('admin.topics.edit', $topic))->assertOk()->assertSee('Admin checkpoint');
        $this->actingAs($admin)->put(route('admin.topics.update', $topic), [
            'title' => 'Updated by admin', 'duration' => 1, 'question_type' => 'true_false',
            'question_text' => '<p>Updated statement.</p>', 'points' => 1,
            'correct_options' => [1], 'explanation' => '',
        ])->assertRedirect(route('admin.lessons.show', $lesson));

        $this->assertTrue($question->refresh()->options[1]->is_correct);
    }

    public function test_instructor_cannot_edit_another_instructors_checkpoint(): void
    {
        [, $lesson] = $this->authoringFixture('instructor');
        $other = User::factory()->create(['role' => 'instructor']);
        $other->assignRole('instructor');
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'interactive_config' => ['placement' => 'between_topics']]);
        QuizQuestion::create(['quiz_id' => null, 'checkpoint_topic_id' => $topic->id, 'question_text' => 'Owned by someone else', 'question_type' => 'identification', 'acceptable_answers' => 'answer', 'points' => 1, 'order' => 1]);

        $this->actingAs($other)->get(route('instructor.topics.edit', $topic))->assertForbidden();
    }

    #[DataProvider('checkpointPayloadProvider')]
    public function test_between_topic_checkpoint_can_edit_to_every_question_type(string $type, array $payload): void
    {
        [$instructor, $lesson] = $this->authoringFixture('instructor');
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'title' => 'Editable checkpoint', 'duration' => 1, 'interactive_config' => ['placement' => 'between_topics']]);
        $question = QuizQuestion::create(['quiz_id' => null, 'checkpoint_topic_id' => $topic->id, 'checkpoint_block_uuid' => null, 'question_text' => 'Old question', 'question_type' => 'multiple_choice', 'points' => 1, 'order' => 1]);
        $question->options()->createMany([
            ['option_text' => 'Old A', 'is_correct' => true, 'order' => 0],
            ['option_text' => 'Old B', 'is_correct' => false, 'order' => 1],
        ]);

        $this->actingAs($instructor)->put(route('instructor.topics.update', $topic), array_merge([
            'title' => 'Edited checkpoint', 'duration' => 2, 'question_type' => $type,
            'question_text' => str_starts_with($type, 'fill_blank') ? '_____ follows _____.' : '<p>Edited question</p>',
            'points' => 1, 'explanation' => 'Edited explanation.',
        ], $payload))->assertRedirect(route('instructor.lessons.show', $lesson));

        $question->refresh();
        $this->assertSame($type, $question->question_type);
        $this->assertSame('Edited explanation.', $question->explanation);
        $this->assertSame($topic->id, $question->checkpoint_topic_id);
        $this->assertSame('between_topics', $topic->refresh()->interactive_config['placement']);

        if (in_array($type, ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            $expectedCount = $type === 'true_false' ? 2 : count($payload['options']);
            $this->assertCount($expectedCount, $question->options()->get());
            $this->assertNull($question->acceptable_answers);
        } else {
            $this->assertCount(0, $question->options()->get());
            $separator = $type === 'identification' ? '|' : ';';
            $this->assertSame(implode($separator, $payload['acceptable_answers']), $question->acceptable_answers);
        }
    }

    private function authoringFixture(string $role): array
    {
        $author = User::factory()->create(['role' => $role]);
        $author->assignRole($role);
        $module = Module::factory()->create([
            'created_by' => $author->id,
            'content_owner_type' => $role === 'admin' ? 'admin' : 'instructor',
        ]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        return [$author, $lesson];
    }
}
