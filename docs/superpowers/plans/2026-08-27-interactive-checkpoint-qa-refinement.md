# Interactive Checkpoint QA Refinement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Interactive Checkpoints optional, correctly composed with Topic content, predictable to answer or skip, and visually consistent without changing formal Quiz or required Lesson progression behavior.

**Architecture:** Preserve `QuizQuestion`, `QuestionAuthoringService`, and `QuestionEvaluator` as the shared question foundation while keeping learner state in `InteractiveCheckpointProgress`. Repair the feature through focused authoring, API-state, learner-rendering, and navigation boundaries; share only the Word Bank selection interaction between Quiz and checkpoint submission adapters.

**Tech Stack:** PHP 8.2, Laravel 12, Blade, Alpine.js 3, Tailwind CSS 3, TinyMCE 8, Node test runner, PHPUnit 11, Vite 7.

**Design reference:** `docs/superpowers/specs/2026-08-27-interactive-checkpoint-qa-refinement-design.md`

## Global Constraints

- Interactive Checkpoints are optional formative activities.
- Standalone checkpoint Topics use `duration = 0` and `is_prerequisite = false`.
- Checkpoints never affect required Topic counts, Lesson completion, Quiz eligibility, certification, locks, shields, scoring, attempts, points, or gamification.
- Incorrect answers never return or display the configured explanation.
- Correct answers display the configured explanation when present.
- Inside-topic checkpoints supplement, never replace, canonical Topic content.
- Support exactly `multiple_choice`, `true_false`, `multiple_select`, `identification`, `fill_blank_text`, and `fill_blank_select`.
- Preserve inside-topic and between-topic placement.
- Preserve ordinary Topic duration and prerequisite behavior.
- Display only one forward-progression action at a time.
- Use `Interactive Checkpoint` in authoring and `Quick Check` for learners.
- Add no runtime dependency.
- Keep mobile and desktop footer layouts stable within their responsive breakpoint and account for mobile safe-area insets.

---

## File Structure

### Data and controllers

- Create `database/migrations/2026_08_27_000001_normalize_interactive_checkpoint_metadata.php` for existing checkpoint metadata.
- Modify `app/Http/Controllers/Instructor/TopicController.php` for neutral checkpoint metadata and checkpoint-specific validation.
- Modify `app/Http/Controllers/Learner/InteractiveCheckpointController.php` for terminal correct state, correct-only explanations, and checkpoint-only progress.
- Modify `app/Http/Controllers/Learner/LessonController.php` for optional checkpoint resolution separate from required progress.

### Focused JavaScript units

- Create `resources/js/word-bank.js` for indexed inline blank selection.
- Create `resources/js/interactive-checkpoint.js` for checkpoint state and Lesson-level continuation coordination.
- Modify `resources/js/app.js` to register the factories.

### Views

- Modify `resources/views/instructor/topics/create.blade.php` and `edit-checkpoint.blade.php`.
- Modify `resources/views/instructor/quizzes/partials/question-fields.blade.php`.
- Modify `resources/views/instructor/lessons/show.blade.php`.
- Modify `resources/views/learner/lessons/partials/topic-page.blade.php` and `interactive-checkpoint.blade.php`.
- Create `resources/views/learner/lessons/partials/lesson-forward-action.blade.php` by extracting the existing contextual footer action block.
- Modify `resources/views/learner/lessons/show.blade.php` and `layouts/learner-fullscreen.blade.php`.
- Modify both Quiz learner views only where needed to reuse Word Bank state.

### Tests

- Extend the existing checkpoint authoring, flow, isolation, Quiz-regression, evaluator, and shared-authoring tests.
- Create `tests/Feature/Learner/InteractiveCheckpointRenderingTest.php`.
- Create `tests/JavaScript/word-bank.test.mjs` and `interactive-checkpoint.test.mjs`.

---

### Task 1: Neutral Checkpoint Metadata and Authoring Controls

**Files:**
- Create: `database/migrations/2026_08_27_000001_normalize_interactive_checkpoint_metadata.php`
- Modify: `app/Http/Controllers/Instructor/TopicController.php`
- Modify: `resources/views/instructor/topics/create.blade.php`
- Modify: `resources/views/instructor/topics/edit-checkpoint.blade.php`
- Test: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php`

**Interfaces:**
- Consumes: `TopicController::storeCheckpoint(Request $request, Lesson $lesson)` and `updateBetweenTopicCheckpoint(Request $request, LessonTopic $topic)`.
- Produces: every standalone checkpoint has neutral metadata; ordinary Topic requests retain current validation.

- [ ] **Step 1: Add failing authoring tests**

Add these methods to `InteractiveCheckpointAuthoringTest`:

```php
public function test_between_topic_checkpoint_needs_no_duration_and_forces_neutral_metadata(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');

    $this->actingAs($instructor)
        ->post(route('instructor.topics.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Optional check',
            'type' => 'interactive_checkpoint',
            'checkpoint_placement' => 'between_topics',
            'is_prerequisite' => 1,
            'question_text' => 'Consent is freely given.',
            'question_type' => 'true_false',
            'options' => ['True', 'False'],
            'correct_options' => [0],
        ])
        ->assertRedirect(route('instructor.lessons.show', $lesson));

    $topic = $lesson->topics()->where('type', 'interactive_checkpoint')->firstOrFail();

    $this->assertSame(0, $topic->duration);
    $this->assertFalse($topic->is_prerequisite);
}

