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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InteractiveActivityProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_first_view_creates_current_progress_and_restores_its_shuffle(): void
    {
        [$learner, $activity] = $this->fixture();

        $first = $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->json();

        $progress = InteractiveActivityProgress::query()->firstOrFail();
        $this->assertSame('in_progress', $progress->status);
        $this->assertSame(1, $progress->activity_revision);
        $this->assertSame(0, $progress->attempt_count);

        $second = $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertOk()
            ->json();

        $this->assertSame($first['payload'], $second['payload']);
        $this->assertCount(1, InteractiveActivityProgress::all());
    }

    public function test_state_for_creates_one_current_revision_row_and_answer_edits_start_a_new_revision(): void
    {
        [$learner, $activity] = $this->fixture();
        $service = app(\App\Services\Learning\InteractiveActivities\InteractiveActivityProgressService::class);

        $service->stateFor($learner, $activity);
        $service->stateFor($learner, $activity);

        $activity->update(['revision' => 2]);
        $activity->refresh();
        $current = $service->stateFor($learner, $activity);

        $this->assertSame(2, $current->activity_revision);
        $this->assertSame(2, InteractiveActivityProgress::query()->count());
        $this->assertSame([1, 2], InteractiveActivityProgress::query()->orderBy('activity_revision')->pluck('activity_revision')->all());
    }

    public function test_skip_is_idempotent_and_resume_retains_working_state_without_attempts(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.skip', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'skipped');

        $progress = InteractiveActivityProgress::query()->firstOrFail();
        $state = $progress->working_state;

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.skip', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'skipped');

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.resume', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress');

        $progress->refresh();
        $this->assertSame($state, $progress->working_state);
        $this->assertSame(0, $progress->attempt_count);
        $this->assertNull($progress->skipped_at);
    }

    public function test_state_save_does_not_increment_attempt_count(): void
    {
        [$learner, $activity] = $this->fixture();
        $progress = app(\App\Services\Learning\InteractiveActivities\InteractiveActivityProgressService::class)
            ->stateFor($learner, $activity);

        $state = $progress->working_state;
        $state['matched'] = [['left_id' => 'left-1', 'right_id' => 'right-1']];

        $this->actingAs($learner)
            ->putJson(route('learner.interactive-activities.state', $activity), [
                'revision' => 1,
                'state' => $state,
            ])
            ->assertOk()
            ->assertJsonPath('attempt_count', 0);

        $this->assertSame(0, $progress->refresh()->attempt_count);
        $this->assertSame($state, $progress->working_state);
    }

    public function test_valid_evaluation_increments_one_attempt_and_completes_matching_activity(): void
    {
        [$learner, $activity] = $this->fixture();
        $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity));

        $response = $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'left-1',
                'right_id' => 'right-1',
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('is_complete', false)
            ->assertJsonPath('attempt_count', 1)
            ->assertJsonMissingPath('configuration')
            ->assertJsonMissingPath('pair_id');

        $response = $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'left-2',
                'right_id' => 'right-2',
            ])
            ->assertOk()
            ->assertJsonPath('is_complete', true)
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('attempt_count', 2)
            ->assertJsonPath('explanation', 'Pairs explained here.');

        $this->assertDatabaseHas('interactive_activity_progress', [
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'status' => 'completed',
            'attempt_count' => 2,
        ]);
    }

    public function test_invalid_evaluation_does_not_increment_or_expose_answer_material(): void
    {
        [$learner, $activity] = $this->fixture();

        $response = $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'unknown',
                'right_id' => 'right-1',
            ])
            ->assertOk()
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('attempt_count', 0)
            ->assertJsonMissingPath('configuration')
            ->assertJsonMissingPath('pair_id');

        $this->assertStringNotContainsString('correct_mapping', $response->getContent());
    }

    public function test_completed_progress_never_downgrades_or_increments_on_revisit(): void
    {
        [$learner, $activity] = $this->fixture();
        $progress = InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
            'status' => 'completed',
            'working_state' => ['matched' => [
                ['left_id' => 'left-1', 'right_id' => 'right-1'],
                ['left_id' => 'left-2', 'right_id' => 'right-2'],
            ]],
            'attempt_count' => 2,
            'completed_at' => now(),
        ]);

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.skip', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('attempt_count', 2)
            ->assertJsonPath('explanation', 'Pairs explained here.');

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.match', $activity), [
                'revision' => 1,
                'left_id' => 'left-1',
                'right_id' => 'right-1',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('attempt_count', 2);

        $this->assertSame('completed', $progress->refresh()->status);
    }

    public function test_practice_is_unresolved_and_does_not_write_progress(): void
    {
        [$learner, $activity] = $this->fixture();
        InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
            'status' => 'completed',
            'working_state' => ['matched' => []],
            'attempt_count' => 3,
            'completed_at' => now(),
        ]);

        $before = InteractiveActivityProgress::query()->count();
        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.practice', $activity), ['revision' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'practice')
            ->assertJsonPath('explanation', null)
            ->assertJsonPath('available', true)
            ->assertJsonMissingPath('configuration');

        $this->assertSame($before, InteractiveActivityProgress::query()->count());
    }

    public function test_learner_authorization_requires_published_lesson_visible_module_and_approved_enrollment(): void
    {
        [$learner, $activity] = $this->fixture();

        $activity->lessonTopic->lesson->update(['is_published' => false]);
        $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertNotFound();

        $activity->lessonTopic->lesson->update(['is_published' => true]);
        $activity->lessonTopic->lesson->module->update(['is_published' => false]);
        $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertForbidden();

        $activity->lessonTopic->lesson->module->update(['is_published' => true]);
        ModuleEnrollment::query()->where('user_id', $learner->id)->update(['status' => EnrollmentStatus::Pending]);
        $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', $activity))
            ->assertForbidden();
    }

    public function test_wrong_activity_revision_returns_conflict(): void
    {
        [$learner, $activity] = $this->fixture();

        $this->actingAs($learner)
            ->postJson(route('learner.interactive-activities.skip', $activity), ['revision' => 99])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Activity changed. Reload to continue.');
    }

    public function test_unknown_activity_returns_not_found_and_routes_are_csrf_protected(): void
    {
        [$learner] = $this->fixture();

        $this->actingAs($learner)
            ->getJson(route('learner.interactive-activities.show', 999999))
            ->assertNotFound();

        $route = app('router')->getRoutes()->getByName('learner.interactive-activities.match');
        $this->assertContains('web', $route->gatherMiddleware());
    }

    /** @return array{User, InteractiveActivity} */
    private function fixture(): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create([
            'is_published' => true,
            'current_review_status' => null,
        ]);
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'is_published' => true,
        ]);
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive',
        ]);
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $topic->id,
            'placement' => 'inside_topic',
            'block_uuid' => (string) Str::uuid(),
            'activity_type' => InteractiveActivityType::MATCHING,
            'title' => 'Match concepts',
            'instructions' => 'Match each concept.',
            'explanation' => 'Pairs explained here.',
            'configuration' => [
                'schema_version' => 1,
                'pairs' => [
                    ['id' => 'pair-1', 'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'One'], 'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'First']],
                    ['id' => 'pair-2', 'left' => ['id' => 'left-2', 'kind' => 'text', 'value' => 'Two'], 'right' => ['id' => 'right-2', 'kind' => 'text', 'value' => 'Second']],
                ],
            ],
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
