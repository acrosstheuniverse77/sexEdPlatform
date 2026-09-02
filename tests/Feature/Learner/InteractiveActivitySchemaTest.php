<?php

namespace Tests\Feature\Learner;

use App\Enums\InteractiveActivityType;
use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Models\LessonTopic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InteractiveActivitySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_activity_schema_and_relationships_are_persisted(): void
    {
        $this->assertTrue(Schema::hasTable('interactive_activities'));
        $this->assertTrue(Schema::hasTable('interactive_activity_progress'));
        $this->assertTrue(Schema::hasColumns('interactive_activities', [
            'id', 'lesson_topic_id', 'placement', 'block_uuid', 'activity_type',
            'title', 'instructions', 'explanation', 'configuration', 'revision',
            'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('interactive_activity_progress', [
            'id', 'user_id', 'interactive_activity_id', 'activity_revision',
            'status', 'working_state', 'attempt_count', 'started_at',
            'completed_at', 'skipped_at', 'created_at', 'updated_at',
        ]));

        $topic = LessonTopic::factory()->create();
        $activity = InteractiveActivity::factory()->matching()->insideTopic()->create([
            'lesson_topic_id' => $topic->id,
        ]);

        $this->assertSame(InteractiveActivityType::MATCHING, $activity->activity_type);
        $this->assertSame(1, $activity->revision);
        $this->assertIsArray($activity->configuration);
        $this->assertSame($activity->id, $topic->interactiveActivities()->firstOrFail()->id);

        $standalone = InteractiveActivity::factory()->betweenTopics()->create([
            'lesson_topic_id' => $topic->id,
        ]);

        $this->assertSame($standalone->id, $topic->standaloneInteractiveActivity->id);

        $progress = InteractiveActivityProgress::factory()->create([
            'interactive_activity_id' => $activity->id,
            'activity_revision' => $activity->revision,
            'working_state' => ['matches' => []],
        ]);

        $this->assertIsArray($progress->working_state);
        $this->assertSame(0, $progress->attempt_count);
        $this->assertNotNull($activity->block_uuid);

        $this->expectException(QueryException::class);
        InteractiveActivityProgress::factory()->create([
            'user_id' => $progress->user_id,
            'interactive_activity_id' => $progress->interactive_activity_id,
            'activity_revision' => $progress->activity_revision,
        ]);
    }

    public function test_activity_and_topic_deletes_cascade_to_owned_records(): void
    {
        $activity = InteractiveActivity::factory()->create();
        $progress = InteractiveActivityProgress::factory()->create([
            'interactive_activity_id' => $activity->id,
        ]);

        $activity->delete();

        $this->assertDatabaseMissing('interactive_activity_progress', ['id' => $progress->id]);

        $topic = LessonTopic::factory()->create();
        $topicActivity = InteractiveActivity::factory()->create(['lesson_topic_id' => $topic->id]);

        $topic->delete();

        $this->assertDatabaseMissing('interactive_activities', ['id' => $topicActivity->id]);
    }
}
