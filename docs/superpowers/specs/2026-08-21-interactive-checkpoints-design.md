# Interactive Checkpoints Design

## Purpose

Interactive Checkpoints add optional formative questions to the existing lesson/topic learning flow. They reuse the existing quiz question types and answer rules, but they are not formal quizzes: they do not create quiz attempts, consume daily shields, affect quiz scores, affect certification eligibility, or trigger quiz gamification.

## Existing System Findings

- Lessons own ordered `LessonTopic` records through `Lesson::topics()`.
- `lesson_topics.order` drives learner topic navigation through the `?topic=N` query index.
- Topic content currently supports `video`, `text`, `worksheet`, and an existing incomplete `interactive` type.
- Text topics store rich text in `lesson_topics.text_content` and images in `image_attachments`.
- Learner topic progress is stored in `lesson_topic_progress` and is used for topic/lesson completion.
- Formal quizzes use `Quiz`, `QuizQuestion`, `QuizOption`, and `QuizAttempt`.
- Six current question types are stored in `quiz_questions.question_type`: `multiple_choice`, `true_false`, `multiple_select`, `fill_blank_text`, `fill_blank_select`, and `identification`.
- Formal quiz evaluation currently lives inside `App\Http\Controllers\Learner\QuizController::submit`.
- Daily quiz/shield behavior is enforced only in `QuizController` through `UserDailyShield`, subscription entitlement checks, `QuizAttempt`, and `GamificationService`.
- Admin content authoring reuses instructor content controllers through `ContentPanelContext`, so checkpoint authoring should extend those shared controllers and policies.

## Approved Decisions

1. Inside-topic checkpoints use precise ordered blocks inside topic content.
2. Between-topic checkpoints are represented as `lesson_topics` rows with a distinct `interactive_checkpoint` type.
3. Checkpoints reuse `QuizQuestion` and `QuizOption` records, with shared validation/evaluation extracted from quiz code.
4. Checkpoint learner state is tracked in a separate progress table.
5. Checkpoints are completed after a correct answer, an incorrect answer followed by continue, or skip.
6. Checkpoints never block topic or lesson completion.
7. Checkpoints allow unlimited retries and record latest state plus attempt count.
8. Explanation appears after answer submission, not after skip.
9. Checkpoints have no score, no XP, no shield usage, and no quiz attempt records.
10. Authoring is added to the existing topic create/edit flow and reuses question form partials.
11. Identification question images are supported for checkpoints.
12. Formal quiz logic is refactored only enough to share evaluation/validation, with regression tests.
13. Existing `interactive` topic type is preserved; checkpoints use a distinct type/config path.

## Data Model

### Reused Question Records

`QuizQuestion` and `QuizOption` remain the canonical question data structures. A checkpoint owns one `QuizQuestion`.

Add nullable checkpoint ownership columns to `quiz_questions`:

- `checkpoint_topic_id` nullable foreign key to `lesson_topics.id`, cascade on delete.
- `checkpoint_block_uuid` nullable string for inside-topic block ownership.
- `explanation` nullable text for per-checkpoint learner feedback.

Rules:

- Formal quiz questions keep `quiz_id` set and checkpoint ownership null.
- Between-topic checkpoint questions set `checkpoint_topic_id`.
- Inside-topic checkpoint questions set `checkpoint_topic_id` to the parent topic and `checkpoint_block_uuid` to the block UUID.
- Query helpers prevent mixing formal quiz questions and checkpoint questions accidentally.

The existing `quiz_id` column is currently non-null. The migration must make it nullable while preserving existing quiz records.

### Between-Topic Checkpoints

Between-topic checkpoints are stored as `lesson_topics` rows:

- `type = interactive_checkpoint`
- `title` is the navigation label.
- `duration` can default to `1`.
- `order` uses the existing topic ordering mechanism.
- `interactive_config` stores checkpoint metadata such as placement and optional display label.

This gives between-topic checkpoints native navigation ordering without introducing a full learning-items table now.

### Inside-Topic Checkpoints

Inside-topic placement uses a new JSON column on `lesson_topics`:

- `content_blocks` nullable JSON.

For rich text/media topics, the controller stores ordered blocks like:

```json
[
  {"type":"rich_text","html":"<p>Consent means...</p>"},
  {"type":"checkpoint","uuid":"01J...","question_id":123},
  {"type":"rich_text","html":"<p>Continue learning...</p>"}
]
```

Existing topics do not need migration into blocks. If `content_blocks` is null, rendering falls back to the current topic rendering exactly as it does now.

### Checkpoint Progress

Create `interactive_checkpoint_progress`:

- `id`
- `user_id`
- `lesson_topic_id`
- `quiz_question_id`
- `checkpoint_block_uuid` nullable
- `status` enum/string: `not_attempted`, `attempted`, `correct`, `incorrect`, `skipped`
- `latest_answer` JSON nullable
- `is_correct` boolean nullable
- `attempt_count` unsigned integer default 0
- `answered_at` timestamp nullable
- `skipped_at` timestamp nullable
- `completed_at` timestamp nullable
- timestamps
- unique index on `user_id`, `quiz_question_id`

Progress is separate from `lesson_topic_progress` and `quiz_attempts`. `completed_at` distinguishes completed checkpoint interactions from merely attempted ones.

## Domain Services

### Question Evaluation

Create `App\Services\Learning\QuestionEvaluator`.

It accepts a loaded `QuizQuestion` and raw answer input, then returns:

```php
[
    'selected' => mixed,
    'correct' => mixed,
    'is_correct' => bool,
    'type' => string,
    'case_sensitive' => bool|null,
    'image_url' => string|null,
]
```

It moves the existing answer checks out of `Learner\QuizController::submit` without changing quiz behavior:

- Exact selected option match for `multiple_choice` and `true_false`.
- Exact selected set match for `multiple_select`.
- Pipe/semicolon answer matching for `fill_blank_text`.
- Ordered word matching for `fill_blank_select`.
- Acceptable answer matching for `identification`.

`QuizController::submit` uses this service to calculate quiz results. The new checkpoint controller uses the same service for immediate feedback.

### Question Validation and Persistence

Create `App\Services\Learning\QuestionAuthoringService`.

Responsibilities:

- Return shared validation rules for all six question types.
- Normalize acceptable answers, word bank, case sensitivity, options, correct options, and identification images.
- Create/update a `QuizQuestion` plus related `QuizOption` rows.

`QuizManagementController` uses this service for formal quiz questions. `TopicController` or a dedicated checkpoint authoring controller uses the same service for checkpoint questions.

## Authoring Design

The existing topic create/edit forms gain an `Interactive Checkpoint` topic type card. The form then shows:

- Placement selector with two options:
  - Inside Topic: "Place this checkpoint within the selected Topic's content."
  - Between Topics: "Place this checkpoint between Topics as a separate step in the Lesson learning flow."
- Question type selector with the six existing question types.
- Question text.
- Existing dynamic question-specific answer fields.
- Optional explanation field.
- Save action.

For between-topic placement, saving creates or updates a `LessonTopic` with `type=interactive_checkpoint` and creates or updates the owned checkpoint question.

For inside-topic placement, saving updates the selected parent topic's `content_blocks` and creates or updates the owned checkpoint question. The authoring UI must preserve the existing TinyMCE/text/image/video/file flow. Existing content remains editable using current fields, and block editing is introduced only when checkpoints are added.

Admin and instructor permissions use existing `Lesson`/`LessonTopic` policies and `ContentOwnershipGuard`. No new authoring permission is required.

## Learner Experience

### Shared Checkpoint Component

Create a Blade partial for checkpoint rendering, used by both placements:

- Label: "Quick Check"
- Question text and optional image.
- Type-specific answer controls using the same UI behavior as quiz questions.
- Check Answer button.
- Skip for now action.
- Correct/incorrect feedback.
- Explanation after submitted answers when configured.
- Retry button after incorrect answers.
- Continue button after answer or skip.

The component posts to checkpoint-specific learner routes and returns JSON for immediate feedback. It must not submit to quiz routes.

