# Interactive Checkpoints Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional Interactive Checkpoints inside topics and between topics while reusing the existing six quiz question types and preserving formal quiz/shield behavior.

**Architecture:** Use `QuizQuestion`/`QuizOption` as the shared question data model, extract quiz answer evaluation into a reusable service, and add checkpoint-specific ownership/progress around it. Between-topic checkpoints use the existing ordered `lesson_topics` sequence with a new `interactive_checkpoint` type; inside-topic checkpoints use ordered `content_blocks` on the parent topic.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Eloquent, PHPUnit, Vite/Tailwind.

## Global Constraints

- Do not create a duplicate quiz/question engine.
- Support exactly these question types: `multiple_choice`, `identification`, `true_false`, `fill_blank_text`, `fill_blank_select`, `multiple_select`.
- Interactive Checkpoints must not create `QuizAttempt` records.
- Interactive Checkpoints must not consume or refund `UserDailyShield` records.
- Interactive Checkpoints must not award quiz gamification points.
- Interactive Checkpoints must not affect formal quiz scoring, quiz eligibility, certification requirements, or formal completion rules.
- Existing topics with no checkpoints must render through the current legacy path.
- Admin and instructor authoring must use existing content permissions and ownership rules.
- Run targeted tests after every task and full regression before completion.

---

## File Structure

- Create: `database/migrations/2026_08_21_000001_add_interactive_checkpoint_fields.php`
  Adds checkpoint ownership columns to `quiz_questions`, `content_blocks` to `lesson_topics`, widens `lesson_topics.type`, and creates `interactive_checkpoint_progress`.
- Modify: `app/Models/LessonTopic.php`
  Adds `content_blocks` cast, checkpoint relationships, and helper scopes.
- Modify: `app/Models/QuizQuestion.php`
  Adds nullable checkpoint fields, `explanation`, fillable entries, and checkpoint scopes/relationships.
- Create: `app/Models/InteractiveCheckpointProgress.php`
  Tracks checkpoint learner state separately from quiz attempts.
- Create: `app/Services/Learning/QuestionEvaluator.php`
  Centralizes answer evaluation for all six existing question types.
- Create: `app/Services/Learning/QuestionAuthoringService.php`
  Centralizes question validation and persistence for formal quizzes and checkpoints.
- Modify: `app/Http/Controllers/Learner/QuizController.php`
  Replaces inline answer checking with `QuestionEvaluator` while preserving shields, attempts, scoring, and gamification.
- Create: `app/Http/Controllers/Learner/InteractiveCheckpointController.php`
  Handles checkpoint submit/skip JSON endpoints with learner access checks.
- Modify: `app/Http/Controllers/Instructor/QuizManagementController.php`
  Reuses `QuestionAuthoringService` for formal quiz question create/update.
- Modify: `app/Http/Controllers/Instructor/TopicController.php`
  Adds checkpoint authoring storage for inside-topic and between-topic placement.
- Modify: `routes/web.php`
  Adds learner checkpoint submit/skip routes.
- Modify: `resources/views/instructor/topics/create.blade.php`
  Adds Interactive Checkpoint authoring UI.
- Modify: `resources/views/instructor/topics/edit.blade.php`
  Adds checkpoint edit UI.
- Create: `resources/views/instructor/quizzes/partials/question-fields.blade.php`
  Shared authoring fields for six question types.
- Modify: `resources/views/instructor/quizzes/add-question.blade.php`
  Uses shared question fields partial.
- Modify: `resources/views/instructor/quizzes/edit-question.blade.php`
  Uses shared question fields partial.
- Create: `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`
  Shared learner checkpoint component.
- Modify: `resources/views/learner/lessons/partials/topic-page.blade.php`
  Renders between-topic checkpoints and inside-topic `content_blocks`.
- Modify: `resources/views/learner/lessons/show.blade.php`
  Updates sidebar, progress counts, and bottom navigation for checkpoint rows.
- Create: `tests/Unit/Services/Learning/QuestionEvaluatorTest.php`
- Create: `tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php`
- Create: `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`
- Create: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`
- Create: `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php`
  Adds shield/attempt regression coverage.

---

### Task 1: Schema and Model Groundwork

**Files:**
- Create: `database/migrations/2026_08_21_000001_add_interactive_checkpoint_fields.php`
- Modify: `app/Models/LessonTopic.php`
- Modify: `app/Models/QuizQuestion.php`
- Create: `app/Models/InteractiveCheckpointProgress.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointSchemaTest.php`

**Interfaces:**
- Produces: `LessonTopic::scopeInstructional($query)`, `LessonTopic::checkpointQuestion()`, `QuizQuestion::scopeFormalQuiz($query)`, `QuizQuestion::scopeCheckpoint($query)`, `InteractiveCheckpointProgress` model.
- Consumes: existing `LessonTopic`, `QuizQuestion`, `QuizOption`, and `LessonTopicProgress`.

- [ ] **Step 1: Write the failing schema/model test**

```php
<?php

namespace Tests\Feature\Learner;

