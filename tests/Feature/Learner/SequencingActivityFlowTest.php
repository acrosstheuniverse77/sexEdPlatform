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

class SequencingActivityFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_sequence_payload_restores_saved_order_without_answer_hints(): void
    {
        [$learner, $activity] = $this->fixture();
        InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
            'working_state' => ['item_order' => ['item-3', 'item-1', 'item-2']],
        ]);

        $response = $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertOk()
            ->assertJsonPath('payload.items.0.id', 'item-3')
            ->assertJsonMissingPath('configuration')
            ->assertJsonMissingPath('correct_position');

        $this->assertStringNotContainsString('correct_position', $response->getContent());
    }

    public function test_sequence_state_save_accepts_exact_order_without_incrementing_attempts(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->putJson(route('learner.interactive-activities.state', $activity), [
                'revision' => 1,
                'state' => ['item_order' => ['item-3', 'item-1', 'item-2']],
            ])
            ->assertOk()
            ->assertJsonPath('attempt_count', 0);

        $this->assertDatabaseHas('interactive_activity_progress', [
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'attempt_count' => 0,
        ]);
    }

    public function test_wrong_sequence_is_accepted_once_without_position_hints_then_correct_completes(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.check-sequence', $activity), [
                'revision' => 1,
                'item_order' => ['item-2', 'item-1', 'item-3'],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('is_correct', false)
            ->assertJsonPath('attempt_count', 1)
            ->assertJsonMissingPath('correct_position');

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.check-sequence', $activity), [
                'revision' => 1,
                'item_order' => ['item-1', 'item-2', 'item-3'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('attempt_count', 2)
            ->assertJsonPath('explanation', 'Sequence explained here.');
    }

    public function test_invalid_sequence_ids_are_rejected_without_incrementing(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.check-sequence', $activity), [
                'revision' => 1,
                'item_order' => ['item-1', 'item-1', 'unknown'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('attempt_count', 0)
            ->assertJsonMissingPath('configuration');
    }

    public function test_sequence_endpoint_rejects_matching_activities_and_practice_is_non_mutating(): void
    {
        [$learner, $matching] = $this->fixture(InteractiveActivityType::MATCHING);

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.check-sequence', $matching), [
                'revision' => 1,
                'item_order' => ['item-1', 'item-2', 'item-3'],
            ])
            ->assertStatus(422);

        [$practiceLearner, $activity] = $this->fixture();
        $progress = InteractiveActivityProgress::factory()->create([
            'user_id' => $practiceLearner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
            'status' => 'completed',
            'attempt_count' => 5,
            'completed_at' => now(),
        ]);
        $this->assertTrue($activity->lessonTopic->lesson->module->isLearnerVisible());
        $this->assertDatabaseHas('module_enrollments', [
            'user_id' => $practiceLearner->id,
            'module_id' => $activity->lessonTopic->lesson->module_id,
            'status' => EnrollmentStatus::Approved->value,
        ]);

        $this->actingAs($practiceLearner)
            ->postJson(route('learner.interactive-activities.practice', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'practice')
            ->assertJsonPath('explanation', null);

        $this->assertSame(5, $progress->refresh()->attempt_count);
    }

    /** @return array{User, InteractiveActivity} */
    private function fixture(InteractiveActivityType $type = InteractiveActivityType::SEQUENCING): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true, 'current_review_status' => null]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive']);
        $configuration = $type === InteractiveActivityType::SEQUENCING
            ? [
                'schema_version' => 1,
                'items' => [
                    ['id' => 'item-1', 'kind' => 'text', 'value' => 'First', 'correct_position' => 1],
                    ['id' => 'item-2', 'kind' => 'text', 'value' => 'Second', 'correct_position' => 2],
                    ['id' => 'item-3', 'kind' => 'text', 'value' => 'Third', 'correct_position' => 3],
                ],
            ]
            : [
                'schema_version' => 1,
                'pairs' => [
                    ['id' => 'pair-1', 'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'One'], 'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'First']],
                    ['id' => 'pair-2', 'left' => ['id' => 'left-2', 'kind' => 'text', 'value' => 'Two'], 'right' => ['id' => 'right-2', 'kind' => 'text', 'value' => 'Second']],
                ],
            ];
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $topic->id,
            'placement' => 'inside_topic',
            'activity_type' => $type,
            'title' => 'Sequence activity',
            'explanation' => 'Sequence explained here.',
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
