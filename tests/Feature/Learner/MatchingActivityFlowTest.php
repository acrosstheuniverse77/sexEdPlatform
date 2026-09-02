<?php

declare(strict_types=1);

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Enums\InteractiveActivityType;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\User;
use Tests\TestCase;

class MatchingActivityFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_matching_payload_is_safe_and_restores_persisted_right_order(): void
    {
        [$learner, $activity] = $this->fixture();
        InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
            'working_state' => ['right_order' => ['right-2', 'right-1'], 'matched' => []],
        ]);

        $response = $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertOk()
            ->assertJsonPath('payload.right_items.0.id', 'right-2')
            ->assertJsonMissingPath('configuration')
            ->assertJsonMissingPath('pair_id');

        $this->assertStringNotContainsString('correct_mapping', $response->getContent());
    }

    public function test_correct_and_incorrect_matching_proposals_have_one_attempt_each(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'left-1',
                'right_id' => 'right-2',
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('is_correct', false)
            ->assertJsonPath('attempt_count', 1)
            ->assertJsonMissingPath('configuration');

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'left-1',
                'right_id' => 'right-1',
            ])
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('attempt_count', 2);
    }

    public function test_unknown_matching_ids_are_rejected_without_incrementing(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'unknown',
                'right_id' => 'right-1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('attempt_count', 0)
            ->assertJsonMissingPath('configuration')
            ->assertJsonMissingPath('pair_id');

        $this->assertDatabaseHas('interactive_activity_progress', [
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'attempt_count' => 0,
        ]);
    }

    public function test_matching_endpoint_rejects_sequencing_activities(): void
    {
        [$learner, $activity] = $this->fixture(InteractiveActivityType::SEQUENCING);

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'left-1',
                'right_id' => 'right-1',
            ])
            ->assertStatus(422)
            ->assertJsonMissingPath('configuration');
    }

    public function test_matching_practice_does_not_mutate_persisted_completion(): void
    {
        [$learner, $activity] = $this->fixture();
        $progress = InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
            'status' => 'completed',
            'attempt_count' => 4,
            'completed_at' => now(),
        ]);

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.practice', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'practice')
            ->assertJsonPath('explanation', null);

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'practice' => true,
                'working_state' => ['matched' => []],
                'left_id' => 'left-1',
                'right_id' => 'right-1',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'practice')
            ->assertJsonPath('explanation', null);

        $this->assertSame(4, $progress->refresh()->attempt_count);
        $this->assertSame('completed', $progress->status);
    }

    /** @return array{User, InteractiveActivity} */
    private function fixture(InteractiveActivityType $type = InteractiveActivityType::MATCHING): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true, 'current_review_status' => null]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive']);
        $configuration = $type === InteractiveActivityType::MATCHING
            ? [
                'schema_version' => 1,
                'pairs' => [
                    ['id' => 'pair-1', 'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'One'], 'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'First']],
                    ['id' => 'pair-2', 'left' => ['id' => 'left-2', 'kind' => 'text', 'value' => 'Two'], 'right' => ['id' => 'right-2', 'kind' => 'text', 'value' => 'Second']],
                ],
            ]
            : [
                'schema_version' => 1,
                'items' => [
                    ['id' => 'item-1', 'kind' => 'text', 'value' => 'One', 'correct_position' => 1],
                    ['id' => 'item-2', 'kind' => 'text', 'value' => 'Two', 'correct_position' => 2],
                    ['id' => 'item-3', 'kind' => 'text', 'value' => 'Three', 'correct_position' => 3],
                ],
            ];
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $topic->id,
            'placement' => 'inside_topic',
            'activity_type' => $type,
            'title' => 'Learner activity',
            'explanation' => 'Review the explanation.',
            'configuration' => $configuration,
            'revision' => 1,
        ]);

        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        return [$learner, $activity];
    }
}
