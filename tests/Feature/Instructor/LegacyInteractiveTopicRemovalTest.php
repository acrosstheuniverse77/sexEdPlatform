<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Conversation;
use App\Models\InteractiveCheckpointProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

class LegacyInteractiveTopicRemovalTest extends TestCase
{
    public function test_migration_removes_legacy_topics_and_recalculates_affected_content(): void
    {
        $module = Module::factory()->create(['duration_minutes' => 99]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'duration' => 99]);
        $unrelatedLesson = Lesson::factory()->create(['module_id' => $module->id, 'duration' => 8]);
        $ordinary = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'duration' => 5, 'order' => 4]);
        $legacy = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive', 'duration' => 99, 'order' => 2]);
        $checkpoint = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'duration' => 20, 'order' => 1]);
        $worksheet = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'worksheet', 'duration' => 7, 'order' => 4]);
        $unrelatedTopic = LessonTopic::factory()->create(['lesson_id' => $unrelatedLesson->id, 'type' => 'text', 'duration' => 8, 'order' => 1]);
        $user = User::factory()->create();

        LessonTopicProgress::create(['user_id' => $user->id, 'lesson_topic_id' => $legacy->id, 'completed' => true, 'completed_at' => now()]);
        $question = QuizQuestion::create(['checkpoint_topic_id' => $legacy->id, 'question_text' => 'Legacy question', 'question_type' => 'multiple_choice', 'points' => 1, 'order' => 1]);
        InteractiveCheckpointProgress::create(['user_id' => $user->id, 'lesson_topic_id' => $legacy->id, 'quiz_question_id' => $question->id, 'status' => 'correct', 'completed_at' => now()]);
        $conversation = Conversation::create([
            'participant_one_id' => $user->id,
            'participant_two_id' => User::factory()->create()->id,
            'pair_key' => 'legacy-topic-pair',
            'conversation_type' => Conversation::TYPE_LESSON_TOPIC_CHAT,
            'status' => Conversation::STATUS_ACTIVE,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'lesson_topic_id' => $legacy->id,
            'context_key' => 'lesson_topic:'.$legacy->id,
        ]);

        $migration = require base_path('database/migrations/2026_09_02_000002_remove_legacy_interactive_topics.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseMissing('lesson_topics', ['id' => $legacy->id]);
        $this->assertDatabaseMissing('lesson_topic_progress', ['lesson_topic_id' => $legacy->id]);
        $this->assertDatabaseMissing('quiz_questions', ['checkpoint_topic_id' => $legacy->id]);
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'lesson_topic_id' => null]);
        $this->assertSame([1, 2, 3], $lesson->topics()->orderBy('order')->pluck('order')->all());
        $this->assertSame(12, $lesson->fresh()->duration);
        $this->assertSame(20, $module->fresh()->duration_minutes);
        $this->assertTrue($ordinary->fresh()->exists);
        $this->assertTrue($checkpoint->fresh()->exists);
        $this->assertTrue($worksheet->fresh()->exists);
        $this->assertTrue($unrelatedTopic->fresh()->exists);
        $this->assertSame(8, $unrelatedLesson->fresh()->duration);
    }
}
