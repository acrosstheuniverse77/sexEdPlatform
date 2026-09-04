# Interactive Checkpoint Question Configuration Refinement Design

## Purpose

Refine Interactive Checkpoint authoring so its six question types use the same configuration capabilities, answer structures, validation semantics, guidance, and platform-aligned UI/UX as the existing formal Quiz system.

This specification refines the authoring portions of `2026-08-21-interactive-checkpoints-design.md`. All previously approved decisions about placement, learner progress, checkpoint optionality, answer evaluation, Quiz isolation, shields, gamification, and certification remain in force unless this document explicitly changes them.

## Existing-System Findings

The implementation already shares important domain behavior:

- `QuizQuestion` and `QuizOption` store both formal Quiz questions and checkpoint questions.
- `QuestionAuthoringService` creates and updates questions for all six types.
- `QuestionEvaluator` evaluates both formal Quiz and checkpoint submissions.
- Checkpoint submissions use their own learner controller and do not create `QuizAttempt` records.
- The optional per-question `explanation` field is already persisted and returned after checkpoint submissions.

The current authoring layers are inconsistent:

- The modern Quiz Add Question page has the richest authoring experience and is the canonical UI/UX reference.
- The older Quiz edit page contains a separate dynamic implementation and does not fully match the modern Add Question page.
- The checkpoint partial always renders four option rows and groups Multiple Choice, True/False, and Multiple Select into one simplified section.
- Because of that grouping, True/False incorrectly appears as a generic Multiple Choice configuration.
- The checkpoint partial lacks the modern Quiz dynamic option, ordered blank-answer, and detailed guidance behavior.
- `QuestionAuthoringService` enforces a minimum of two options but defines no maximum option count.
- The existing 10-word Word Bank limit is enforced in the Quiz controller rather than shared validation, so checkpoint creation does not inherit it.
- Existing UI, CSV validation, persistence, and evaluation collectively express stronger type-specific rules than the base service currently enforces.
- The current Topic update path does not update an existing checkpoint question, despite rendering checkpoint fields in the edit form.

## Approved Decisions

1. The modern Quiz Add Question experience is the behavioral and visual source of truth.
2. Question configuration uses a shared authoring core with thin Quiz and checkpoint wrappers.
3. Shared validation consolidates the effective rules expressed by the Quiz UI, CSV validation, persistence, and evaluator.
4. Multiple Choice and Multiple Select require at least two options and have no maximum option count.
5. Checkpoints hide points and submit an internal value of `1`.
6. Choice and Identification questions retain the Quiz rich-text behavior; blank questions use plain text with blank tools.
7. Switching types retains common fields and discards all previous type-specific data.
8. Checkpoint placement is fixed after creation.
9. True/False always uses fixed, read-only True and False options.
10. Multiple Select requires at least one correct answer, matching current Quiz semantics.
11. Fill-in-the-blank answer counts must match detected blank counts.
12. Word Banks contain at most 10 words, and every correct answer must exist in the bank.
13. Optional explanations remain available for every checkpoint type.
14. Automated and browser verification cover all six types, the complete switching sequence, both placements, and formal Quiz regression boundaries.

## Goals

- Give all six checkpoint types their appropriate configuration interface.
- Match the modern Quiz authoring experience without duplicating the entire Quiz page.
- Eliminate the True/False rendering bug.
- Add dynamic Add Option and Remove Option controls where appropriate.
- Prevent invalid or stale type-specific data from being stored.
- Support creation and editing for both checkpoint placement models.
- Preserve existing learner checkpoint behavior and formal Quiz behavior.
- Avoid unnecessary duplicate question logic.

## Non-Goals

- Multiple questions within one checkpoint.
- Moving an existing checkpoint between Inside Topic and Between Topics placement.
- New question types.
- Checkpoint scoring, XP, shields, attempts, or certification effects.
- Mandatory checkpoints.
- A full redesign of Topic or Quiz management pages.
- Analytics or reporting for checkpoint responses.

## Architecture

### Shared Question-Authoring Core

Create one reusable question-authoring core consisting of:

- A shared Blade fields component.
- A shared Alpine state controller.
- Shared type labels, descriptions, instructions, and helper content.
- Shared server normalization and validation in `QuestionAuthoringService`.
- Existing `QuestionAuthoringService` persistence into `QuizQuestion` and `QuizOption`.

The core owns:

- Question-type configuration rendering.
- Dynamic options and acceptable answers.
- Correct-answer selection.
- Blank insertion and live blank counting.
- Word Bank configuration.
- Identification image configuration.
- Case sensitivity.
- Optional explanation when enabled by the wrapper.
- Type-switch state cleanup.
- Client-side guidance and error presentation.

### Thin Context Wrappers

Each authoring context configures the shared core without duplicating its type-specific logic:

| Context | Type switching | Points | Explanation | Placement |
| --- | --- | --- | --- | --- |
| Quiz create | Fixed after selection | Visible | Preserve existing Quiz behavior | Not applicable |
| Quiz edit | Allowed | Visible | Preserve existing Quiz behavior | Not applicable |
| Checkpoint create | Allowed | Hidden and fixed at `1` | Visible and optional | Selected during creation |
| Checkpoint edit | Allowed | Hidden and fixed at `1` | Visible and optional | Displayed read-only |

Quiz and Topic pages retain their existing surrounding layout, navigation, and save actions. Only the question-configuration core is shared.

### Placement Persistence Boundary

Checkpoint placement remains distinct from question persistence:

- `QuestionAuthoringService` validates, normalizes, creates, and updates question data.
- Placement-specific orchestration creates or updates the owning `LessonTopic` or `content_blocks` entry.
- Placement and question writes are transactional so validation or persistence failures cannot leave orphan topics, blocks, or questions.

## Platform-Aligned UI/UX

The modern Quiz Add Question experience defines the visual and interaction language:

- Existing rounded card sections and section shells.
- Current platform spacing and typography.
- Purple brand-gradient primary actions.
- Existing neutral, secondary, and destructive actions.
- Pastel question-type badges and descriptions.
- Purple focus states.
- Green correct-answer highlighting and Correct badges.
- Inline validation messages and a top-level error summary.
- Contextual helper text immediately beside the relevant controls.
- Existing responsive Tailwind breakpoints.

The checkpoint wrapper stays within the Topic create/edit flow. It does not reproduce the Quiz question-bank page or CSV import workflow.

The checkpoint question-type selector remains compact but shows the same label, type description, badge treatment, and configuration guidance as the Quiz type bank.

## Common Question Fields

Every question contains:

- Question type.
- Question text.
- Type-specific answer configuration.
- Internal points.

Every checkpoint additionally exposes:

- `Explanation (Optional)`.

Question text behavior mirrors Quiz authoring:

- Multiple Choice, True/False, Multiple Select, and Identification use the existing rich-text behavior.
- Fill in the Blanks types use plain text so `_____` markers remain predictable.
- Meaningful-text validation ignores empty HTML markup.
- Blank types provide an Insert Blank action and live blank count.

Checkpoint points are not displayed. A hidden `points=1` value maintains the shared record contract without implying that checkpoints are scored.

Explanation is optional, limited to 5,000 characters, retained while switching types, and available for all six checkpoint types.

## Type-Specific Authoring

### Multiple Choice

- Initialize with two blank options.
- Allow unlimited additional options, matching the current Quiz implementation.
- Display Add Option beside the section guidance.
- Display a remove icon for each removable option while more than two options exist.
- Use radio buttons for the correct answer.
- Highlight the selected correct row and display a Correct badge.
- Removing the correct option clears the correct selection.
- Require the author to select a new correct answer before saving.
- Require every visible option to contain non-empty text.

### True or False

- Render exactly two fixed, read-only options: True and False.
- Use radio buttons because exactly one answer is correct.
- Do not render Add Option or Remove Option controls.
- Normalize the server payload to the fixed True/False values.
- Reject configurations without exactly one valid correct selection.

### Identification

- Require at least one acceptable answer.
- Allow dynamic acceptable-answer rows.
- Prevent removal of the final acceptable-answer row.
- Preserve the existing case-sensitivity option.
- Support an optional JPG or PNG image up to 2 MB.
- Show the current image during editing and allow replacement.
- Remove the obsolete image association and file when switching to a non-Identification type.

