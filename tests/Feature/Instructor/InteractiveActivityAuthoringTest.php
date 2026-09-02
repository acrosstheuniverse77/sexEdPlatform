<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\InteractiveActivity;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\User;
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