public function test_between_topic_checkpoint_edit_needs_no_duration_and_repairs_metadata(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'interactive_checkpoint',
        'duration' => 12,
        'is_prerequisite' => true,
        'interactive_config' => ['placement' => 'between_topics'],
    ]);
    QuizQuestion::create([
        'checkpoint_topic_id' => $topic->id,
        'question_text' => 'Name the concept.',
        'question_type' => 'identification',
        'acceptable_answers' => 'Consent',
        'points' => 1,
        'order' => 1,
    ]);

    $this->actingAs($instructor)
        ->put(route('instructor.topics.update', $topic), [
            'title' => 'Updated check',
            'question_text' => 'Name the concept.',
            'question_type' => 'identification',
            'acceptable_answers' => ['Consent'],
        ])
        ->assertRedirect(route('instructor.lessons.show', $lesson));

    $topic->refresh();
    $this->assertSame(0, $topic->duration);
    $this->assertFalse($topic->is_prerequisite);
}
```

Update the create-page test to assert that the duration/prerequisite wrapper is controlled by `type !== 'interactive_checkpoint'`.

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
```

Expected: FAIL because edit requires duration and creation defaults to one minute.

- [ ] **Step 3: Add the normalization migration**

Create the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_topics')
            ->where('type', 'interactive_checkpoint')
            ->update([
                'duration' => 0,
                'is_prerequisite' => false,
            ]);
    }

    public function down(): void
    {
        // Historical values cannot be reconstructed safely.
    }
};
```

- [ ] **Step 4: Persist neutral values**

Remove `duration` from checkpoint validation. Create and update standalone checkpoint Topics with:

```php
[
    'title' => $topicData['title'],
    'type' => 'interactive_checkpoint',
    'duration' => 0,
    'is_prerequisite' => false,
    'interactive_config' => ['placement' => 'between_topics'],
]
```

Use `$lesson->topics()->instructional()->sum('duration')` for Lesson duration recalculations in checkpoint create/edit and in the Topic update/destroy paths touched by this work. Keep ordinary Topic request validation unchanged.

- [ ] **Step 5: Make metadata controls unavailable for checkpoint authoring**

Wrap the existing duration and prerequisite controls in a `data-topic-metadata` container shown only when `type !== 'interactive_checkpoint'`, and disable its fieldset for checkpoints. Remove both controls entirely from `edit-checkpoint.blade.php`.

- [ ] **Step 6: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
vendor/bin/pint --dirty
git add database/migrations/2026_08_27_000001_normalize_interactive_checkpoint_metadata.php app/Http/Controllers/Instructor/TopicController.php resources/views/instructor/topics/create.blade.php resources/views/instructor/topics/edit-checkpoint.blade.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
git commit -m "fix: make checkpoints optional metadata"
```

Expected: targeted tests PASS and only checkpoint Topics receive neutral values.

---

### Task 2: Safe Human-Readable Checkpoint Editing

**Files:**
- Modify: `resources/js/question-authoring.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/instructor/topics/edit-checkpoint.blade.php`
- Modify: `resources/views/instructor/quizzes/partials/question-fields.blade.php`
- Test: `tests/JavaScript/question-authoring.test.mjs`
- Test: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`
- Test: `tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php`

**Interfaces:**
- Produces: `questionTextForEditor(html: string, type: string): string`.
- Consumes: the existing `RICH_TYPES` and `stripQuestionHtml` behavior.

- [ ] **Step 1: Add failing conversion tests**

Add to `question-authoring.test.mjs`:

```javascript
import {
    createQuestionAuthoring,
    questionTextForEditor,
    stripQuestionHtml,
} from '../../resources/js/question-authoring.js';

