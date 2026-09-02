<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class InteractiveActivityAuthoringTest extends TestCase
{
    public function test_instructor_can_create_a_matching_between_topic_activity_without_duration(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Match the concepts',
                'type' => 'interactive',
                'activity_type' => 'matching',
                'placement' => 'between_topics',
                'instructions' => '<p>Select <strong>each</strong>.</p><script>alert(1)</script>',
                'explanation' => '<p>Pairs explain the concepts.</p>',
                'configuration' => $this->matchingConfiguration(),
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson))
            ->assertSessionHas('success', 'Interactive activity created successfully.');

        $host = $lesson->topics()->where('type', 'interactive')->firstOrFail();
        $activity = $host->interactiveActivities()->firstOrFail();

        $this->assertSame('Match the concepts', $host->title);
        $this->assertSame(0, $host->duration);
        $this->assertFalse($host->is_prerequisite);
        $this->assertSame('between_topics', $activity->placement);
        $this->assertNull($activity->block_uuid);
        $this->assertSame('matching', $activity->activity_type->value);
        $this->assertSame(1, $activity->configuration['schema_version']);
        $this->assertCount(2, $activity->configuration['pairs']);
        $this->assertSame('<p>Select <strong>each</strong>.</p>alert(1)', $activity->instructions);
        $this->assertSame(0, $lesson->fresh()->duration);
    }

    public function test_instructor_can_create_a_sequencing_between_topic_activity(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Order the steps',
                'type' => 'interactive',
                'activity_type' => 'sequencing',
                'placement' => 'between_topics',
                'configuration' => $this->sequencingConfiguration(),
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $activity = InteractiveActivity::query()->where('activity_type', 'sequencing')->firstOrFail();

        $this->assertSame('interactive', $activity->lessonTopic->type);
        $this->assertSame(3, count($activity->configuration['items']));
        $this->assertSame([1, 2, 3], array_column($activity->configuration['items'], 'correct_position'));
    }

    public function test_inside_topic_activity_uses_a_server_block_reference_and_same_lesson_parent(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        $parent = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'content_blocks' => [['type' => 'rich_text', 'html' => '<p>Lesson content</p>']],
        ]);

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Inside the lesson',
                'type' => 'interactive',
                'activity_type' => 'matching',
                'placement' => 'inside_topic',
                'parent_topic_id' => $parent->id,
                'insert_after_block' => 0,
                'configuration' => $this->matchingConfiguration(),
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $activity = $parent->interactiveActivities()->firstOrFail();
        $this->assertNotEmpty($activity->block_uuid);
        $this->assertSame('inside_topic', $activity->placement);
        $this->assertContains([
            'type' => 'interactive_activity',
            'uuid' => $activity->block_uuid,
            'activity_id' => $activity->id,
        ], $parent->fresh()->content_blocks);
        $this->assertSame(5, $lesson->fresh()->duration);
    }

    public function test_inside_topic_rejects_parent_from_another_lesson_and_optional_parents(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        $otherLesson = Lesson::factory()->create(['module_id' => $lesson->module_id]);
        $otherParent = LessonTopic::factory()->create(['lesson_id' => $otherLesson->id]);
        $checkpointParent = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive_checkpoint',
        ]);

        foreach ([$otherParent, $checkpointParent] as $parent) {
            $this->actingAs($instructor)
                ->post(route('instructor.topics.store'), [
                    'lesson_id' => $lesson->id,
                    'title' => 'Rejected parent',
                    'type' => 'interactive',
                    'activity_type' => 'matching',
                    'placement' => 'inside_topic',
                    'parent_topic_id' => $parent->id,
                    'configuration' => $this->matchingConfiguration(),
                ])
                ->assertSessionHasErrors('parent_topic_id');
        }

        $this->assertDatabaseCount('interactive_activities', 0);
    }

    public function test_invalid_configuration_is_rejected_before_persistence(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();

        $configuration = $this->matchingConfiguration();
        $configuration['pairs'][1]['left']['value'] = ' consent ';

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Duplicate pairs',
                'type' => 'interactive',
                'activity_type' => 'matching',
                'placement' => 'between_topics',
                'configuration' => $configuration,
            ])
            ->assertSessionHasErrors('pairs.left');

        $this->assertDatabaseCount('interactive_activities', 0);
        $this->assertDatabaseCount('lesson_topics', 0);
    }

    public function test_instructor_cannot_create_on_another_instructors_lesson(): void
    {
        [, $lesson] = $this->authoringFixture();
        $other = User::factory()->create(['role' => 'instructor']);
        $other->assignRole('instructor');

        $this->actingAs($other)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Not allowed',
                'type' => 'interactive',
                'activity_type' => 'matching',
                'placement' => 'between_topics',
                'configuration' => $this->matchingConfiguration(),
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_mutate_instructor_owned_content_through_admin_panel(): void
    {
        [, $lesson] = $this->authoringFixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Not allowed',
                'type' => 'interactive',
                'activity_type' => 'matching',
                'placement' => 'between_topics',
                'configuration' => $this->matchingConfiguration(),
            ])
            ->assertForbidden();
    }

    public function test_wording_only_edit_preserves_revision_and_answer_edit_increments_once(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [$parent, $activity] = $this->insideActivity($lesson);

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity, [
                'title' => 'Updated wording',
                'instructions' => 'New instructions',
            ]))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame(1, $activity->refresh()->revision);

        $configuration = $activity->configuration;
        $configuration['pairs'][0]['right']['value'] = 'A freely chosen agreement';

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity, [
                'configuration' => $configuration,
            ]))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame(2, $activity->refresh()->revision);

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame(2, $activity->refresh()->revision);
        $this->assertTrue($parent->fresh()->interactiveActivities()->whereKey($activity->id)->exists());
    }

    public function test_instructor_can_open_activity_editor(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [, $activity] = $this->insideActivity($lesson);

        $this->actingAs($instructor)
            ->get(route('instructor.interactive-activities.edit', $activity))
            ->assertOk()
            ->assertSee('Edit interactive activity')
            ->assertSee('Existing activity');
    }

    public function test_lesson_details_shows_activity_actions_and_distinct_warning(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [, $activity] = $this->insideActivity($lesson);

        $this->actingAs($instructor)
            ->get(route('instructor.lessons.show', $lesson))
            ->assertOk()
            ->assertSee(route('instructor.interactive-activities.edit', $activity))
            ->assertSee('Remove Activity')
            ->assertSee('The activity will be removed; its parent topic will remain.');
    }

    public function test_create_page_exposes_matching_and_sequencing_activity_cards(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'title' => 'Eligible parent']);

        $this->actingAs($instructor)
            ->get(route('instructor.topics.create', ['lesson' => $lesson]))
            ->assertOk()
            ->assertSee('Matching')
            ->assertSee('Sequencing')
            ->assertSee('Interactive Activity')
            ->assertSee('data-activity-type="matching"', false)
            ->assertSee('data-activity-type="sequencing"', false)
            ->assertSee('name="activity_type"', false)
            ->assertSee('Eligible parent')
            ->assertSee("!['interactive_checkpoint', 'interactive'].includes(type)", false)
            ->assertDontSee('Simulation')
            ->assertDontSee('Exercise');
    }

    public function test_activity_edit_page_has_no_duration_or_prerequisite_controls(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [, $activity] = $this->insideActivity($lesson);

        $this->actingAs($instructor)
            ->get(route('instructor.interactive-activities.edit', $activity))
            ->assertOk()
            ->assertSee('interactiveActivityAuthoring')
            ->assertSee('Existing activity')
            ->assertDontSee('name="duration"', false)
            ->assertDontSee('is_prerequisite');
    }

    public function test_activity_type_is_immutable(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [, $activity] = $this->insideActivity($lesson);

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), [
                ...$this->activityPayload($activity),
                'activity_type' => 'sequencing',
                'configuration' => $this->sequencingConfiguration(),
            ])
            ->assertSessionHasErrors('activity_type');

        $this->assertSame('matching', $activity->refresh()->activity_type->value);
    }

    public function test_sequencing_answer_changes_increment_revision_once(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        $parent = LessonTopic::factory()->create(['lesson_id' => $lesson->id]);
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $parent->id,
            'placement' => 'inside_topic',
            'block_uuid' => (string) Str::uuid(),
            'activity_type' => 'sequencing',
            'title' => 'Order me',
            'configuration' => [
                'schema_version' => 1,
                'items' => [
                    ['id' => 'item-1', 'kind' => 'text', 'value' => 'First', 'correct_position' => 1],
                    ['id' => 'item-2', 'kind' => 'text', 'value' => 'Second', 'correct_position' => 2],
                    ['id' => 'item-3', 'kind' => 'text', 'value' => 'Third', 'correct_position' => 3],
                ],
            ],
            'revision' => 1,
        ]);

        $changed = $activity->configuration;
        $changed['items'][0]['value'] = 'Begin';

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity, [
                'configuration' => $changed,
            ]))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame(2, $activity->refresh()->revision);

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertSame(2, $activity->refresh()->revision);
    }

    public function test_inside_activity_can_move_to_another_parent_and_removes_old_reference(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [$oldParent, $activity] = $this->insideActivity($lesson);
        $newParent = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'order' => 2]);

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity, [
                'parent_topic_id' => $newParent->id,
            ]))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $activity->refresh();
        $this->assertSame($newParent->id, $activity->lesson_topic_id);
        $this->assertSame('inside_topic', $activity->placement);
        $this->assertFalse(collect($oldParent->fresh()->content_blocks)->contains('activity_id', $activity->id));
        $this->assertContains([
            'type' => 'interactive_activity',
            'uuid' => $activity->block_uuid,
            'activity_id' => $activity->id,
        ], $newParent->fresh()->content_blocks);
    }

    public function test_activity_can_move_between_and_inside_without_orphaning_hosts(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        $host = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive',
            'duration' => 55,
            'order' => 1,
            'is_prerequisite' => true,
        ]);
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $host->id,
            'placement' => 'between_topics',
            'block_uuid' => null,
            'activity_type' => 'matching',
            'title' => 'Move me',
            'configuration' => $this->storedMatchingConfiguration(),
            'revision' => 1,
        ]);
        $parent = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'order' => 2]);

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity, [
                'placement' => 'inside_topic',
                'parent_topic_id' => $parent->id,
            ]))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $activity->refresh();
        $this->assertDatabaseMissing('lesson_topics', ['id' => $host->id]);
        $this->assertSame($parent->id, $activity->lesson_topic_id);
        $this->assertContains('interactive_activity', array_column($parent->fresh()->content_blocks, 'type'));

        $this->actingAs($instructor)
            ->put(route('instructor.interactive-activities.update', $activity), $this->activityPayload($activity, [
                'placement' => 'between_topics',
                'parent_topic_id' => null,
            ]))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $activity->refresh();
        $this->assertSame('between_topics', $activity->placement);
        $this->assertNull($activity->block_uuid);
        $this->assertSame('interactive', $activity->lessonTopic->type);
        $this->assertFalse(collect($parent->fresh()->content_blocks)->contains('activity_id', $activity->id));
        $this->assertFalse($activity->lessonTopic->is_prerequisite);
        $this->assertSame(0, $activity->lessonTopic->duration);
    }

    public function test_deleting_inside_activity_preserves_parent_and_removes_progress(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [$parent, $activity] = $this->insideActivity($lesson);
        $learner = User::factory()->create(['role' => 'learner']);
        InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
        ]);

        $this->actingAs($instructor)
            ->delete(route('instructor.interactive-activities.destroy', $activity))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertDatabaseMissing('interactive_activities', ['id' => $activity->id]);
        $this->assertDatabaseMissing('interactive_activity_progress', ['interactive_activity_id' => $activity->id]);
        $this->assertDatabaseHas('lesson_topics', ['id' => $parent->id]);
        $this->assertFalse(collect($parent->fresh()->content_blocks)->contains('activity_id', $activity->id));
    }

    public function test_deleting_between_activity_removes_host_and_resequences_topics(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        $first = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'order' => 1]);
        $host = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive', 'order' => 2, 'duration' => 0]);
        $last = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'order' => 3]);
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $host->id,
            'placement' => 'between_topics',
            'block_uuid' => null,
            'activity_type' => 'matching',
            'title' => 'Delete me',
            'configuration' => $this->storedMatchingConfiguration(),
            'revision' => 1,
        ]);

        $this->actingAs($instructor)
            ->delete(route('instructor.interactive-activities.destroy', $activity))
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $this->assertDatabaseMissing('lesson_topics', ['id' => $host->id]);
        $this->assertSame([$first->id, $last->id], $lesson->topics()->orderBy('order')->pluck('id')->all());
        $this->assertSame([1, 2], $lesson->topics()->orderBy('order')->pluck('order')->all());
    }

    public function test_admin_cannot_delete_activity_from_instructor_owned_content(): void
    {
        [, $lesson] = $this->authoringFixture();
        [, $activity] = $this->insideActivity($lesson);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->delete(route('admin.interactive-activities.destroy', $activity))
            ->assertForbidden();

        $this->assertDatabaseHas('interactive_activities', ['id' => $activity->id]);
    }

    public function test_admin_cannot_edit_or_update_instructor_owned_activity(): void
    {
        [, $lesson] = $this->authoringFixture();
        [, $activity] = $this->insideActivity($lesson);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.interactive-activities.edit', $activity))
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.interactive-activities.update', $activity), $this->activityPayload($activity))
            ->assertForbidden();
    }

    public function test_deleting_parent_topic_cascades_its_activity_and_progress(): void
    {
        [$instructor, $lesson] = $this->authoringFixture();
        [$parent, $activity] = $this->insideActivity($lesson);
        $learner = User::factory()->create(['role' => 'learner']);
        InteractiveActivityProgress::factory()->create([
            'user_id' => $learner->id,
            'interactive_activity_id' => $activity->id,
            'activity_revision' => 1,
        ]);

        $this->actingAs($instructor)
            ->delete(route('instructor.topics.destroy', $parent))
            ->assertRedirect();

        $this->assertDatabaseMissing('interactive_activities', ['id' => $activity->id]);
        $this->assertDatabaseMissing('interactive_activity_progress', ['interactive_activity_id' => $activity->id]);
    }

    /** @return array<string, mixed> */
    private function matchingConfiguration(): array
    {
        return [
            'pairs' => [
                ['left' => ['value' => 'Consent'], 'right' => ['value' => 'Freely given agreement']],
                ['left' => ['value' => 'Boundary'], 'right' => ['value' => 'A personal limit']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function sequencingConfiguration(): array
    {
        return [
            'items' => [
                ['value' => 'Notice'],
                ['value' => 'Name'],
                ['value' => 'Negotiate'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function storedMatchingConfiguration(): array
    {
        return [
            'schema_version' => 1,
            'pairs' => [
                ['id' => 'pair-1', 'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'Consent'], 'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'Agreement']],
                ['id' => 'pair-2', 'left' => ['id' => 'left-2', 'kind' => 'text', 'value' => 'Boundary'], 'right' => ['id' => 'right-2', 'kind' => 'text', 'value' => 'Limit']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function activityPayload(InteractiveActivity $activity, array $overrides = []): array
    {
        return array_merge([
            'lesson_id' => $activity->lessonTopic->lesson_id,
            'type' => 'interactive',
            'activity_type' => $activity->activity_type->value,
            'placement' => $activity->placement,
            'parent_topic_id' => $activity->placement === 'inside_topic' ? $activity->lesson_topic_id : null,
            'title' => $activity->title,
            'instructions' => $activity->instructions,
            'explanation' => $activity->explanation,
            'configuration' => $activity->configuration,
        ], $overrides);
    }

    /** @return array{LessonTopic, InteractiveActivity} */
    private function insideActivity(Lesson $lesson): array
    {
        $parent = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'content_blocks' => [['type' => 'rich_text', 'html' => '<p>Parent</p>']],
        ]);
        $blockUuid = (string) Str::uuid();
        $activity = InteractiveActivity::create([
            'lesson_topic_id' => $parent->id,
            'placement' => 'inside_topic',
            'block_uuid' => $blockUuid,
            'activity_type' => 'matching',
            'title' => 'Existing activity',
            'configuration' => $this->storedMatchingConfiguration(),
            'revision' => 1,
        ]);
        $parent->update(['content_blocks' => [[
            'type' => 'interactive_activity',
            'uuid' => $blockUuid,
            'activity_id' => $activity->id,
        ]]]);

        return [$parent, $activity];
    }

    /** @return array{User, Lesson} */
    private function authoringFixture(): array
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
        ]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        return [$instructor, $lesson];
    }
}
