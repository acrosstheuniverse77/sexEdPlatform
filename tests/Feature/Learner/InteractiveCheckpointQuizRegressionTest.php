<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserDailyShield;
use Tests\TestCase;

class InteractiveCheckpointQuizRegressionTest extends TestCase
{
    public function test_formal_quiz_submission_still_creates_attempt_and_drains_shield_on_failure(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        $quiz = Quiz::factory()->create([
            'module_id' => $module->id,
            'passing_score' => 100,
            'attempt_limit' => null,
        ]);
        $question = $quiz->questions()->create([
            'question_text' => 'Consent requires pressure.',
            'question_type' => 'true_false',
            'points' => 1,
            'order' => 1,
        ]);
        $true = $question->options()->create(['option_text' => 'True', 'is_correct' => false, 'order' => 0]);
        $false = $question->options()->create(['option_text' => 'False', 'is_correct' => true, 'order' => 1]);

        UserDailyShield::refillFull($learner);
        $before = UserDailyShield::getShields($learner);

        $this->actingAs($learner)
            ->post(route('quizzes.submit', $quiz), [
                'started_at' => now()->timestamp,
                'answers' => [$question->id => $true->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $learner->id,
            'quiz_id' => $quiz->id,
            'score' => 0,
            'passed' => false,
        ]);
        $this->assertSame($before - 1, UserDailyShield::getShields($learner->refresh()));
        $this->assertSame(1, QuizAttempt::where('quiz_id', $quiz->id)->count());
        $this->assertSame($false->id, $question->options()->where('is_correct', true)->value('id'));
    }
}
