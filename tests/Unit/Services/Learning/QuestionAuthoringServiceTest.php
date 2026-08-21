<?php

namespace Tests\Unit\Services\Learning;

use App\Models\Quiz;
use App\Services\Learning\QuestionAuthoringService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
