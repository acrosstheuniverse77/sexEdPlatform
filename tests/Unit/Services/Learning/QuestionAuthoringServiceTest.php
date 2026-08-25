<?php

namespace Tests\Unit\Services\Learning;

use App\Models\Quiz;
use App\Models\User;
use App\Services\Learning\QuestionAuthoringService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuestionAuthoringServiceTest extends TestCase
{
    public function test_creates_multiple_choice_question_with_correct_option(): void
    {
        $quiz = Quiz::factory()->create();

        $question = app(QuestionAuthoringService::class)->createQuestion([
            'question_text' => 'What does consent require?',
            'question_type' => 'multiple_choice',
            'points' => 1,
            'options' => ['Pressure', 'Free agreement'],
            'correct_options' => [1],
            'explanation' => 'Consent must be freely given.',
        ], ['quiz_id' => $quiz->id]);

        $this->assertSame($quiz->id, $question->quiz_id);
        $this->assertSame('Consent must be freely given.', $question->explanation);
        $this->assertTrue($question->options()->where('option_text', 'Free agreement')->first()->is_correct);
    }

    public function test_creates_identification_question_with_image(): void
    {
        Storage::fake('public');
        $quiz = Quiz::factory()->create();

        $question = app(QuestionAuthoringService::class)->createQuestion([
            'question_text' => 'Identify the symbol.',
            'question_type' => 'identification',
            'points' => 1,
            'acceptable_answers' => ['consent'],
            'case_sensitive' => false,
            'image' => UploadedFile::fake()->create('symbol.png', 10, 'image/png'),
        ], ['quiz_id' => $quiz->id]);

        $this->assertSame('consent', $question->acceptable_answers);
        Storage::disk('public')->assertExists($question->image_path);
    }

    public function test_multiple_choice_requires_exactly_one_in_range_correct_option(): void
    {
        $service = app(QuestionAuthoringService::class);

        foreach ([[], [0, 1], [5], [0, 0]] as $correct) {
            try {
                $service->validate(Request::create('/', 'POST', [
                    'question_type' => 'multiple_choice',
                    'question_text' => '<p>Choose one.</p>',
                    'points' => 1,
                    'options' => ['First', 'Second'],
                    'correct_options' => $correct,
                ]));
                $this->fail('Invalid Multiple Choice configuration passed validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('correct_options', $exception->errors());
            }
        }
    }

    public function test_choice_types_require_two_non_empty_options_without_a_maximum(): void
    {
        $service = app(QuestionAuthoringService::class);

        $manyOptions = array_map(fn (int $i) => "Option {$i}", range(1, 25));
        $validated = $service->validate(Request::create('/', 'POST', [
            'question_type' => 'multiple_select',
            'question_text' => '<p>Select every valid answer.</p>',
            'points' => 1,
            'options' => $manyOptions,
            'correct_options' => [0, 24],
        ]));

        $this->assertCount(25, $validated['options']);

        $this->expectException(ValidationException::class);
        $service->validate(Request::create('/', 'POST', [
            'question_type' => 'multiple_choice',
            'question_text' => '<p>Choose one.</p>',
            'points' => 1,
            'options' => ['Only one'],
            'correct_options' => [0],
        ]));
    }

    public function test_true_false_normalizes_fixed_options_and_discards_stale_fields(): void
    {
        $validated = app(QuestionAuthoringService::class)->validate(Request::create('/', 'POST', [
            'question_type' => 'true_false',
            'question_text' => '<p>The statement is true.</p>',
            'points' => 1,
            'options' => ['Yes', 'No', 'Maybe'],
            'correct_options' => ['1'],
            'acceptable_answers' => ['stale'],
            'word_bank' => 'stale, values',
            'case_sensitive' => 1,
        ]));

        $this->assertSame(['True', 'False'], $validated['options']);
        $this->assertSame([1], $validated['correct_options']);
        $this->assertArrayNotHasKey('acceptable_answers', $validated);
        $this->assertArrayNotHasKey('word_bank', $validated);
        $this->assertArrayNotHasKey('case_sensitive', $validated);
    }

    public function test_blank_types_require_matching_ordered_answers_and_word_bank_membership(): void
    {
        $service = app(QuestionAuthoringService::class);

        $text = $service->validate(Request::create('/', 'POST', [
            'question_type' => 'fill_blank_text',
            'question_text' => 'The _____ is _____ .',
            'points' => 1,
            'acceptable_answers' => ['color|colour', 'blue'],
            'case_sensitive' => 0,
        ]));
        $this->assertSame(['color|colour', 'blue'], $text['acceptable_answers']);

        $wordBank = $service->validate(Request::create('/', 'POST', [
            'question_type' => 'fill_blank_select',
            'question_text' => '_____ follows _____.',
            'points' => 1,
            'word_bank' => ' beta, alpha, , gamma ',
            'acceptable_answers' => ['alpha', 'beta'],
        ]));
        $this->assertSame('beta, alpha, gamma', $wordBank['word_bank']);

        foreach ([
            ['question_text' => 'No marker', 'acceptable_answers' => ['answer'], 'word_bank' => null],
            ['question_text' => '_____ and _____', 'acceptable_answers' => ['one'], 'word_bank' => null],
            ['question_text' => '_____', 'acceptable_answers' => ['missing'], 'word_bank' => 'present'],
            ['question_text' => '_____', 'acceptable_answers' => ['one'], 'word_bank' => implode(',', range(1, 11))],
        ] as $invalid) {
            try {
                $service->validate(Request::create('/', 'POST', array_filter([
                    'question_type' => $invalid['word_bank'] === null ? 'fill_blank_text' : 'fill_blank_select',
                    'question_text' => $invalid['question_text'],
                    'points' => 1,
                    'acceptable_answers' => $invalid['acceptable_answers'],
                    'word_bank' => $invalid['word_bank'],
                ], fn ($value) => $value !== null)));
                $this->fail('Invalid blank configuration passed validation.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_identification_requires_meaningful_text_and_an_answer(): void
    {
        $this->expectException(ValidationException::class);

        app(QuestionAuthoringService::class)->validate(Request::create('/', 'POST', [
            'question_type' => 'identification',
            'question_text' => '<p><br></p>&nbsp;',
            'points' => 1,
            'acceptable_answers' => [''],
        ]));
    }

    public function test_update_to_choice_type_clears_text_state_and_deletes_identification_image(): void
    {
        Storage::fake('public');
        $author = User::factory()->create();
        $this->actingAs($author);
        $quiz = Quiz::factory()->create();
        $service = app(QuestionAuthoringService::class);
        $image = UploadedFile::fake()->create('prompt.png', 10, 'image/png');
        $createRequest = Request::create('/', 'POST', [
            'question_type' => 'identification',
            'question_text' => '<p>Name it.</p>',
            'points' => 1,
            'acceptable_answers' => ['Consent'],
            'case_sensitive' => 1,
            'explanation' => 'Helpful feedback.',
        ], [], ['image' => $image]);
        $question = $service->createQuestion($service->validate($createRequest), [
            'quiz_id' => $quiz->id,
            'order' => 1,
        ]);
        $oldPath = $question->image_path;
        Storage::disk('public')->assertExists($oldPath);

        $update = Request::create('/', 'PUT', [
            'question_type' => 'multiple_choice',
            'question_text' => '<p>Choose one.</p>',
            'points' => 1,
            'options' => ['A', 'B'],
            'correct_options' => [0],
            'acceptable_answers' => ['stale'],
            'word_bank' => 'stale, words',
            'case_sensitive' => 1,
            'explanation' => 'Helpful feedback.',
        ]);
        $question = $service->updateQuestion($question, $service->validate($update));

        $this->assertNull($question->acceptable_answers);
        $this->assertNull($question->word_bank);
        $this->assertFalse($question->case_sensitive);
        $this->assertNull($question->image_path);
        $this->assertSame('Helpful feedback.', $question->explanation);
        $this->assertCount(2, $question->options);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_explanation_is_optional_and_limited_to_five_thousand_characters(): void
    {
        $service = app(QuestionAuthoringService::class);
        $valid = $service->validate(Request::create('/', 'POST', [
            'question_type' => 'true_false',
            'question_text' => '<p>Statement.</p>',
            'points' => 1,
            'correct_options' => [0],
        ]));
        $this->assertArrayNotHasKey('explanation', $valid);

        $this->expectException(ValidationException::class);
        $service->validate(Request::create('/', 'POST', [
            'question_type' => 'true_false',
            'question_text' => '<p>Statement.</p>',
            'points' => 1,
            'correct_options' => [0],
            'explanation' => str_repeat('x', 5001),
        ]));
    }
}