### Fill in the Blanks — Text

- Require at least one `_____` marker.
- Provide an Insert Blank action.
- Show the live number of detected blanks.
- Render one ordered answer group per blank.
- Allow alternatives inside a group with `|`, for example `color|colour`.
- Serialize answer groups with `;` so answer order remains explicit.
- Require the answer-group count to equal the blank count.
- Preserve the case-sensitivity option.
- Reject empty answer groups.

### Fill in the Blanks — Word Bank

- Require at least one `_____` marker.
- Provide an Insert Blank action and live blank count.
- Accept comma-separated Word Bank entries.
- Trim whitespace and discard empty entries.
- Enforce the existing maximum of 10 words.
- Render one ordered correct answer for each blank.
- Require every correct answer to exist in the Word Bank.
- Require the correct-answer count to equal the blank count.
- Preserve exact ordered evaluation through `QuestionEvaluator`.

### Multiple Select

- Initialize with two blank options.
- Allow unlimited additional options.
- Reuse the Multiple Choice Add and Remove controls.
- Use checkboxes for correct answers.
- Require at least one correct option.
- Remove a deleted option from the correct-answer set.
- Require every visible option to contain non-empty text.

## Dynamic Type Switching

The Alpine state controller owns the active type and all type-specific state.

When the author changes type, retain:

- Question text.
- Checkpoint explanation.
- Internal or visible points.

Before changing editor modes, synchronize the active editor into component state. Switching from a rich-text type to a blank type preserves the visible text while removing HTML markup that cannot be represented safely in the plain-text blank editor. Switching from a blank type to a rich-text type seeds the rich-text editor with the current plain text. Formatting removed by a rich-to-plain transition is not restored if the author later switches back.

Discard and recreate:

- Options.
- Correct-option selections.
- Acceptable answers.
- Word Bank values.
- Blank-answer groups.
- Case-sensitivity state.
- Unsaved Identification image input.

The new type receives its correct default state. For example:

- Multiple Choice to True/False creates fixed True and False rows.
- True/False to Identification removes all option controls.
- Identification to Fill Blank Word Bank removes image and Identification-answer controls.
- Word Bank to Multiple Select removes all blank and Word Bank fields.
- Multiple Select to Multiple Choice changes checkboxes to radios and resets correctness.

Only the active section is enabled and submitted. Server normalization independently removes fields that do not belong to the selected type.

Dynamic rows use stable client-side keys. Submitted option indices are regenerated from the final visible order so removals cannot leave incorrect or out-of-range correct-answer indices.

After server validation fails, old input restores only the active type and its valid common fields. Hidden stale data from other types is not restored.

## Shared Validation Contract

### Base Rules

- `question_type` is required and must be one of the six supported values.
- `question_text` is required and must contain meaningful text.
- `points` is an integer of at least 1.
- `explanation` is optional, textual, and at most 5,000 characters.
- Identification images are optional JPG or PNG files no larger than 2 MB.

### Choice Rules

Multiple Choice:

- At least two non-empty options.
- Exactly one correct index.
- Correct index must refer to a submitted option.

True/False:

- Exactly the normalized True and False options.
- Exactly one correct index: `0` or `1`.

Multiple Select:

- At least two non-empty options.
- At least one correct index.
- Every correct index must refer to a submitted option.

All correct indices must be integers and unique.

### Text-Answer Rules

Identification:

- At least one non-empty acceptable answer.

Fill Blank Text:

- At least one blank marker.
- Exactly one non-empty answer group per blank.

Fill Blank Word Bank:

- At least one blank marker.
- One to 10 non-empty Word Bank entries.
- Exactly one correct word per blank.
- Every correct word must exist in the normalized Word Bank.

### Validation Ownership

Move effective question validation into the shared authoring boundary. Remove controller-only duplication such as the Word Bank count check after equivalent shared validation exists.

Client validation improves feedback but never replaces server validation.

## Normalization and Persistence

Before validation and persistence:

- Remove fields that do not apply to the active type.
- Trim option, answer, and Word Bank strings.
- Remove empty array entries where allowed.
- Normalize correct indices to integers.
- Normalize Word Bank text consistently.
- Serialize blank-answer groups in the format consumed by `QuestionEvaluator`.

When updating:

- Replace option rows in final visible order for choice types.
- Delete obsolete `QuizOption` rows when changing to a non-choice type.
- Clear `acceptable_answers` and `case_sensitive` when leaving text-answer types.
- Clear `word_bank` when leaving Fill Blank Word Bank.
- Clear and delete Identification images when changing to another type.
- Retain explanation because it is common checkpoint data.

Existing valid Quiz records remain readable. No broad data migration is required. Legacy delimiter formats are interpreted deterministically when loading an edit form:

- One-blank Fill Blank Text values treat `|` as alternatives within one answer group.
- Multi-blank Fill Blank Text values containing `;` split into ordered answer groups, with `|` retained for alternatives inside each group.
- Multi-blank Fill Blank Text values without `;` treat pipe-delimited tokens as ordered single-answer groups only when the token count equals the blank count. Otherwise they load as an incomplete configuration that the author must correct before saving.
- Fill Blank Word Bank values continue treating either `;` or the existing pipe-delimited format as ordered answers.

Legacy records are normalized to the canonical delimiter format only when the author saves the question.

## Checkpoint Creation and Editing

### Between Topics

Creation continues to:

- Create a `LessonTopic` with `type=interactive_checkpoint`.
- Store `placement=between_topics` in `interactive_config`.
- Create one associated checkpoint `QuizQuestion`.
- Use existing topic ordering.

Editing:

- Loads the existing question and options into the shared component.
- Displays Between Topics as read-only placement information.
- Updates the same question record.
- Preserves question identity and learner-progress associations.
- Updates the topic title and duration through the existing Topic workflow.

### Inside Topic

Creation continues to:

- Select a containing instructional topic.
- Create a question owned by that topic.
- Assign a checkpoint block UUID.
- Insert the checkpoint into `content_blocks`.

Inside-topic checkpoint management gains an explicit Edit action. Its edit wrapper:

- Verifies that the question belongs to the requested parent topic.
- Loads the same shared question component.
- Displays Inside Topic as read-only placement information.
- Updates the same question record.
- Preserves the block UUID and block position.
- Does not create a separate navigation topic.

Placement cannot change during editing. Relocating a checkpoint requires deleting and recreating it so topic order, block ownership, and learner progress are never silently rewritten.

## Learner Experience

The existing shared checkpoint learner partial continues to render both placement modes.

After an answer submission:

- Correct answers show immediate positive feedback.
- Incorrect answers show immediate corrective feedback.
- Explanation appears when configured.
- Incorrect responses allow retry.
- Continue remains available.

Skip remains available and does not reveal the explanation.

Both formal Quiz submissions and checkpoint submissions continue to delegate answer checking to `QuestionEvaluator`. No second answer-evaluation implementation is introduced.

## Error Handling

When configuration is invalid:

- Prevent saving.
- Show a top-level error summary.
- Show an inline message at each relevant field.
- Preserve the active type.
- Restore valid common and active-type input.
- Focus or scroll to the first invalid control where practical.
- Preserve non-image form input after image validation errors.

Failed validation writes no topic, block, question, or option records.

Placement and question persistence run transactionally. Unexpected persistence failures return to the form with a safe error message and do not leave orphan database records.

## Responsive and Accessibility Requirements

Desktop behavior:

- Section headings and Add actions share a row.
- Correct controls, option labels, inputs, badges, and remove actions stay aligned.
- Long guidance and Word Bank content remain within the form container.

Mobile behavior:

- Option rows stack when needed.
- Text inputs use the available width.
- Add actions remain discoverable.
- Remove icons retain touch-friendly targets.
- Validation messages wrap without horizontal overflow.
- Placement and type controls collapse to one column.

Accessibility behavior:

- Every input has an explicit label.
- Icon-only Remove controls have descriptive accessible names.
- Single-answer types use radio semantics.
- Multiple Select uses checkbox semantics.
- Hidden sections are removed from keyboard interaction and submission.
- Error text is associated with its field.
- Correctness is communicated through text and control state, not color alone.
- Existing platform focus styles remain visible.

