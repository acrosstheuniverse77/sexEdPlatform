# Interactive Checkpoint QA Refinement Design

**Date:** 2026-08-27
**Status:** Approved
**Scope:** Interactive Checkpoint authoring, learner rendering, feedback, optional progression, Word Bank interaction, Lesson navigation, Topic removal, and regression protection

## 1. Purpose

Interactive Checkpoints are optional formative activities placed either inside a Topic or between Topics. The existing implementation reuses the formal Quiz question model and evaluator, but several authoring, rendering, feedback, and navigation defects make checkpoints behave like required learning items or cause them to replace their parent Topic content.

This refinement preserves the shared question engine while establishing clear boundaries between:

- canonical Topic content;
- checkpoint placement and rendering;
- checkpoint-only learner progress;
- formal Quiz attempts and scoring; and
- Lesson-level navigation and completion.

The completed workflow must let an instructor create and publish a Topic with one or more optional checkpoints, then let a learner view all Topic content, answer or skip each checkpoint, receive correct feedback, and continue without changing formal Quiz or Lesson completion behavior.

## 2. Goals

The implementation must:

- distinguish correct feedback, incorrect feedback, and optional explanation;
- hide the configured explanation after an incorrect answer and show it after a correct answer;
- remove duration and prerequisite controls and behavior from checkpoints only;
- render stored question text in the appropriate editor representation;
- render inside-topic checkpoints alongside, never instead of, Topic content;
- support video, text, worksheet, interactive, and other existing Topic renderers;
- make Retry, Skip for now, and Continue state-dependent and predictable;
- expose only one forward-progression action at a time;
- provide a stable viewport-anchored Lesson footer on mobile and desktop;
- reuse the formal Quiz Word Bank interaction for inline checkpoint blanks;
- replace native Topic deletion confirmation with an accessible platform modal;
- preserve both inside-topic and between-topic checkpoint placement;
- preserve formal Quiz, Topic, Lesson, learner progress, shield, scoring, and gamification behavior; and
- protect the complete instructor-to-learner workflow with automated and browser verification.

## 3. Non-goals

This refinement will not:

- create a second question model or evaluator;
- redesign formal Quiz scoring or attempts;
- add checkpoint scores, points, shields, pass thresholds, or certification rules;
- make checkpoints required;
- convert every Topic type into a generic block-content system;
- remove duration or prerequisite support from ordinary Topics, Lessons, or Quizzes;
- add new question types; or
- undertake unrelated Lesson viewer or authoring refactors.

## 4. Architectural Approach

Use a targeted compatibility repair rather than a broad renderer rewrite or a separate checkpoint subsystem.

The following shared foundations remain authoritative:

- `QuizQuestion` and `QuizOption` define questions and options.
- `QuestionAuthoringService` validates and persists the six supported question types.
- `QuestionEvaluator` evaluates answers consistently for Quizzes and checkpoints.
- The shared instructor question-fields partial provides authoring controls.
- `InteractiveCheckpointProgress` stores checkpoint-specific learner state.

Formal Quizzes and checkpoints share question configuration and evaluation only. Checkpoints must never create or modify Quiz attempts, scores, shields, points, eligibility, or certification state.

The learner experience is separated into four responsibilities:

1. Render the canonical Topic body for its Topic type.
2. Resolve and inject checkpoint placements without replacing the Topic body.
3. Run an explicit checkpoint interaction state machine.
4. Coordinate checkpoint continuation with the Lesson footer so only one forward action is visible.

## 5. Data Model and Compatibility

### 5.1 Shared Topic columns

Between-topic checkpoints remain ordered `lesson_topics` rows. The shared `duration` and `is_prerequisite` columns therefore remain in the schema for compatibility.

Checkpoint rows use neutral values:

- `duration = 0`
- `is_prerequisite = false`

Checkpoint creation and editing do not display, accept, or depend on these settings.

### 5.2 Existing checkpoint normalization

A targeted data migration normalizes every existing `lesson_topics` row whose type is `interactive_checkpoint` to the neutral values. It preserves:

- the Topic row and its order;
- the checkpoint question and options;
- placement metadata;
- explanations; and
- existing learner checkpoint progress.

Ordinary Topics, Lessons, and Quizzes are not modified by the normalization.

### 5.3 Progress isolation

`InteractiveCheckpointProgress` is the source of truth for checkpoint status. It records:

- `not_attempted`, `incorrect`, `correct`, or `skipped` status;
- the latest evaluated answer;
- correctness;
- attempt count;
- answer and skip timestamps; and
- completion time for correct or skipped states.

Checkpoint-only Topic rows are excluded from:

