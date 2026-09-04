<?php

declare(strict_types=1);

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserDailyShield;
use Tests\TestCase;

class InteractiveActivityQuizRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureProfileCompleted::class);
    }

    public function test_completing_matching_activity_does_not_create_quiz_attempt_or_consume_shield(): void
    {
        [$learner, $activity] = $this->matchingFixture();
        UserDailyShield::refillFull($learner);
        $before = UserDailyShield::getShields($learner);

        foreach ([
            ['left_id' => 'left-1', 'right_id' => 'right-1'],
            ['left_id' => 'left-2', 'right_id' => 'right-2'],
        ] as $proposal) {
            $this->actingAs($learner)
                ->postJson(route('learner.interactive-activities.match', $activity), [
                    'revision' => 1,
                    ...$proposal,
                ])
                ->assertOk();
        }

        $this->assertSame('completed', InteractiveActivityProgress::query()
            ->where('user_id', $learner->id)
            ->where('interactive_activity_id', $activity->id)
            ->value('status'));
        $this->assertSame(0, QuizAttempt::query()->where('user_id', $learner->id)->count());
        $this->assertSame($before, UserDailyShield::getShields($learner->refresh()));
    }

    /** @return array{User, InteractiveActivity} */
    private function matchingFixture(): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true, 'current_review_status' => null]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text']);
        $activity = InteractiveActivity::query()->create([
            'lesson_topic_id' => $topic->id,
            'placement' => 'inside_topic',
            'activity_type' => 'matching',
            'title' => 'Quiz-safe matching',
            'configuration' => [
                'schema_version' => 1,
                'pairs' => [
                    ['id' => 'pair-1', 'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'One'], 'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'First']],
                    ['id' => 'pair-2', 'left' => ['id' => 'left-2', 'kind' => 'text', 'value' => 'Two'], 'right' => ['id' => 'right-2', 'kind' => 'text', 'value' => 'Second']],
                ],
            ],
            'revision' => 1,
        ]);
        ModuleEnrollment::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        return [$learner, $activity];
    }
}