No new visual framework or unrelated styling system is introduced.

## Formal Quiz Regression Boundary

The refinement must not alter:

- Quiz score calculation.
- Quiz attempt creation.
- Passing thresholds.
- Attempt limits.
- Daily shield consumption or refunds.
- Gamification awards.
- Certification behavior.
- Quiz learner navigation.
- Quiz result rendering.
- Quiz CSV import behavior.

Formal Quiz authoring may adopt the shared fields internally, but valid visible behavior remains equivalent to the modern Quiz Add Question experience.

Checkpoint submission continues using checkpoint-specific routes. It creates no `QuizAttempt`, consumes no shield, awards no Quiz gamification, and does not affect certification eligibility.

## Testing Strategy

### Shared Validation and Persistence

Cover:

- Valid persistence for all six types.
- Minimum two-option enforcement.
- Absence of an artificial maximum option cap.
- Invalid and duplicate correct indices.
- Multiple Choice with zero or multiple correct answers.
- True/False fixed-option enforcement.
- Multiple Select with one or more correct answers.
- Blank marker and answer-count matching.
- Word Bank maximum and answer-membership validation.
- Identification acceptable answers, case sensitivity, and images.
- Optional explanation.
- Type-change stale-data cleanup.

### Checkpoint Authoring

For instructor and admin contexts, cover:

- Create each of the six types.
- Edit each of the six types.
- Create Inside Topic and Between Topics checkpoints.
- Edit both placement models without changing placement.
- Reject unauthorized ownership.
- Preserve block UUIDs and placement during editing.
- Prevent invalid configurations from creating partial records.

### Switching Sequence

Exercise this exact sequence:

```text
Multiple Choice
→ True or False
→ Identification
→ Fill in the Blanks — Text
→ Fill in the Blanks — Word Bank
→ Multiple Select
→ Multiple Choice
```

At every transition, verify:

- The correct configuration is visible.
- The previous configuration is absent and disabled.
- The correct radio or checkbox semantics are used.
- Stale type-specific data is absent from submission.
- Validation matches the active type.

### Learner Flow

For all six types and both placements, cover:

- Correct response.
- Incorrect response.
- Retry.
- Continue.
- Skip.
- Optional explanation.

### Formal Quiz Regression

Verify:

- All six formal Quiz types still save and evaluate correctly.
- Formal submissions still create `QuizAttempt` records.
- Failed formal Quiz attempts still consume shields.
- Passing shield behavior remains unchanged.
- Checkpoints create no Quiz attempts.
- Checkpoints consume no shields.
- Checkpoints award no Quiz gamification.
- Checkpoints do not affect completion or certification eligibility.

### End-to-End and Responsive Verification

Exercise the complete instructor/admin-to-learner workflow for both placements. Verify the authoring and learner interfaces at representative desktop and mobile widths, including long question text, dynamic options, Multiple Select, Word Bank, Explanation, validation, and retry/continue/skip states.

Do not add a new browser-test dependency solely for this refinement. Use the repository's existing automated tooling plus documented browser verification.

## Acceptance Criteria

The refinement is complete when:

- Multiple Choice provides working Add and Remove controls.
- Multiple Choice enforces at least two options and exactly one correct answer.
- True/False renders only fixed True and False configuration.
- Identification renders acceptable answers, case sensitivity, and optional image configuration.
- Fill Blank Text renders blank tools and ordered text-answer configuration.
- Fill Blank Word Bank renders blank tools, the capped Word Bank, and ordered answer configuration.
- Multiple Select renders dynamic options and multiple correct-answer controls.
- The complete switching sequence removes stale type-specific state.
- Shared validation prevents invalid active-type configurations from saving.
- Optional Explanation remains available for every checkpoint type.
- Both placement modes can be created, edited, and used by learners.
- The refined interface matches modern Quiz and platform UI/UX patterns on desktop and mobile.
- Checkpoints remain optional and isolated from formal Quiz limits, scoring, shields, gamification, completion, and certification.
- Formal Quiz regression tests pass.
- No unnecessary duplicate question-authoring or answer-evaluation logic is introduced.
