<?php

namespace App\Services\Learning;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuestionAuthoringService
{
    public const TYPES = [
        'multiple_choice',
        'true_false',
        'multiple_select',
        'fill_blank_text',
        'fill_blank_select',
        'identification',
    ];

    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'in:' . implode(',', self::TYPES)],
            'points' => ['nullable', 'integer', 'min:1'],
            'options' => ['required_if:question_type,multiple_choice,true_false,multiple_select', 'array', 'min:2'],
            'options.*' => ['required_with:options', 'string'],
            'correct_options' => ['required_if:question_type,multiple_choice,true_false,multiple_select', 'array', 'min:1'],
            'correct_options.*' => ['required_with:correct_options', 'integer'],
            'acceptable_answers' => ['required_if:question_type,fill_blank_text,fill_blank_select,identification', 'array', 'min:1'],
            'acceptable_answers.*' => ['required_with:acceptable_answers', 'string'],
            'case_sensitive' => ['nullable', 'boolean'],
            'word_bank' => ['nullable', 'required_if:question_type,fill_blank_select', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'explanation' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function normalizeRequest(Request $request): void
    {
        $type = (string) $request->input('question_type');

        if (!in_array($type, ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            $request->request->remove('options');
            $request->request->remove('correct_options');
        }

        if (!in_array($type, ['fill_blank_text', 'fill_blank_select', 'identification'], true)) {
            $request->request->remove('acceptable_answers');
            $request->request->remove('case_sensitive');
        }

        if ($type !== 'fill_blank_select') {
            $request->request->remove('word_bank');
        }
    }

    public function createQuestion(array $data, array $owner): QuizQuestion
    {
        return DB::transaction(function () use ($data, $owner): QuizQuestion {
            $question = QuizQuestion::create($this->questionPayload($data, $owner));
            $this->replaceOptions($question, $data);

            return $question->load('options');
        });
    }

    public function updateQuestion(QuizQuestion $question, array $data): QuizQuestion
    {
        return DB::transaction(function () use ($question, $data): QuizQuestion {
            $payload = $this->questionPayload($data, [], $question->image_path);

            if (($data['image'] ?? null) instanceof UploadedFile && $question->image_path) {
                Storage::disk('public')->delete($question->image_path);
            }

            $question->update($payload);
            $this->replaceOptions($question, $data);

            return $question->refresh()->load('options');
        });
    }

    private function questionPayload(array $data, array $owner, ?string $existingImagePath = null): array
    {
        $usesAcceptableAnswers = in_array($data['question_type'], ['fill_blank_text', 'fill_blank_select', 'identification'], true);

        return array_merge($owner, [
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'],
            'points' => (int) ($data['points'] ?? 1),
            'acceptable_answers' => $usesAcceptableAnswers && isset($data['acceptable_answers'])
                ? implode('|', array_map('trim', $data['acceptable_answers']))
                : null,
            'case_sensitive' => $usesAcceptableAnswers && !empty($data['case_sensitive']),
            'word_bank' => $data['question_type'] === 'fill_blank_select' && !empty($data['word_bank'])
                ? array_map('trim', explode(',', $data['word_bank']))
                : null,
            'image_path' => ($data['image'] ?? null) instanceof UploadedFile
                ? $data['image']->store($this->imageDirectory(), 'public')
                : ($data['image_path'] ?? $existingImagePath),
            'explanation' => $data['explanation'] ?? null,
        ]);
    }

    private function replaceOptions(QuizQuestion $question, array $data): void
    {
        $question->options()->delete();

        if (!in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)
            || !isset($data['options'])
            || !is_array($data['options'])) {
            return;
        }

        $correct = array_map('intval', $data['correct_options'] ?? []);

        foreach (array_values($data['options']) as $index => $optionText) {
            $question->options()->create([
                'option_text' => $optionText,
                'is_correct' => in_array($index, $correct, true),
                'order' => $index,
            ]);
        }
    }

    private function imageDirectory(): string
    {
        return 'quiz-images/user-' . (int) Auth::id();
    }
}
