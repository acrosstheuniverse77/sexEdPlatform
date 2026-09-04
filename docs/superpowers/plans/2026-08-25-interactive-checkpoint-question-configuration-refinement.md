# Interactive Checkpoint Question Configuration Refinement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Interactive Checkpoint authoring and editing use the formal Quiz system's complete six-type question configuration, validation, guidance, and platform-aligned UI/UX while preserving checkpoint optionality and Quiz behavior.

**Architecture:** Promote the existing Quiz question fields partial into the single Blade/Alpine authoring core, and move the effective type-specific validation and serialization contract into `QuestionAuthoringService`. Quiz and checkpoint pages remain thin wrappers around that core; `TopicController` continues to own checkpoint placement while the existing service owns question persistence. Existing `QuestionEvaluator` remains the only learner answer-checking implementation.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, Alpine.js 3, Tailwind CSS 3/4 compatibility layer, TinyMCE 8, Vite 7, PHPUnit, Node.js built-in test runner, Puppeteer already present for manual browser verification only.

## Global Constraints

- The modern Quiz Add Question page is the behavioral and visual source of truth.
- Support exactly these six slugs: `multiple_choice`, `true_false`, `identification`, `fill_blank_text`, `fill_blank_select`, and `multiple_select`.
- Multiple Choice and Multiple Select require at least two non-empty options and have no maximum option count.
- True/False always persists exactly `True` and `False` and exactly one correct answer.
- Fill Blank Text and Word Bank answer counts must equal the number of `_____` markers.
- A Word Bank has 1-10 normalized entries, and every ordered correct answer must exist in it.
- Checkpoint points remain hidden and fixed at `1`; Explanation remains optional with a 5,000-character maximum.
- Type switching retains question text, explanation, and points, and resets every type-specific field.
- Checkpoint placement is immutable after creation.
- Both checkpoint placements must update the same `QuizQuestion` record, block UUID, and block position.
- Do not change Quiz scoring, attempts, attempt limits, shields, gamification, certification, CSV import, or learner result behavior.
- Do not add a frontend framework, form library, validation dependency, or browser-test dependency.
- Preserve unrelated working-tree changes, especially generated public assets and local media.
- Follow TDD: observe each focused test fail for the intended reason before adding production code.

---

## File Structure

### Create

- `resources/js/question-authoring.js` — pure, reusable Alpine state factory for options, ordered answers, blanks, type switching, and TinyMCE lifecycle hooks.
- `tests/JavaScript/question-authoring.test.mjs` — dependency-free Node tests for dynamic rows and the full switching sequence.
- `resources/views/instructor/topics/edit-checkpoint.blade.php` — thin edit wrapper shared by Inside Topic and Between Topics placements.
- `tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php` — formal Quiz authoring parity and ownership regression tests.
- `docs/superpowers/verification/2026-08-25-interactive-checkpoint-question-configuration-refinement-e2e.md` — recorded desktop/mobile and end-to-end verification evidence.

### Modify

- `app/Services/Learning/QuestionAuthoringService.php` — normalize, validate, serialize, and clean obsolete data for all six types.
- `app/Http/Controllers/Instructor/QuizManagementController.php` — consume the shared validation entry point and remove controller-only Word Bank validation.
- `app/Http/Controllers/Instructor/TopicController.php` — transactional checkpoint creation plus both placement-specific edit/update paths.
- `app/Http/Controllers/Instructor/LessonController.php` — eager-load checkpoint questions for authoring links.
- `resources/js/app.js` — register the shared Alpine data factory before `Alpine.start()`.
- `resources/views/instructor/quizzes/partials/question-fields.blade.php` — become the canonical six-type fields component.
- `resources/views/instructor/quizzes/add-question.blade.php` — retain the refined Quiz shell and delegate fields/state to the shared component.
- `resources/views/instructor/quizzes/edit-question.blade.php` — replace legacy field JavaScript with the shared component.
- `resources/views/instructor/topics/create.blade.php` — use the checkpoint-configured shared component and platform theme.
- `resources/views/instructor/topics/edit.blade.php` — remove the nonfunctional checkpoint editor from the generic topic form.
- `resources/views/instructor/lessons/show.blade.php` — expose edit actions for checkpoints embedded inside instructional topics.
- `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php` — preserve all six learner inputs while correcting Multiple Select retry state and Continue behavior.
- `routes/instructor.php` and `routes/admin.php` — add parallel Inside Topic checkpoint edit/update routes.
- `tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php` — full validation, serialization, stale-state, and image cleanup coverage.
- `tests/Unit/Services/Learning/QuestionEvaluatorTest.php` — canonical and legacy delimiter regression coverage.
- `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php` — create/edit/authorization/transaction coverage for all types and placements.
- `tests/Feature/Learner/InteractiveCheckpointFlowTest.php` — all-type learner response and both-placement coverage.
- `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php` — formal Quiz isolation assertions.

### Intentionally Unchanged

- Database schema and models: the existing `QuizQuestion`, `QuizOption`, `LessonTopic`, and progress tables already support the approved design.
- `app/Http/Controllers/Learner/InteractiveCheckpointController.php`: it already returns immediate feedback and optional explanation without creating Quiz attempts.
- Formal Quiz learner controllers: regression-test them; do not route checkpoint submissions through them.

---

### Task 1: Build the Reusable Alpine Question State

**Files:**

- Create: `resources/js/question-authoring.js`
- Create: `tests/JavaScript/question-authoring.test.mjs`
- Modify: `resources/js/app.js`

**Interfaces:**

- Consumes: a serializable configuration object emitted by the Blade partial and optional browser globals (`window.tinymce`, Alpine `$nextTick`).
- Produces: `createQuestionAuthoring(config): object`, `stripQuestionHtml(html): string`, and Alpine data name `questionAuthoring`.
- State contract: `questionType`, `questionText`, `points`, `explanation`, `options`, `answers`, `wordBank`, `caseSensitive`, `errors`.

- [ ] **Step 1: Write failing Node tests for rows, semantics, and the exact switch sequence**

Create `tests/JavaScript/question-authoring.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    createQuestionAuthoring,
    stripQuestionHtml,
} from '../../resources/js/question-authoring.js';

test('multiple choice adds unlimited options and never removes below two', () => {
    const state = createQuestionAuthoring({
        type: 'multiple_choice',
        options: [
            { text: 'A', isCorrect: true },
            { text: 'B', isCorrect: false },
        ],
    });

    for (let index = 0; index < 23; index += 1) state.addOption();
    assert.equal(state.options.length, 25);

    state.removeOption(0);
    assert.equal(state.options.some((option) => option.isCorrect), false);
    while (state.options.length > 2) state.removeOption(0);
    state.removeOption(0);
    assert.equal(state.options.length, 2);
});

test('true false always owns two fixed rows with radio semantics', () => {
    const state = createQuestionAuthoring({ type: 'true_false' });

    assert.deepEqual(state.options.map(({ text, readonly }) => ({ text, readonly })), [
        { text: 'True', readonly: true },
        { text: 'False', readonly: true },
    ]);
    state.setOnlyCorrect(1);
    assert.deepEqual(state.correctIndices(), [1]);
    assert.equal(state.canAddOptions(), false);
    assert.equal(state.canRemoveOptions(), false);
});

test('multiple select removes deleted answers from the correct set', () => {
    const state = createQuestionAuthoring({
        type: 'multiple_select',
        options: [
            { text: 'A', isCorrect: true },
            { text: 'B', isCorrect: false },
            { text: 'C', isCorrect: true },
        ],
    });

    state.removeOption(2);
    assert.deepEqual(state.correctIndices(), [0]);
});

test('blank helpers insert markers and count ordered answer groups', () => {
    const state = createQuestionAuthoring({
        type: 'fill_blank_text',
        questionText: 'The _____ is _____.',
        answers: ['color|colour', 'blue'],
    });

    assert.equal(state.blankCount(), 2);
    assert.deepEqual(state.validationErrors(), {});
    state.questionText += ' _____';
    state.syncAnswersToBlanks();
    assert.equal(state.answers.length, 3);
    assert.match(state.validationErrors().acceptable_answers, /one answer for each blank/i);
});

test('word bank validation caps ten entries and requires membership', () => {
    const state = createQuestionAuthoring({
        type: 'fill_blank_select',
        questionText: '_____ follows _____.',
        wordBank: 'alpha, beta',
        answers: ['alpha', 'missing'],
    });

    assert.match(state.validationErrors().acceptable_answers, /Word Bank/i);
    state.answers[1] = 'beta';
    assert.deepEqual(state.validationErrors(), {});
    state.wordBank = Array.from({ length: 11 }, (_, index) => `word${index}`).join(',');
    assert.match(state.validationErrors().word_bank, /10 words/i);
});

test('the full switch sequence resets type state and retains common fields', () => {
    const state = createQuestionAuthoring({
        type: 'multiple_choice',
        questionText: '<p>Keep this question</p>',
        explanation: 'Keep this explanation',
        points: 4,
        options: [
            { text: 'A', isCorrect: true },
            { text: 'B', isCorrect: false },
        ],
    });

    const sequence = [
        'true_false',
        'identification',
        'fill_blank_text',
        'fill_blank_select',
        'multiple_select',
        'multiple_choice',
    ];

    for (const type of sequence) {
        state.switchType(type);
        assert.equal(state.questionType, type);
        assert.equal(state.explanation, 'Keep this explanation');
        assert.equal(state.points, 4);
        assert.deepEqual(state.answers, ['']);
        assert.equal(state.wordBank, '');
        assert.equal(state.caseSensitive, false);

        if (type === 'true_false') {
            assert.deepEqual(state.options.map((option) => option.text), ['True', 'False']);
        } else if (['multiple_choice', 'multiple_select'].includes(type)) {
            assert.equal(state.options.length, 2);
            assert.equal(state.options.every((option) => option.text === ''), true);
        } else {
            assert.deepEqual(state.options, []);
        }
    }

    assert.equal(state.questionText, 'Keep this question');
});

test('rich to plain conversion removes markup and decodes visible text', () => {
    assert.equal(stripQuestionHtml('<p>Consent&nbsp;<strong>matters</strong></p>'), 'Consent matters');
});
```

- [ ] **Step 2: Run the JavaScript test and verify it fails**

Run:

```bash
node --test tests/JavaScript/question-authoring.test.mjs
```

Expected: FAIL with `ERR_MODULE_NOT_FOUND` for `resources/js/question-authoring.js`.

- [ ] **Step 3: Implement the dependency-free state factory**

Create `resources/js/question-authoring.js` with browser operations guarded so the same object is testable in Node:

```js
const RICH_TYPES = ['multiple_choice', 'true_false', 'multiple_select', 'identification'];
const CHOICE_TYPES = ['multiple_choice', 'true_false', 'multiple_select'];
const BLANK_TYPES = ['fill_blank_text', 'fill_blank_select'];

export function stripQuestionHtml(html = '') {
    return String(html)
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;|&#160;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function defaultOptions(type, nextKey) {
    if (type === 'true_false') {
        return [
            { key: nextKey(), text: 'True', isCorrect: false, readonly: true },
            { key: nextKey(), text: 'False', isCorrect: false, readonly: true },
        ];
    }

    if (['multiple_choice', 'multiple_select'].includes(type)) {
        return [
            { key: nextKey(), text: '', isCorrect: false, readonly: false },
            { key: nextKey(), text: '', isCorrect: false, readonly: false },
        ];
    }

    return [];
}

export function createQuestionAuthoring(config = {}) {
    let key = 0;
    const nextKey = () => `question-row-${key += 1}`;
    const type = config.type || 'multiple_choice';
    const suppliedOptions = Array.isArray(config.options) ? config.options : [];
    const initialAnswers = Array.isArray(config.answers) && config.answers.length
        ? config.answers.map(String)
        : [''];

    return {
        questionType: type,
        questionText: config.questionText || '',
        points: Number(config.points || 1),
        explanation: config.explanation || '',
        options: suppliedOptions.length
            ? suppliedOptions.map((option) => ({
                key: nextKey(),
                text: String(option.text || ''),
                isCorrect: Boolean(option.isCorrect),
                readonly: type === 'true_false' || Boolean(option.readonly),
            }))
            : defaultOptions(type, nextKey),
        answers: initialAnswers,
        answerKeys: initialAnswers.map(() => nextKey()),
        wordBank: config.wordBank || '',
        caseSensitive: Boolean(config.caseSensitive),
        currentImageUrl: config.currentImageUrl || null,
        typeMeta: config.typeMeta || {},
        errors: {},
        editorUploadUrl: config.editorUploadUrl || null,

        init() {
            this.$root?.closest('form')?.addEventListener('submit', (event) => this.submit(event));
            this.$nextTick?.(() => this.configureEditor());
        },

        isRichType(typeToCheck = this.questionType) {
            return RICH_TYPES.includes(typeToCheck);
        },

        isChoiceType() {
            return CHOICE_TYPES.includes(this.questionType);
        },

        isBlankType() {
            return BLANK_TYPES.includes(this.questionType);
        },

        canAddOptions() {
            return ['multiple_choice', 'multiple_select'].includes(this.questionType);
        },

        canRemoveOptions() {
            return this.canAddOptions() && this.options.length > 2;
        },

        addOption() {
            if (!this.canAddOptions()) return;
            this.options.push({ key: nextKey(), text: '', isCorrect: false, readonly: false });
        },

        removeOption(index) {
            if (!this.canRemoveOptions()) return;
            this.options.splice(index, 1);
        },

        setOnlyCorrect(index) {
            this.options.forEach((option, optionIndex) => {
                option.isCorrect = optionIndex === index;
            });
        },

        correctIndices() {
            return this.options
                .map((option, index) => option.isCorrect ? index : null)
                .filter((index) => index !== null);
        },

        addAnswer() {
            this.answers.push('');
            this.answerKeys.push(nextKey());
        },

        removeAnswer(index) {
            if (this.answers.length > 1) {
                this.answers.splice(index, 1);
                this.answerKeys.splice(index, 1);
            }
        },

        blankCount() {
            return (String(this.questionText).match(/_____/g) || []).length;
        },

        syncAnswersToBlanks() {
            if (!this.isBlankType()) return;
            const target = Math.max(1, this.blankCount());
            while (this.answers.length < target) this.addAnswer();
            if (this.answers.length > target) {
                this.answers.splice(target);
                this.answerKeys.splice(target);
            }
        },

        wordBankEntries() {
            return String(this.wordBank)
                .split(',')
                .map((word) => word.trim())
                .filter(Boolean);
        },

        insertBlank() {
            const textarea = this.$refs?.plainQuestion;
            const start = textarea?.selectionStart ?? this.questionText.length;
            const end = textarea?.selectionEnd ?? start;
            this.questionText = `${this.questionText.slice(0, start)}_____${this.questionText.slice(end)}`;
            this.syncAnswersToBlanks();
            this.$nextTick?.(() => {
                if (!textarea) return;
                textarea.focus();
                textarea.setSelectionRange(start + 5, start + 5);
            });
        },

        syncEditor() {
            const editor = globalThis.window?.tinymce?.get('question_text');
            if (!editor) return;
            this.questionText = editor.getContent();
            editor.save();
        },

        removeEditor() {
            globalThis.window?.tinymce?.get('question_text')?.remove();
        },

        configureEditor() {
            if (!this.isRichType() || !globalThis.window?.tinymce) return;
            if (globalThis.window.tinymce.get('question_text')) return;

            globalThis.window.tinymce.init({
                selector: '#question_text',
                license_key: 'gpl',
                promotion: false,
                height: 220,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount',
                ],
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | image link | removeformat',
                images_upload_url: this.editorUploadUrl,
                automatic_uploads: true,
                images_reuse_filename: true,
                setup: (editor) => {
                    editor.on('change input undo redo', () => {
                        this.questionText = editor.getContent();
                    });
                },
            });
        },

        switchType(nextType) {
            if (!nextType || nextType === this.questionType) return;
            const wasRich = this.isRichType();
            this.syncEditor();
            if (wasRich && !this.isRichType(nextType)) {
                this.questionText = stripQuestionHtml(this.questionText);
            }
            if (wasRich) this.removeEditor();

            this.questionType = nextType;
            this.options = defaultOptions(nextType, nextKey);
            this.answers = [''];
            this.answerKeys = [nextKey()];
            this.wordBank = '';
            this.caseSensitive = false;
            this.currentImageUrl = null;
            this.errors = {};
            if (this.$refs?.imageInput) this.$refs.imageInput.value = '';
            this.syncAnswersToBlanks();

            this.$nextTick?.(() => this.configureEditor());
        },

        validationErrors() {
            const errors = {};
            if (!stripQuestionHtml(this.questionText)) errors.question_text = 'Question text is required.';

            if (this.isChoiceType()) {
                if (this.options.length < 2 || this.options.some((option) => !option.text.trim())) {
                    errors.options = 'Provide at least two non-empty answer options.';
                }
                const correctCount = this.correctIndices().length;
                if (['multiple_choice', 'true_false'].includes(this.questionType) && correctCount !== 1) {
                    errors.correct_options = 'Select exactly one correct answer.';
                }
                if (this.questionType === 'multiple_select' && correctCount < 1) {
                    errors.correct_options = 'Select at least one correct answer.';
                }
            }

            if (this.isBlankType()) {
                if (this.blankCount() < 1) errors.question_text = 'Add at least one blank using five underscores (_____).';
                if (this.answers.length !== this.blankCount() || this.answers.some((answer) => !answer.trim())) {
                    errors.acceptable_answers = 'Add one answer for each blank.';
                }
            }

            if (this.questionType === 'fill_blank_select') {
                const words = this.wordBankEntries();
                if (words.length < 1 || words.length > 10) errors.word_bank = 'Use between 1 and 10 Word Bank words.';
                if (this.answers.some((answer) => !words.includes(answer.trim()))) {
                    errors.acceptable_answers = 'Every correct answer must appear in the Word Bank.';
                }
            }

            if (this.questionType === 'identification' && this.answers.some((answer) => !answer.trim())) {
                errors.acceptable_answers = 'Provide at least one acceptable answer.';
            }

            return errors;
        },

        submit(event) {
            this.syncEditor();
            this.errors = this.validationErrors();
            if (Object.keys(this.errors).length === 0) return;
            event.preventDefault();
            this.$nextTick?.(() => {
                const editor = globalThis.window?.tinymce?.get('question_text');
                if (this.errors.question_text && editor) {
                    editor.focus();
                    return;
                }
                this.$root?.querySelector('[aria-invalid="true"]')?.focus();
            });
        },
    };
}
```

- [ ] **Step 4: Register the factory before Alpine starts**

In `resources/js/app.js`, add the import beside the Alpine imports and register it immediately before `Alpine.start()`:

```js
import { createQuestionAuthoring } from './question-authoring';

Alpine.data('questionAuthoring', createQuestionAuthoring);
Alpine.start();
```

Keep the file's existing Alpine plugins and other registrations in their current order.

- [ ] **Step 5: Run JavaScript tests and compile assets**

Run:

```bash
node --test tests/JavaScript/question-authoring.test.mjs
npm run build
```

Expected: seven Node subtests PASS and Vite exits successfully. Generated `public/build` changes are verification artifacts; do not include them in the feature commit when pre-existing generated-asset changes are present.

- [ ] **Step 6: Commit the shared client state**

```bash
git add resources/js/question-authoring.js resources/js/app.js tests/JavaScript/question-authoring.test.mjs
git commit -m "feat: share question authoring state"
```

---

### Task 2: Centralize the Six-Type Server Contract

**Files:**

- Modify: `app/Services/Learning/QuestionAuthoringService.php`
- Modify: `app/Http/Controllers/Instructor/QuizManagementController.php`
- Test: `tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php`
- Test: `tests/Unit/Services/Learning/QuestionEvaluatorTest.php`

**Interfaces:**

- Consumes: `Illuminate\Http\Request`, `QuizQuestion`, `QuizOption`, public storage, and the six existing question slugs.
- Produces: `QuestionAuthoringService::validate(Request $request): array`, canonical `acceptable_answers` serialization, and existing `createQuestion()` / `updateQuestion()` persistence methods.
- Invariant: controllers never reproduce type-specific question rules after this task.

- [ ] **Step 1: Add failing unit tests for normalization and validation**

Extend `tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php` with direct validator tests. Use a real `Request` so normalization is tested at the public boundary:

```php
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
    $image = UploadedFile::fake()->image('prompt.png');
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
```

- [ ] **Step 2: Run the new service tests and verify the intended failure**

Run:

```bash
php artisan test tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php
```

Expected: FAIL because `QuestionAuthoringService::validate()` does not exist.

- [ ] **Step 3: Implement the shared normalization and validator entry point**

In `app/Services/Learning/QuestionAuthoringService.php`, import `Validator` and `Illuminate\Validation\Validator`, then replace the public rule/normalization boundary with:

```php
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

private const CHOICE_TYPES = ['multiple_choice', 'true_false', 'multiple_select'];
private const TEXT_ANSWER_TYPES = ['fill_blank_text', 'fill_blank_select', 'identification'];

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
        'question_type' => ['required', 'in:' . implode(',', self::TYPES)],
        'points' => ['required', 'integer', 'min:1'],
        'options' => ['required_if:question_type,' . implode(',', self::CHOICE_TYPES), 'array', 'min:2'],
        'options.*' => ['required_with:options', 'string'],
        'correct_options' => ['required_if:question_type,' . implode(',', self::CHOICE_TYPES), 'array', 'min:1'],
        'correct_options.*' => ['required_with:correct_options', 'integer', 'distinct'],
        'acceptable_answers' => ['required_if:question_type,' . implode(',', self::TEXT_ANSWER_TYPES), 'array', 'min:1'],
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

    if (in_array($type, self::CHOICE_TYPES, true)) {
        $options = array_values(array_map(
            fn ($option) => trim((string) $option),
            (array) $request->input('options', []),
        ));
        $correct = array_values(array_map(
            'intval',
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
        $invalidIndices = array_filter($correct, fn ($index) => !array_key_exists((int) $index, $options));

        if ($invalidIndices !== []) {
            $validator->errors()->add('correct_options', 'Every correct answer must refer to an answer option.');
        }
        if (in_array($type, ['multiple_choice', 'true_false'], true) && count($correct) !== 1) {
            $validator->errors()->add('correct_options', 'Select exactly one correct answer.');
        }
        if ($type === 'true_false' && !in_array($correct[0] ?? null, [0, 1], true)) {
            $validator->errors()->add('correct_options', 'Select True or False as the correct answer.');
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
            if (!in_array($answer, $words, true)) {
                $validator->errors()->add('acceptable_answers', 'Every correct answer must appear in the Word Bank.');
                break;
            }
        }
    }
}
```

Keep validation messages field-scoped so the shared Blade component can render them inline. Do not filter empty option or answer rows during normalization; a visible empty row must fail validation.

- [ ] **Step 4: Make persistence canonical and remove obsolete type data**

Replace the answer/image portion of `questionPayload()` with the following exact type dispatch:

```php
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
    'case_sensitive' => $usesTextAnswers && !empty($data['case_sensitive']),
    'word_bank' => $data['question_type'] === 'fill_blank_select'
        ? array_map('trim', explode(',', $data['word_bank']))
        : null,
    'image_path' => $usesImage
        ? (($data['image'] ?? null) instanceof UploadedFile
            ? $data['image']->store($this->imageDirectory(), 'public')
            : ($data['image_path'] ?? $existingImagePath))
        : null,
    'explanation' => $data['explanation'] ?? null,
]);
```

In `updateQuestion()`, capture the old image path, finish the database transaction, and delete the old file only when a replacement was stored or the new type is not Identification:

```php
public function updateQuestion(QuizQuestion $question, array $data): QuizQuestion
{
    $oldImagePath = $question->image_path;

    $updated = DB::transaction(function () use ($question, $data): QuizQuestion {
        $question->update($this->questionPayload($data, [], $question->image_path));
        $this->replaceOptions($question, $data);

        return $question->refresh()->load('options');
    });

    $imageWasReplaced = ($data['image'] ?? null) instanceof UploadedFile;
    if ($oldImagePath && ($imageWasReplaced || $updated->question_type !== 'identification')) {
        Storage::disk('public')->delete($oldImagePath);
    }

    return $updated;
}
```

- [ ] **Step 5: Prove canonical and legacy delimiters remain evaluable**

Add these assertions to `tests/Unit/Services/Learning/QuestionEvaluatorTest.php`:

```php
public function test_fill_blank_text_keeps_legacy_single_blank_alternatives(): void
{
    $question = $this->textQuestion('fill_blank_text', 'color|colour', false);

    $this->assertTrue($this->evaluator->evaluate($question, ['colour'])['is_correct']);
}

public function test_fill_blank_select_keeps_legacy_pipe_delimited_order(): void
{
    $question = $this->textQuestion('fill_blank_select', 'first|second', false, ['first', 'second']);

    $this->assertTrue($this->evaluator->evaluate($question, ['first', 'second'])['is_correct']);
}
```

- [ ] **Step 6: Route formal Quiz create/update through the shared validator**

In both `storeQuestion()` and `updateQuestion()` in `app/Http/Controllers/Instructor/QuizManagementController.php`, replace the `normalizeRequest()` plus `request->validate(rules())` sequence with:

```php
$validated = $this->questionAuthoring->validate($request);
```

Pass `$validated` directly to `createQuestion()` / `updateQuestion()` because the validated result now includes `image`. Delete both controller-local `fill_blank_select` Word Bank count checks.

In `addQuestion()`, replace the duplicated slug array with the service constant:

```php
$selectedType = in_array($preselectedType, QuestionAuthoringService::TYPES, true)
    ? $preselectedType
    : null;
```

- [ ] **Step 7: Run service and evaluator tests**

Run:

```bash
php artisan test tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php
```

Expected: all tests PASS; the output reports zero failures and zero errors.

- [ ] **Step 8: Commit the shared server contract**

```bash
git add app/Services/Learning/QuestionAuthoringService.php app/Http/Controllers/Instructor/QuizManagementController.php tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php
git commit -m "refactor: centralize question authoring rules"
```

---

### Task 3: Make the Refined Quiz Fields the Shared UI Core

**Files:**

- Modify: `resources/views/instructor/quizzes/partials/question-fields.blade.php`
- Modify: `resources/views/instructor/quizzes/add-question.blade.php`
- Modify: `resources/views/instructor/quizzes/edit-question.blade.php`
- Create: `tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php`

**Interfaces:**

- Consumes: `questionAuthoring(@js($initialState))` from Task 1 and Laravel validation errors from Task 2.
- Produces: one include API with `$question`, `$selectedType`, `$allowTypeSwitch`, `$showPoints`, `$showExplanation`, and `$editorUploadUrl`.
- Wrapper defaults: Quiz add uses `allowTypeSwitch=false`, Quiz edit uses `true`, both use `showPoints=true` and preserve the current Quiz explanation behavior (`false`).

- [ ] **Step 1: Write failing formal Quiz UI regression tests**

Create `tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php`:

```php
<?php

namespace Tests\Feature\Instructor;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
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
```

- [ ] **Step 2: Run the Quiz UI test and verify it fails on the old partial/edit page**

Run:

```bash
php artisan test tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php
```

Expected: FAIL because the add and edit pages do not both use `questionAuthoring` or expose the same guidance.

- [ ] **Step 3: Replace the partial's fixed four-row initialization with canonical edit state**

At the top of `resources/views/instructor/quizzes/partials/question-fields.blade.php`, build the complete serialized state. This is the only place that interprets legacy edit delimiters:

```blade
@php
    $allowTypeSwitch = $allowTypeSwitch ?? true;
    $showPoints = $showPoints ?? true;
    $showExplanation = $showExplanation ?? false;
    $selectedType = old('question_type', $question->question_type ?? ($selectedType ?? 'multiple_choice'));
    $questionText = old('question_text', $question->question_text ?? '');
    $blankCount = substr_count(strip_tags((string) $questionText), '_____');
    $existingOptions = isset($question) ? $question->options->values() : collect();
    $submittedOptions = old('options');
    $submittedCorrect = array_map('intval', (array) old('correct_options', []));

    if (is_array($submittedOptions)) {
        $options = collect($submittedOptions)->values()->map(fn ($text, $index) => [
            'text' => $text,
            'isCorrect' => in_array($index, $submittedCorrect, true),
            'readonly' => $selectedType === 'true_false',
        ])->all();
    } else {
        $options = $existingOptions->map(fn ($option) => [
            'text' => $option->option_text,
            'isCorrect' => (bool) $option->is_correct,
            'readonly' => $selectedType === 'true_false',
        ])->all();
    }

    $storedAnswers = (string) ($question->acceptable_answers ?? '');
    if (old('acceptable_answers') !== null) {
        $answers = array_values((array) old('acceptable_answers'));
    } elseif ($selectedType === 'identification') {
        $answers = $storedAnswers === '' ? [''] : explode('|', $storedAnswers);
    } elseif ($selectedType === 'fill_blank_select') {
        $answers = $storedAnswers === '' ? [''] : preg_split('/[;|]/', $storedAnswers);
    } elseif ($selectedType === 'fill_blank_text' && str_contains($storedAnswers, ';')) {
        $answers = explode(';', $storedAnswers);
    } elseif ($selectedType === 'fill_blank_text' && $blankCount > 1) {
        $tokens = $storedAnswers === '' ? [] : explode('|', $storedAnswers);
        $answers = count($tokens) === $blankCount ? $tokens : [$storedAnswers];
    } else {
        $answers = [$storedAnswers];
    }

    $typeMeta = [
        'multiple_choice' => ['label' => 'Multiple Choice', 'description' => 'Learners choose one answer.', 'badge' => 'bg-brand-50 text-brand-700 border-brand-200'],
        'true_false' => ['label' => 'True or False', 'description' => 'Learners decide whether the statement is true or false.', 'badge' => 'bg-green-50 text-green-700 border-green-200'],
        'identification' => ['label' => 'Identification', 'description' => 'Learners type a short accepted answer.', 'badge' => 'bg-pink-50 text-pink-700 border-pink-200'],
        'fill_blank_text' => ['label' => 'Fill in the Blanks — Text', 'description' => 'Learners type an answer for every blank.', 'badge' => 'bg-yellow-50 text-yellow-700 border-yellow-200'],
        'fill_blank_select' => ['label' => 'Fill in the Blanks — Word Bank', 'description' => 'Learners choose ordered answers from a Word Bank.', 'badge' => 'bg-orange-50 text-orange-700 border-orange-200'],
        'multiple_select' => ['label' => 'Multiple Select', 'description' => 'Learners select every correct answer.', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200'],
    ];

    $initialState = [
        'type' => $selectedType,
        'questionText' => $questionText,
        'points' => old('points', $question->points ?? 1),
        'explanation' => old('explanation', $question->explanation ?? ''),
        'options' => $options,
        'answers' => $answers,
        'wordBank' => old('word_bank', isset($question) && is_array($question->word_bank) ? implode(', ', $question->word_bank) : ''),
        'caseSensitive' => (bool) old('case_sensitive', $question->case_sensitive ?? false),
        'currentImageUrl' => $question->image_url ?? null,
        'editorUploadUrl' => $editorUploadUrl,
        'typeMeta' => $typeMeta,
    ];
@endphp
```

- [ ] **Step 4: Render one accessible active-type configuration**

Replace the partial markup and inline script with a single `x-data="questionAuthoring(@js($initialState))"` root. Use `<template x-if>` so inactive inputs are absent from the DOM and cannot submit stale data. The essential field markup is:

```blade
<div x-data="questionAuthoring(@js($initialState))" class="space-y-6" data-question-authoring>
    <input type="hidden" name="question_type" :value="questionType">

    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <span class="inline-flex rounded-xl border px-3 py-1 text-sm font-semibold" :class="typeMeta[questionType].badge" x-text="typeMeta[questionType].label"></span>
                <p class="mt-1 text-xs text-gray-500" x-text="typeMeta[questionType].description"></p>
                <p class="sr-only" aria-live="polite" x-text="`${typeMeta[questionType].label} configuration selected`"></p>
            </div>
            @if($allowTypeSwitch)
                <div class="w-full sm:w-72">
                    <label for="question_type_selector" class="block text-xs font-semibold text-gray-700 mb-1">Change Question Type</label>
                    <select id="question_type_selector" :value="questionType" @change="switchType($event.target.value)"
                        class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
                        @foreach($typeMeta as $value => $meta)
                            <option value="{{ $value }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3 mb-2">
            <label for="question_text" class="text-sm font-semibold text-gray-700">Question Text <span class="text-red-500">*</span></label>
            <button x-show="isBlankType()" type="button" @click="insertBlank()"
                class="min-h-10 rounded-xl border border-purple-200 bg-purple-50 px-3 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                Insert Blank (_____)
            </button>
        </div>
        <template x-if="isRichType()">
            <textarea id="question_text" name="question_text" x-model="questionText" rows="5" aria-describedby="question_text_error question_text_client_error"
                :aria-invalid="Boolean(errors.question_text || @js($errors->has('question_text')))"
                class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300"></textarea>
        </template>
        <template x-if="isBlankType()">
            <textarea id="question_text" name="question_text" x-ref="plainQuestion" x-model="questionText" @input="syncAnswersToBlanks()" rows="5" aria-describedby="question_text_error question_text_client_error"
                :aria-invalid="Boolean(errors.question_text || @js($errors->has('question_text')))"
                class="w-full rounded-xl border-gray-200 font-mono text-sm focus:border-purple-400 focus:ring-purple-300"
                placeholder="Use _____ (five underscores) for every blank."></textarea>
        </template>
        <p x-show="isBlankType()" class="mt-2 text-xs text-purple-700"><span x-text="blankCount()"></span> blank(s) detected.</p>
        @error('question_text') <p class="mt-1 text-xs text-red-600" id="question_text_error">{{ $message }}</p> @enderror
        <p id="question_text_client_error" x-show="errors.question_text" x-text="errors.question_text" class="mt-1 text-xs text-red-600" role="alert"></p>
    </section>

    @if($showPoints)
        <div>
            <label for="points" class="block text-sm font-semibold text-gray-700 mb-2">Points <span class="text-red-500">*</span></label>
            <input id="points" name="points" type="number" min="1" x-model.number="points" required
                class="w-32 rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
            @error('points') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @else
        <input type="hidden" name="points" value="1">
    @endif

    <template x-if="isChoiceType()">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm" role="group" aria-labelledby="answer_options_heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="border-l-4 border-purple-700 pl-3">
                    <p id="answer_options_heading" class="text-sm font-semibold text-gray-900">Answer Options</p>
                    <p x-show="questionType === 'multiple_choice'" class="text-xs text-gray-500">Select exactly one correct answer.</p>
                    <p x-show="questionType === 'true_false'" class="text-xs text-gray-500">Choose whether True or False is correct.</p>
                    <p x-show="questionType === 'multiple_select'" class="text-xs text-gray-500">Select every correct answer.</p>
                </div>
                <button x-show="canAddOptions()" type="button" @click="addOption()"
                    class="min-h-10 rounded-xl border border-purple-200 bg-purple-50 px-3 text-xs font-semibold text-purple-700 hover:bg-purple-100">Add Option</button>
            </div>
            <div class="mt-4 space-y-3">
                <template x-for="(option, index) in options" :key="option.key">
                    <div class="flex flex-col gap-3 rounded-xl border p-3 sm:flex-row sm:items-center"
                        :class="option.isCorrect ? 'border-green-300 bg-green-50' : 'border-gray-200'">
                        <template x-if="questionType !== 'multiple_select'">
                            <input type="radio" name="correct_options[]" :value="index"
                                :checked="option.isCorrect" @change="setOnlyCorrect(index)" :aria-label="`Mark option ${index + 1} correct`"
                                :aria-invalid="Boolean(errors.correct_options || @js($errors->has('correct_options')))" aria-describedby="correct_options_server_error correct_options_error"
                                class="h-6 w-6 text-green-600 focus:ring-green-500">
                        </template>
                        <template x-if="questionType === 'multiple_select'">
                            <input type="checkbox" name="correct_options[]" :value="index" :checked="option.isCorrect"
                                @change="option.isCorrect = $event.target.checked" :aria-label="`Mark option ${index + 1} correct`"
                                :aria-invalid="Boolean(errors.correct_options || @js($errors->has('correct_options')))" aria-describedby="correct_options_server_error correct_options_error"
                                class="h-6 w-6 rounded text-green-600 focus:ring-green-500">
                        </template>
                        <input type="text" name="options[]" x-model="option.text" :readonly="option.readonly" required
                            :aria-label="`Answer option ${index + 1}`"
                            :aria-invalid="Boolean(errors.options || @js($errors->has('options') || $errors->has('options.*')))" aria-describedby="answer_options_server_error answer_options_error"
                            class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
                        <span x-show="option.isCorrect" class="text-xs font-semibold text-green-700">Correct</span>
                        <button x-show="canRemoveOptions()" type="button" @click="removeOption(index)"
                            :aria-label="`Remove option ${index + 1}`"
                            class="min-h-10 min-w-10 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600">×</button>
                    </div>
                </template>
            </div>
            @if($errors->has('options') || $errors->has('options.*'))
                <p id="answer_options_server_error" class="mt-2 text-xs text-red-600" role="alert">{{ $errors->first('options') ?: $errors->first('options.*') }}</p>
            @endif
            @error('correct_options') <p id="correct_options_server_error" class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            <p id="answer_options_error" x-show="errors.options" x-text="errors.options" class="mt-2 text-xs text-red-600" role="alert"></p>
            <p id="correct_options_error" x-show="errors.correct_options" x-text="errors.correct_options" class="mt-2 text-xs text-red-600" role="alert"></p>
        </section>
    </template>

    <template x-if="questionType === 'fill_blank_text' || questionType === 'fill_blank_select' || questionType === 'identification'">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="border-l-4 border-purple-700 pl-3">
                    <p class="text-sm font-semibold text-gray-900" x-text="questionType === 'identification' ? 'Acceptable Answers' : 'Correct Answers (in order)'"></p>
                    <p x-show="questionType === 'fill_blank_text'" class="text-xs text-gray-500">Add one row per blank. Alternatives within one blank use |, for example color|colour.</p>
                    <p x-show="questionType === 'fill_blank_select'" class="text-xs text-gray-500">Add one Word Bank answer per blank in question order.</p>
                    <p x-show="questionType === 'identification'" class="text-xs text-gray-500">Add every short answer that should be accepted.</p>
                </div>
                <button x-show="questionType === 'identification'" type="button" @click="addAnswer()"
                    class="min-h-10 rounded-xl border border-purple-200 bg-purple-50 px-3 text-xs font-semibold text-purple-700">Add Answer</button>
            </div>
            <div class="mt-4 space-y-2">
                <template x-for="(answer, index) in answers" :key="answerKeys[index]">
                    <div class="flex items-center gap-3">
                        <span class="w-16 text-xs text-gray-500" x-text="questionType === 'identification' ? `${index + 1}.` : `Blank ${index + 1}`"></span>
                        <input type="text" name="acceptable_answers[]" x-model="answers[index]" required
                            :aria-label="`Accepted answer ${index + 1}`"
                            :aria-invalid="Boolean(errors.acceptable_answers || @js($errors->has('acceptable_answers') || $errors->has('acceptable_answers.*')))" aria-describedby="acceptable_answers_server_error acceptable_answers_error"
                            class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
                        <button x-show="questionType === 'identification' && answers.length > 1" type="button" @click="removeAnswer(index)"
                            :aria-label="`Remove acceptable answer ${index + 1}`" class="min-h-10 min-w-10 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600">×</button>
                    </div>
                </template>
            </div>
            <label x-show="questionType !== 'fill_blank_select'" class="mt-4 flex items-start gap-3 rounded-xl border border-yellow-200 bg-yellow-50 p-3">
                <input type="checkbox" name="case_sensitive" value="1" x-model="caseSensitive" class="mt-0.5 h-6 w-6 rounded text-purple-600">
                <span><span class="block text-sm font-medium text-gray-700">Case Sensitive</span><span class="block text-xs text-gray-500">Require capitalization to match exactly.</span></span>
            </label>
            @if($errors->has('acceptable_answers') || $errors->has('acceptable_answers.*'))
                <p id="acceptable_answers_server_error" class="mt-2 text-xs text-red-600" role="alert">{{ $errors->first('acceptable_answers') ?: $errors->first('acceptable_answers.*') }}</p>
            @endif
            <p id="acceptable_answers_error" x-show="errors.acceptable_answers" x-text="errors.acceptable_answers" class="mt-2 text-xs text-red-600" role="alert"></p>
        </section>
    </template>

    <template x-if="questionType === 'fill_blank_select'">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <label for="word_bank" class="block text-sm font-semibold text-gray-900">Word Bank</label>
            <p class="mb-3 text-xs text-gray-500">Enter comma-separated words learners can choose from. Max 10 words.</p>
            <input id="word_bank" name="word_bank" type="text" x-model="wordBank" required aria-describedby="word_bank_server_error word_bank_error"
                :aria-invalid="Boolean(errors.word_bank || @js($errors->has('word_bank')))"
                class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
            @error('word_bank') <p id="word_bank_server_error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            <p id="word_bank_error" x-show="errors.word_bank" x-text="errors.word_bank" class="mt-1 text-xs text-red-600" role="alert"></p>
        </section>
    </template>

    <template x-if="questionType === 'identification'">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <label for="image" class="block text-sm font-semibold text-gray-900">Question Image <span class="font-normal text-gray-400">(optional)</span></label>
            <p class="mb-3 text-xs text-gray-500">JPG or PNG, max 2 MB.</p>
            <img x-show="currentImageUrl" :src="currentImageUrl" alt="Current question image" class="mb-3 max-h-48 rounded-xl border border-gray-200 object-contain">
            <input id="image" name="image" type="file" x-ref="imageInput" accept=".jpg,.jpeg,.png"
                class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:font-semibold file:text-purple-700">
            @error('image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </section>
    </template>

    @if($showExplanation)
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <label for="explanation" class="block text-sm font-semibold text-gray-900">Explanation <span class="font-normal text-gray-400">(Optional)</span></label>
            <p class="mb-3 text-xs text-gray-500">Shown after the learner answers. It is not shown when the learner skips.</p>
            <textarea id="explanation" name="explanation" rows="4" maxlength="5000" x-model="explanation"
                class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300"></textarea>
            @error('explanation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </section>
    @endif
</div>
```

Add the TinyMCE asset once and remove the old partial's `DOMContentLoaded` toggler:

```blade
@once
    @push('head')
        <script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
    @endpush
@endonce
```

Keep server errors above the form in each wrapper, and ensure array wildcard errors appear within their active section.

- [ ] **Step 5: Convert Quiz add and edit into thin wrappers**

In `resources/views/instructor/quizzes/add-question.blade.php`, retain the breadcrumb, selected-type chip, error summary, form action, `after_save`, buttons, and cancel links. Replace its duplicated question fields and `questionForm()` inline script with:

```blade
@include('instructor.quizzes.partials.question-fields', [
    'selectedType' => $selectedType,
    'allowTypeSwitch' => false,
    'showPoints' => true,
    'showExplanation' => false,
    'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
])
```

In `resources/views/instructor/quizzes/edit-question.blade.php`, retain ownership-safe form action, method spoofing, Save, Cancel, and Delete controls. Eager-loaded `$question->options` is passed to:

```blade
@include('instructor.quizzes.partials.question-fields', [
    'question' => $question,
    'selectedType' => $question->question_type,
    'allowTypeSwitch' => true,
    'showPoints' => true,
    'showExplanation' => false,
    'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
])
```

Delete both pages' legacy option/answer row scripts and TinyMCE initializers; the shared Alpine factory now owns those behaviors.

- [ ] **Step 6: Run Quiz authoring regression tests and build**

Run:

```bash
php artisan test tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php
npm run build
```

Expected: PHPUnit PASS, Vite succeeds, and no duplicate `questionForm()` function remains in the rendered pages.

- [ ] **Step 7: Commit the shared Quiz UI core**

```bash
git add resources/views/instructor/quizzes/partials/question-fields.blade.php resources/views/instructor/quizzes/add-question.blade.php resources/views/instructor/quizzes/edit-question.blade.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php
git commit -m "refactor: share quiz question fields"
```

---

### Task 4: Apply the Shared Authoring Core to Checkpoint Creation

**Files:**

- Modify: `app/Http/Controllers/Instructor/TopicController.php`
- Modify: `resources/views/instructor/topics/create.blade.php`
- Modify: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`

**Interfaces:**

- Consumes: `QuestionAuthoringService::validate()`, `createQuestion()`, and the shared Blade include API from Task 3.
- Produces: transactional Inside Topic and Between Topics checkpoint creation with hidden `points=1` and optional Explanation.
- Ownership rule: the selected parent topic must belong to the already-authorized lesson.

- [ ] **Step 1: Add failing create coverage for every type and atomic validation**

Add a data provider and one test to `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`:

```php
/**
 * @dataProvider checkpointPayloadProvider
 */
public function test_instructor_can_create_each_checkpoint_question_type(string $type, array $question): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');

    $this->actingAs($instructor)
        ->post(route('instructor.topics.store'), array_merge([
            'lesson_id' => $lesson->id,
            'title' => "{$type} checkpoint",
            'type' => 'interactive_checkpoint',
            'duration' => 1,
            'checkpoint_placement' => 'between_topics',
            'question_type' => $type,
            'question_text' => str_starts_with($type, 'fill_blank') ? '_____ follows _____.' : '<p>Question text</p>',
            'points' => 1,
            'explanation' => 'Optional learner feedback.',
        ], $question))
        ->assertRedirect(route('instructor.lessons.show', $lesson));

    $saved = QuizQuestion::query()->where('question_type', $type)->latest('id')->firstOrFail();
    $this->assertSame(1, $saved->points);
    $this->assertSame('Optional learner feedback.', $saved->explanation);
}