test('editor prefill preserves rich markup and cleans plain checkpoint text', () => {
    const html = '<p>HTML&nbsp;<strong>creates</strong> _____.</p><br><p>Choose carefully.</p>';

    assert.equal(questionTextForEditor(html, 'multiple_choice'), html);
    assert.equal(
        questionTextForEditor(html, 'fill_blank_select'),
        'HTML creates _____.\nChoose carefully.',
    );
});
```

Add this feature test:

```php
public function test_checkpoint_edit_serializes_question_text_for_the_correct_editor(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'interactive_checkpoint',
        'duration' => 0,
        'interactive_config' => ['placement' => 'between_topics'],
    ]);
    QuizQuestion::create([
        'checkpoint_topic_id' => $topic->id,
        'question_text' => '<p>HTML <strong>creates</strong> _____.</p>',
        'question_type' => 'fill_blank_select',
        'acceptable_answers' => 'structure',
        'word_bank' => ['structure', 'style'],
        'points' => 1,
        'order' => 1,
    ]);

    $this->actingAs($instructor)
        ->get(route('instructor.topics.edit', $topic))
        ->assertOk()
        ->assertSee('questionTextForEditor', false)
        ->assertSee('HTML <strong>creates</strong> _____.', false);
}
```

- [ ] **Step 2: Run tests and verify failure**

```powershell
node --test tests/JavaScript/question-authoring.test.mjs
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php
```

Expected: FAIL because `questionTextForEditor` is missing.

- [ ] **Step 3: Implement the conversion boundary**

Add:

```javascript
export function questionTextForEditor(html = '', type = 'multiple_choice') {
    return RICH_TYPES.includes(type) ? String(html) : stripQuestionHtml(html);
}
```

Initialize `createQuestionAuthoring` with:

```javascript
questionText: questionTextForEditor(config.questionText || '', type),
```

Expose the helper from `app.js` and initialize the edit page with safely JSON-encoded `@js` values for type, question text, explanation, options, acceptable answers, Word Bank, case sensitivity, and image URL.

- [ ] **Step 4: Correct explanation guidance**

Change the shared help copy to: `Shown after a correct answer. It is hidden after an incorrect answer or skip.`

- [ ] **Step 5: Verify and commit**

```powershell
node --test tests/JavaScript/question-authoring.test.mjs
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php
pnpm.cmd build
git add resources/js/question-authoring.js resources/js/app.js resources/views/instructor/topics/edit-checkpoint.blade.php resources/views/instructor/quizzes/partials/question-fields.blade.php tests/JavaScript/question-authoring.test.mjs tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php
git commit -m "fix: render checkpoint editor content safely"
```

Expected: JavaScript and PHP tests PASS and Vite builds.

---

### Task 3: Correct Feedback and Durable Checkpoint Progress

**Files:**
- Modify: `app/Http/Controllers/Learner/InteractiveCheckpointController.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php`

**Interfaces:**
- Consumes: `QuestionEvaluator::evaluate(QuizQuestion $question, mixed $selectedAnswer): array`.
- Produces: checkpoint JSON with explanation only for correct state; a correct state cannot be downgraded.

- [ ] **Step 1: Add failing endpoint tests**

Add tests that assert:

```php
$response = $this->actingAs($learner)
    ->postJson(route('learner.checkpoints.submit', $question), ['answer' => $wrongOption->id])
    ->assertOk()
    ->assertJsonPath('status', 'incorrect')
    ->assertJsonPath('is_correct', false)
    ->assertJsonPath('explanation', null);

$this->assertDatabaseHas('interactive_checkpoint_progress', [
    'user_id' => $learner->id,
    'quiz_question_id' => $question->id,
    'status' => 'incorrect',
    'completed_at' => null,
]);
```

Add these two methods:

```php
public function test_correct_checkpoint_cannot_be_downgraded(): void
{
    [$learner, $question] = $this->checkpointFixture();
    $correct = $question->options()->where('is_correct', true)->firstOrFail();
    $wrong = $question->options()->where('is_correct', false)->firstOrFail();

    $this->actingAs($learner)
        ->postJson(route('learner.checkpoints.submit', $question), ['answer' => $correct->id])
        ->assertJsonPath('status', 'correct');
    $this->postJson(route('learner.checkpoints.skip', $question))
        ->assertJsonPath('status', 'correct');
    $this->postJson(route('learner.checkpoints.submit', $question), ['answer' => $wrong->id])
        ->assertJsonPath('status', 'correct');

    $this->assertDatabaseHas('interactive_checkpoint_progress', [
        'user_id' => $learner->id,
        'quiz_question_id' => $question->id,
        'status' => 'correct',
        'attempt_count' => 1,
    ]);
}

public function test_between_topic_checkpoint_uses_only_checkpoint_progress(): void
{
    [$learner, $question] = $this->checkpointFixture();

    $this->actingAs($learner)
        ->postJson(route('learner.checkpoints.skip', $question))
        ->assertOk();

    $this->assertDatabaseMissing('lesson_topic_progress', [
        'user_id' => $learner->id,
        'lesson_topic_id' => $question->checkpoint_topic_id,
    ]);
}
```

Update the existing twelve-case provider so incorrect responses expect a null explanation.

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
```

Expected: FAIL because incorrect responses expose explanation, all answers set completion time, and skip writes Topic progress.

- [ ] **Step 3: Implement terminal correct state**

Before evaluation, load `InteractiveCheckpointProgress::firstOrNew`. If an existing row is correct, return:

```php
return response()->json([
    'status' => 'correct',
    'is_correct' => true,
    'result' => $progress->latest_answer,
    'explanation' => $question->explanation,
]);
```

Persist evaluated state with:

```php
$progress->fill([
    'lesson_topic_id' => $question->checkpoint_topic_id,
    'checkpoint_block_uuid' => $question->checkpoint_block_uuid,
    'status' => $status,
    'latest_answer' => $result,
    'is_correct' => $result['is_correct'],
    'attempt_count' => ((int) $progress->attempt_count) + 1,
    'answered_at' => now(),
    'skipped_at' => null,
    'completed_at' => $result['is_correct'] ? now() : null,
])->save();
```

Return `$result['is_correct'] ? $question->explanation : null`. In `skip`, preserve an existing correct state; otherwise persist skipped state. Remove `LessonTopicProgress`, `markBetweenTopicCheckpointComplete`, and its calls.

