<?php

namespace Tests\Feature\Instructor;

use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_create_page_loads_for_instructor(): void
    {
        /** @var User $instructor */
        $instructor = User::factory()->createOne();
        $instructor->assignRole('instructor');

        Module::factory()->create([
            'created_by' => $instructor->id,
            'is_published' => true,
        ]);

        $this->actingAs($instructor)
            ->get(route('instructor.lessons.create'))
            ->assertOk();
    }

    public function test_updating_a_lesson_excludes_optional_interactions_from_duration(): void
    {
        $instructor = User::factory()->createOne();
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'duration' => 99]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'duration' => 6]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive', 'duration' => 30]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'duration' => 20]);

        $this->actingAs($instructor)
            ->put(route('instructor.lessons.update', $lesson), [
                'module_id' => $module->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
            ])
            ->assertRedirect();

        $this->assertSame(6, $lesson->fresh()->duration);
        $this->assertSame(6, $module->fresh()->duration_minutes);
    }

    public function test_generic_topic_authoring_rejects_legacy_interactive_topics(): void
    {
        $instructor = User::factory()->createOne();
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text']);

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Legacy interactive',
                'type' => 'interactive',
                'duration' => 99,
                'is_prerequisite' => 1,
            ])
            ->assertSessionHasErrors('type');

        $this->assertSame(1, $lesson->topics()->count());

        $this->actingAs($instructor)
            ->put(route('instructor.topics.update', $topic), [
                'title' => 'Legacy interactive update',
                'type' => 'interactive',
                'duration' => 99,
                'is_prerequisite' => 1,
            ])
            ->assertSessionHasErrors('type');

        $topic->refresh();
        $this->assertSame('text', $topic->type);
        $this->assertSame(5, $topic->duration);
        $this->assertFalse($topic->is_prerequisite);
    }
}
