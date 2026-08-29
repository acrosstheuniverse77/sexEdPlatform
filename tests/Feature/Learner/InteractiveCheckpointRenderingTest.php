<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\InteractiveCheckpointProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_video_topic_keeps_content_before_checkpoint(): void
    {
        [$learner, $topic, $question] = $this->insideCheckpointFixture('video', [
            'text_content' => '<p>Watch before answering.</p>',
            'video_provider' => 'youtube',
            'video_id' => 'dQw4w9WgXcQ',
        ]);

        $html = $this->page($learner, $topic);

        $this->assertOrdered($html, 'Watch before answering.', 'youtube.com/embed', $question->question_text);
    }

    public function test_text_topic_renders_body_images_and_ordered_checkpoints_once(): void
    {
        [$learner, $topic, $first] = $this->insideCheckpointFixture('text', [
            'text_content' => '<p>Canonical text body</p>',
            'image_attachments' => [[
                'path' => 'lesson-images/example.jpg',
                'caption' => 'Example caption',
            ]],
        ]);
        $second = $this->appendCheckpoint($topic, 'second-block', 'Second checkpoint');

        $html = $this->page($learner, $topic);

        $this->assertSame(1, substr_count($html, 'Canonical text body'));
        $this->assertStringContainsString('Example caption', $html);
        $this->assertOrdered($html, $first->question_text, $second->question_text);
    }

    public function test_text_topic_uses_explicit_rich_text_blocks_once(): void
    {
        [$learner, $topic] = $this->insideCheckpointFixture('text', [
            'text_content' => '<p>Legacy text must not repeat</p>',
        ]);
        $topic->update(['content_blocks' => [
            ['type' => 'rich_text', 'html' => '<p>First rich block</p>'],
            ['type' => 'rich_text', 'html' => '<p>Second rich block</p>'],
        ]]);

        $html = $this->page($learner, $topic);

        $this->assertSame(1, substr_count($html, 'First rich block'));
        $this->assertSame(1, substr_count($html, 'Second rich block'));
        $this->assertStringNotContainsString('Legacy text must not repeat', $html);
    }

    public function test_worksheet_topic_renders_instructions_file_and_checkpoint_in_order(): void
    {
        [$learner, $topic, $question] = $this->insideCheckpointFixture('worksheet', [
            'text_content' => '<p>Download and complete the worksheet.</p>',
            'file_path' => 'worksheets/activity.pdf',
        ]);

        $this->assertOrdered(
            $this->page($learner, $topic),
            'Download and complete the worksheet.',
            'activity.pdf',
            $question->question_text,
        );
    }

    public function test_invalid_checkpoint_reference_does_not_hide_topic_body(): void
    {
        [$learner, $topic] = $this->insideCheckpointFixture('text', [
            'text_content' => '<p>Content remains visible</p>',
        ]);
        $topic->update(['content_blocks' => [[
            'type' => 'checkpoint', 'uuid' => 'missing-block', 'question_id' => 999999,
        ]]]);

        $this->actingAs($learner)
            ->get(route('learner.lessons.show', $topic->lesson))
            ->assertOk()
            ->assertSee('Content remains visible', false)
            ->assertDontSee('Quick Check');
    }

    public function test_between_topic_checkpoint_renders_as_its_own_learning_item(): void
    {
        [$learner, $topic, $question] = $this->betweenCheckpointFixture();

        $this->actingAs($learner)
            ->get(route('learner.lessons.show', ['lesson' => $topic->lesson, 'topic' => 0]))
            ->assertOk()
            ->assertSee('Quick Check')
            ->assertSee($question->question_text)
            ->assertSee('Skip for now');
    }

    public function test_checkpoint_sidebar_uses_optional_quick_check_metadata(): void
    {
        [$learner, $topic] = $this->betweenCheckpointFixture();

        $html = $this->actingAs($learner)
            ->get(route('learner.lessons.show', $topic->lesson))
            ->assertOk()
            ->assertSee('QUICK CHECK · Optional', false)
            ->getContent();

        $checkpointRow = substr($html, strpos($html, $topic->title), 500);
        $this->assertStringNotContainsString('0m', $checkpointRow);
        $this->assertStringNotContainsString('Required', $checkpointRow);
    }

    public function test_lesson_footer_has_one_coordinated_action_region(): void
    {
        [$learner, $topic] = $this->betweenCheckpointFixture();

        $html = $this->actingAs($learner)
            ->get(route('learner.lessons.show', $topic->lesson))
            ->assertOk()
            ->assertSee('checkpointCoordinator()', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-lesson-footer'));
    }

    public function test_resolved_inside_checkpoint_claims_footer_on_reload(): void
    {
        [$learner, $topic, $question] = $this->insideCheckpointFixture('text');
        InteractiveCheckpointProgress::create([
            'user_id' => $learner->id,
            'lesson_topic_id' => $topic->id,
            'quiz_question_id' => $question->id,
            'checkpoint_block_uuid' => $question->checkpoint_block_uuid,
            'status' => 'correct',
            'is_correct' => true,
            'completed_at' => now(),
        ]);

        $this->assertStringContainsString(
            'x-init="if (true) $dispatch(\'checkpoint-active\'',
            $this->page($learner, $topic),
        );
    }

    private function page(User $learner, LessonTopic $topic): string
    {
        return $this->actingAs($learner)
            ->get(route('learner.lessons.show', $topic->lesson))
            ->assertOk()
            ->getContent();
    }

    private function assertOrdered(string $html, string ...$needles): void
    {
        $positions = array_map(fn (string $needle) => strpos($html, $needle), $needles);
        foreach ($positions as $position) {
            $this->assertNotFalse($position);
        }
        foreach (array_keys($positions) as $index) {
            if ($index > 0) {
                $this->assertLessThan($positions[$index], $positions[$index - 1]);
            }
        }
    }

    private function insideCheckpointFixture(string $type, array $attributes = []): array
    {
        [$learner, $lesson] = $this->lessonFixture();
        $topic = LessonTopic::factory()->create(array_merge([
            'lesson_id' => $lesson->id,
            'type' => $type,
            'content_blocks' => [],
        ], $attributes));
        $question = $this->appendCheckpoint($topic, 'first-block', 'First checkpoint');

        return [$learner, $topic->refresh(), $question];
    }

    private function betweenCheckpointFixture(): array
    {
        [$learner, $lesson] = $this->lessonFixture();
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive_checkpoint',
            'interactive_config' => ['placement' => 'between_topics'],
            'duration' => 0,
        ]);
        $question = QuizQuestion::create([
            'checkpoint_topic_id' => $topic->id,
            'question_text' => 'Standalone checkpoint',
            'question_type' => 'multiple_choice',
            'points' => 1,
            'order' => 1,
        ]);
        $question->options()->create(['option_text' => 'Correct', 'is_correct' => true, 'order' => 0]);

        return [$learner, $topic, $question];
    }

    private function appendCheckpoint(LessonTopic $topic, string $uuid, string $text): QuizQuestion
    {
        $question = QuizQuestion::create([
            'checkpoint_topic_id' => $topic->id,
            'checkpoint_block_uuid' => $uuid,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'points' => 1,
            'order' => count($topic->content_blocks ?? []) + 1,
        ]);
        $question->options()->create(['option_text' => 'Correct', 'is_correct' => true, 'order' => 0]);
        $blocks = $topic->content_blocks ?? [];
        $blocks[] = ['type' => 'checkpoint', 'uuid' => $uuid, 'question_id' => $question->id];
        $topic->update(['content_blocks' => $blocks]);

        return $question;
    }

    private function lessonFixture(): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        return [$learner, $lesson];
    }
}