public static function checkpointPayloadProvider(): array
{
    return [
        'multiple choice' => ['multiple_choice', ['options' => ['A', 'B'], 'correct_options' => [0]]],
        'true false' => ['true_false', ['options' => ['stale', 'values'], 'correct_options' => [1]]],
        'identification' => ['identification', ['acceptable_answers' => ['Consent', 'Permission'], 'case_sensitive' => 1]],
        'fill blank text' => ['fill_blank_text', ['acceptable_answers' => ['alpha|Alpha', 'beta']]],
        'fill blank word bank' => ['fill_blank_select', ['word_bank' => 'alpha, beta, extra', 'acceptable_answers' => ['alpha', 'beta']]],
        'multiple select' => ['multiple_select', ['options' => ['A', 'B', 'C'], 'correct_options' => [0, 2]]],
    ];
}

public function test_invalid_checkpoint_writes_no_topic_question_or_option(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topicCount = LessonTopic::count();
    $questionCount = QuizQuestion::count();

    $this->actingAs($instructor)
        ->from(route('instructor.topics.create', ['lesson' => $lesson]))
        ->post(route('instructor.topics.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Invalid checkpoint',
            'type' => 'interactive_checkpoint',
            'duration' => 1,
            'checkpoint_placement' => 'between_topics',
            'question_type' => 'multiple_choice',
            'question_text' => '<p><br></p>',
            'points' => 1,
            'options' => ['Only one'],
            'correct_options' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['question_text', 'options', 'correct_options']);

    $this->assertSame($topicCount, LessonTopic::count());
    $this->assertSame($questionCount, QuizQuestion::count());
}

private function authoringFixture(string $role): array
{
    $author = User::factory()->create(['role' => $role]);
    $author->assignRole($role);
    $module = Module::factory()->create([
        'created_by' => $author->id,
        'content_owner_type' => $role === 'admin' ? 'admin' : 'instructor',
    ]);
    $lesson = Lesson::factory()->create(['module_id' => $module->id]);

    return [$author, $lesson];
}
```

Refactor the existing fixture setup in that class to call `authoringFixture()` so the tests do not duplicate role/module/lesson creation.

- [ ] **Step 2: Run the checkpoint authoring test and verify it fails**

Run:

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Expected: at least the Word Bank, ordered delimiter, or strict invalid-configuration assertions FAIL before `storeCheckpoint()` consumes the centralized validator.

- [ ] **Step 3: Make placement plus question creation one transaction**

Import `Illuminate\Support\Facades\DB` in `TopicController`, then replace `storeCheckpoint()` with this structure:

```php
private function storeCheckpoint(Request $request, Lesson $lesson)
{
    $placement = $request->validate([
        'lesson_id' => ['required', 'exists:lessons,id'],
        'title' => ['required', 'string', 'max:255'],
        'duration' => ['nullable', 'integer', 'min:1'],
        'checkpoint_placement' => ['required', 'in:inside_topic,between_topics'],
        'parent_topic_id' => ['nullable', 'required_if:checkpoint_placement,inside_topic', 'integer', 'exists:lesson_topics,id'],
        'insert_after_block' => ['nullable', 'integer', 'min:0'],
    ]);
    $questionData = $this->questionAuthoring->validate($request);

    return DB::transaction(function () use ($placement, $questionData, $lesson) {
        if ($placement['checkpoint_placement'] === 'inside_topic') {
            $parentTopic = $lesson->topics()
                ->where('type', '!=', 'interactive_checkpoint')
                ->findOrFail($placement['parent_topic_id']);
            $this->authorize('update', $parentTopic);
            $blockUuid = (string) Str::uuid();
            $question = $this->questionAuthoring->createQuestion($questionData, [
                'quiz_id' => null,
                'checkpoint_topic_id' => $parentTopic->id,
                'checkpoint_block_uuid' => $blockUuid,
                'order' => $parentTopic->checkpointQuestions()->count() + 1,
            ]);

            $blocks = $this->blocksForTopic($parentTopic);
            $insertAfter = (int) ($placement['insert_after_block'] ?? 0);
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
            'title' => $placement['title'],
            'type' => 'interactive_checkpoint',
            'duration' => $placement['duration'] ?? 1,
            'is_prerequisite' => false,
            'order' => $lesson->topics()->max('order') + 1,
            'interactive_config' => ['placement' => 'between_topics'],
        ]);
        $this->questionAuthoring->createQuestion($questionData, [
            'quiz_id' => null,
            'checkpoint_topic_id' => $topic->id,
            'checkpoint_block_uuid' => null,
            'order' => 1,
        ]);

        $lesson->update(['duration' => $lesson->topics()->sum('duration')]);
        $lesson->module->update(['duration_minutes' => $lesson->module->lessons()->sum('duration')]);

        return redirect()->route($this->routeName('lessons.show'), $lesson)
            ->with('success', 'Interactive checkpoint created successfully.');
    });
}
```

Do not catch `ValidationException`. Laravel must return the field errors and old active-type input. The transaction begins only after both placement and question validation have passed.

- [ ] **Step 4: Replace the simplified create fields with the shared checkpoint wrapper**

In `resources/views/instructor/topics/create.blade.php`, replace the current placement controls with this Alpine-backed block so only the active placement selector submits:

```blade
<div x-data="{ placement: @js(old('checkpoint_placement', 'between_topics')) }" class="mb-6 space-y-4">
    <fieldset>
        <legend class="mb-3 text-sm font-semibold text-gray-900">Checkpoint Placement</legend>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="rounded-xl border border-gray-200 p-4" :class="placement === 'inside_topic' && 'border-purple-300 bg-purple-50'">
                <input type="radio" name="checkpoint_placement" value="inside_topic" x-model="placement" class="text-purple-600 focus:ring-purple-500">
                <span class="ml-2 font-semibold">Inside Topic</span>
                <span class="mt-1 block text-sm text-gray-500">Place this checkpoint within a selected Topic's content.</span>
            </label>
            <label class="rounded-xl border border-gray-200 p-4" :class="placement === 'between_topics' && 'border-purple-300 bg-purple-50'">
                <input type="radio" name="checkpoint_placement" value="between_topics" x-model="placement" class="text-purple-600 focus:ring-purple-500">
                <span class="ml-2 font-semibold">Between Topics</span>
                <span class="mt-1 block text-sm text-gray-500">Place this checkpoint as a separate step in the Lesson flow.</span>
            </label>
        </div>
    </fieldset>
    <div x-show="placement === 'inside_topic'">
        <label for="parent_topic_id" class="block text-sm font-medium text-gray-700 mb-2">Containing Topic</label>
        <select id="parent_topic_id" name="parent_topic_id" :disabled="placement !== 'inside_topic'"
            class="w-full rounded-xl border-gray-200 focus:border-purple-400 focus:ring-purple-300">
            @foreach($lesson->topics->where('type', '!=', 'interactive_checkpoint') as $lessonTopic)
                <option value="{{ $lessonTopic->id }}" @selected((int) old('parent_topic_id') === $lessonTopic->id)>{{ $lessonTopic->title }}</option>
            @endforeach
        </select>
        @error('parent_topic_id') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
    </div>
</div>
```

Inside the Interactive Checkpoint card, add the standard error summary and include:

```blade
@if($errors->any() && old('type') === 'interactive_checkpoint')
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4" role="alert">
        <p class="text-sm font-semibold text-red-800">Please fix the checkpoint configuration.</p>
        <ul class="mt-1 list-inside list-disc text-xs text-red-700">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@include('instructor.quizzes.partials.question-fields', [
    'selectedType' => old('question_type', 'multiple_choice'),
    'allowTypeSwitch' => true,
    'showPoints' => false,
    'showExplanation' => true,
    'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
])
```


- [ ] **Step 5: Verify admin and instructor creation plus the frontend build**

Run:

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
node --test tests/JavaScript/question-authoring.test.mjs
npm run build
```

Expected: all checkpoint authoring and Node tests PASS; Vite succeeds.

- [ ] **Step 6: Commit checkpoint creation parity**

```bash
git add app/Http/Controllers/Instructor/TopicController.php resources/views/instructor/topics/create.blade.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
git commit -m "feat: mirror quiz checkpoint creation"
```

---

### Task 5: Add Placement-Safe Checkpoint Editing

**Files:**

- Modify: `app/Http/Controllers/Instructor/TopicController.php`
- Modify: `app/Http/Controllers/Instructor/LessonController.php`
- Modify: `routes/instructor.php`
- Modify: `routes/admin.php`
- Create: `resources/views/instructor/topics/edit-checkpoint.blade.php`
- Modify: `resources/views/instructor/topics/edit.blade.php`
- Modify: `resources/views/instructor/lessons/show.blade.php`
- Modify: `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`

**Interfaces:**

- Consumes: existing `topics.edit` / `topics.update` routes for Between Topics, and the shared question component.
- Produces: `topics.checkpoints.edit` and `topics.checkpoints.update` routes for embedded Inside Topic questions.
- Identity invariant: neither update path creates a new `QuizQuestion`, changes `checkpoint_topic_id`, changes `checkpoint_block_uuid`, reorders a block, or changes placement.

- [ ] **Step 1: Write failing edit, identity, and ownership tests**

Add the following tests to `tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php`; use the existing `authoringFixture()` and create options with `createMany()`:

```php
public function test_between_topic_checkpoint_edit_updates_same_question_and_keeps_placement(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'interactive_checkpoint',
        'title' => 'Original title',
        'duration' => 1,
        'interactive_config' => ['placement' => 'between_topics'],
    ]);
    $question = QuizQuestion::create([
        'quiz_id' => null,
        'checkpoint_topic_id' => $topic->id,
        'checkpoint_block_uuid' => null,
        'question_text' => 'Original question',
        'question_type' => 'true_false',
        'points' => 1,
        'order' => 1,
    ]);
    $question->options()->createMany([
        ['option_text' => 'True', 'is_correct' => true, 'order' => 0],
        ['option_text' => 'False', 'is_correct' => false, 'order' => 1],
    ]);

    $this->actingAs($instructor)
        ->get(route('instructor.topics.edit', $topic))
        ->assertOk()
        ->assertSee('Between Topics')
        ->assertSee('Original question');

    $this->actingAs($instructor)
        ->put(route('instructor.topics.update', $topic), [
            'title' => 'Updated title',
            'duration' => 2,
            'question_type' => 'identification',
            'question_text' => '<p>Name the concept.</p>',
            'points' => 1,
            'acceptable_answers' => ['Consent'],
            'explanation' => 'Updated explanation.',
            'checkpoint_placement' => 'inside_topic',
        ])
        ->assertRedirect(route('instructor.lessons.show', $lesson));

    $this->assertSame($question->id, $topic->checkpointQuestion()->value('id'));
    $this->assertSame('between_topics', $topic->refresh()->interactive_config['placement']);
    $this->assertSame('identification', $question->refresh()->question_type);
    $this->assertNull($question->checkpoint_block_uuid);
}

public function test_inside_topic_checkpoint_edit_preserves_block_uuid_and_position(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'content_blocks' => [
            ['type' => 'rich_text', 'html' => '<p>Before</p>'],
            ['type' => 'checkpoint', 'uuid' => 'block-uuid', 'question_id' => 999],
            ['type' => 'rich_text', 'html' => '<p>After</p>'],
        ],
    ]);
    $question = QuizQuestion::create([
        'quiz_id' => null,
        'checkpoint_topic_id' => $topic->id,
        'checkpoint_block_uuid' => 'block-uuid',
        'question_text' => 'Old question',
        'question_type' => 'multiple_choice',
        'points' => 1,
        'order' => 1,
    ]);
    $blocks = $topic->content_blocks;
    $blocks[1]['question_id'] = $question->id;
    $topic->update(['content_blocks' => $blocks]);
    $question->options()->createMany([
        ['option_text' => 'A', 'is_correct' => true, 'order' => 0],
        ['option_text' => 'B', 'is_correct' => false, 'order' => 1],
    ]);

    $this->actingAs($instructor)
        ->put(route('instructor.topics.checkpoints.update', [$topic, $question]), [
            'question_type' => 'multiple_select',
            'question_text' => '<p>Choose two.</p>',
            'points' => 1,
            'options' => ['A', 'B', 'C'],
            'correct_options' => [0, 2],
            'explanation' => 'Two answers are correct.',
            'checkpoint_placement' => 'between_topics',
        ])
        ->assertRedirect(route('instructor.lessons.show', $lesson));

    $this->assertSame($question->id, $question->refresh()->id);
    $this->assertSame('block-uuid', $question->checkpoint_block_uuid);
    $this->assertSame($blocks, $topic->refresh()->content_blocks);
    $this->assertCount(2, $question->options()->where('is_correct', true)->get());
}

public function test_inside_checkpoint_route_rejects_a_question_from_another_topic(): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text']);
    $otherTopic = LessonTopic::factory()->create(['lesson_id' => $lesson->id, 'type' => 'text']);
    $question = QuizQuestion::create([
        'quiz_id' => null,
        'checkpoint_topic_id' => $otherTopic->id,
        'checkpoint_block_uuid' => 'other-block',
        'question_text' => 'Other question',
        'question_type' => 'identification',
        'acceptable_answers' => 'answer',
        'points' => 1,
        'order' => 1,
    ]);

    $this->actingAs($instructor)
        ->get(route('instructor.topics.checkpoints.edit', [$topic, $question]))
        ->assertNotFound();
}

public function test_admin_can_edit_an_admin_owned_checkpoint_through_admin_routes(): void
{
    [$admin, $lesson] = $this->authoringFixture('admin');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'interactive_checkpoint',
        'interactive_config' => ['placement' => 'between_topics'],
    ]);
    $question = QuizQuestion::create([
        'quiz_id' => null,
        'checkpoint_topic_id' => $topic->id,
        'question_text' => 'Admin checkpoint',
        'question_type' => 'true_false',
        'points' => 1,
        'order' => 1,
    ]);
    $question->options()->createMany([
        ['option_text' => 'True', 'is_correct' => true, 'order' => 0],
        ['option_text' => 'False', 'is_correct' => false, 'order' => 1],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.topics.edit', $topic))
        ->assertOk()
        ->assertSee('Admin checkpoint');
    $this->actingAs($admin)
        ->put(route('admin.topics.update', $topic), [
            'title' => 'Updated by admin',
            'duration' => 1,
            'question_type' => 'true_false',
            'question_text' => '<p>Updated statement.</p>',
            'points' => 1,
            'correct_options' => [1],
            'explanation' => '',
        ])
        ->assertRedirect(route('admin.lessons.show', $lesson));

    $this->assertTrue($question->refresh()->options[1]->is_correct);
}

public function test_instructor_cannot_edit_another_instructors_checkpoint(): void
{
    [$owner, $lesson] = $this->authoringFixture('instructor');
    $other = User::factory()->create(['role' => 'instructor']);
    $other->assignRole('instructor');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'interactive_checkpoint',
        'interactive_config' => ['placement' => 'between_topics'],
    ]);
    QuizQuestion::create([
        'quiz_id' => null,
        'checkpoint_topic_id' => $topic->id,
        'question_text' => 'Owned by someone else',
        'question_type' => 'identification',
        'acceptable_answers' => 'answer',
        'points' => 1,
        'order' => 1,
    ]);

    $this->actingAs($other)
        ->get(route('instructor.topics.edit', $topic))
        ->assertForbidden();
}
```

Use the existing six-case `checkpointPayloadProvider()` for this explicit edit test:

```php
/**
 * @dataProvider checkpointPayloadProvider
 */
public function test_between_topic_checkpoint_can_edit_to_every_question_type(string $type, array $payload): void
{
    [$instructor, $lesson] = $this->authoringFixture('instructor');
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => 'interactive_checkpoint',
        'title' => 'Editable checkpoint',
        'duration' => 1,
        'interactive_config' => ['placement' => 'between_topics'],
    ]);
    $question = QuizQuestion::create([
        'quiz_id' => null,
        'checkpoint_topic_id' => $topic->id,
        'checkpoint_block_uuid' => null,
        'question_text' => 'Old question',
        'question_type' => 'multiple_choice',
        'points' => 1,
        'order' => 1,
    ]);
    $question->options()->createMany([
        ['option_text' => 'Old A', 'is_correct' => true, 'order' => 0],
        ['option_text' => 'Old B', 'is_correct' => false, 'order' => 1],
    ]);

    $this->actingAs($instructor)
        ->put(route('instructor.topics.update', $topic), array_merge([
            'title' => 'Edited checkpoint',
            'duration' => 2,
            'question_type' => $type,
            'question_text' => str_starts_with($type, 'fill_blank') ? '_____ follows _____.' : '<p>Edited question</p>',
            'points' => 1,
            'explanation' => 'Edited explanation.',
        ], $payload))
        ->assertRedirect(route('instructor.lessons.show', $lesson));

    $question->refresh();
    $this->assertSame($type, $question->question_type);
    $this->assertSame('Edited explanation.', $question->explanation);
    $this->assertSame($topic->id, $question->checkpoint_topic_id);
    $this->assertSame('between_topics', $topic->refresh()->interactive_config['placement']);

    if (in_array($type, ['multiple_choice', 'true_false', 'multiple_select'], true)) {
        $expectedCount = $type === 'true_false' ? 2 : count($payload['options']);
        $this->assertCount($expectedCount, $question->options()->get());
        $this->assertNull($question->acceptable_answers);
    } else {
        $this->assertCount(0, $question->options()->get());
        $separator = $type === 'identification' ? '|' : ';';
        $this->assertSame(implode($separator, $payload['acceptable_answers']), $question->acceptable_answers);
    }
}
```

- [ ] **Step 2: Run the edit tests and verify the routes/behavior are missing**

Run:

```bash
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php --filter=edit
```

Expected: FAIL because embedded checkpoint edit routes do not exist and the current topic update path does not update questions.

- [ ] **Step 3: Add matching instructor and admin routes**

Place these routes before each panel's `Route::resource('topics', ...)` declaration so the resource `{topic}` binding cannot absorb the `checkpoints` segment:

```php
Route::get('topics/{topic}/checkpoints/{question}/edit', [Instructor\TopicController::class, 'editCheckpoint'])
    ->name('topics.checkpoints.edit');
Route::put('topics/{topic}/checkpoints/{question}', [Instructor\TopicController::class, 'updateCheckpoint'])
    ->name('topics.checkpoints.update');
```

Use the same controller and route suffixes in `routes/instructor.php` and `routes/admin.php`; their existing route groups provide the `instructor.` and `admin.` prefixes.

- [ ] **Step 4: Branch the existing topic edit/update path for Between Topics checkpoints**

At the beginning of `TopicController::edit()`, after authorization and loading `lesson`, add:

```php
if ($topic->type === 'interactive_checkpoint') {
    $question = $topic->checkpointQuestion()->with('options')->firstOrFail();

    return view('instructor.topics.edit-checkpoint', [
        'topic' => $topic,
        'question' => $question,
        'placement' => 'between_topics',
        'formAction' => route($this->routeName('topics.update'), $topic),
    ]);
}
```

At the beginning of `TopicController::update()`, after authorization, add:

```php
if ($topic->type === 'interactive_checkpoint') {
    return $this->updateBetweenTopicCheckpoint($request, $topic);
}
```

Implement the private updater with fixed placement and a shared validation call:

```php
private function updateBetweenTopicCheckpoint(Request $request, LessonTopic $topic)
{
    $topicData = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'duration' => ['required', 'integer', 'min:1'],
    ]);
    $questionData = $this->questionAuthoring->validate($request);
    $question = $topic->checkpointQuestion()->with('options')->firstOrFail();

    DB::transaction(function () use ($topic, $topicData, $question, $questionData) {
        $topic->update([
            'title' => $topicData['title'],
            'duration' => $topicData['duration'],
            'interactive_config' => ['placement' => 'between_topics'],
        ]);
        $topic->lesson->update(['duration' => $topic->lesson->topics()->sum('duration')]);
        $topic->lesson->module->update([
            'duration_minutes' => $topic->lesson->module->lessons()->sum('duration'),
        ]);
        $this->questionAuthoring->updateQuestion($question, $questionData);
    });

    return redirect()->route($this->routeName('lessons.show'), $topic->lesson)
        ->with('success', 'Interactive checkpoint updated successfully.');
}
```

- [ ] **Step 5: Implement the Inside Topic ownership-safe edit/update endpoints**

Add these public methods to `TopicController`:

```php
public function editCheckpoint(LessonTopic $topic, QuizQuestion $question)
{
    $this->authorize('update', $topic);
    $this->ensureAdminCanMutateTopic($topic);
    $topic->load('lesson');
    $this->assertInsideCheckpointBelongsToTopic($topic, $question);

    return view('instructor.topics.edit-checkpoint', [
        'topic' => $topic,
        'question' => $question->load('options'),
        'placement' => 'inside_topic',
        'formAction' => route($this->routeName('topics.checkpoints.update'), [$topic, $question]),
    ]);
}

public function updateCheckpoint(Request $request, LessonTopic $topic, QuizQuestion $question)
{
    $this->authorize('update', $topic);
    $this->ensureAdminCanMutateTopic($topic);
    $this->assertInsideCheckpointBelongsToTopic($topic, $question);
    $questionData = $this->questionAuthoring->validate($request);

    $this->questionAuthoring->updateQuestion($question, $questionData);

    return redirect()->route($this->routeName('lessons.show'), $topic->lesson)
        ->with('success', 'Interactive checkpoint updated successfully.');
}

private function assertInsideCheckpointBelongsToTopic(LessonTopic $topic, QuizQuestion $question): void
{
    abort_unless(
        (int) $question->checkpoint_topic_id === (int) $topic->id
        && $question->checkpoint_block_uuid !== null
        && collect($this->blocksForTopic($topic))->contains(fn ($block) =>
            ($block['type'] ?? null) === 'checkpoint'
            && ($block['uuid'] ?? null) === $question->checkpoint_block_uuid
            && (int) ($block['question_id'] ?? 0) === (int) $question->id
        ),
        404,
    );
}
```

Import `App\Models\QuizQuestion`. Ignore any submitted `checkpoint_placement`, `parent_topic_id`, or block position; these routes accept only active question fields (plus title/duration on the Between Topics path).

- [ ] **Step 6: Create the shared placement-read-only edit wrapper**

Create `resources/views/instructor/topics/edit-checkpoint.blade.php` with the existing content panel layout, breadcrumb, error summary, and this form body:

```blade
@extends($contentPanelLayout ?? 'layouts.instructor-app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route($contentRoutePrefix . '.lessons.show', $topic->lesson) }}" class="hover:text-purple-600">{{ $topic->lesson->title }}</a>
        <span>/</span><span class="font-medium text-gray-700">Edit Interactive Checkpoint</span>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4" role="alert">
            <p class="text-sm font-semibold text-red-800">Please fix the following errors:</p>
            <ul class="mt-1 list-inside list-disc text-xs text-red-700">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Placement</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $placement === 'inside_topic' ? 'Inside Topic' : 'Between Topics' }}</p>
            <p class="mt-1 text-xs text-gray-500">Placement is fixed after creation.</p>
        </section>

        @if($placement === 'between_topics')
            <section class="grid gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto]">
                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700">Checkpoint Title</label>
                    <input id="title" name="title" value="{{ old('title', $topic->title) }}" required maxlength="255"
                        class="mt-2 w-full rounded-xl border-gray-200 focus:border-purple-400 focus:ring-purple-300">
                </div>
                <div>
                    <label for="duration" class="block text-sm font-semibold text-gray-700">Duration</label>
                    <input id="duration" name="duration" type="number" min="1" value="{{ old('duration', $topic->duration) }}" required
                        class="mt-2 w-full rounded-xl border-gray-200 focus:border-purple-400 focus:ring-purple-300 sm:w-28">
                </div>
            </section>
        @endif

        @include('instructor.quizzes.partials.question-fields', [
            'question' => $question,
            'selectedType' => old('question_type', $question->question_type),
            'allowTypeSwitch' => true,
            'showPoints' => false,
            'showExplanation' => true,
            'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
        ])

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route($contentRoutePrefix . '.lessons.show', $topic->lesson) }}"
                class="rounded-xl px-5 py-3 text-center text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-xl px-6 py-3 text-sm font-semibold text-white hover:opacity-90"
                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Save Checkpoint</button>
        </div>
    </form>
</div>
@endsection
```

- [ ] **Step 7: Expose embedded edit links and retire the broken generic editor**

Change the instructor `LessonController::show()` eager load to:

```php
$lesson->load([
    'module.creator',
    'topics' => fn ($query) => $query->orderBy('order')->with('checkpointQuestions'),
    'quizzes.questions',
]);
```

In `resources/views/instructor/lessons/show.blade.php`, beneath each instructional topic's action row, render its embedded checkpoints:

```blade
@foreach($topic->checkpointQuestions->whereNotNull('checkpoint_block_uuid') as $checkpoint)
    <a href="{{ $isReadOnlyAdminPanel ? '#' : route($contentRoutePrefix . '.topics.checkpoints.edit', [$topic, $checkpoint]) }}"
        @if($isReadOnlyAdminPanel) aria-disabled="true" tabindex="-1" @click.prevent @endif
        class="mt-2 inline-flex items-center gap-2 rounded-xl border border-purple-100 bg-purple-50 px-3 py-2 text-xs font-semibold text-purple-700 {{ $isReadOnlyAdminPanel ? 'pointer-events-none opacity-50' : 'hover:bg-purple-100' }}">
        Edit checkpoint: {{ \Illuminate\Support\Str::limit(strip_tags($checkpoint->question_text), 55) }}
    </a>
@endforeach
```

In `resources/views/instructor/topics/edit.blade.php`, remove `interactive_checkpoint` from the regular topic type selector and delete the old Interactive Checkpoint content section. Existing checkpoint topics are already redirected to `edit-checkpoint.blade.php`; regular topics cannot silently change placement by changing type.

- [ ] **Step 8: Run all checkpoint authoring tests and route checks**

Run:

```bash
php artisan route:list --name=topics.checkpoints
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Expected: four named routes are listed (instructor/admin × edit/update), all tests PASS, same-record assertions pass, and cross-topic access returns 404.

- [ ] **Step 9: Commit checkpoint editing**

```bash
git add app/Http/Controllers/Instructor/TopicController.php app/Http/Controllers/Instructor/LessonController.php routes/instructor.php routes/admin.php resources/views/instructor/topics/edit-checkpoint.blade.php resources/views/instructor/topics/edit.blade.php resources/views/instructor/lessons/show.blade.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
git commit -m "feat: edit checkpoint questions safely"
```

---

### Task 6: Verify All Learner Types and Formal Quiz Isolation

**Files:**

- Modify: `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`
- Modify: `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php`
- Verify: `tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php`
- Verify: `tests/Unit/Services/Learning/QuestionEvaluatorTest.php`

**Interfaces:**

- Consumes: the existing learner checkpoint submit/skip routes and `QuestionEvaluator` result envelope.
- Produces: correct initial/retry answer shapes for every learner control, working Continue feedback dismissal, and a 12-case type × placement verification matrix.
- Isolation invariant: checkpoint requests create only `InteractiveCheckpointProgress`; formal Quiz requests retain their existing attempt and shield side effects.

- [ ] **Step 1: Add failing all-type, both-placement learner coverage**

Add this data-driven test and provider to `tests/Feature/Learner/InteractiveCheckpointFlowTest.php`:

```php
/**
 * @dataProvider typeAndPlacementProvider
 */
public function test_every_question_type_works_in_both_placements(
    string $type,
    string $placement,
    array $definition,
): void {
    [$learner, $question, $correctAnswer, $wrongAnswer] = $this->typedCheckpointFixture(
        $type,
        $placement,
        $definition,
    );
    UserDailyShield::refillFull($learner);
    $shieldBefore = UserDailyShield::getShields($learner);
    $pointsBefore = (int) $learner->gamification()->value('score');

    $this->actingAs($learner)
        ->get(route('learner.lessons.show', $question->checkpointTopic->lesson))
        ->assertOk()
        ->assertSee('Quick Check')
        ->assertSee('Check Answer')
        ->assertSee('Retry')
        ->assertSee('Continue')
        ->assertSee('Skip for now')
        ->assertSee("if (type === 'multiple_select') return [];", false);

    $this->actingAs($learner)
        ->postJson(route('learner.checkpoints.submit', $question), ['answer' => $wrongAnswer])
        ->assertOk()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('status', 'incorrect')
        ->assertJsonPath('explanation', 'Why this answer is correct.');

    $this->actingAs($learner)
        ->postJson(route('learner.checkpoints.submit', $question), ['answer' => $correctAnswer])
        ->assertOk()
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('status', 'correct')
        ->assertJsonPath('explanation', 'Why this answer is correct.');

    $this->actingAs($learner)
        ->postJson(route('learner.checkpoints.skip', $question))
        ->assertOk()
        ->assertJsonPath('status', 'skipped')
        ->assertJsonPath('explanation', null);

    $this->assertSame(0, QuizAttempt::count());
    $this->assertSame($shieldBefore, UserDailyShield::getShields($learner->refresh()));
    $this->assertSame($pointsBefore, (int) $learner->gamification()->value('score'));
    $this->assertDatabaseHas('interactive_checkpoint_progress', [
        'user_id' => $learner->id,
        'quiz_question_id' => $question->id,
        'attempt_count' => 2,
        'status' => 'skipped',
    ]);
}

public static function typeAndPlacementProvider(): array
{
    $definitions = [
        'multiple_choice' => ['options' => [['A', true], ['B', false]]],
        'true_false' => ['options' => [['True', true], ['False', false]]],
        'identification' => ['answers' => 'Consent|Permission', 'correct' => 'Consent', 'wrong' => 'Pressure'],
        'fill_blank_text' => ['answers' => 'blue|Blue;sky|Sky', 'correct' => ['blue', 'sky'], 'wrong' => ['blue', 'grass']],
        'fill_blank_select' => ['answers' => 'alpha;beta', 'word_bank' => ['alpha', 'beta'], 'correct' => ['alpha', 'beta'], 'wrong' => ['beta', 'alpha']],
        'multiple_select' => ['options' => [['A', true], ['B', false], ['C', true]]],
    ];
    $cases = [];
    foreach (['inside_topic', 'between_topics'] as $placement) {
        foreach ($definitions as $type => $definition) {
            $cases["{$placement} {$type}"] = [$type, $placement, $definition];
        }
    }

    return $cases;
}
```

Implement the test-only fixture without duplicating evaluator rules:

```php
private function typedCheckpointFixture(string $type, string $placement, array $definition): array
{
    $learner = User::factory()->create(['role' => 'learner']);
    $learner->assignRole('learner');
    $module = Module::factory()->create(['is_published' => true]);
    $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
    $topic = LessonTopic::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => $placement === 'inside_topic' ? 'text' : 'interactive_checkpoint',
        'interactive_config' => ['placement' => $placement],
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
        'checkpoint_block_uuid' => $placement === 'inside_topic' ? 'typed-block' : null,
        'question_text' => str_starts_with($type, 'fill_blank') ? '_____ follows _____.' : '<p>Question text</p>',
        'question_type' => $type,
        'points' => 1,
        'order' => 1,
        'acceptable_answers' => $definition['answers'] ?? null,
        'word_bank' => $definition['word_bank'] ?? null,
        'explanation' => 'Why this answer is correct.',
    ]);

    if ($placement === 'inside_topic') {
        $topic->update(['content_blocks' => [[
            'type' => 'checkpoint',
            'uuid' => 'typed-block',
            'question_id' => $question->id,
        ]]]);
    }

    foreach ($definition['options'] ?? [] as $index => [$text, $correct]) {
        $question->options()->create([
            'option_text' => $text,
            'is_correct' => $correct,
            'order' => $index,
        ]);
    }
    $question = $question->refresh()->load('options');

    if ($type === 'multiple_select') {
        $correct = $question->options->where('is_correct', true)->pluck('id')->all();
        $wrong = [$correct[0]];
    } elseif (in_array($type, ['multiple_choice', 'true_false'], true)) {
        $correct = $question->options->firstWhere('is_correct', true)->id;
        $wrong = $question->options->firstWhere('is_correct', false)->id;
    } else {
        $correct = $definition['correct'];
        $wrong = $definition['wrong'];
    }

    return [$learner, $question, $correct, $wrong];
}
```

- [ ] **Step 2: Run the 12-case learner matrix and observe the Multiple Select failure**

Run:

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php --filter=every_question_type
```

Expected before the view fix: FAIL on the source assertion because Multiple Select initializes as `['']`; the browser would submit an extra zero-like value.

- [ ] **Step 3: Correct learner answer initialization and Continue behavior**

In `resources/views/learner/lessons/partials/interactive-checkpoint.blade.php`, replace `arrayTypes` initialization/reset with:

```js
function emptyCheckpointAnswer(type, blankCount) {
    if (type === 'multiple_select') return [];
    if (['fill_blank_text', 'fill_blank_select'].includes(type)) return Array(blankCount).fill('');
    return '';
}

function interactiveCheckpoint(config) {
    return {
        answer: emptyCheckpointAnswer(config.type, config.blankCount),
        feedback: false,
        isCorrect: null,
        explanation: null,
        chooseWord(word) {
            const index = this.answer.findIndex((value) => !value);
            this.answer[index === -1 ? this.answer.length - 1 : index] = word;
        },
        async submit() {
            const response = await fetch(config.submitUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json'},
                body: JSON.stringify({answer: this.answer}),
            });
            const data = await response.json();
            this.feedback = true;
            this.isCorrect = data.is_correct;
            this.explanation = data.explanation;
        },
        async skip() {
            await fetch(config.skipUrl, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json'},
            });
            this.feedback = false;
            this.explanation = null;
        },
        reset() {
            this.answer = emptyCheckpointAnswer(config.type, config.blankCount);
            this.feedback = false;
            this.isCorrect = null;
            this.explanation = null;
        },
        continueLearning() {
            this.feedback = false;
            this.$dispatch('checkpoint-continued', { questionId: config.questionId });
        },
    };
}
```

Pass `questionId: {{ $question->id }}` in the existing `x-data` configuration and change the Continue button to `@click="continueLearning()"`. Keep Explanation inside the feedback block, so skip never reveals it.

- [ ] **Step 4: Strengthen the formal Quiz boundary regression**

In `tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php`, retain the existing failed-formal-attempt test and add a passing case:

```php
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
```

Use `formalQuizFixture()` from both formal tests. In the failure test, select the question's non-correct option. Do not change `Learner\QuizController` to satisfy either test.

- [ ] **Step 5: Run learner, evaluator, progress, and formal Quiz regression tests**

Run:

```bash
php artisan test tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php
```

