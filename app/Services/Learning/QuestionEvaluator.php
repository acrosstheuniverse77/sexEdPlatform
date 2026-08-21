<?php

namespace App\Services\Learning;

use App\Models\QuizQuestion;

class QuestionEvaluator
{
    public function evaluate(QuizQuestion $question, mixed $selectedAnswer): array
    {
        return match ($question->question_type) {
            'multiple_select' => $this->evaluateMultipleSelect($question, $selectedAnswer),
            'fill_blank_text' => $this->evaluateFillBlankText($question, $selectedAnswer),
            'fill_blank_select' => $this->evaluateFillBlankSelect($question, $selectedAnswer),
            'identification' => $this->evaluateIdentification($question, $selectedAnswer),
            default => $this->evaluateSingleOption($question, $selectedAnswer),
        };
    }

    private function evaluateMultipleSelect(QuizQuestion $question, mixed $selectedAnswer): array
    {
        $selectedIds = is_array($selectedAnswer) ? array_map('intval', $selectedAnswer) : [];
        $correctIds = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        sort($selectedIds);
        sort($correctIds);

        return [
            'selected' => $selectedIds,
            'correct' => $correctIds,
            'is_correct' => $selectedIds === $correctIds,
            'type' => 'multiple_select',
        ];
    }

    private function evaluateFillBlankText(QuizQuestion $question, mixed $selectedAnswer): array
    {
        $answerText = (string) $question->acceptable_answers;

        if (str_contains($answerText, ';')) {
            $blankAnswerSets = collect(explode(';', $answerText))
                ->map(fn ($set) => array_map('trim', explode('|', $set)))
                ->all();

            $isCorrect = is_array($selectedAnswer) && count($selectedAnswer) === count($blankAnswerSets);
            if ($isCorrect) {
                foreach (array_values($selectedAnswer) as $index => $userInput) {
                    if (!$this->matchesAny((string) $userInput, $blankAnswerSets[$index], (bool) $question->case_sensitive)) {
                        $isCorrect = false;
                        break;
                    }
                }
            }

            return [
                'selected' => $selectedAnswer,
                'correct' => array_merge(...$blankAnswerSets),
                'is_correct' => $isCorrect,
                'type' => 'fill_blank_text',
                'case_sensitive' => (bool) $question->case_sensitive,
            ];
        }

        $acceptableAnswers = array_map('trim', explode('|', $answerText));
        $answersToCheck = is_array($selectedAnswer) ? $selectedAnswer : [$selectedAnswer];
        $isCorrect = count($answersToCheck) > 0;

        foreach ($answersToCheck as $userInput) {
            if (!$this->matchesAny((string) $userInput, $acceptableAnswers, (bool) $question->case_sensitive)) {
                $isCorrect = false;
                break;
            }
        }

        return [
            'selected' => $selectedAnswer,
            'correct' => $acceptableAnswers,
            'is_correct' => $isCorrect,
            'type' => 'fill_blank_text',
            'case_sensitive' => (bool) $question->case_sensitive,
        ];
    }

    private function evaluateFillBlankSelect(QuizQuestion $question, mixed $selectedAnswer): array
    {
        $expectedAnswers = str_contains((string) $question->acceptable_answers, ';')
            ? explode(';', (string) $question->acceptable_answers)
            : explode('|', (string) $question->acceptable_answers);
        $expectedAnswers = array_map('trim', $expectedAnswers);
        $selectedWords = is_array($selectedAnswer) ? array_values($selectedAnswer) : [];

        $isCorrect = count($selectedWords) === count($expectedAnswers);
        if ($isCorrect) {
            foreach ($selectedWords as $index => $word) {
                if (!isset($expectedAnswers[$index]) || trim((string) $word) !== $expectedAnswers[$index]) {
                    $isCorrect = false;
                    break;
                }
            }
        }

        return [
            'selected' => $selectedWords,
            'correct' => $expectedAnswers,
            'is_correct' => $isCorrect,
            'type' => 'fill_blank_select',
        ];
    }

    private function evaluateIdentification(QuizQuestion $question, mixed $selectedAnswer): array
    {
        $acceptableAnswers = array_map('trim', explode('|', (string) $question->acceptable_answers));

        return [
            'selected' => $selectedAnswer,
            'correct' => $acceptableAnswers,
            'is_correct' => $this->matchesAny((string) $selectedAnswer, $acceptableAnswers, (bool) $question->case_sensitive),
            'type' => 'identification',
            'case_sensitive' => (bool) $question->case_sensitive,
            'image_url' => $question->image_url,
        ];
    }

    private function evaluateSingleOption(QuizQuestion $question, mixed $selectedAnswer): array
    {
        $correctOption = $question->options->where('is_correct', true)->first();
        $correctId = $correctOption?->id;

        return [
            'selected' => $selectedAnswer,
            'correct' => $correctId,
            'is_correct' => $selectedAnswer !== null && (int) $selectedAnswer === (int) $correctId,
            'type' => $question->question_type,
        ];
    }

    private function matchesAny(string $input, array $acceptableAnswers, bool $caseSensitive): bool
    {
        $input = trim($input);

        foreach ($acceptableAnswers as $acceptable) {
            $acceptable = trim((string) $acceptable);
            if ($caseSensitive ? $input === $acceptable : strtolower($input) === strtolower($acceptable)) {
                return true;
            }
        }

        return false;
    }
}