- [ ] **Step 4: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php
vendor/bin/pint --dirty
git add app/Http/Controllers/Learner/InteractiveCheckpointController.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
git commit -m "fix: separate checkpoint feedback states"
```

Expected: endpoint, evaluator, and formal Quiz isolation tests PASS.

---

### Task 4: Shared Word Bank and Testable Checkpoint State

**Files:**
- Create: `resources/js/word-bank.js`
- Create: `resources/js/interactive-checkpoint.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`
- Modify: `resources/views/quizzes/take.blade.php`
- Modify: `resources/views/learner/lessons/partials/quiz-page.blade.php`
- Create: `tests/JavaScript/word-bank.test.mjs`
- Create: `tests/JavaScript/interactive-checkpoint.test.mjs`

**Interfaces:**
- Produces: `createWordBank(words, blankCount, onChange)`.
- Produces: `createInteractiveCheckpoint(config, request)`.
- Produces: `createCheckpointCoordinator()`.
- Keeps formal Quiz input names unchanged.

- [ ] **Step 1: Write failing Word Bank tests**

Create `word-bank.test.mjs`:

```javascript
import test from 'node:test';
import assert from 'node:assert/strict';
import { createWordBank } from '../../resources/js/word-bank.js';

test('fills the first empty blank and returns a removed word', () => {
    const bank = createWordBank(['HTML', 'CSS', 'JavaScript'], 2);

    bank.selectWord(1);
    bank.selectWord(0);
    assert.deepEqual(bank.answers(), ['CSS', 'HTML']);

    bank.removeWord(0);
    assert.deepEqual(bank.answers(), ['', 'HTML']);
    assert.equal(bank.isUsed(1), false);
});

test('tracks duplicate display values by index', () => {
    const bank = createWordBank(['same', 'same'], 2);

    bank.selectWord(0);
    bank.selectWord(1);

    assert.deepEqual(bank.selectedIndices, [0, 1]);
    assert.deepEqual(bank.answers(), ['same', 'same']);
});
```

- [ ] **Step 2: Write failing state tests**

Create `interactive-checkpoint.test.mjs` covering these exact transitions:

```javascript
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    createCheckpointCoordinator,
    createInteractiveCheckpoint,
} from '../../resources/js/interactive-checkpoint.js';