- Lesson and Module duration totals;
- required Topic totals;
- completion percentages;
- sequential access locks;
- formal Quiz eligibility;
- Lesson completion; and
- certification requirements.

Legacy `LessonTopicProgress` rows for checkpoints may remain harmlessly in the database, but required-progress calculations and checkpoint UI must not depend on them.

## 6. Instructor and Admin Authoring

### 6.1 Create workflow

When the author selects `Interactive Checkpoint`:

- hide duration and prerequisite fields;
- bypass ordinary Topic duration and prerequisite validation;
- assign neutral values on the server;
- require `Inside Topic` or `Between Topics` placement;
- require a valid parent Topic for inside-topic placement;
- allow all six existing question types;
- allow an optional explanation; and
- retain existing authorization, ownership, and read-only admin restrictions.

Checkpoint-specific request handling occurs before generic Topic validation so ordinary Topic requirements cannot leak into checkpoint requests.

### 6.2 Placement and multiplicity

A normal Topic may contain multiple inside-topic checkpoints. Their block UUIDs and placement metadata preserve authored order.

A standalone between-topic checkpoint row contains one checkpoint question and participates in Lesson ordering without participating in required progress.

### 6.3 Edit workflow and question text

The dedicated checkpoint edit page continues to reuse the shared question-fields partial.

For rich question types, the stored HTML initializes the rich-text editor as formatted content. For blank-based and other plain-text types, stored HTML is converted to a human-readable representation:

- paragraph and break elements become line breaks;
- formatting tags are removed;
- HTML entities are decoded;
- blank markers such as `_____` remain intact; and
- excess whitespace is normalized without changing intended blank order.

Initial editor data is safely JSON-encoded rather than interpolated through fragile inline escaping.

Inside-topic edit and update requests verify that the question belongs to the parent Topic and that its block UUID exists in that Topic. Between-topic requests verify ownership through their standalone Topic row.

## 7. Canonical Topic Rendering and Placement

The current top-level `content_blocks` branch is the primary rendering defect: when blocks exist, it prevents the normal Topic-type renderer from executing. The corrected renderer always renders the canonical Topic body first and treats checkpoint metadata as an insertion concern.

### 7.1 Video Topics

Render in this order:

1. Topic title and metadata.
2. Instructions or description.
3. Uploaded or embedded video.
4. Ordered inside-topic checkpoints.
5. Topic-level progression area.

The video, captions, player initialization, and instructions remain available when checkpoints exist.

### 7.2 Text Topics

Preserve rich text, image attachments, gallery/slideshow behavior, captions, and zoom behavior. Where existing text-segment placement metadata is valid, place checkpoints after their authored segment. Treat a legacy Topic with one text body as one segment and place its checkpoints after that body.

The same authoritative text content must not render twice when both legacy columns and placement metadata describe it.

### 7.3 Worksheet Topics

Render instructions, previews or download controls, and then ordered checkpoints. Existing PDF, Word document, legacy single-file, and multiple-file behavior remains unchanged.

### 7.4 Interactive and other Topic types

Render the existing Topic-specific content first, then inject checkpoints at the nearest supported placement point, normally after the canonical body. Do not replace the established renderer with a speculative generic block system.

### 7.5 Invalid references

If a checkpoint block references a missing question or invalid UUID:

- render the rest of the Topic normally;
- omit only the invalid checkpoint;
- log the Topic, block, and question identifiers; and
- leave Lesson progression available.

## 8. Learner Checkpoint State Machine

The checkpoint component uses explicit states.

| State | Feedback | Actions | Explanation |
|---|---|---|---|
| Ready | None | Check Answer, Skip for now | Hidden |
| Submitting | None | Controls disabled | Hidden |
| Incorrect | Not quite; try again | Retry, Skip for now | Hidden |
| Correct | Positive/correct feedback | Continue | Shown when configured |
| Skipped | Neutral skipped message | Continue | Hidden |
| Request failed | Accessible inline error | Retry request | Hidden |

### 8.1 Ready and submitting

Answer controls are enabled in the ready state. Check Answer becomes available only when the answer is sufficiently complete for its question type. Skip for now remains available.

During submission, disable all conflicting actions and show an in-progress state to prevent duplicate requests.

### 8.2 Incorrect answer

Persist `incorrect`, the latest evaluated answer, correctness, the answer time, and the incremented attempt count. Show an explicit incorrect message, hide the configured explanation, and offer:

- Retry as the primary action; and
- Skip for now as the optional exit.

Do not expose Continue until the learner answers correctly or explicitly skips. Retry resets the current inputs and feedback without discarding recorded attempt history.