use App\Models\InteractiveCheckpointProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointSchemaTest extends TestCase
{
    public function test_checkpoint_question_can_belong_to_lesson_topic_without_formal_quiz(): void
    {
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive_checkpoint',
            'interactive_config' => ['placement' => 'between_topics'],
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => null,
            'checkpoint_topic_id' => $topic->id,
            'question_text' => 'Consent can be withdrawn at any time.',
            'question_type' => 'true_false',
            'points' => 1,
            'order' => 1,
            'explanation' => 'Consent must remain freely given.',
        ]);

        $progress = InteractiveCheckpointProgress::create([
            'user_id' => User::factory()->create(['role' => 'learner'])->id,
            'lesson_topic_id' => $topic->id,
            'quiz_question_id' => $question->id,
            'status' => 'skipped',
            'attempt_count' => 0,
            'completed_at' => now(),
        ]);

        $this->assertTrue($question->is($topic->checkpointQuestion));
        $this->assertSame('skipped', $progress->status);
        $this->assertCount(0, QuizQuestion::formalQuiz()->get());
        $this->assertCount(1, QuizQuestion::checkpoint()->get());
    }

    public function test_instructional_scope_excludes_between_topic_checkpoints(): void
    {
        $lesson = Lesson::factory()->create();
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 1]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'order' => 2]);

        $this->assertSame(1, $lesson->topics()->instructional()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointSchemaTest.php
```

Expected: FAIL because the migration/model fields and `InteractiveCheckpointProgress` model do not exist.

- [ ] **Step 3: Add migration**

Create `database/migrations/2026_08_21_000001_add_interactive_checkpoint_fields.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_topics', function (Blueprint $table): void {
            $table->json('content_blocks')->nullable()->after('text_content');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lesson_topics MODIFY COLUMN type ENUM('video', 'text', 'worksheet', 'quiz', 'interactive', 'interactive_checkpoint') DEFAULT 'text'");
        }

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->foreignId('checkpoint_topic_id')
                ->nullable()
                ->after('quiz_id')
                ->constrained('lesson_topics')
                ->cascadeOnDelete();
            $table->string('checkpoint_block_uuid')->nullable()->after('checkpoint_topic_id');
            $table->text('explanation')->nullable()->after('image_path');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->dropForeign(['quiz_id']);
            });
        }

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->foreignId('quiz_id')->nullable()->change();
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            });
        }

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->index(['checkpoint_topic_id', 'checkpoint_block_uuid'], 'quiz_questions_checkpoint_owner_index');
        });

        Schema::create('interactive_checkpoint_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_topic_id')->constrained('lesson_topics')->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('checkpoint_block_uuid')->nullable();
            $table->string('status', 32)->default('not_attempted');
            $table->json('latest_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quiz_question_id'], 'checkpoint_progress_user_question_unique');
            $table->index(['user_id', 'lesson_topic_id'], 'checkpoint_progress_user_topic_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_checkpoint_progress');

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropIndex('quiz_questions_checkpoint_owner_index');
            $table->dropForeign(['checkpoint_topic_id']);
            $table->dropColumn(['checkpoint_topic_id', 'checkpoint_block_uuid', 'explanation']);
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->foreignId('quiz_id')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lesson_topics MODIFY COLUMN type ENUM('video', 'text', 'worksheet', 'quiz', 'interactive') DEFAULT 'text'");
        }

        Schema::table('lesson_topics', function (Blueprint $table): void {
            $table->dropColumn('content_blocks');
        });
    }
};
```

- [ ] **Step 4: Update models**

In `app/Models/LessonTopic.php`, add `content_blocks` to `$fillable`, add its cast, and add helpers:

```php
'content_blocks',
```

```php
'content_blocks' => 'array',
```

```php
public function checkpointQuestion()
{
    return $this->hasOne(QuizQuestion::class, 'checkpoint_topic_id')->whereNull('checkpoint_block_uuid');
}

public function checkpointQuestions()
{
    return $this->hasMany(QuizQuestion::class, 'checkpoint_topic_id');
}

public function scopeInstructional($query)
{
    return $query->where('type', '!=', 'interactive_checkpoint');
}
```

In `app/Models/QuizQuestion.php`, add fillables:

```php
'checkpoint_topic_id',
'checkpoint_block_uuid',
'explanation',
```

Add relationships/scopes:

```php
public function checkpointTopic()
{
    return $this->belongsTo(LessonTopic::class, 'checkpoint_topic_id');
}

public function checkpointProgress()
{
    return $this->hasMany(InteractiveCheckpointProgress::class);
}

public function scopeFormalQuiz($query)
{
    return $query->whereNotNull('quiz_id')->whereNull('checkpoint_topic_id');
}

