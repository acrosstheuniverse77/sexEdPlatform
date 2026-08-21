<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\UserDailyShield;
use Tests\TestCase;

class InteractiveCheckpointFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_learner_can_answer_checkpoint_without_spending_shield_or_creating_quiz_attempt(): void
    {
        [$learner, $question] = $this->checkpointFixture();
        $correctOption = $question->options()->where('is_correct', true)->first();
        UserDailyShield::refillFull($learner);
        $before = UserDailyShield::getShields($learner);

        $this->actingAs($learner)
            ->postJson(route('learner.checkpoints.submit', $question), [
                'answer' => $correctOption->id,
            ])
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('status', 'correct')
            ->assertJsonPath('explanation', 'Consent must be freely given.');

        $this->assertSame($before, UserDailyShield::getShields($learner->refresh()));
        $this->assertSame(0, QuizAttempt::count());
        $this->assertDatabaseHas('interactive_checkpoint_progress', [
            'user_id' => $learner->id,
            'quiz_question_id' => $question->id,
            'status' => 'correct',
            'is_correct' => true,
            'attempt_count' => 1,
        ]);
    }

    public function test_learner_can_skip_checkpoint_without_being_marked_incorrect(): void
    {
        [$learner, $question] = $this->checkpointFixture();

        $this->actingAs($learner)
            ->postJson(route('learner.checkpoints.skip', $question))
            ->assertOk()
            ->assertJsonPath('status', 'skipped')
            ->assertJsonPath('is_correct', null);

        $this->assertDatabaseHas('interactive_checkpoint_progress', [
            'user_id' => $learner->id,
            'quiz_question_id' => $question->id,
            'status' => 'skipped',
            'is_correct' => null,
        ]);
    }

    public function test_unenrolled_learner_cannot_submit_checkpoint(): void
    {
        [, $question] = $this->checkpointFixture();
        $otherLearner = User::factory()->create(['role' => 'learner']);
        $otherLearner->assignRole('learner');

        $this->actingAs($otherLearner)
            ->postJson(route('learner.checkpoints.submit', $question), ['answer' => 1])
            ->assertForbidden();
    }

    public function test_between_topic_checkpoint_appears_in_lesson_navigation(): void
    {
        [$learner, $question] = $this->checkpointFixture();

        $this->actingAs($learner)
            ->get(route('learner.lessons.show', $question->checkpointTopic->lesson))
            ->assertOk()
            ->assertSee('Quick Check')
            ->assertSee('What does consent require?')
            ->assertSee('Skip for now');
    }

    public function test_inside_topic_checkpoint_renders_between_content_blocks(): void
    {
        [$learner, $question] = $this->checkpointFixture('inside_topic');
        $topic = $question->checkpointTopic;
        $topic->update([
            'type' => 'text',
            'content_blocks' => [
                ['type' => 'rich_text', 'html' => '<p>Before checkpoint</p>'],
                ['type' => 'checkpoint', 'uuid' => $question->checkpoint_block_uuid, 'question_id' => $question->id],
                ['type' => 'rich_text', 'html' => '<p>After checkpoint</p>'],
            ],
        ]);

        $response = $this->actingAs($learner)
            ->get(route('learner.lessons.show', $topic->lesson));

        $response->assertOk()
            ->assertSee('Before checkpoint', false)
            ->assertSee('Quick Check')
            ->assertSee('After checkpoint', false);
    }

    private function checkpointFixture(string $placement = 'between_topics'): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => $placement === 'inside_topic' ? 'text' : 'interactive_checkpoint',
            'interactive_config' => ['placement' => $placement],
        ]);

        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => null,
            'checkpoint_topic_id' => $topic->id,
            'checkpoint_block_uuid' => $placement === 'inside_topic' ? 'test-block-1' : null,
            'question_text' => 'What does consent require?',
            'question_type' => 'multiple_choice',
            'points' => 1,
            'order' => 1,
            'explanation' => 'Consent must be freely given.',
        ]);
        $question->options()->create(['option_text' => 'Pressure', 'is_correct' => false, 'order' => 0]);
        $question->options()->create(['option_text' => 'Free agreement', 'is_correct' => true, 'order' => 1]);

        return [$learner, $question->refresh()];
    }
}
