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
        [$learner, $quiz, $question, $correctOption] = $this->formalQuizFixture();
        $wrongOption = $question->options()->where('is_correct', false)->firstOrFail();

        UserDailyShield::refillFull($learner);
        $before = UserDailyShield::getShields($learner);

        $this->actingAs($learner)
            ->post(route('quizzes.submit', $quiz), [
                'started_at' => now()->timestamp,
                'answers' => [$question->id => $wrongOption->id],
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
        $this->assertSame($correctOption->id, $question->options()->where('is_correct', true)->value('id'));
    }

    public function test_passing_formal_quiz_keeps_shield_and_checkpoint_progress_is_not_created(): void
    {
        [$learner, $quiz, $question, $correctOption] = $this->formalQuizFixture();
        UserDailyShield::refillFull($learner);
        $before = UserDailyShield::getShields($learner);

        $this->actingAs($learner)
            ->post(route('quizzes.submit', $quiz), [
                'started_at' => now()->timestamp,
                'answers' => [$question->id => $correctOption->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $learner->id,
            'quiz_id' => $quiz->id,
            'passed' => true,
        ]);
        $this->assertSame($before, UserDailyShield::getShields($learner->refresh()));
        $this->assertDatabaseMissing('interactive_checkpoint_progress', [
            'quiz_question_id' => $question->id,
        ]);
    }

    private function formalQuizFixture(): array
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
            'question_text' => 'Consent requires free agreement.',
            'question_type' => 'true_false',
            'points' => 1,
            'order' => 1,
        ]);
        $correct = $question->options()->create([
            'option_text' => 'True',
            'is_correct' => true,
            'order' => 0,
        ]);
        $question->options()->create([
            'option_text' => 'False',
            'is_correct' => false,
            'order' => 1,
        ]);

        return [$learner, $quiz, $question, $correct];
    }
}