public function scopeCheckpoint($query)
{
    return $query->whereNotNull('checkpoint_topic_id');
}
```

Create `app/Models/InteractiveCheckpointProgress.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractiveCheckpointProgress extends Model
{
    protected $table = 'interactive_checkpoint_progress';

    protected $fillable = [
        'user_id',
        'lesson_topic_id',
        'quiz_question_id',
        'checkpoint_block_uuid',
        'status',
        'latest_answer',
        'is_correct',
        'attempt_count',
        'answered_at',
        'skipped_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'latest_answer' => 'array',
            'is_correct' => 'boolean',
            'attempt_count' => 'integer',
            'answered_at' => 'datetime',
            'skipped_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessonTopic(): BelongsTo
    {
        return $this->belongsTo(LessonTopic::class);
    }

    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointSchemaTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_21_000001_add_interactive_checkpoint_fields.php app/Models/LessonTopic.php app/Models/QuizQuestion.php app/Models/InteractiveCheckpointProgress.php tests/Feature/Learner/InteractiveCheckpointSchemaTest.php
git commit -m "feat: add checkpoint data model"
```

---

### Task 2: Shared Question Evaluator

**Files:**
- Create: `app/Services/Learning/QuestionEvaluator.php`
- Test: `tests/Unit/Services/Learning/QuestionEvaluatorTest.php`

**Interfaces:**
- Produces: `QuestionEvaluator::evaluate(QuizQuestion $question, mixed $selectedAnswer): array`.
- Consumes: `QuizQuestion` with loaded `options`.

- [ ] **Step 1: Write failing evaluator tests**

Create tests covering at least one correct and one incorrect case for each question type:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Services/Learning/QuestionEvaluatorTest.php
```

Expected: FAIL because `QuestionEvaluator` does not exist.

- [ ] **Step 3: Implement evaluator by moving existing quiz logic**

Create `app/Services/Learning/QuestionEvaluator.php`:

```php
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
```

- [ ] **Step 4: Run evaluator tests**

```bash
php artisan test tests/Unit/Services/Learning/QuestionEvaluatorTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Learning/QuestionEvaluator.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php
git commit -m "feat: add shared question evaluator"
```

---

### Task 3: Refactor Formal Quiz Submission Safely

**Files:**
- Modify: `app/Http/Controllers/Learner/QuizController.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php`

**Interfaces:**
- Consumes: `QuestionEvaluator::evaluate()`.
- Produces: unchanged `QuizController::submit()` external behavior.

- [ ] **Step 1: Write regression tests for formal quiz attempts and shields**

Create `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php` with a formal quiz test before modifying the controller:

```php
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
```

- [ ] **Step 2: Run regression test before refactor**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
```

Expected: PASS on current code before the refactor. If it fails, the implementer must inspect the failed assertion and adjust only the test fixture data so it matches existing quiz access requirements; production code must remain unchanged in this step.

- [ ] **Step 3: Inject and use `QuestionEvaluator` in `QuizController`**

Modify constructor:

```php
use App\Services\Learning\QuestionEvaluator;

public function __construct(
    private GamificationService $gamificationService,
    private SubscriptionService $subscriptionService,
    private LearnerModuleCompletionService $completionService,
    private QuestionEvaluator $questionEvaluator,
) {}
```

Replace the per-question answer branching inside `submit()` with:

```php
foreach ($quiz->questions as $question) {
    $selectedAnswer = ($request->input('answers', []))[$question->id] ?? null;
    $result = $this->questionEvaluator->evaluate($question, $selectedAnswer);

    $userAnswers[$question->id] = $result;

    if ($result['is_correct']) {
        $correctAnswers++;
    }
}
```

Do not touch:

- `canStartAttempt()`
- `UserDailyShield::getShields()`
- `UserDailyShield::drainShield()`
- `UserDailyShield::refillOne()`
- `QuizAttempt::create()`
- gamification award calls
- final quiz/module completion logic

- [ ] **Step 4: Run quiz regression tests**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Feature/Learner/LearnerQuizAttemptLimitTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Learner/QuizController.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
git commit -m "refactor: share quiz answer evaluation"
```

---

### Task 4: Shared Question Authoring Service

**Files:**
- Create: `app/Services/Learning/QuestionAuthoringService.php`
- Modify: `app/Http/Controllers/Instructor/QuizManagementController.php`
- Create: `tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php`

**Interfaces:**
- Produces:
  - `QuestionAuthoringService::rules(): array`
  - `QuestionAuthoringService::createQuestion(array $data, array $owner): QuizQuestion`
  - `QuestionAuthoringService::updateQuestion(QuizQuestion $question, array $data): QuizQuestion`
- Consumes: existing quiz question request payload shape.

- [ ] **Step 1: Write service tests**

```php
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
            'image' => UploadedFile::fake()->image('symbol.png'),
        ], ['quiz_id' => $quiz->id]);

        $this->assertSame('consent', $question->acceptable_answers);
        Storage::disk('public')->assertExists($question->image_path);
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

```bash
php artisan test tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php
```

Expected: FAIL because the service does not exist.

- [ ] **Step 3: Implement `QuestionAuthoringService`**

Create `app/Services/Learning/QuestionAuthoringService.php`:

```php
<?php

namespace App\Services\Learning;

use App\Models\QuizQuestion;
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
        return array_merge($owner, [
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'],
            'points' => (int) ($data['points'] ?? 1),
            'acceptable_answers' => isset($data['acceptable_answers'])
                ? implode('|', array_map('trim', $data['acceptable_answers']))
                : null,
            'case_sensitive' => !empty($data['case_sensitive']),
            'word_bank' => !empty($data['word_bank'])
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

        if (!isset($data['options']) || !is_array($data['options'])) {
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
```

- [ ] **Step 4: Refactor `QuizManagementController`**

Inject the service:

```php
use App\Services\Learning\QuestionAuthoringService;

public function __construct(private QuestionAuthoringService $questionAuthoring) {}
```

In `storeQuestion()`, replace inline validation and persistence with:

```php
$validated = $request->validate($this->questionAuthoring->rules());

if ($request->question_type === 'fill_blank_select' && $request->word_bank) {
    $words = array_map('trim', explode(',', $request->word_bank));
    if (count($words) > 10) {
        return back()->withErrors(['word_bank' => 'Word bank cannot exceed 10 words.'])->withInput();
    }
}

$this->questionAuthoring->createQuestion($validated + ['image' => $request->file('image')], [
    'quiz_id' => $quiz->id,
    'order' => $quiz->questions()->max('order') + 1,
]);
```

In `updateQuestion()`, validate with the same rules and call:

```php
$this->questionAuthoring->updateQuestion($question, $validated + ['image' => $request->file('image')]);
```

Preserve existing redirects and authorization checks.

- [ ] **Step 5: Run question authoring tests and existing quiz authoring smoke**

```bash
php artisan test tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php
php artisan test tests/Feature/Admin/AdminSharedContentPolicyTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Learning/QuestionAuthoringService.php app/Http/Controllers/Instructor/QuizManagementController.php tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php
git commit -m "refactor: share question authoring"
```

---

### Task 5: Learner Checkpoint Submit and Skip API

**Files:**
- Create: `app/Http/Controllers/Learner/InteractiveCheckpointController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`

**Interfaces:**
- Produces:
  - `POST learner.checkpoints.submit` at `/learn/checkpoints/{question}/submit`
  - `POST learner.checkpoints.skip` at `/learn/checkpoints/{question}/skip`
- Consumes: `QuestionEvaluator`, `InteractiveCheckpointProgress`, existing enrollment access rules.

- [ ] **Step 1: Write learner flow tests**

```php
<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Models\InteractiveCheckpointProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\UserDailyShield;
use Tests\TestCase;

class InteractiveCheckpointFlowTest extends TestCase
{
    public function test_learner_can_answer_checkpoint_without_spending_shield_or_creating_quiz_attempt(): void
    {
        [$learner, $question] = $this->checkpointFixture();
        $correctOption = $question->options()->where('is_correct', true)->first();
        UserDailyShield::refillFull($learner);
        $before = UserDailyShield::getShields($learner);

        $this->actingAs($learner)
            ->postJson(route('learner.checkpoints.submit', $question), [
                'answer' => $correctOption->id,
            ])
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('status', 'correct')
            ->assertJsonPath('explanation', 'Consent must be freely given.');

        $this->assertSame($before, UserDailyShield::getShields($learner->refresh()));
        $this->assertSame(0, QuizAttempt::count());
        $this->assertDatabaseHas('interactive_checkpoint_progress', [
            'user_id' => $learner->id,
            'quiz_question_id' => $question->id,
            'status' => 'correct',
            'is_correct' => true,
            'attempt_count' => 1,
        ]);
    }

    public function test_learner_can_skip_checkpoint_without_being_marked_incorrect(): void
    {
        [$learner, $question] = $this->checkpointFixture();

        $this->actingAs($learner)
            ->postJson(route('learner.checkpoints.skip', $question))
            ->assertOk()
            ->assertJsonPath('status', 'skipped')
            ->assertJsonPath('is_correct', null);

        $this->assertDatabaseHas('interactive_checkpoint_progress', [
            'user_id' => $learner->id,
            'quiz_question_id' => $question->id,
            'status' => 'skipped',
            'is_correct' => null,
        ]);
    }

    public function test_unenrolled_learner_cannot_submit_checkpoint(): void
    {
        [, $question] = $this->checkpointFixture();
        $otherLearner = User::factory()->create(['role' => 'learner']);
        $otherLearner->assignRole('learner');

        $this->actingAs($otherLearner)
            ->postJson(route('learner.checkpoints.submit', $question), ['answer' => 1])
            ->assertForbidden();
    }

    private function checkpointFixture(): array
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive_checkpoint',
            'interactive_config' => ['placement' => 'between_topics'],
        ]);

        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => null,
            'checkpoint_topic_id' => $topic->id,
            'question_text' => 'What does consent require?',
            'question_type' => 'multiple_choice',
            'points' => 1,
            'order' => 1,
            'explanation' => 'Consent must be freely given.',
        ]);
        $question->options()->create(['option_text' => 'Pressure', 'is_correct' => false, 'order' => 0]);
        $question->options()->create(['option_text' => 'Free agreement', 'is_correct' => true, 'order' => 1]);

        return [$learner, $question->refresh()];
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php
```

Expected: FAIL because routes/controller do not exist.

- [ ] **Step 3: Add routes**

In `routes/web.php`, inside the existing learner `/learn` group:

```php
Route::post('/checkpoints/{question}/submit', [\App\Http\Controllers\Learner\InteractiveCheckpointController::class, 'submit'])
    ->name('checkpoints.submit');
Route::post('/checkpoints/{question}/skip', [\App\Http\Controllers\Learner\InteractiveCheckpointController::class, 'skip'])
    ->name('checkpoints.skip');
```

- [ ] **Step 4: Implement learner controller**

Create `app/Http/Controllers/Learner/InteractiveCheckpointController.php`:

```php
<?php

namespace App\Http\Controllers\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\InteractiveCheckpointProgress;
use App\Models\LessonTopicProgress;
use App\Models\QuizQuestion;
use App\Services\Learning\QuestionEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InteractiveCheckpointController extends Controller
{
    public function __construct(private QuestionEvaluator $questionEvaluator) {}

    public function submit(Request $request, QuizQuestion $question): JsonResponse
    {
        $question->load(['options', 'checkpointTopic.lesson.module']);
        $this->authorizeCheckpointAccess($question);

        $validated = $request->validate(['answer' => ['nullable']]);
        $result = $this->questionEvaluator->evaluate($question, $validated['answer'] ?? null);
        $status = $result['is_correct'] ? 'correct' : 'incorrect';

        $progress = InteractiveCheckpointProgress::firstOrNew([
            'user_id' => Auth::id(),
            'quiz_question_id' => $question->id,
        ]);

        $progress->fill([
            'lesson_topic_id' => $question->checkpoint_topic_id,
            'checkpoint_block_uuid' => $question->checkpoint_block_uuid,
            'status' => $status,
            'latest_answer' => $result,
            'is_correct' => $result['is_correct'],
            'attempt_count' => ((int) $progress->attempt_count) + 1,
            'answered_at' => now(),
            'completed_at' => now(),
        ])->save();

        $this->markBetweenTopicCheckpointComplete($question);

        return response()->json([
            'status' => $status,
            'is_correct' => $result['is_correct'],
            'result' => $result,
            'explanation' => $question->explanation,
        ]);
    }

    public function skip(QuizQuestion $question): JsonResponse
    {
        $question->load(['checkpointTopic.lesson.module']);
        $this->authorizeCheckpointAccess($question);

        InteractiveCheckpointProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'quiz_question_id' => $question->id],
            [
                'lesson_topic_id' => $question->checkpoint_topic_id,
                'checkpoint_block_uuid' => $question->checkpoint_block_uuid,
                'status' => 'skipped',
                'latest_answer' => null,
                'is_correct' => null,
                'skipped_at' => now(),
                'completed_at' => now(),
            ],
        );

        $this->markBetweenTopicCheckpointComplete($question);

        return response()->json([
            'status' => 'skipped',
            'is_correct' => null,
            'explanation' => null,
        ]);
    }

    private function authorizeCheckpointAccess(QuizQuestion $question): void
    {
        abort_unless($question->checkpoint_topic_id !== null, 404);

        $topic = $question->checkpointTopic;
        $lesson = $topic?->lesson;
        $module = $lesson?->module;

        abort_unless($topic && $lesson && $module, 404);
        abort_unless($lesson->is_published, 404);
        abort_unless($module->isLearnerVisible(), 403);

        $isEnrolled = Auth::user()->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists();

        abort_unless($isEnrolled, 403);
    }

    private function markBetweenTopicCheckpointComplete(QuizQuestion $question): void
    {
        $topic = $question->checkpointTopic;

        if ($topic?->type !== 'interactive_checkpoint' || $question->checkpoint_block_uuid !== null) {
            return;
        }

        LessonTopicProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'lesson_topic_id' => $topic->id],
            ['completed' => true, 'completed_at' => now()],
        );
    }
}
```

- [ ] **Step 5: Run learner checkpoint tests**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learner/InteractiveCheckpointController.php routes/web.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php
git commit -m "feat: add learner checkpoint flow"
```

