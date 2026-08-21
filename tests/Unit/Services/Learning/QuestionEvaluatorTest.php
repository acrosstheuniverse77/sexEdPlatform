<?php

namespace Tests\Unit\Services\Learning;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\Learning\QuestionEvaluator;
use Tests\TestCase;

class QuestionEvaluatorTest extends TestCase
{
    private QuestionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new QuestionEvaluator();
    }

    public function test_multiple_choice_uses_correct_option_id(): void
    {
        $question = $this->optionQuestion('multiple_choice', ['A' => false, 'B' => true]);
        $result = $this->evaluator->evaluate($question->load('options'), $question->options[1]->id);

        $this->assertTrue($result['is_correct']);
        $this->assertSame('multiple_choice', $result['type']);
    }

    public function test_multiple_select_requires_exact_set(): void
    {
        $question = $this->optionQuestion('multiple_select', ['A' => true, 'B' => false, 'C' => true]);

        $right = $this->evaluator->evaluate($question->load('options'), [
            $question->options[0]->id,
            $question->options[2]->id,
        ]);
        $wrong = $this->evaluator->evaluate($question->load('options'), [$question->options[0]->id]);

        $this->assertTrue($right['is_correct']);
        $this->assertFalse($wrong['is_correct']);
    }

    public function test_fill_blank_text_supports_multiple_blanks_and_alternatives(): void
    {
        $question = $this->textQuestion('fill_blank_text', 'blue|Blue;sky|Sky', false);

        $this->assertTrue($this->evaluator->evaluate($question, ['blue', 'sky'])['is_correct']);
        $this->assertFalse($this->evaluator->evaluate($question, ['blue', 'grass'])['is_correct']);
    }

    public function test_fill_blank_select_uses_ordered_words(): void
    {
        $question = $this->textQuestion('fill_blank_select', 'grass;sky', false, ['sky', 'grass']);

        $this->assertTrue($this->evaluator->evaluate($question, ['grass', 'sky'])['is_correct']);
        $this->assertFalse($this->evaluator->evaluate($question, ['sky', 'grass'])['is_correct']);
    }

    public function test_identification_respects_case_sensitivity(): void
    {
        $question = $this->textQuestion('identification', 'Consent', true);

        $this->assertTrue($this->evaluator->evaluate($question, 'Consent')['is_correct']);
        $this->assertFalse($this->evaluator->evaluate($question, 'consent')['is_correct']);
    }

    public function test_true_false_uses_option_id(): void
    {
        $question = $this->optionQuestion('true_false', ['True' => true, 'False' => false]);

        $this->assertTrue($this->evaluator->evaluate($question->load('options'), $question->options[0]->id)['is_correct']);
    }

    private function optionQuestion(string $type, array $options): QuizQuestion
    {
        $question = Quiz::factory()->create()->questions()->create([
            'question_text' => 'Question?',
            'question_type' => $type,
            'points' => 1,
            'order' => 1,
        ]);

        foreach (array_values($options) as $index => $isCorrect) {
            $question->options()->create([
                'option_text' => array_keys($options)[$index],
                'is_correct' => $isCorrect,
                'order' => $index,
            ]);
        }

        return $question->refresh();
    }

    private function textQuestion(string $type, string $answers, bool $caseSensitive, ?array $wordBank = null): QuizQuestion
    {
        return Quiz::factory()->create()->questions()->create([
            'question_text' => 'Question?',
            'question_type' => $type,
            'points' => 1,
            'order' => 1,
            'acceptable_answers' => $answers,
            'case_sensitive' => $caseSensitive,
            'word_bank' => $wordBank,
        ]);
    }
}
