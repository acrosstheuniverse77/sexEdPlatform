<?php

namespace App\Services\Learning;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

class QuestionAuthoringService
{
    private const CHOICE_TYPES = ['multiple_choice', 'true_false', 'multiple_select'];

    private const TEXT_ANSWER_TYPES = ['fill_blank_text', 'fill_blank_select', 'identification'];

    public const TYPES = [
        'multiple_choice',
        'true_false',
        'multiple_select',
        'fill_blank_text',
        'fill_blank_select',
        'identification',
    ];

    public function validate(Request $request): array
    {
        $this->normalizeRequest($request);

        $validator = ValidatorFacade::make(
            array_merge($request->all(), ['image' => $request->file('image')]),
            $this->rules(),
        );
        $validator->after(fn (Validator $validator) => $this->validateConfiguration($validator, $request));

        return $validator->validate();
    }

    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'in:'.implode(',', self::TYPES)],
            'points' => ['required', 'integer', 'min:1'],
            'options' => ['required_if:question_type,'.implode(',', self::CHOICE_TYPES), 'array', 'min:2'],
            'options.*' => ['required_with:options', 'string'],
            'correct_options' => ['required_if:question_type,'.implode(',', self::CHOICE_TYPES), 'array', 'min:1'],
            'correct_options.*' => ['required_with:correct_options', 'integer', 'distinct'],
            'acceptable_answers' => ['required_if:question_type,'.implode(',', self::TEXT_ANSWER_TYPES), 'array', 'min:1'],
            'acceptable_answers.*' => ['required_with:acceptable_answers', 'string'],
            'case_sensitive' => ['nullable', 'boolean'],
            'word_bank' => ['nullable', 'required_if:question_type,fill_blank_select', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'remove_existing_image' => ['nullable', 'boolean'],
            'explanation' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function normalizeRequest(Request $request): void
    {
        $type = (string) $request->input('question_type');

        if (in_array($type, self::CHOICE_TYPES, true)) {
            $options = array_values(array_map(
                fn ($option) => trim((string) $option),
                (array) $request->input('options', []),
            ));
            $correct = array_values(array_map(
                fn ($index) => filter_var($index, FILTER_VALIDATE_INT) !== false ? (int) $index : $index,
                (array) $request->input('correct_options', []),
            ));
            $request->merge([
                'options' => $type === 'true_false' ? ['True', 'False'] : $options,
                'correct_options' => $correct,
            ]);
        } else {
            $request->request->remove('options');
            $request->request->remove('correct_options');
        }

        if (in_array($type, self::TEXT_ANSWER_TYPES, true)) {
            $request->merge([
                'acceptable_answers' => array_values(array_map(
                    fn ($answer) => trim((string) $answer),
                    (array) $request->input('acceptable_answers', []),
                )),
                'case_sensitive' => $request->boolean('case_sensitive'),
            ]);
        } else {
            $request->request->remove('acceptable_answers');
            $request->request->remove('case_sensitive');
        }

        if ($type === 'fill_blank_select') {
            $words = array_values(array_filter(array_map(
                fn ($word) => trim((string) $word),
                explode(',', (string) $request->input('word_bank')),
            ), fn ($word) => $word !== ''));
            $request->merge(['word_bank' => implode(', ', $words)]);
        } else {
            $request->request->remove('word_bank');
        }
    }

    private function validateConfiguration(Validator $validator, Request $request): void
    {
        $type = (string) $request->input('question_type');
        $questionText = trim(html_entity_decode(strip_tags(
            str_replace(['&nbsp;', '&#160;'], ' ', (string) $request->input('question_text')),
        )));

        if ($questionText === '') {
            $validator->errors()->add('question_text', 'Question text is required.');
        }

        if (in_array($type, self::CHOICE_TYPES, true)) {
            $options = (array) $request->input('options', []);
            $correct = (array) $request->input('correct_options', []);
            $invalidIndices = array_filter($correct, fn ($index) => ! array_key_exists((int) $index, $options));

            if ($invalidIndices !== []) {
                $validator->errors()->add('correct_options', 'Every correct answer must refer to an answer option.');
            }
            if (in_array($type, ['multiple_choice', 'true_false'], true) && count($correct) !== 1) {
                $validator->errors()->add('correct_options', 'Select exactly one correct answer.');
            }
            if ($type === 'true_false' && ! in_array($correct[0] ?? null, [0, 1], true)) {
                $validator->errors()->add('correct_options', 'Select True or False as the correct answer.');
            }
        }

        if (in_array($type, self::TEXT_ANSWER_TYPES, true)) {
            foreach ((array) $request->input('acceptable_answers', []) as $answer) {
                $invalid = match ($type) {
                    'fill_blank_text' => str_contains($answer, ';')
                        || collect(explode('|', $answer))->contains(fn ($alternative) => trim($alternative) === ''),
                    'fill_blank_select' => str_contains($answer, ';') || str_contains($answer, '|'),
                    'identification' => str_contains($answer, '|'),
                    default => false,
                };
                if ($invalid) {
                    $validator->errors()->add('acceptable_answers', 'Answers contain a reserved separator or an empty alternative.');
                    break;
                }
            }
        }

        if (in_array($type, ['fill_blank_text', 'fill_blank_select'], true)) {
            $blankCount = substr_count((string) $request->input('question_text'), '_____');
            $answers = (array) $request->input('acceptable_answers', []);
            if ($blankCount < 1) {
                $validator->errors()->add('question_text', 'Add at least one blank using five underscores (_____).');
            }
            if ($blankCount !== count($answers)) {
                $validator->errors()->add('acceptable_answers', 'Add exactly one answer for each blank.');
            }
        }

        if ($type === 'fill_blank_select') {
            $words = array_map('trim', explode(',', (string) $request->input('word_bank')));
            if (count($words) > 10) {
                $validator->errors()->add('word_bank', 'Word bank cannot exceed 10 words.');
            }
            foreach ((array) $request->input('acceptable_answers', []) as $answer) {
                if (! in_array($answer, $words, true)) {
                    $validator->errors()->add('acceptable_answers', 'Every correct answer must appear in the Word Bank.');
                    break;
                }
            }
        }
    }

    public function createQuestion(array $data, array $owner): QuizQuestion
    {
        return $this->withinTransaction(function () use ($data, $owner): QuizQuestion {
            $payload = $this->questionPayload($data, $owner);
            $this->deleteNewImageOnRollback($data, $payload['image_path']);
            $question = QuizQuestion::create($payload);
            $this->replaceOptions($question, $data);

            return $question->load('options');
        });
    }

    public function updateQuestion(QuizQuestion $question, array $data): QuizQuestion
    {
        $oldImagePath = $question->image_path;

        return $this->withinTransaction(function () use ($question, $data, $oldImagePath): QuizQuestion {
            $payload = $this->questionPayload($data, [], $question->image_path);
            $this->deleteNewImageOnRollback($data, $payload['image_path']);
            $question->update($payload);
            $this->replaceOptions($question, $data);

            $imageWasReplaced = ($data['image'] ?? null) instanceof UploadedFile;
            $removeExisting = ! empty($data['remove_existing_image']);
            if ($oldImagePath && ($imageWasReplaced || $question->question_type !== 'identification' || $removeExisting)) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($oldImagePath));
            }

            return $question->refresh()->load('options');
        });
    }

    private function questionPayload(array $data, array $owner, ?string $existingImagePath = null): array
    {
        $answers = array_map('trim', $data['acceptable_answers'] ?? []);
        $acceptableAnswers = match ($data['question_type']) {
            'identification' => implode('|', $answers),
            'fill_blank_text', 'fill_blank_select' => implode(';', $answers),
            default => null,
        };
        $usesTextAnswers = in_array($data['question_type'], self::TEXT_ANSWER_TYPES, true);
        $usesImage = $data['question_type'] === 'identification';

        return array_merge($owner, [
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'],
            'points' => (int) $data['points'],
            'acceptable_answers' => $acceptableAnswers,
            'case_sensitive' => $usesTextAnswers && ! empty($data['case_sensitive']),
            'word_bank' => $data['question_type'] === 'fill_blank_select'
                ? array_map('trim', explode(',', $data['word_bank']))
                : null,
            'image_path' => $usesImage
                ? (($data['image'] ?? null) instanceof UploadedFile
                    ? $data['image']->store($this->imageDirectory(), 'public')
                    : (! empty($data['remove_existing_image']) ? null : ($data['image_path'] ?? $existingImagePath)))
                : null,
            'explanation' => $data['explanation'] ?? null,
        ]);
    }

    private function replaceOptions(QuizQuestion $question, array $data): void
    {
        $question->options()->delete();

        if (! in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)
            || ! isset($data['options'])
            || ! is_array($data['options'])) {
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
        return 'quiz-images/user-'.(int) Auth::id();
    }

    private function deleteNewImageOnRollback(array $data, ?string $imagePath): void
    {
        if (($data['image'] ?? null) instanceof UploadedFile && $imagePath) {
            DB::afterRollBack(fn () => Storage::disk('public')->delete($imagePath));
        }
    }

    private function withinTransaction(callable $callback): mixed
    {
        return DB::transactionLevel() > 0 ? $callback() : DB::transaction($callback);
    }
}
