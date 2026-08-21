<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointProgressIsolationTest extends TestCase
{
    public function test_lesson_completion_ignores_uncompleted_between_topic_checkpoint(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 1]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'order' => 2]);

        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);
        LessonTopicProgress::create([
            'user_id' => $learner->id,
            'lesson_topic_id' => $topic->id,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $this->assertTrue($lesson->allTopicsCompletedBy($learner->id));
        $this->assertSame(100, $lesson->getTopicCompletionPercentage($learner->id));
    }
}