---

### Task 6: Authoring Backend for Checkpoints

**Files:**
- Modify: `app/Http/Controllers/Instructor/TopicController.php`
- Test: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`

**Interfaces:**
- Consumes: `QuestionAuthoringService`.
- Produces: `TopicController::store()`/`update()` support `type=interactive_checkpoint`, `checkpoint_placement=between_topics|inside_topic`, and question fields.

- [ ] **Step 1: Write authoring tests**

```php
<?php

namespace Tests\Feature\Instructor;

use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointAuthoringTest extends TestCase
{
    public function test_instructor_can_create_between_topic_checkpoint(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id, 'content_owner_type' => 'instructor']);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Understanding Consent',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'between_topics',
                'question_text' => 'Consent requires free agreement.',
                'question_type' => 'true_false',
                'points' => 1,
                'options' => ['True', 'False'],
                'correct_options' => [0],
                'explanation' => 'Consent cannot be pressured.',
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $topic = LessonTopic::where('lesson_id', $lesson->id)->where('type', 'interactive_checkpoint')->firstOrFail();
        $this->assertSame('between_topics', $topic->interactive_config['placement']);
        $this->assertDatabaseHas('quiz_questions', [
            'checkpoint_topic_id' => $topic->id,
            'question_type' => 'true_false',
            'explanation' => 'Consent cannot be pressured.',
        ]);
    }

    public function test_instructor_can_add_inside_topic_checkpoint_block(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $module = Module::factory()->create(['created_by' => $instructor->id, 'content_owner_type' => 'instructor']);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $parentTopic = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'text_content' => '<p>First</p><p>Second</p>',
        ]);

        $this->actingAs($instructor)
            ->post(route('instructor.topics.store'), [
                'lesson_id' => $lesson->id,
                'title' => 'Inline Check',
                'type' => 'interactive_checkpoint',
                'duration' => 1,
                'checkpoint_placement' => 'inside_topic',
                'parent_topic_id' => $parentTopic->id,
                'insert_after_block' => 0,
                'question_text' => 'Pick two safe actions.',
                'question_type' => 'multiple_select',
                'points' => 1,
                'options' => ['Ask', 'Pressure', 'Pause'],
                'correct_options' => [0, 2],
            ])
            ->assertRedirect(route('instructor.lessons.show', $lesson));

        $parentTopic->refresh();
        $this->assertNotNull($parentTopic->content_blocks);
        $this->assertSame('checkpoint', $parentTopic->content_blocks[1]['type']);
        $this->assertSame(1, QuizQuestion::where('checkpoint_topic_id', $parentTopic->id)->checkpoint()->count());
        $this->assertSame(0, LessonTopic::where('lesson_id', $lesson->id)->where('type', 'interactive_checkpoint')->count());
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Expected: FAIL because controller validation does not accept checkpoint payloads.

