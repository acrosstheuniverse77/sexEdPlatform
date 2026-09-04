# Interactive Checkpoints End-to-End Verification

Use this checklist to verify the Interactive Checkpoints implementation from database through instructor authoring, learner interaction, and quiz regression behavior.

## Scope

This verifies:

- Between-topic Interactive Checkpoints as their own lesson step.
- Inside-topic Interactive Checkpoints inside ordered topic content blocks.
- All six supported question types:
  - `multiple_choice`
  - `true_false`
  - `multiple_select`
  - `fill_blank_text`
  - `fill_blank_select`
  - `identification`
- Checkpoint submit, retry, skip, explanation, and progress behavior.
- Isolation from formal quiz attempts, shields, XP, lesson completion, and certificate rules.

## Prerequisites

Run from the project root:

```bash
composer install
npm install
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\TestUserSeeder
```

Reusable seeded accounts, if your local database has the test seeder:

- Instructor: `instructor@test.local`
- Learner: `adult@test.local`
- Premium learner: `premium.learner@test.local`
- Password: `password123`

Start the app:

```bash
php artisan serve
npm run dev
```

On Windows PowerShell, use `npm.cmd run dev` if script execution policy blocks `npm run dev`.

## Automated Verification

Run the checkpoint-specific suite:

```bash
php vendor/bin/phpunit --do-not-cache-result tests/Unit/Services/Learning/QuestionEvaluatorTest.php tests/Unit/Services/Learning/QuestionAuthoringServiceTest.php tests/Feature/Learner/InteractiveCheckpointSchemaTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
```

Run quiz and lesson regressions:

```bash
php vendor/bin/phpunit --do-not-cache-result tests/Feature/Learner/LearnerQuizAttemptLimitTest.php tests/Feature/Learner/QuizProgressionUxTest.php tests/Feature/Learner/LearnerQuizTimerAutoSubmitTest.php tests/Feature/Learner/LessonPageTest.php tests/Feature/Learner/LearnerFinalQuizCompletionFlowTest.php
```

Run the full suite before release:

```bash
php vendor/bin/phpunit --do-not-cache-result
```

Build production assets:

```bash
npm run build
```

On Windows PowerShell:

```bash
npm.cmd run build
```

If `php artisan test` or `composer test` reports a Symfony Process cwd error, use the direct PHPUnit commands above.

## Manual Authoring Verification

### 1. Create A Between-Topic Checkpoint

1. Sign in as an instructor.
2. Open `http://localhost:8000/instructor/modules`.
3. Open an editable module, then an editable lesson.
4. Click add topic or open `http://localhost:8000/instructor/topics/create?lesson={LESSON_ID}`.
5. Select `Interactive Checkpoint`.
6. Select `Between Topics`.
7. Enter a title, for example `Consent Quick Check`.
8. Choose `True/False`.
9. Enter question text: `Consent can be withdrawn at any time.`
10. Mark the correct answer as `True`.
11. Add explanation: `Consent remains valid only while it is freely given.`
12. Save.

Expected:

- Save succeeds with no validation error.
- The lesson topic list shows the checkpoint in normal lesson order.
- The checkpoint has a `Quick Check` style label or equivalent checkpoint label.

Database spot check:

```bash
php artisan tinker
```

```php
App\Models\LessonTopic::where('type', 'interactive_checkpoint')->latest('id')->first(['id', 'lesson_id', 'title', 'type', 'interactive_config']);
App\Models\QuizQuestion::checkpoint()->latest('id')->first(['id', 'quiz_id', 'checkpoint_topic_id', 'question_type', 'explanation']);
```

Expected:

- `lesson_topics.type` is `interactive_checkpoint`.
- `quiz_questions.quiz_id` is `null`.
- `quiz_questions.checkpoint_topic_id` is set.
- `explanation` is stored.

### 2. Create An Inside-Topic Checkpoint

1. Open the same lesson in the instructor panel.
2. Add or edit a normal text topic.
3. Select `Interactive Checkpoint`.
4. Select `Inside Topic`.
5. Choose the containing topic.
6. Choose `Multiple Choice`.
7. Enter question text: `Which action shows consent?`
8. Add options:
   - `Clear yes`
   - `Silence`
   - `Pressure`
9. Mark `Clear yes` as correct.
10. Add explanation: `Consent requires a clear, freely given yes.`
11. Save.

Expected:

- Save succeeds.
- The containing topic remains editable.
- The checkpoint is stored as a content block and does not become a separate sidebar topic.

Database spot check:

```php
$topic = App\Models\LessonTopic::whereNotNull('content_blocks')->latest('id')->first();
$topic->content_blocks;
App\Models\QuizQuestion::checkpoint()->where('checkpoint_topic_id', $topic->id)->latest('id')->first(['id', 'checkpoint_block_uuid', 'question_type']);
```

Expected:

- `content_blocks` contains a `checkpoint` block.
- The checkpoint question has `checkpoint_topic_id` set to the containing topic.
- `checkpoint_block_uuid` is set.

## Manual Learner Verification

Before starting, make sure the module is published and the learner is enrolled or otherwise allowed to view it.

### 3. View A Between-Topic Checkpoint

1. Sign in as a learner.
2. Open `http://localhost:8000/learn/modules`.
3. Open the module.
4. Open the lesson containing the checkpoint.
5. Navigate to the checkpoint step in the lesson sidebar or by using next navigation.

Expected:

- The checkpoint appears in lesson order.
- The page displays `Quick Check`.
- The question renders the correct answer controls.
- The normal quiz page is not shown.

### 4. Submit A Correct Answer

1. Select the correct answer.
2. Click `Check Answer`.