Expected: all tests PASS; the 12 data-provider cases cover every type in both placements, formal attempts still exist, failed formal attempts still drain one shield, and checkpoints change neither shields nor gamification score.

- [ ] **Step 6: Commit learner and isolation coverage**

```bash
git add resources/views/learner/lessons/partials/interactive-checkpoint.blade.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
git commit -m "test: cover checkpoint learner parity"
```

---

### Task 7: Run Full Regression and Responsive End-to-End Verification

**Files:**

- Create: `docs/superpowers/verification/2026-08-25-interactive-checkpoint-question-configuration-refinement-e2e.md`
- Verify: all files changed in Tasks 1-6

**Interfaces:**

- Consumes: the completed shared server/UI implementation, existing local authenticated browser session, and repository test/build commands.
- Produces: reproducible automated output plus a completed desktop/mobile evidence record.
- Completion rule: a failed check is implementation work, not a documentation exception; fix it in the owning task and rerun the full gate.

- [ ] **Step 1: Inspect the final diff for accidental scope expansion**

Run:

```bash
git status --short
git diff -- app/Services/Learning/QuestionAuthoringService.php app/Http/Controllers/Instructor/QuizManagementController.php app/Http/Controllers/Instructor/TopicController.php app/Http/Controllers/Instructor/LessonController.php resources/js/app.js resources/js/question-authoring.js resources/views/instructor/quizzes resources/views/instructor/topics resources/views/instructor/lessons/show.blade.php resources/views/learner/lessons/partials/interactive-checkpoint.blade.php routes/instructor.php routes/admin.php tests/Unit/Services/Learning tests/Feature/Instructor tests/Feature/Learner
git diff --check
```

Expected: only planned source/test files plus pre-existing unrelated files are listed; `git diff --check` prints nothing and exits 0. Confirm there is no change to Quiz scoring, daily limits, shields, gamification services, certification services, migrations, or CSV parsing.

- [ ] **Step 2: Run the focused automated gate**

Run:

```bash
node --test tests/JavaScript/question-authoring.test.mjs
php artisan test tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php
npm run build
```

Expected: all Node and PHPUnit tests PASS and Vite exits 0. Record the exact test counts and build result in the verification document.

- [ ] **Step 3: Run the existing formal Quiz and completion regression suites**

Run:

```bash
php artisan test tests/Feature/Learner/LearnerQuizAttemptLimitTest.php tests/Feature/Learner/LearnerQuizTimerAutoSubmitTest.php tests/Feature/Learner/LearnerQuizResultShieldPopupTest.php tests/Feature/Learner/LearnerFinalQuizCompletionFlowTest.php tests/Feature/Learner/QuizProgressionUxTest.php tests/Feature/Instructor/InstructorQuizSettingsValidationTest.php tests/Feature/Instructor/InstructorQuizAttemptLimitSchemaTest.php tests/Feature/CertificatePdfFlowTest.php
```

Expected: all existing formal Quiz, shield, attempt-limit, timer, progression, and completion tests PASS without changing their assertions.

- [ ] **Step 4: Run the full test suite**

Run:

```bash
php artisan test
```

Expected: the full suite exits 0. If an unrelated pre-existing failure appears, rerun that exact test, capture its output and unchanged-file evidence, and do not describe the suite as passing.

- [ ] **Step 5: Start the application for browser verification**

Use two terminals:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev -- --host 127.0.0.1 --port 5173
```

Use the `browser:control-in-app-browser` skill at execution time because it can reuse the existing signed-in instructor/admin and learner sessions. Do not install Playwright, Cypress, or another browser runner.

- [ ] **Step 6: Verify authoring at 1440×900 and 390×844**

Run the following matrix once as an instructor and repeat the access-sensitive create/edit smoke path as an admin:

| Check | Desktop and mobile expected result |
| --- | --- |
| Multiple Choice initial state | Two rows, radios, no Remove at minimum, Add Option visible |
| Multiple Choice add/remove | Grow past four rows with no maximum; remove any row above two; removing correct clears correctness |
| Multiple Choice validation | Empty row or no/excess correct selection shows summary + inline error and does not save |
| True/False | Only read-only True/False rows and radios; no Add/Remove; exactly one saves |
| Identification | Dynamic acceptable answers, final row cannot be removed, case toggle, JPG/PNG 2 MB guidance, current image on edit |
| Fill Blank Text | Plain textarea, Insert Blank, live count, one ordered row per blank, `|` alternatives guidance |
| Fill Blank Word Bank | Plain textarea, Insert Blank, live count, comma guidance, max-10 guidance, ordered answers constrained to bank |
| Multiple Select | Dynamic rows, checkboxes, more than one Correct badge, at least one correct required |
| Explanation | Visible and optional for all checkpoint types; long text wraps without horizontal overflow |
| Points | Hidden for checkpoints and fixed to 1; visible and editable for formal Quiz questions |
| Validation return | Active type and common fields return; inactive stale fields remain absent |
| Responsive controls | Inputs take available width; row controls stack; Remove target remains at least 40×40 px; error text wraps |

At both widths, inspect keyboard focus, labels, radio/checkbox semantics, `aria-label` on Remove controls, visible and unobscured focus rings, error announcements, and that correctness is communicated by the word `Correct`, not color alone. Repeat the form at 200% zoom, in Windows High Contrast Mode, and with reduced-motion emulation; verify no keyboard trap and no horizontal scrolling. Use NVDA for a short pass over the type selector, one option group, one inline error, and the Add/Remove controls. WCAG 2.2 AA is the target, including at least 24×24 CSS-pixel controls; the planned 40px Remove targets exceed it.

- [ ] **Step 7: Exercise the exact dynamic switching sequence**

On checkpoint create and checkpoint edit, enter a rich question, explanation, and type-specific values, then execute:

```text
Multiple Choice
→ True or False
→ Identification
→ Fill in the Blanks — Text
→ Fill in the Blanks — Word Bank
→ Multiple Select
→ Multiple Choice
```

At each arrow verify the old section is removed from the DOM, the new controls and helper copy appear, common fields remain, the new type starts from defaults, and the Network request contains only the final Multiple Choice fields. Also verify rich-to-plain removes markup while preserving visible text, and switching back does not restore removed formatting.

- [ ] **Step 8: Exercise both placement workflows end to end**

For each placement, complete this path with at least one choice type and one blank type:

```text
Admin/Instructor → Create/Edit Topic → Add Interactive Checkpoint
→ Select fixed placement → Select/configure type → Add optional Explanation
→ Save → Publish/make available → Learner opens Lesson
→ Checkpoint renders → Incorrect answer → Explanation → Retry
→ Correct answer → Continue → Skip a separate checkpoint
```

For Between Topics, verify it remains a separate ordered lesson navigation item. For Inside Topic, verify it remains between the same before/after content blocks after editing. Confirm edit URLs keep placement read-only and preserve question IDs and block UUIDs.

- [ ] **Step 9: Recheck formal Quiz UI and side-effect boundaries in the browser**

Create and edit one formal Quiz question of each type. Confirm the fields, helper text, answers, points, and validation match the shared core. Submit one passing and one failing formal Quiz attempt and confirm normal result rendering, attempt history, attempt limits, and shield behavior. Then answer checkpoints and confirm no formal Quiz attempt appears, no shield changes, no Quiz XP/toast appears, and lesson/certificate eligibility still ignores optional checkpoints.

- [ ] **Step 10: Write the completed verification evidence**

Create `docs/superpowers/verification/2026-08-25-interactive-checkpoint-question-configuration-refinement-e2e.md` only after the checks run. Use the title `Interactive Checkpoint Question Configuration Refinement — E2E Verification`, date `2026-08-25`, the actual local environment, and the two tested viewports.

The document must contain:

- `Automated Results`: one row per Node, focused PHPUnit, formal Quiz regression, full PHPUnit, and Vite command, including the exact command, observed test count, exit code, and PASS/FAIL.
- `Six-Type Authoring Matrix`: rows for all six types and columns for Create, Edit, Validation, Type Switch Cleanup, Desktop, and Mobile. Every cell contains PASS or FAIL plus a concise observation.
- `Placement and Learner Matrix`: rows for Inside Topic and Between Topics and columns for Create/Edit Identity, Learner Render, Incorrect/Retry/Correct, Continue, Skip, and Explanation.
- `Formal Quiz Isolation`: observed results for authoring parity, scoring, attempt creation, daily/attempt limits, shields, gamification, completion, certification, and CSV preview/import.
- `Accessibility and Responsive Notes`: keyboard order, Enter/Space operation, focus visibility and non-obscuring behavior, programmatic labels, live error announcements, radio/checkbox semantics, 200% zoom, Windows High Contrast, reduced motion, 24×24 minimum targets, wrapping, and long-content behavior.
- `Final Result`: PASS only when every required row passes; otherwise FAIL with each exact remaining defect and reproduction path.

Do not infer browser results from source inspection or automated tests.

- [ ] **Step 11: Request a correctness review and address findings**

Use the `requesting-code-review` skill against the complete diff. The reviewer must check the approved design, all six payloads, stale-state cleanup, ownership, transaction boundaries, image deletion, Quiz regression boundaries, responsive/accessibility behavior, and test evidence. Apply each valid finding in the owning task and rerun Steps 1-4.

- [ ] **Step 12: Commit verification evidence and any reviewed corrections**

Stage only planned source/tests/docs, explicitly excluding pre-existing generated assets and media:

```bash
git add app/Services/Learning/QuestionAuthoringService.php app/Http/Controllers/Instructor/QuizManagementController.php app/Http/Controllers/Instructor/TopicController.php app/Http/Controllers/Instructor/LessonController.php resources/js/app.js resources/js/question-authoring.js resources/views/instructor/quizzes resources/views/instructor/topics resources/views/instructor/lessons/show.blade.php resources/views/learner/lessons/partials/interactive-checkpoint.blade.php routes/instructor.php routes/admin.php tests/JavaScript/question-authoring.test.mjs tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php tests/Unit/Services/Learning/QuestionEvaluatorTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php docs/superpowers/verification/2026-08-25-interactive-checkpoint-question-configuration-refinement-e2e.md
git commit -m "test: verify checkpoint question parity"
```

---

## Final Acceptance Gate

- [ ] Multiple Choice has unlimited Add Option, removable rows above a minimum of two, one correct radio, Correct text, and server rejection for invalid configurations.
- [ ] True/False renders only fixed True and False radio rows; the Multiple Choice editor is absent.
- [ ] Identification, both Fill Blank variants, and Multiple Select expose their complete Quiz-equivalent configuration and guidance.
- [ ] The exact six-type switch sequence discards every stale type-specific value in UI state and request payloads.
- [ ] Optional Explanation is present on all checkpoint create/edit states and returned only after answering.
- [ ] Server normalization, validation, persistence, and legacy loading use the canonical structures consumed by `QuestionEvaluator`.
- [ ] Both placements create and edit the same question identity without moving placement or block position.
- [ ] Instructor and admin authorization reject cross-owner and cross-topic mutation.
- [ ] Learners can answer, retry, continue, and skip every type in both placements.
- [ ] Checkpoints remain optional and do not create Quiz attempts, consume shields, award Quiz gamification, or affect certification eligibility.
- [ ] Formal Quiz authoring, scoring, attempts, limits, shields, gamification, certification, CSV import, and learner UX remain unchanged for valid behavior.
- [ ] Desktop and mobile authoring follow current platform theme, spacing, helper, validation, focus, label, semantics, and touch-target patterns.
- [ ] Focused tests, formal Quiz regressions, full PHPUnit suite, Node tests, Vite build, browser matrix, and final code review all pass with recorded evidence.
