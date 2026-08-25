<?php

namespace Tests\Feature\Instructor;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use Tests\TestCase;

class QuizQuestionAuthoringRegressionTest extends TestCase
{
    public function test_quiz_add_page_uses_refined_type_specific_guidance(): void
    {
        [$instructor, $quiz] = $this->quizFixture();

        $expectations = [
            'multiple_choice' => ['Add Option', 'Select exactly one correct answer.'],
            'true_false' => ['True', 'False'],
            'identification' => ['Acceptable Answers', 'JPG or PNG, max 2 MB.'],
            'fill_blank_text' => ['Insert Blank (_____)', 'Alternatives'],
            'fill_blank_select' => ['Word Bank', 'Max 10 words.'],
            'multiple_select' => ['Add Option', 'Select every correct answer.'],
        ];

        foreach ($expectations as $type => $copy) {
            $response = $this->actingAs($instructor)->get(route(
                'instructor.quizzes.add-question',
                ['quiz' => $quiz, 'type' => $type],
            ));

            $response->assertOk()->assertSee('questionAuthoring', false);
            foreach ($copy as $text) $response->assertSee($text, false);
        }
    }

    public function test_quiz_edit_page_uses_shared_switchable_fields_and_existing_values(): void
    {
        [$instructor, $quiz] = $this->quizFixture();
        $question = $quiz->questions()->create([
            'question_text' => '<p>Existing question</p>',
            'question_type' => 'multiple_choice',
            'points' => 3,
            'order' => 1,
        ]);
        $question->options()->createMany([
            ['option_text' => 'First', 'is_correct' => true, 'order' => 0],
            ['option_text' => 'Second', 'is_correct' => false, 'order' => 1],
        ]);

        $this->actingAs($instructor)
            ->get(route('instructor.quizzes.edit-question', [$quiz, $question]))
            ->assertOk()
            ->assertSee('Existing question', false)
            ->assertSee('First', false)
            ->assertSee('Change Question Type', false)
            ->assertSee('Points', false);
    }

    public function test_quiz_question_update_rejects_cross_quiz_question(): void
    {
        [$instructor, $quiz] = $this->quizFixture();
        [, $otherQuiz] = $this->quizFixture($instructor);
        $question = $otherQuiz->questions()->create([
            'question_text' => 'Other quiz question',
            'question_type' => 'true_false',
            'points' => 1,
            'order' => 1,
        ]);

        $this->actingAs($instructor)
            ->put(route('instructor.quizzes.update-question', [$quiz, $question]), [
                'question_type' => 'true_false',
                'question_text' => 'Changed',
                'points' => 1,
                'options' => ['True', 'False'],
                'correct_options' => [0],
            ])
            ->assertNotFound();
    }

    private function quizFixture(?User $instructor = null): array
    {
        $instructor ??= User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
        ]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $quiz = Quiz::factory()->create([
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
        ]);

        return [$instructor, $quiz];
    }
}