Expected:

- Feedback indicates the answer is correct.
- Explanation appears if configured.
- A continue action is available.
- No formal quiz result page appears.
- No daily shield prompt appears.

Database spot check:

```php
$user = App\Models\User::where('email', 'adult@test.local')->first();
App\Models\InteractiveCheckpointProgress::where('user_id', $user->id)->latest('id')->first(['status', 'is_correct', 'attempt_count', 'answered_at', 'completed_at']);
App\Models\QuizAttempt::where('user_id', $user->id)->latest('id')->first();
```

Expected:

- Checkpoint progress exists.
- `status` is `correct`.
- `is_correct` is `true`.
- `attempt_count` increased.
- No new `QuizAttempt` was created for this checkpoint interaction.

### 5. Submit An Incorrect Answer And Retry

1. Open a checkpoint that has not been answered correctly.
2. Choose an incorrect answer.
3. Click `Check Answer`.
4. Confirm incorrect feedback appears.
5. Confirm explanation appears if configured.
6. Click retry.
7. Submit the correct answer.

Expected:

- Incorrect feedback appears first.
- Retry keeps the learner on the checkpoint.
- Correct retry updates the checkpoint progress.
- `attempt_count` increments again.
- No shield is consumed.
- No quiz XP or quiz attempt is awarded.

### 6. Skip A Checkpoint

1. Open a fresh checkpoint.
2. Click `Skip for now`.

Expected:

- The checkpoint is marked completed/skipped.
- No explanation appears from skipping.
- Learner can continue through the lesson.
- No `QuizAttempt` record is created.
- No shield is consumed.

Database spot check:

```php
App\Models\InteractiveCheckpointProgress::latest('id')->first(['status', 'is_correct', 'attempt_count', 'skipped_at', 'completed_at']);
```

Expected:

- `status` is `skipped`.
- `is_correct` is `null` or falsey.
- `attempt_count` does not increment for skip.
- `skipped_at` and `completed_at` are set.

### 7. View An Inside-Topic Checkpoint

1. Open the learner lesson page.
2. Navigate to the normal topic that contains the checkpoint block.

Expected:

- Normal rich text still renders.
- The checkpoint appears in the correct position inside the topic content.
- The checkpoint does not appear as its own sidebar topic.
- Submit, retry, and skip work the same as between-topic checkpoints.

## Progress And Completion Verification

### 8. Confirm Checkpoints Do Not Block Lesson Completion

1. As learner, complete all normal instructional topics.
2. Leave one or more checkpoints skipped or unanswered.
3. Open the lesson page and progress/sidebar.

Expected:

- Normal topic completion still works.
- Lesson progress is based on instructional topics, not checkpoint rows.
- Certificate/formal completion requirements ignore checkpoints.
- Between-topic checkpoints may show their own local complete/skipped state, but they do not change lesson completion math.

Tinker check:

```php
$lesson = App\Models\Lesson::latest('id')->first();
$lesson->topics()->count();
$lesson->topics()->instructional()->count();
```

Expected:

- `topics()->count()` may include checkpoint rows.
- `topics()->instructional()->count()` excludes `interactive_checkpoint`.

## Formal Quiz Regression Verification

### 9. Submit A Formal Quiz

1. As learner, open a lesson with a formal quiz.
2. Start the quiz.
3. Submit answers.

Expected:

- A `QuizAttempt` is created.
- Quiz scoring still works for all existing question types.
- Daily shield rules still apply to quiz attempts.
- Quiz result and pass/fail behavior are unchanged.

Database spot check:

```php
$user = App\Models\User::where('email', 'adult@test.local')->first();
App\Models\QuizAttempt::where('user_id', $user->id)->latest('id')->first(['quiz_id', 'score', 'passed', 'completed_at']);
App\Models\QuizQuestion::formalQuiz()->count();
App\Models\QuizQuestion::checkpoint()->count();
```

Expected:

- Formal quiz questions still have `quiz_id`.
- Checkpoint questions are separate through checkpoint ownership columns.
- Formal quiz attempts are only created by quiz submission, not checkpoint submission.

## API Verification

Use browser dev tools or an HTTP client while signed in as a learner.

Checkpoint routes:

- `POST /learn/checkpoints/{question}/submit`
- `POST /learn/checkpoints/{question}/skip`

Submit payload examples:

```json
{"answer":"true"}
```

```json
{"answer":["1","3"]}
```

```json
{"answer":["consent","boundaries"]}
```

Expected submit response shape:

```json
{
  "is_correct": true,
  "status": "correct",
  "explanation": "..."
}
```

Expected skip response shape:

```json
{
  "status": "skipped"
}
```

Access checks:

- Signed-out requests redirect or fail auth.
- Learners without module access cannot submit.
- Checkpoints from unpublished or inaccessible modules cannot be submitted.

## Release Checklist

- [ ] Migrations run successfully.
- [ ] Instructor can create between-topic checkpoint.
- [ ] Instructor can create inside-topic checkpoint.
- [ ] Learner can answer correctly.
- [ ] Learner can answer incorrectly, see explanation, and retry.
- [ ] Learner can skip.
- [ ] Checkpoints create `interactive_checkpoint_progress`.
- [ ] Checkpoints do not create `quiz_attempts`.
- [ ] Checkpoints do not consume shields.
- [ ] Checkpoints do not award quiz gamification points.
- [ ] Existing formal quizzes still create attempts and score correctly.
- [ ] Lesson completion and certificate eligibility ignore checkpoint rows.
- [ ] Existing topics without `content_blocks` still render normally.
- [ ] Full PHPUnit suite passes.
- [ ] Production asset build passes.