- [ ] **Step 3: Inject `QuestionAuthoringService` into `TopicController`**

Add import and constructor:

```php
use App\Services\Learning\QuestionAuthoringService;
use Illuminate\Support\Str;

public function __construct(private QuestionAuthoringService $questionAuthoring) {}
```

If `TopicController` has no constructor, add one. Do not remove existing methods.

- [ ] **Step 4: Extend store validation**

Change the topic type rule in `store()` and `update()`:

```php
'type' => 'required|in:video,text,worksheet,interactive,interactive_checkpoint',
'checkpoint_placement' => 'nullable|required_if:type,interactive_checkpoint|in:inside_topic,between_topics',
'parent_topic_id' => 'nullable|required_if:checkpoint_placement,inside_topic|integer|exists:lesson_topics,id',
'insert_after_block' => 'nullable|integer|min:0',
```

Merge in `$this->questionAuthoring->rules()` when `type === 'interactive_checkpoint'`.

- [ ] **Step 5: Add store branch before normal topic creation**

At the start of `store()` after lesson authorization:

```php
if ($request->input('type') === 'interactive_checkpoint') {
    return $this->storeCheckpoint($request, $lessonForAuthorization);
}
```

Add private methods:

```php
private function storeCheckpoint(Request $request, Lesson $lesson)
{
    $validated = $request->validate(array_merge([
        'lesson_id' => ['required', 'exists:lessons,id'],
        'title' => ['required', 'string', 'max:255'],
        'duration' => ['nullable', 'integer', 'min:1'],
        'checkpoint_placement' => ['required', 'in:inside_topic,between_topics'],
        'parent_topic_id' => ['nullable', 'required_if:checkpoint_placement,inside_topic', 'integer', 'exists:lesson_topics,id'],
        'insert_after_block' => ['nullable', 'integer', 'min:0'],
    ], $this->questionAuthoring->rules()));

    if ($validated['checkpoint_placement'] === 'inside_topic') {
        $parentTopic = LessonTopic::where('lesson_id', $lesson->id)->findOrFail($validated['parent_topic_id']);
        $blockUuid = (string) Str::uuid();
        $question = $this->questionAuthoring->createQuestion($validated + ['image' => $request->file('image')], [
            'quiz_id' => null,
            'checkpoint_topic_id' => $parentTopic->id,
            'checkpoint_block_uuid' => $blockUuid,
            'order' => $parentTopic->checkpointQuestions()->count() + 1,
        ]);

        $blocks = $this->blocksForTopic($parentTopic);
        $insertAfter = (int) ($validated['insert_after_block'] ?? 0);
        array_splice($blocks, min($insertAfter + 1, count($blocks)), 0, [[
            'type' => 'checkpoint',
            'uuid' => $blockUuid,
            'question_id' => $question->id,
        ]]);

        $parentTopic->update(['content_blocks' => array_values($blocks)]);

        return redirect()->route($this->routeName('lessons.show'), $lesson)
            ->with('success', 'Interactive checkpoint added to topic.');
    }

    $topic = $lesson->topics()->create([
        'title' => $validated['title'],
        'type' => 'interactive_checkpoint',
        'duration' => $validated['duration'] ?? 1,
        'is_prerequisite' => false,
        'order' => $lesson->topics()->max('order') + 1,
        'interactive_config' => ['placement' => 'between_topics'],
    ]);

    $this->questionAuthoring->createQuestion($validated + ['image' => $request->file('image')], [
        'quiz_id' => null,
        'checkpoint_topic_id' => $topic->id,
        'checkpoint_block_uuid' => null,
        'order' => 1,
    ]);

    return redirect()->route($this->routeName('lessons.show'), $lesson)
        ->with('success', 'Interactive checkpoint created successfully.');
}

private function blocksForTopic(LessonTopic $topic): array
{
    if (is_array($topic->content_blocks) && count($topic->content_blocks) > 0) {
        return $topic->content_blocks;
    }

    return [[
        'type' => 'rich_text',
        'html' => $topic->text_content ?? '',
    ]];
}
```

- [ ] **Step 6: Run authoring tests**

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Instructor/TopicController.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
git commit -m "feat: add checkpoint authoring backend"
```

---

### Task 7: Shared Authoring UI

**Files:**
- Create: `resources/views/instructor/quizzes/partials/question-fields.blade.php`
- Modify: `resources/views/instructor/quizzes/add-question.blade.php`
- Modify: `resources/views/instructor/quizzes/edit-question.blade.php`
- Modify: `resources/views/instructor/topics/create.blade.php`
- Modify: `resources/views/instructor/topics/edit.blade.php`
- Test: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`

**Interfaces:**
- Consumes: existing topic form routes and quiz routes.
- Produces: visible checkpoint authoring fields in admin/instructor panels.

- [ ] **Step 1: Add UI assertions**

Add to `InteractiveCheckpointAuthoringTest`:

```php
public function test_topic_create_page_shows_checkpoint_authoring_controls(): void
{
    $instructor = User::factory()->create(['role' => 'instructor']);
    $instructor->assignRole('instructor');
    $module = Module::factory()->create(['created_by' => $instructor->id, 'content_owner_type' => 'instructor']);
    $lesson = Lesson::factory()->create(['module_id' => $module->id]);

    $this->actingAs($instructor)
        ->get(route('instructor.topics.create', ['lesson' => $lesson->id]))
        ->assertOk()
        ->assertSee('Interactive Checkpoint')
        ->assertSee('Inside Topic')
        ->assertSee('Between Topics')
        ->assertSee('Question Type')
        ->assertSee('Explanation');
}
```