### 8.3 Correct answer

Persist `correct`, the latest evaluated answer, correctness, the answer time, completion time, and incremented attempt count. Then:

- show positive feedback;
- show the configured explanation when present;
- make answer controls read-only for the current interaction;
- remove Skip for now; and
- show one Continue action.

### 8.4 Skipped checkpoint

Persist `skipped`, skip and completion timestamps, and no explanation. Disable the answer controls for the current interaction and show one Continue action.

### 8.5 Revisited checkpoint

- Correct checkpoints reopen completed and read-only and may show their explanation.
- Incorrect checkpoints may be retried.
- Skipped checkpoints may be retried later.
- A later correct answer supersedes the displayed skipped or incorrect status while preserving accumulated attempt history.

## 9. Skip, Continue, and Progression Ownership

A Lesson-level checkpoint coordinator ensures that only one forward-progression action is visible.

### 9.1 Between-topic checkpoint

The checkpoint is the current Lesson item. Keep Previous navigation where applicable, but suppress the normal footer forward action. The learner answers or chooses Skip for now. Correct or skipped state then exposes the checkpoint Continue action, which navigates to the next Lesson item.

The checkpoint remains optional because Skip for now is always available before resolution.

### 9.2 Inside-topic checkpoint

When a learner begins interacting with an inside-topic checkpoint, it becomes active and temporarily owns continuation. Suppress the conflicting footer forward action.

After correct or skipped state, checkpoint Continue:

- clears the active checkpoint state;
- moves focus or scroll toward the next checkpoint when one follows; or
- restores the regular Topic progression action when no checkpoint follows.

Checkpoint Continue does not mark the parent Topic complete. Parent Topic completion remains the responsibility of Lesson navigation.

## 10. Word Bank Interaction

Generalize the existing formal Quiz inline Word Bank presentation and selection behavior for reuse by checkpoints while keeping submission adapters separate.

Required interaction:

- split the question with `_____` markers;
- render every blank directly in the sentence;
- select a word to fill the first empty blank;
- make a used word unavailable in the bank;
- click a filled blank to return its word to the bank;
- preserve multiple-blank answer order;
- distinguish duplicate display values internally by index;
- support keyboard activation and visible focus; and
- submit selected words in blank order.

Formal Quizzes continue submitting through the Quiz form. Checkpoints continue submitting JSON to their checkpoint endpoint. Only the visual interaction and selection-state logic are shared.

## 11. Other Question Types

All six existing types remain supported:

- multiple choice;
- true/false;
- multiple select;
- identification;
- fill-in-the-blank text; and
- fill-in-the-blank Word Bank.

Checkpoint rendering preserves formal Quiz semantics. Multiple choice and true/false require one selection; multiple select requires the exact correct set; identification observes acceptable answers and case sensitivity; and blank answers preserve authored order.

Any shared evaluator change requires formal Quiz regression coverage.

## 12. Stable Lesson Footer

Use a viewport-anchored footer row inside the existing fullscreen Lesson shell instead of an uncontrolled fixed overlay.

The shell contains:

1. the existing stable top navigation;
2. a middle Lesson body that owns scrolling; and
3. a dedicated stable footer row.

Requirements:

- consistent base height at each responsive breakpoint;
- no state-dependent height changes;
- mobile safe-area padding;
- no overlap with Topic content;
- sufficient content-end spacing and scroll padding;
- one-row controls;
- Previous plus one contextual primary action where applicable; and
- compact but identifiable labels on narrow screens.

Move variable-height elements, including the Topic progress-dot strip, outside the footer. The contextual primary action may be Mark Complete and Continue, Continue, Take Lesson Quiz, Next Lesson, or Back to Module. When a checkpoint owns continuation, suppress the conflicting footer action.

## 13. Labels and Curriculum Sidebar

Use consistent labels:

- `Interactive Checkpoint` in instructor and admin interfaces;
- `Quick Check` in learner cards and the curriculum sidebar;
- `Inside Topic` and `Between Topics` for placement choices; and
- `QUICK CHECK` as the sidebar type label.

Do not show checkpoint duration, `0m`, prerequisite status, or `Required`. Between-topic checkpoints remain visible in Lesson order but do not contribute to required Topic counts. Inside-topic checkpoints remain associated with their parent Topic.

## 14. Remove Topic Confirmation

Replace the browser `confirm()` call with the platform's established modal/dialog pattern.

The modal includes:

- the Topic title;
- a warning that associated inside-topic checkpoints are also removed;
- Cancel and explicit Remove Topic actions;
- destructive visual treatment for confirmation;
- Escape-key and supported backdrop dismissal;
- initial focus and focus restoration;
- `role="dialog"` and `aria-modal="true"`; and
- an associated heading and description.