### Inside Topic

`learner.lessons.partials.topic-page` renders `content_blocks` when present. Rich text blocks render with the current prose styling. Checkpoint blocks render the shared checkpoint component in the correct position. If `content_blocks` is null, current topic rendering is unchanged.

Inside-topic checkpoints do not appear as separate sidebar navigation entries.

### Between Topics

Between-topic checkpoint topics render as the current topic page with the shared checkpoint component as the main content. The sidebar shows them in order with a "Quick Check" label/icon and separate completion state.

Because they are optional, completing/skipping the checkpoint records checkpoint progress but normal topic completion must still be possible. The bottom action bar continues to allow learners forward movement.

## Navigation and Progress

The existing `lesson_topics.order` model remains the lesson sequence source for v1.

For progress:

- Formal topic progress remains in `lesson_topic_progress`.
- Checkpoint interaction progress is read from `interactive_checkpoint_progress`.
- Lesson progress/certificate eligibility continues to use instructional topic completion and formal quiz completion only. Queries that decide lesson completion, module completion, or certificate eligibility must exclude `interactive_checkpoint` topic rows.
- Between-topic checkpoint rows may be marked complete in `lesson_topic_progress` only to keep existing sidebar/progress UI coherent, but that completion must be triggered by skip/continue and must not affect lesson completion, certificate eligibility, quiz eligibility, or shields.
- Inside-topic checkpoint progress is never counted as topic completion.

Sequential prerequisite locking remains topic-based. Checkpoints are never treated as blockers.

## Access Rules

Learners may view and submit a checkpoint only when:

- The parent lesson is published.
- The module is learner-visible.
- The learner has an approved enrollment for the module.
- The checkpoint belongs to the requested lesson/topic.

Unpublished/deactivated modules inherit existing lesson access behavior.

## Deletion and Editing

- Deleting a between-topic checkpoint topic cascades its checkpoint question and progress.
- Deleting a parent topic cascades inside-topic checkpoint questions and progress through `checkpoint_topic_id`.
- Editing a checkpoint updates the same `QuizQuestion` where possible. Existing learner progress is not rewritten; future submissions overwrite latest checkpoint progress.
- Reordering topics uses existing `topics.reorder`; between-topic checkpoints move like topics.
- Moving an inside-topic checkpoint updates `content_blocks` order only.

## Formal Quiz Regression Protection

The implementation must include tests proving:

- Formal quiz attempts still create `QuizAttempt`.
- Formal quiz submission still drains/refunds shields exactly as before.
- Formal quiz scoring for all six question types remains unchanged.
- Checkpoint submission creates no `QuizAttempt`.
- Checkpoint submission never calls `UserDailyShield::drainShield`.
- Checkpoint submission does not award quiz pass/attempt gamification points.

## Testing Strategy

Feature tests:

- Admin can create between-topic checkpoints using each question type.
- Instructor can create checkpoints for authorized lessons.
- Unauthorized instructors cannot mutate lessons they do not own.
- Learner can submit correct checkpoint answers and receives correct feedback.
- Learner can submit incorrect checkpoint answers, see explanation when present, retry, and continue.
- Learner can skip without being marked incorrect.
- Checkpoint submission does not change shield count.
- Formal quiz shield behavior remains unchanged.
- Between-topic checkpoints appear in ordered lesson navigation.
- Inside-topic checkpoints render in `content_blocks` order.
- Existing topics with null `content_blocks` render through the legacy path.

Unit tests:

- `QuestionEvaluator` covers all six question types.
- `QuestionAuthoringService` validates and persists all six question types.

Responsive/manual checks:

- Desktop, tablet, and mobile for option lists, multiple select, word bank blanks, feedback, explanation, and sidebar navigation.

## Out of Scope

- Analytics dashboard for checkpoint data.
- New interactive activity types beyond checkpoints.
- Mandatory checkpoints.
- Checkpoint scoring, badges, XP, shields, certification rules, or quiz attempt limits.
- Replacing the existing topic authoring flow.
- Replacing formal quiz pages.