- [ ] **Step 2: Run UI assertion to verify failure**

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php --filter=topic_create_page_shows_checkpoint_authoring_controls
```

Expected: FAIL until views are updated.

- [ ] **Step 3: Extract shared question fields partial**

Create `resources/views/instructor/quizzes/partials/question-fields.blade.php` with these inputs:

```blade
@php
    $selectedType = old('question_type', $question->question_type ?? ($selectedType ?? 'multiple_choice'));
@endphp

<div class="space-y-5" id="questionFields">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Question Type</label>
        <select name="question_type" id="question_type" class="w-full rounded-xl border-gray-200" required>
            @foreach([
                'multiple_choice' => 'Multiple Choice',
                'true_false' => 'True or False',
                'identification' => 'Identification',
                'fill_blank_text' => 'Fill in the Blanks - Text',
                'fill_blank_select' => 'Fill in the Blanks - Word Bank',
                'multiple_select' => 'Multiple Select',
            ] as $value => $label)
                <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Question</label>
        <textarea name="question_text" rows="4" class="w-full rounded-xl border-gray-200" required>{{ old('question_text', $question->question_text ?? '') }}</textarea>
    </div>

    <input type="hidden" name="points" value="{{ old('points', $question->points ?? 1) }}">

    <div data-question-section="options" class="space-y-3">
        <label class="block text-sm font-medium text-gray-700">Answer Options</label>
        @for($i = 0; $i < 4; $i++)
            <div class="flex items-center gap-3">
                <input type="text" name="options[]" value="{{ old('options.' . $i, $question->options[$i]->option_text ?? '') }}" class="flex-1 rounded-xl border-gray-200" placeholder="Option {{ $i + 1 }}">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="correct_options[]" value="{{ $i }}" class="rounded border-gray-300 text-purple-600">
                    Correct
                </label>
            </div>
        @endfor
    </div>

    <div data-question-section="text-answer" class="space-y-3 hidden">
        <label class="block text-sm font-medium text-gray-700">Acceptable Answers</label>
        <input type="text" name="acceptable_answers[]" class="w-full rounded-xl border-gray-200" placeholder="Use pipes for alternatives, semicolons for blanks">
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="case_sensitive" value="1" class="rounded border-gray-300 text-purple-600">
            Case sensitive
        </label>
    </div>

    <div data-question-section="word-bank" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Word Bank</label>
        <input type="text" name="word_bank" class="w-full rounded-xl border-gray-200" placeholder="word one, word two, word three">
    </div>

    <div data-question-section="identification-image" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Identification Image</label>
        <input type="file" name="image" accept="image/*" class="w-full rounded-xl border-gray-200">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Explanation</label>
        <textarea name="explanation" rows="3" class="w-full rounded-xl border-gray-200" placeholder="Optional feedback shown after learners answer.">{{ old('explanation', $question->explanation ?? '') }}</textarea>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('question_type');
    if (!select) return;

    const toggleQuestionSections = () => {
        const type = select.value;
        document.querySelectorAll('[data-question-section]').forEach((el) => el.classList.add('hidden'));

        if (['multiple_choice', 'true_false', 'multiple_select'].includes(type)) {
            document.querySelector('[data-question-section="options"]')?.classList.remove('hidden');
        }
        if (['fill_blank_text', 'fill_blank_select', 'identification'].includes(type)) {
            document.querySelector('[data-question-section="text-answer"]')?.classList.remove('hidden');
        }
        if (type === 'fill_blank_select') {
            document.querySelector('[data-question-section="word-bank"]')?.classList.remove('hidden');
        }
        if (type === 'identification') {
            document.querySelector('[data-question-section="identification-image"]')?.classList.remove('hidden');
        }
    };

    select.addEventListener('change', toggleQuestionSections);
    toggleQuestionSections();
});
</script>
@endpush
```

When extracting the partial, move the existing question add/edit fields into `question-fields.blade.php` first, then add only the `explanation` field. This preserves the current quiz authoring behavior while making checkpoints use the same markup.

- [ ] **Step 4: Add checkpoint card and placement UI to topic create/edit**

Add a fourth type card:

```blade
<label class="relative flex flex-col items-center p-6 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:shadow-md transition-all topic-type-card">
    <input type="radio" name="type" value="interactive_checkpoint" class="sr-only topic-type-radio" {{ old('type') === 'interactive_checkpoint' ? 'checked' : '' }} required>
    <span class="text-sm font-semibold text-gray-900">Interactive Checkpoint</span>
</label>
```

Add section:

```blade
<div id="interactive_checkpointContent" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 content-section hidden">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Create Interactive Checkpoint</h2>
    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <label class="rounded-xl border border-gray-200 p-4">
            <input type="radio" name="checkpoint_placement" value="inside_topic" class="text-purple-600">
            <span class="ml-2 font-semibold">Inside Topic</span>
            <p class="mt-1 text-sm text-gray-500">Place this checkpoint within the selected Topic's content.</p>
        </label>
        <label class="rounded-xl border border-gray-200 p-4">
            <input type="radio" name="checkpoint_placement" value="between_topics" class="text-purple-600" checked>
            <span class="ml-2 font-semibold">Between Topics</span>
            <p class="mt-1 text-sm text-gray-500">Place this checkpoint between Topics as a separate step in the Lesson learning flow.</p>
        </label>
    </div>
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Parent Topic for Inside Topic Placement</label>
        <select name="parent_topic_id" class="w-full rounded-xl border-gray-200">
            @foreach($lesson->topics as $lessonTopic)
                <option value="{{ $lessonTopic->id }}">{{ $lessonTopic->title }}</option>
            @endforeach
        </select>
    </div>
    @include('instructor.quizzes.partials.question-fields')
</div>
```

- [ ] **Step 5: Run UI and authoring tests**

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/instructor/quizzes/partials/question-fields.blade.php resources/views/instructor/quizzes/add-question.blade.php resources/views/instructor/quizzes/edit-question.blade.php resources/views/instructor/topics/create.blade.php resources/views/instructor/topics/edit.blade.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
git commit -m "feat: add checkpoint authoring UI"
```

---

### Task 8: Learner Checkpoint Component and Rendering

**Files:**
- Create: `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`
- Modify: `resources/views/learner/lessons/partials/topic-page.blade.php`
- Modify: `resources/views/learner/lessons/show.blade.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`

**Interfaces:**
- Consumes: `QuizQuestion`, `InteractiveCheckpointProgress`, learner checkpoint JSON routes.
- Produces: shared checkpoint learner UI for inside-topic and between-topic placement.

- [ ] **Step 1: Add rendering tests**

Add:

```php
public function test_between_topic_checkpoint_appears_in_lesson_navigation(): void
{
    [$learner, $question] = $this->checkpointFixture();

    $this->actingAs($learner)
        ->get(route('learner.lessons.show', $question->checkpointTopic->lesson))
        ->assertOk()
        ->assertSee('Quick Check')
        ->assertSee('What does consent require?')
        ->assertSee('Skip for now');
}

public function test_inside_topic_checkpoint_renders_between_content_blocks(): void
{
    [$learner, $question] = $this->checkpointFixture('inside_topic');
    $topic = $question->checkpointTopic;
    $topic->update([
        'type' => 'text',
        'content_blocks' => [
            ['type' => 'rich_text', 'html' => '<p>Before checkpoint</p>'],
            ['type' => 'checkpoint', 'uuid' => $question->checkpoint_block_uuid, 'question_id' => $question->id],
            ['type' => 'rich_text', 'html' => '<p>After checkpoint</p>'],
        ],
    ]);

    $response = $this->actingAs($learner)
        ->get(route('learner.lessons.show', $topic->lesson));

    $response->assertOk()
        ->assertSee('Before checkpoint', false)
        ->assertSee('Quick Check')
        ->assertSee('After checkpoint', false);
}
```

Adjust `checkpointFixture()` so it accepts placement and sets `checkpoint_block_uuid` when inside-topic.

- [ ] **Step 2: Run rendering tests to verify failure**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php --filter=checkpoint
```

Expected: FAIL until views are implemented.

- [ ] **Step 3: Create shared learner partial**

Create `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`:

```blade
@php
    $progress = $checkpointProgress[$question->id] ?? null;
@endphp

<section
    x-data="interactiveCheckpoint({
        submitUrl: '{{ route('learner.checkpoints.submit', $question) }}',
        skipUrl: '{{ route('learner.checkpoints.skip', $question) }}',
        csrf: '{{ csrf_token() }}'
    })"
    class="my-6 rounded-2xl border border-purple-200 bg-purple-50/50 dark:border-purple-800 dark:bg-purple-900/10 p-5">
    <p class="text-xs font-bold uppercase tracking-widest text-purple-700 dark:text-purple-300">Quick Check</p>
    <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-white">{!! $question->question_text !!}</h3>

    @if($question->image_url)
        <img src="{{ $question->image_url }}" alt="Question image" class="mt-4 max-h-56 rounded-xl border object-contain">
    @endif

    <div class="mt-4 space-y-3">
        @if(in_array($question->question_type, ['multiple_choice', 'true_false']))
            @foreach($question->options as $option)
                <label class="flex items-center gap-3 rounded-xl border bg-white p-3">
                    <input type="radio" name="checkpoint_{{ $question->id }}" value="{{ $option->id }}" x-model="answer">
                    <span>{{ $option->option_text }}</span>
                </label>
            @endforeach
        @elseif($question->question_type === 'multiple_select')
            @foreach($question->options as $option)
                <label class="flex items-center gap-3 rounded-xl border bg-white p-3">
                    <input type="checkbox" value="{{ $option->id }}" x-model="answer">
                    <span>{{ $option->option_text }}</span>
                </label>
            @endforeach
        @elseif(in_array($question->question_type, ['fill_blank_text', 'identification']))
            <input type="text" x-model="answer" class="w-full rounded-xl border-gray-200" placeholder="Your answer">
        @elseif($question->question_type === 'fill_blank_select')
            <div class="flex flex-wrap gap-2">
                @foreach($question->word_bank ?? [] as $word)
                    <button type="button" @click="answer.push('{{ addslashes($word) }}')" class="rounded-xl border bg-white px-3 py-2 text-sm">{{ $word }}</button>
                @endforeach
            </div>
            <p class="text-sm text-gray-600" x-text="answer.join(' / ')"></p>
        @endif
    </div>

    <template x-if="feedback">
        <div class="mt-4 rounded-xl p-4" :class="isCorrect ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
            <p class="font-semibold" x-text="isCorrect ? 'Correct' : 'Not quite'"></p>
            <p x-show="explanation" class="mt-2 text-sm" x-text="explanation"></p>
        </div>
    </template>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button type="button" @click="submit()" class="rounded-xl px-4 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
            Check Answer
        </button>
        <button type="button" x-show="feedback && !isCorrect" @click="reset()" class="rounded-xl border px-4 py-2 text-sm font-semibold">
            Retry
        </button>
        <button type="button" @click="skip()" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
            Skip for now
        </button>
        <button type="button" x-show="feedback" class="rounded-xl border px-4 py-2 text-sm font-semibold">
            Continue
        </button>
    </div>
</section>

@once
@push('scripts')
<script>
function interactiveCheckpoint(config) {
    return {
        answer: [],
        feedback: false,
        isCorrect: null,
        explanation: null,
        async submit() {
            const response = await fetch(config.submitUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json'},
                body: JSON.stringify({answer: this.answer})
            });
            const data = await response.json();
            this.feedback = true;
            this.isCorrect = data.is_correct;
            this.explanation = data.explanation;
        },
        async skip() {
            await fetch(config.skipUrl, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json'}
            });
            this.feedback = false;
        },
        reset() {
            this.answer = [];
            this.feedback = false;
            this.isCorrect = null;
            this.explanation = null;
        }
    };
}
</script>
@endpush
@endonce
```

Before Step 6, replace the simplified fill-blank controls in this partial with the same blank parsing and word-bank interaction already used in `resources/views/quizzes/take.blade.php` and `resources/views/learner/lessons/partials/quiz-page.blade.php`: split `question_text` on `_____`, render one input/hidden value per blank, and keep word bank buttons in a flex-wrap container.

- [ ] **Step 4: Load checkpoint progress/questions in learner lesson controller**

In `Learner\LessonController::show()`, eager-load checkpoint questions:

```php
$lessonTopics = $lesson->topics()->ordered()->with('checkpointQuestions.options')->get();
$checkpointQuestionIds = $lessonTopics->flatMap->checkpointQuestions->pluck('id');
$checkpointProgress = InteractiveCheckpointProgress::where('user_id', $user->id)
    ->whereIn('quiz_question_id', $checkpointQuestionIds)
    ->get()
    ->keyBy('quiz_question_id');
```

Pass `$checkpointProgress` to the view.

- [ ] **Step 5: Render between-topic and inside-topic checkpoints**

In `topic-page.blade.php`:

```blade
@elseif($currentTopic->type === 'interactive_checkpoint')
    @if($currentTopic->checkpointQuestion)
        @include('learner.lessons.partials.interactive-checkpoint', ['question' => $currentTopic->checkpointQuestion])
    @endif