The delete form submits only after confirmation. Read-only admin contexts keep removal disabled.

## 15. Error Handling and Resilience

### 15.1 Submission failures

For validation, authorization, server, or network failures:

- retain the learner's current answer;
- re-enable controls;
- show an accessible inline error;
- do not fabricate completion or feedback state; and
- allow retry without affecting Topic or Lesson completion.

### 15.2 Duplicate requests

Disable controls during active requests. Checkpoint progress remains unique per learner and question. Increment attempt count only for accepted answer submissions. Repeated skip requests resolve idempotently to skipped state.

### 15.3 Access control

Checkpoint endpoints verify that:

- the question is a checkpoint;
- its Topic, Lesson, and Module exist;
- the Lesson is published;
- the Module is learner-visible; and
- the learner has an approved enrollment.

Return `403` or `404` without exposing correct-answer data.

## 16. Regression Protection

Inspect shared usage before modifying question, Topic, or navigation components. Verify formal Quiz authoring, Lesson and Module Quizzes, Topic completion, sequential Lesson access, shields, gamification, and instructor/admin ownership restrictions.

Checkpoint actions must not create or modify:

- `QuizAttempt` records;
- formal Quiz answers or scores;
- shield consumption or refund;
- gamification awards;
- pass thresholds; or
- required progress locks.

## 17. Verification Strategy

### 17.1 Instructor feature coverage

Verify:

- checkpoint creation and editing without duration or prerequisite input;
- neutral stored values and existing-data normalization;
- unchanged ordinary Topic validation;
- inside-topic and between-topic placement;
- multiple inside-topic checkpoints and order;
- safe rich and plain question prefill;
- block/question ownership checks; and
- instructor/admin authorization.

### 17.2 Learner feature coverage

Verify:

- incorrect feedback excludes explanation;
- correct feedback includes configured explanation;
- skip excludes explanation;
- Retry and attempt counts;
- correct and skipped continuation;
- Skip removal after correct;
- correct read-only revisit;
- incorrect and skipped retry;
- progress isolation from Topic, Lesson, Quiz eligibility, and certification;
- no Quiz attempt, shield, scoring, or points side effects; and
- authorized and unauthorized endpoint behavior.

### 17.3 Rendering coverage

Verify presence and DOM order for:

- video instructions, video, and checkpoint;
- text, images, and checkpoint;
- worksheet instructions/files and checkpoint;
- interactive content and checkpoint;
- multiple inside-topic checkpoints;
- standalone between-topic checkpoints; and
- malformed checkpoint references without Topic-body loss.

### 17.4 JavaScript coverage

Verify:

- every checkpoint state transition;
- request locking and recovery;
- Retry and Skip behavior;
- explanation visibility;
- checkpoint coordinator events;
- footer action suppression and restoration;
- inline Word Bank filling and removal;
- multiple blanks and duplicate bank values; and
- accessible keyboard interactions.

### 17.5 Formal Quiz regression coverage

Verify all six question types, Word Bank payloads, scoring, attempts, shields, and existing Lesson and Module Quiz workflows.

### 17.6 Responsive browser verification

Exercise representative mobile, tablet, and desktop viewports, including approximately 375, 768, and 1440 pixels wide. Verify footer stability, safe-area behavior, absence of content overlap, scrolling, modal interaction, long questions, multiple blanks, video Topics, worksheet Topics, and supported color modes.

## 18. End-to-End Acceptance Flow

Verify the complete flow:

1. Instructor creates or edits a Lesson.
2. Instructor creates a Topic and adds its canonical content.
3. Instructor adds one or more Interactive Checkpoints.
4. Instructor selects inside-topic or between-topic placement.
5. Instructor configures a supported question and optional explanation.
6. Instructor saves and publishes.
7. Learner opens the Lesson.
8. The complete Topic body renders in the intended order.
9. The learner answers or skips the checkpoint.
10. Incorrect answers show only incorrect feedback.
11. Correct answers show positive feedback and the optional explanation.
12. Retry, Skip, and Continue follow the state machine.
13. Only one forward action is visible.
14. The learner advances without checkpoint gating.
15. Required Topic and Lesson progress remain correct.
16. Formal Quizzes remain unchanged.

## 19. Acceptance Criteria

The refinement is complete when all requested QA criteria are satisfied, all targeted and full regression tests pass, the end-to-end author-to-learner workflow succeeds, and mobile and desktop browser verification confirms that Topic content, checkpoints, and navigation remain accessible and stable.