test('incorrect hides explanation and exposes retry or skip', async () => {
    const request = async () => ({
        ok: true,
        json: async () => ({ status: 'incorrect', is_correct: false, explanation: null }),
    });
    const checkpoint = createInteractiveCheckpoint({
        type: 'identification',
        submitUrl: '/submit',
        skipUrl: '/skip',
        csrf: 'token',
    }, request);
    checkpoint.answer = 'Pressure';

    await checkpoint.submit();

    assert.equal(checkpoint.state, 'incorrect');
    assert.equal(checkpoint.explanation, null);
    assert.equal(checkpoint.showSkip(), true);
    assert.equal(checkpoint.showContinue(), false);
});
```

Add these state assertions to the same file:

```javascript
test('correct removes skip and exposes continuation', async () => {
    const request = async () => ({
        ok: true,
        json: async () => ({ status: 'correct', is_correct: true, explanation: 'Freely given.' }),
    });
    const checkpoint = createInteractiveCheckpoint({ type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token' }, request);
    checkpoint.answer = 'Consent';

    await checkpoint.submit();

    assert.equal(checkpoint.state, 'correct');
    assert.equal(checkpoint.showSkip(), false);
    assert.equal(checkpoint.showContinue(), true);
});

test('request failure retains the answer and exposes an error', async () => {
    const request = async () => ({
        ok: false,
        json: async () => ({ message: 'Unable to save the checkpoint.' }),
    });
    const checkpoint = createInteractiveCheckpoint({ type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token' }, request);
    checkpoint.answer = 'Consent';

    await checkpoint.submit();

    assert.equal(checkpoint.state, 'error');
    assert.equal(checkpoint.answer, 'Consent');
    assert.equal(checkpoint.error, 'Unable to save the checkpoint.');
});

test('retry clears the answer and coordinator releases footer ownership', () => {
    const checkpoint = createInteractiveCheckpoint({ type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token' });
    checkpoint.answer = 'Pressure';
    checkpoint.state = 'incorrect';
    checkpoint.retry();
    assert.equal(checkpoint.state, 'ready');
    assert.equal(checkpoint.answer, '');

    const coordinator = createCheckpointCoordinator();
    coordinator.activate(17);
    assert.equal(coordinator.footerForwardVisible(), false);
    coordinator.release(17);
    assert.equal(coordinator.footerForwardVisible(), true);
});
```

- [ ] **Step 3: Run tests and verify failure**

```powershell
node --test tests/JavaScript/word-bank.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
```

Expected: FAIL because both modules are missing.

- [ ] **Step 4: Implement indexed Word Bank state**

Create `word-bank.js`:

```javascript
export function createWordBank(words = [], blankCount = 1, onChange = () => {}) {
    return {
        words: Array.from(words, String),
        selectedIndices: Array(Math.max(1, blankCount)).fill(null),
        selectWord(wordIndex) {
            if (this.isUsed(wordIndex)) return;
            const blankIndex = this.selectedIndices.findIndex((value) => value === null);
            if (blankIndex === -1) return;
            this.selectedIndices[blankIndex] = wordIndex;
            onChange(this.answers());
        },
        removeWord(blankIndex) {
            if (blankIndex < 0 || blankIndex >= this.selectedIndices.length) return;
            this.selectedIndices[blankIndex] = null;
            onChange(this.answers());
        },
        isUsed(wordIndex) {
            return this.selectedIndices.includes(wordIndex);
        },
        answers() {
            return this.selectedIndices.map((index) => index === null ? '' : this.words[index]);
        },
        complete() {
            return this.selectedIndices.every((index) => index !== null);
        },
    };
}
```

- [ ] **Step 5: Implement checkpoint state**

Create `interactive-checkpoint.js`:

```javascript
export function emptyCheckpointAnswer(type, blankCount = 1) {
    if (type === 'multiple_select') return [];
    if (['fill_blank_text', 'fill_blank_select'].includes(type)) {
        return Array(Math.max(1, blankCount)).fill('');
    }
    return '';
}

async function readResponse(response) {
    const data = await response.json();
    if (!response.ok) {
        throw new Error(data.message || 'Unable to save the checkpoint.');
    }
    return data;
}

export function createInteractiveCheckpoint(config = {}, request = globalThis.fetch?.bind(globalThis)) {
    const initialStatus = ['correct', 'incorrect', 'skipped'].includes(config.initialStatus)
        ? config.initialStatus
        : 'ready';

    return {
        answer: emptyCheckpointAnswer(config.type, config.blankCount),
        state: initialStatus,
        isCorrect: initialStatus === 'correct' ? true : null,
        explanation: initialStatus === 'correct' ? config.initialExplanation || null : null,
        error: '',
        showSkip() {
            return ['ready', 'incorrect', 'error'].includes(this.state);
        },
        showContinue() {
            return ['correct', 'skipped'].includes(this.state);
        },
        retry() {
            this.answer = emptyCheckpointAnswer(config.type, config.blankCount);
            this.state = 'ready';
            this.isCorrect = null;
            this.explanation = null;
            this.error = '';
        },
        async submit() {
            this.state = 'submitting';
            this.error = '';
            try {
                const response = await request(config.submitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ answer: this.answer }),
                });
                const data = await readResponse(response);
                this.state = data.status;
                this.isCorrect = data.is_correct;
                this.explanation = data.status === 'correct' ? data.explanation : null;
            } catch (error) {
                this.state = 'error';
                this.error = error.message || 'Unable to save the checkpoint.';
            }
        },
        async skip() {
            this.state = 'submitting';
            this.error = '';
            try {
                const response = await request(config.skipUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrf,
                        Accept: 'application/json',
                    },
                });
                const data = await readResponse(response);
                this.state = data.status;
                this.isCorrect = data.is_correct;
                this.explanation = data.status === 'correct' ? data.explanation : null;
            } catch (error) {
                this.state = 'error';
                this.error = error.message || 'Unable to skip the checkpoint.';
            }
        },
        continueLearning() {
            this.$dispatch?.('checkpoint-continued', { questionId: config.questionId });
        },
    };
}

export function createCheckpointCoordinator() {
    return {
        activeQuestionId: null,
        activate(questionId) {
            this.activeQuestionId = Number(questionId);
        },
        release(questionId) {
            if (this.activeQuestionId === Number(questionId)) this.activeQuestionId = null;
        },
        footerForwardVisible() {
            return this.activeQuestionId === null;
        },
    };
}
```

Register all factories from `app.js`:

```javascript
window.interactiveCheckpoint = createInteractiveCheckpoint;
window.checkpointCoordinator = createCheckpointCoordinator;
window.wordBankQuestion = createWordBank;
```

- [ ] **Step 6: Replace inline state and reuse Word Bank**

Remove the inline checkpoint factory. Pass persisted status and correct-only explanation through `@js`. Render Word Bank blanks inside the question sentence, and use the shared indexed state in both Quiz views while retaining hidden names shaped as `answers[question_id][blank_index]`.

- [ ] **Step 7: Verify and commit**

```powershell
node --test tests/JavaScript/question-authoring.test.mjs tests/JavaScript/word-bank.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
pnpm.cmd build
git add resources/js/word-bank.js resources/js/interactive-checkpoint.js resources/js/app.js resources/views/learner/lessons/partials/interactive-checkpoint.blade.php resources/views/quizzes/take.blade.php resources/views/learner/lessons/partials/quiz-page.blade.php tests/JavaScript/word-bank.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
git commit -m "feat: unify checkpoint word bank interaction"
```

Expected: JavaScript, checkpoint, and formal Quiz regressions PASS.

---

### Task 5: Compose Checkpoints with Canonical Topic Content

**Files:**
- Modify: `resources/views/learner/lessons/partials/topic-page.blade.php`
- Create: `tests/Feature/Learner/InteractiveCheckpointRenderingTest.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`

**Interfaces:**
- Consumes: `LessonTopic::$content_blocks` and eager-loaded `checkpointQuestions`.
- Produces: canonical Topic content plus ordered, valid checkpoint cards.
- Test helper: `insideCheckpointFixture(string $type, array $attributes = []): array{User, LessonTopic, QuizQuestion}` creates a published Module/Lesson and approved enrollment.
- Test helper: `appendCheckpoint(LessonTopic $topic, string $uuid, string $text): QuizQuestion` appends a matching block and question.
- Test helper: `betweenCheckpointFixture(): array{User, LessonTopic, QuizQuestion}` creates a standalone checkpoint learning item.

- [ ] **Step 1: Create failing rendering tests**

Create a published/enrolled fixture and tests that compare HTML positions:

```php
$instructions = strpos($html, 'Watch before answering.');
$video = strpos($html, 'youtube.com/embed');
$checkpoint = strpos($html, $question->question_text);

$this->assertNotFalse($instructions);
$this->assertNotFalse($video);
$this->assertNotFalse($checkpoint);
$this->assertTrue($instructions < $video && $video < $checkpoint);
```

Add these concrete cases using the helpers defined above:

```php
public function test_text_topic_renders_body_images_and_ordered_checkpoints_once(): void
{
    [$learner, $topic, $first] = $this->insideCheckpointFixture('text', [
        'text_content' => '<p>Canonical text body</p>',
        'image_attachments' => [[
            'path' => 'lesson-images/example.jpg',
            'caption' => 'Example caption',
        ]],
    ]);
    $second = $this->appendCheckpoint($topic, 'second-block', 'Second checkpoint');

    $html = $this->actingAs($learner)
        ->get(route('learner.lessons.show', $topic->lesson))
        ->assertOk()
        ->getContent();

    $this->assertSame(1, substr_count($html, 'Canonical text body'));
    $this->assertStringContainsString('Example caption', $html);
    $this->assertLessThan(strpos($html, $second->question_text), strpos($html, $first->question_text));
}

public function test_invalid_checkpoint_reference_does_not_hide_topic_body(): void
{
    [$learner, $topic] = $this->insideCheckpointFixture('text', [
        'text_content' => '<p>Content remains visible</p>',
    ]);
    $topic->update(['content_blocks' => [[
        'type' => 'checkpoint',
        'uuid' => 'missing-block',
        'question_id' => 999999,
    ]]]);

    $this->actingAs($learner)
        ->get(route('learner.lessons.show', $topic->lesson))
        ->assertOk()
        ->assertSee('Content remains visible', false)
        ->assertDontSee('Quick Check');
}
```

Add these methods:

```php
public function test_worksheet_topic_renders_instructions_file_and_checkpoint_in_order(): void
{
    [$learner, $topic, $question] = $this->insideCheckpointFixture('worksheet', [
        'text_content' => '<p>Download and complete the worksheet.</p>',
        'file_path' => 'worksheets/activity.pdf',
    ]);

    $html = $this->actingAs($learner)
        ->get(route('learner.lessons.show', $topic->lesson))
        ->assertOk()
        ->getContent();

    $this->assertLessThan(strpos($html, 'activity.pdf'), strpos($html, 'Download and complete the worksheet.'));
    $this->assertLessThan(strpos($html, $question->question_text), strpos($html, 'activity.pdf'));
}

public function test_between_topic_checkpoint_renders_as_its_own_learning_item(): void
{
    [$learner, $topic, $question] = $this->betweenCheckpointFixture();

    $this->actingAs($learner)
        ->get(route('learner.lessons.show', ['lesson' => $topic->lesson, 'topic' => 0]))
        ->assertOk()
        ->assertSee('Quick Check')
        ->assertSee($question->question_text)
        ->assertSee('Skip for now');
}
```

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php
```

Expected: video and other canonical composition cases FAIL because `content_blocks` currently replaces the Topic-type renderer.

- [ ] **Step 3: Reorder the Topic renderer**

Keep standalone checkpoint rows as their own branch. For every ordinary Topic:

1. Render its established video, text, worksheet, or interactive body.
2. Resolve checkpoint blocks from `content_blocks` in array order.
3. Match block question ID and block UUID against the eager-loaded question.
4. Include only valid matches.
5. For text Topics with explicit `rich_text` blocks, render those blocks once instead of duplicating `text_content` while retaining image/gallery behavior.

Use this validity check:

```blade
@php
    $question = $currentTopic->checkpointQuestions
        ->firstWhere('id', (int) ($block['question_id'] ?? 0));
    $validBlock = $question
        && $question->checkpoint_block_uuid === ($block['uuid'] ?? null);
@endphp
@if($validBlock)
    @include('learner.lessons.partials.interactive-checkpoint', ['question' => $question])
@endif
```

Keep existing video player, caption, gallery, worksheet, PDF, and interactive markup intact. Do not introduce a generic renderer service.

- [ ] **Step 4: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
git add resources/views/learner/lessons/partials/topic-page.blade.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php
git commit -m "fix: compose checkpoints with topic content"
```

Expected: every Topic type retains its content and checkpoint order.

---

### Task 6: Optional Progress Resolution and Stable Footer

**Files:**
- Modify: `app/Http/Controllers/Learner/LessonController.php`
- Modify: `resources/views/learner/lessons/show.blade.php`
- Modify: `resources/views/layouts/learner-fullscreen.blade.php`
- Modify: `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`
- Create: `resources/views/learner/lessons/partials/lesson-forward-action.blade.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointRenderingTest.php`
- Modify: `tests/JavaScript/interactive-checkpoint.test.mjs`

**Interfaces:**
- Consumes: correct/skipped `InteractiveCheckpointProgress` and `createCheckpointCoordinator()`.
- Produces: `$resolvedCheckpointTopicIds` and a single stable footer region.
- Test helper: `orderedCheckpointFixture(): array{User, Lesson, LessonTopic, QuizQuestion, LessonTopic}` creates ordered `text`, checkpoint, and `text` items.

- [ ] **Step 1: Add failing navigation tests**

Add these methods:

```php
public function test_skipped_checkpoint_does_not_block_default_navigation_or_required_progress(): void
{
    [$learner, $lesson, $checkpoint, $question, $nextTopic] = $this->orderedCheckpointFixture();
    InteractiveCheckpointProgress::create([
        'user_id' => $learner->id,
        'lesson_topic_id' => $checkpoint->id,
        'quiz_question_id' => $question->id,
        'status' => 'skipped',
        'skipped_at' => now(),
        'completed_at' => now(),
    ]);

    $this->actingAs($learner)
        ->get(route('learner.lessons.show', $lesson))
        ->assertOk()
        ->assertViewHas('currentTopic', fn (LessonTopic $topic) => $topic->is($nextTopic));

    $this->assertSame(0, $lesson->getTopicCompletionPercentage($learner->id));
}

public function test_lesson_footer_has_one_coordinated_action_region(): void
{
    [$learner, $topic] = $this->insideCheckpointFixture('text', [
        'text_content' => '<p>Topic body</p>',
    ]);

    $html = $this->actingAs($learner)
        ->get(route('learner.lessons.show', $topic->lesson))
        ->assertOk()
        ->assertSee('checkpointCoordinator()', false)
        ->getContent();

    $this->assertSame(1, substr_count($html, 'data-lesson-footer'));
}
```

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php
```

Expected: FAIL because skipped checkpoint rows are unresolved without `LessonTopicProgress` and the footer lacks coordinator ownership.

- [ ] **Step 3: Derive optional resolution separately**

After loading checkpoint progress, add:

```php
$resolvedCheckpointTopicIds = $checkpointProgress
    ->filter(fn (InteractiveCheckpointProgress $progress) => in_array($progress->status, ['correct', 'skipped'], true))
    ->filter(fn (InteractiveCheckpointProgress $progress) => $progress->checkpoint_block_uuid === null)
    ->pluck('lesson_topic_id')
    ->map(fn ($id) => (int) $id)
    ->all();

$resolvedLearningItemIds = array_values(array_unique([
    ...array_map('intval', $completedTopicIds),
    ...$resolvedCheckpointTopicIds,
]));
```

Use `$resolvedLearningItemIds` only for current ordered-item selection and optional sidebar state. Continue using `$completedTopicIds` plus `instructional()` for locks, completion, Quiz eligibility, and certification. Pass `$resolvedCheckpointTopicIds` to the view.

Reject direct completion of a checkpoint-only Topic without awarding Topic or Lesson points; checkpoint submit/skip remains its progress interface.

- [ ] **Step 4: Stabilize footer structure**

Make the middle Lesson region the only scroll owner. Give the footer one row:

```blade
<div data-lesson-footer
     class="flex-shrink-0 border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
     style="height: calc(4.5rem + env(safe-area-inset-bottom)); padding-bottom: env(safe-area-inset-bottom);">
    <div class="flex h-[4.5rem] items-center justify-between gap-3 px-3 sm:px-6">
        <div x-show="footerForwardVisible()" x-cloak>
            @include('learner.lessons.partials.lesson-forward-action')
        </div>
    </div>
</div>
```

Move the variable-height progress dots above the scrollable Topic card or into its header. A between-topic checkpoint owns forwarding immediately. An inside-topic checkpoint owns forwarding after interaction and releases it from checkpoint Continue.

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php
node --test tests/JavaScript/interactive-checkpoint.test.mjs
pnpm.cmd build
vendor/bin/pint --dirty
git add app/Http/Controllers/Learner/LessonController.php resources/views/learner/lessons/show.blade.php resources/views/layouts/learner-fullscreen.blade.php resources/views/learner/lessons/partials/interactive-checkpoint.blade.php resources/views/learner/lessons/partials/lesson-forward-action.blade.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/JavaScript/interactive-checkpoint.test.mjs
git commit -m "fix: stabilize optional checkpoint navigation"
```

Expected: required progress stays isolated, footer height is state-independent, and one forward action remains visible.

---

### Task 7: Labels and Accessible Topic Removal

**Files:**
- Modify: `resources/views/instructor/lessons/show.blade.php`
- Modify: `resources/views/learner/lessons/show.blade.php`
- Test: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`
- Test: `tests/Feature/Learner/InteractiveCheckpointRenderingTest.php`

**Interfaces:**
- Produces: consistent labels and one modal-confirmed Topic DELETE form.
- Consumes: existing Topic destroy route and read-only ownership flags.

- [ ] **Step 1: Add failing view tests**

Add to `InteractiveCheckpointAuthoringTest`:

```php
public function test_lesson_details_uses_accessible_topic_removal_modal(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'title' => 'Topic to remove',
    ]);

    $this->actingAs($instructor)
        ->get(route('instructor.lessons.show', $lesson))
        ->assertOk()
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('Remove Topic')
        ->assertSee('associated inside-topic checkpoints')
        ->assertDontSee("confirm('Delete this topic?')", false);
}
```

Add to `InteractiveCheckpointRenderingTest`:

```php
public function test_checkpoint_sidebar_uses_optional_quick_check_metadata(): void
{
    [$learner, $topic] = $this->betweenCheckpointFixture();

    $html = $this->actingAs($learner)
        ->get(route('learner.lessons.show', $topic->lesson))
        ->assertOk()
        ->assertSee('QUICK CHECK · Optional', false)
        ->getContent();

    $checkpointRow = substr($html, strpos($html, $topic->title), 500);
    $this->assertStringNotContainsString('0m', $checkpointRow);
    $this->assertStringNotContainsString('Required', $checkpointRow);
}
```

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php
```

Expected: FAIL because deletion uses native confirmation and checkpoint metadata is generic.

- [ ] **Step 3: Implement the modal**

Use one Alpine modal state on the Lesson details page. Each enabled delete button passes the Topic title and destroy URL. Render one dialog with Cancel and DELETE form actions:

```blade
<div x-show="removeTopicOpen" x-cloak @keydown.escape.window="closeRemoveTopic()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
     role="dialog" aria-modal="true"
     aria-labelledby="remove-topic-title" aria-describedby="remove-topic-description">
    <div @click.outside="closeRemoveTopic()" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h2 id="remove-topic-title" class="text-lg font-bold text-gray-900">Remove Topic</h2>
        <p id="remove-topic-description" class="mt-2 text-sm text-gray-600">
            Remove <strong x-text="removeTopicTitle"></strong>? Its associated inside-topic checkpoints will also be removed. This cannot be undone.
        </p>
        <form :action="removeTopicAction" method="POST" class="mt-6 flex justify-end gap-3">
            @csrf
            @method('DELETE')
            <button x-ref="removeTopicCancel" type="button" @click="closeRemoveTopic()">Cancel</button>
            <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 font-semibold text-white">Remove Topic</button>
        </form>
    </div>
</div>
```

Focus Cancel on open and restore focus on close. Preserve disabled removal for read-only admins.

- [ ] **Step 4: Standardize labels**

Render checkpoint sidebar metadata through a dedicated checkpoint branch: `QUICK CHECK · Optional`. Keep `Interactive Checkpoint`, `Inside Topic`, and `Between Topics` in authoring.

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php
pnpm.cmd build
git add resources/views/instructor/lessons/show.blade.php resources/views/learner/lessons/show.blade.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php
git commit -m "fix: clarify checkpoint labels and removal"
```

Expected: accessible modal and label tests PASS.

---

### Task 8: Full Regression and End-to-End Verification

**Files:**
- Verify: all files changed by Tasks 1–7
- Verify against: `docs/superpowers/specs/2026-08-27-interactive-checkpoint-qa-refinement-design.md`

**Interfaces:**
- Consumes: complete authoring, learner, rendering, and navigation workflow.
- Produces: a verified implementation with no failing targeted, full-suite, build, or responsive-browser checks.

- [ ] **Step 1: Run checkpoint and shared-question tests**

```powershell
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php tests/Feature/Learner/InteractiveCheckpointSchemaTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php
```

Expected: all listed tests PASS.

- [ ] **Step 2: Run JavaScript tests**

```powershell
node --test tests/JavaScript/question-authoring.test.mjs tests/JavaScript/word-bank.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
```

Expected: all JavaScript tests PASS.

- [ ] **Step 3: Run full regression, build, and formatting**

```powershell
php artisan test
pnpm.cmd build
vendor/bin/pint --test
```

Expected: zero PHPUnit failures, successful Vite build, and no Pint violations.

- [ ] **Step 4: Exercise the browser workflow**

Verify create/edit/publish as an instructor, then as a learner verify:

1. Video instructions, video, and two inside checkpoints render in order.
2. A between-topic checkpoint remains optional.
3. Rich and blank question editors are human-readable.
4. Incorrect answer shows no explanation or Continue.
5. Retry then correct shows explanation, no Skip, and one Continue.
6. Correct revisit is read-only.
7. Skipped revisit can be retried.
8. Word Bank selections fill and clear inline blanks.
9. Required Topic and Lesson progress ignore checkpoints.
10. Formal Quiz scoring and shields retain existing behavior.
11. Topic removal uses the named warning modal.

Repeat learner checks on mobile, tablet, and desktop at approximately 375, 768, and 1440 pixels wide. Confirm stable footer height, safe-area spacing, no content overlap, keyboard access, and one forward action.

- [ ] **Step 5: Inspect final workspace state**

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors. Preserve unrelated pre-existing build artifacts, uploaded media, and untracked documents.

- [ ] **Step 6: Commit verification corrections only when needed**

If verification changed code or tests, stage only those exact files and run:

```powershell
git commit -m "test: verify checkpoint QA workflow"
```

If no correction was needed, finish without an empty commit.