@elseif(is_array($currentTopic->content_blocks))
    @foreach($currentTopic->content_blocks as $block)
        @if(($block['type'] ?? null) === 'rich_text')
            <div class="prose max-w-none">{!! $block['html'] ?? '' !!}</div>
        @elseif(($block['type'] ?? null) === 'checkpoint')
            @php $question = $currentTopic->checkpointQuestions->firstWhere('id', $block['question_id'] ?? null); @endphp
            @if($question)
                @include('learner.lessons.partials.interactive-checkpoint', ['question' => $question])
            @endif
        @endif
    @endforeach
```

Keep the existing legacy text/video/worksheet rendering as fallback when `content_blocks` is null.

- [ ] **Step 6: Run rendering tests**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Learner/LessonController.php resources/views/learner/lessons/partials/interactive-checkpoint.blade.php resources/views/learner/lessons/partials/topic-page.blade.php resources/views/learner/lessons/show.blade.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php
git commit -m "feat: render learner checkpoints"
```

---

### Task 9: Navigation, Progress, and Completion Isolation

**Files:**
- Modify: `app/Http/Controllers/Learner/LessonController.php`
- Modify: `app/Models/Lesson.php`
- Modify: `resources/views/learner/lessons/show.blade.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php`

**Interfaces:**
- Consumes: `LessonTopic::instructional()`.
- Produces: checkpoint rows visible in navigation without becoming completion/certificate blockers.

- [ ] **Step 1: Write progress isolation tests**

```php
<?php

namespace Tests\Feature\Learner;

use App\Enums\EnrollmentStatus;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\User;
use Tests\TestCase;

class InteractiveCheckpointProgressIsolationTest extends TestCase
{
    public function test_lesson_completion_ignores_uncompleted_between_topic_checkpoint(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text', 'order' => 1]);
        LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'interactive_checkpoint', 'order' => 2]);

        ModuleEnrollment::create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);
        LessonTopicProgress::create([
            'user_id' => $learner->id,
            'lesson_topic_id' => $topic->id,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $this->assertTrue($lesson->allTopicsCompletedBy($learner->id));
        $this->assertSame(100, $lesson->getTopicCompletionPercentage($learner->id));
    }
}
```

- [ ] **Step 2: Run isolation test to verify failure**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
```

Expected: FAIL until completion queries exclude checkpoint rows.

- [ ] **Step 3: Update completion queries**

In `app/Models/Lesson.php`:

```php
public function getTopicCompletionPercentage($userId): int
{
    $totalTopics = $this->topics()->instructional()->count();
    ...
    $completedTopics = $this->topics()
        ->instructional()
        ->whereHas('progress', function ($query) use ($userId) {
            $query->where('user_id', $userId)->where('completed', true);
        })
        ->count();
}
```

In `Learner\LessonController::show()` and `completeTopic()`, use instructional topic IDs for:

- `$allTopicsCompleted`
- `$certificateEligible`
- auto-completing lessons
- progress percentage labels where they mean required instructional content

Keep sidebar item counts inclusive of checkpoints so learners see the sequence.

- [ ] **Step 4: Update navigation labels**

In `show.blade.php`, set checkpoint labels:

```php
$__tLabel = match($__t->type) {
    'interactive_checkpoint' => 'QUICK CHECK',
    'video' => 'VIDEO',
    'text' => 'TEXT',
    'worksheet' => 'FILE',
    'quiz' => 'QUIZ',
    'interactive' => 'INTERACTIVE',
    default => 'CONTENT',
};
```

Ensure prerequisite locking skips checkpoint rows:

```php
if ($topic->type === 'interactive_checkpoint') {
    continue;
}
```

- [ ] **Step 5: Run isolation and lesson tests**

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/LessonPageTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Lesson.php app/Http/Controllers/Learner/LessonController.php resources/views/learner/lessons/show.blade.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
git commit -m "fix: isolate checkpoints from completion"
```

---

### Task 10: End-to-End Regression and Responsive Verification

**Files:**
- Modify: only files changed in Tasks 1-9, and only when a verification command identifies a concrete defect in those changes.
- Test: targeted and full regression commands listed below.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: verified implementation.

- [ ] **Step 1: Run targeted backend test suite**

```bash
php artisan test tests/Unit/Services/Learning/QuestionEvaluatorTest.php tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Expected: PASS.

- [ ] **Step 2: Run existing quiz and lesson regression tests**

```bash
php artisan test tests/Feature/Learner/LearnerQuizAttemptLimitTest.php tests/Feature/Learner/QuizProgressionUxTest.php tests/Feature/Learner/LearnerQuizTimerAutoSubmitTest.php tests/Feature/Learner/LessonPageTest.php tests/Feature/Learner/LearnerFinalQuizCompletionFlowTest.php
```

Expected: PASS. If a test fails because checkpoint rows are being counted as required instructional topics, fix the query to use `instructional()`.

- [ ] **Step 3: Run build**

```bash
npm run build
```

Expected: Vite build succeeds.

- [ ] **Step 4: Run full PHP test suite if time allows**

```bash
composer test
```

Expected: PASS. If full suite is too slow, record that targeted suites passed and list unrun suites in the final implementation report.

- [ ] **Step 5: Manual browser checks**

Start the app:

```bash
php artisan serve
```

Open the learner lesson page and test:

- Desktop width: between-topic checkpoint displays in navigation, answer/skip/retry/continue works.
- Tablet width: answer options wrap without horizontal overflow.
- Mobile width: multiple select and word bank controls have usable touch targets.
- Existing text topic with no `content_blocks` renders unchanged.
- Formal quiz submission still shows shield notice and drains/refunds shields.
- Checkpoint answer leaves shield count unchanged.

- [ ] **Step 6: Final commit for verification fixes**

Only if Step 1-5 required fixes:

```bash
git add <changed-files>
git commit -m "test: verify interactive checkpoints"
```

---

## Plan Self-Review

- Spec coverage: data model, shared question reuse, both placement modes, optional explanation, skip/retry/continue behavior, progress isolation, admin/instructor authoring, learner access, gamification isolation, and regression testing are covered.
- Placeholder scan: no `TBD`, `TODO`, or deferred implementation placeholders remain.
- Type consistency: services consistently use `QuestionEvaluator`, `QuestionAuthoringService`, `InteractiveCheckpointProgress`, `interactive_checkpoint`, `inside_topic`, and `between_topics`.
- Risk note: the migration changes `quiz_questions.quiz_id` to nullable. Run migrations against the project’s real database engine before deploy, because enum and foreign-key alteration behavior differs between SQLite and MySQL.
