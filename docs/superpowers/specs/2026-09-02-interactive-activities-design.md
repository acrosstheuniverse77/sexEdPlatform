# Interactive Activities Design

**Date:** 2026-09-02
**Status:** Approved
**Scope:** Optional Matching and Sequencing activities in the existing Lesson Topic system

## 1. Purpose

Interactive Activities extend the existing Lesson Topic system with two optional formative activity types:

- Matching
- Sequencing

Both activities support inside-topic and between-topic placement, instructor and admin authoring, unsaved interactive preview, learner retry and skip behavior, persistent working state, and responsive keyboard-accessible interaction.

Interactive Activities are not formal Quizzes. They never create Quiz attempts, consume shields or daily limits, award scores or gamification, affect certification, or contribute to required Topic and Lesson progress.

Matching and Sequencing ship together on one shared foundation. The design preserves the current Lesson and Topic architecture instead of creating a second Lesson sequence.

## 2. Approved Decisions

1. Matching and Sequencing ship together.
2. Every Interactive Activity is optional and never gates Lesson progress.
3. Matching and Sequencing appear directly among the existing Topic type cards.
4. Both activity types support inside-topic and between-topic placement.
5. A shared `interactive_activities` entity owns common metadata and typed configuration.
6. Activity-specific configuration uses validated, versioned JSON with stable item UUIDs.
7. All pre-existing legacy `interactive` Topic records are removed rather than migrated.
8. Interactive Activities follow Interactive Checkpoint metadata behavior: no duration input, `duration = 0`, and `is_prerequisite = false`.
9. Learner state is stored separately from Lesson Topic and Quiz progress.
10. The activity builder appears dynamically on the existing Create Topic page.
11. Version one supports text items only while retaining typed item envelopes for future media.
12. Matching allows 2-12 pairs; Sequencing allows 3-12 items.
13. Normalized duplicate values are rejected within each activity collection.
14. Activities use the existing Lesson/module publishing and content-review workflow rather than activity-specific drafts.
15. Matching uses tap-to-match on all devices and automatically adds connector lines at wider viewports.
16. Each proposed Matching pair is evaluated immediately by the server.
17. Matching right-side order is shuffled once per unresolved revision and persisted.
18. Sequencing supports dragging, visible move buttons, and keyboard reordering without a new runtime dependency.
19. Incorrect sequencing feedback does not reveal positions or the correct order.
20. Sequencing starts from a persisted non-correct shuffle.
21. Completed activities reopen as read-only summaries with optional non-mutating practice; skipped activities can resume.
22. Answer-affecting edits create a new activity revision; wording-only edits preserve completion.
23. Authors can interactively preview unsaved form data.
24. Learners see the family label `INTERACTIVE ACTIVITY · Optional`.

## 3. Core Architecture

Lessons and ordered `LessonTopic` records remain the Lesson-navigation foundation. The feature does not introduce another navigation collection or learning-item sequence.

A new common `InteractiveActivity` entity owns each activity definition. It attaches to:

- a standalone `LessonTopic` host for between-topic placement; or
- a normal parent Topic plus a content-block UUID for inside-topic placement.

A centralized activity registry recognizes `matching` and `sequencing`. Each registered type supplies its own validation, normalization, safe learner payload, evaluation, authoring component, and learner component. A future type such as `body_diagram` can be introduced by registering its handler and components without changing Lesson navigation, placement, authorization, publishing, or common activity progress.

All standalone activity hosts use neutral Topic metadata:

- `duration = 0`
- `is_prerequisite = false`

Authoring never displays or accepts duration and prerequisite values for Interactive Activities. Required-progress and duration queries exclude standalone `interactive` Topics just as checkpoint-specific queries exclude `interactive_checkpoint` Topics.

## 4. Data Model

### 4.1 Interactive activities

Create `interactive_activities` with:

- `id`
- `lesson_topic_id` foreign key with cascade-on-delete
- `placement`: `inside_topic` or `between_topics`
- `block_uuid`, nullable for between-topic activities and unique when present
- `activity_type`: `matching` or `sequencing`
- `title`
- `instructions`, nullable rich text
- `explanation`, nullable rich text
- `configuration`, JSON
- `revision`, unsigned integer starting at `1`
- timestamps

Invariants:

- A between-topic host owns exactly one activity.
- A normal Topic may own multiple inside-topic activities.
- An inside-topic activity requires a unique block UUID referenced by its parent Topic's `content_blocks`.
- The referenced parent or host Topic must belong to the authorized Lesson.
- Interactive Activities and standalone Interactive Checkpoints cannot be inside-topic parents.
- `activity_type` is immutable after creation. Changing between Matching and Sequencing requires deletion and recreation.
- Configuration is validated and normalized before it is stored.

The common table owns shared metadata. Matching and Sequencing do not duplicate titles, instructions, explanations, placement, or revision fields.

### 4.2 Matching configuration

Matching uses a versioned JSON envelope:

```json
{
  "schema_version": 1,
  "pairs": [
    {
      "id": "pair-uuid",
      "left": {
        "id": "left-item-uuid",
        "kind": "text",
        "value": "Puberty"
      },
      "right": {
        "id": "right-item-uuid",
        "kind": "text",
        "value": "The process of physical maturation"
      }
    }
  ]
}
```

The `kind` property preserves a future extension point for image or audio items, but version one accepts only `text`.

Left and right item UUIDs are generated independently. Learner payloads omit the enclosing pair ID and any other data that exposes the correct mapping.

### 4.3 Sequencing configuration

Sequencing uses:

```json
{
  "schema_version": 1,
  "items": [
    {
      "id": "item-uuid",
      "kind": "text",
      "value": "Wet hands",
      "correct_position": 1
    }
  ]
}
```

The server normalizes positions into a continuous one-based sequence. Learner payloads contain opaque item IDs and display content but omit `correct_position`.

### 4.4 Interactive activity progress

Create `interactive_activity_progress` with:

- `id`
- `user_id` foreign key
- `interactive_activity_id` foreign key with cascade-on-delete
- `activity_revision`
- `status`: `in_progress`, `completed`, or `skipped`
- `working_state`, nullable JSON
- `attempt_count`, unsigned integer starting at `0`
- `started_at`, nullable timestamp
- `completed_at`, nullable timestamp
- `skipped_at`, nullable timestamp
- timestamps

Use a unique constraint on `user_id`, `interactive_activity_id`, and `activity_revision`.

Older revision records remain available for consistency, but only the activity's current revision controls the learner display. Activity actions never write to `lesson_topic_progress`, Quiz attempts or answers, shields, scores, or gamification records.

## 5. Legacy Interactive Topic Removal

A targeted data migration removes every pre-existing `lesson_topics` row whose type is `interactive` before Matching and Sequencing begin using that host type. This includes legacy `activity`, `simulation`, `exercise`, missing, and unrecognized configurations.

Deletion also removes records that belong exclusively to each deleted legacy Topic, including:

- Lesson Topic progress;
- attached inside-topic checkpoint blocks;
- checkpoint questions and checkpoint progress; and
- any other dependent rows covered by explicit cleanup or existing cascades.

After deletion, the migration normalizes remaining Topic order within every affected Lesson and recalculates affected Lesson and Module duration totals. Ordinary video, text, worksheet, and checkpoint Topics; formal Quizzes; and unrelated progress remain untouched.

## 6. Activity Registry and Service Boundaries

Introduce a central `InteractiveActivityType` definition containing `matching` and `sequencing`.

An activity-handler contract provides:

- the activity type identifier;
- authoring validation rules;
- configuration normalization;
- safe learner-payload generation;
- answer evaluation; and
- preview preparation.

Implement one handler per type rather than scattering type checks through Topic, Lesson, and view code.

### 6.1 Authoring service

The authoring service:

- creates the activity and its host Topic or content block;
- updates shared metadata and type-specific configuration;
- enforces neutral Topic metadata;
- determines whether an update increments the revision;
- deletes activities and cleans placement references;
- normalizes Topic ordering after standalone deletion; and
- performs all related writes transactionally.

### 6.2 Progress service

The progress service:

- resolves progress for the current revision;
- initializes and persists non-correct shuffled working state;
- evaluates learner requests through the registered handler;
- records accepted attempts;
- completes, skips, and resumes activities;
- supports non-mutating practice mode; and
- uses atomic updates to prevent duplicate completion or attempt increments.

### 6.3 Learner presenter

The presenter:

- combines the activity definition and current progress;
- removes Matching relationships and Sequencing positions from learner output;
- returns only learner-visible IDs and content;
- resolves safe UI state; and
- rejects malformed configuration without breaking the Lesson page.

The existing `TopicController` detects Interactive Activity requests and delegates them. Focused instructor and learner activity controllers own activity-specific edit, update, preview, evaluation, state, skip, resume, and practice endpoints.

### 6.4 Request and data flow

Authoring follows this flow:

1. The existing Topic form submits the common metadata, placement, activity type, and type-specific builder data.
2. The instructor controller authorizes the Lesson and delegates to the authoring service.
3. The registry resolves the appropriate handler, which validates and normalizes the configuration.
4. One transaction creates or updates the standalone host or parent content-block reference, activity definition, neutral metadata, and revision.
5. The author returns to the existing Lesson details workflow.

Learner interaction follows this flow:

1. `LessonController` resolves the current Topic and eager-loads valid activities and current-revision progress.
2. The presenter produces a safe payload without Matching relationships or Sequencing positions.
3. The learner component submits a pair proposal, complete order, working-state update, skip, resume, or practice request to an activity-specific endpoint.
4. The learner controller reauthorizes the activity and delegates evaluation or state mutation to the progress service.
5. The progress service locks the current progress row when needed, resolves the registered handler, persists the accepted transition, and returns UI state without an answer key.
6. The shared component updates feedback and coordinates the single forward action with the Lesson shell.

Preview uses the authoring validation path and the same presentation components but substitutes a non-persisting preview adapter for learner endpoints.

## 7. Instructor and Admin Authoring Entry Point

Matching and Sequencing appear directly among the existing Create Topic type cards:

```text
Video
Text
Worksheet
Interactive Checkpoint
Matching
Sequencing
```

Selecting Matching or Sequencing:

- stores the Topic family as `interactive`;
- sets the registered activity type;
- hides and disables duration and prerequisite controls;
- reveals the appropriate activity builder; and
- displays Inside Topic and Between Topics placement controls.

For Inside Topic, the author selects an eligible parent Topic in the same Lesson and an insertion point among its checkpoint and activity blocks. Version one does not split or convert the parent Topic's canonical video, text, worksheet, or other body into a new generic block editor. For Between Topics, the service creates a standalone neutral `LessonTopic` host in the existing ordered Lesson sequence.

The implementation retains the existing Lesson/module publication, content-review, policy, panel-context, ownership, and read-only-admin behavior. It does not introduce activity-specific draft or publishing state.

## 8. Shared Authoring Fields

Both activity builders provide:

- Activity title
- Placement
- Parent Topic and insertion position for inside-topic placement
- Rich-text instructions
- Optional rich-text explanation
- Type-specific configuration
- Interactive Preview
- Save

Validation requires an allowlisted activity type and placement, an authorized same-Lesson parent, a server-generated block UUID, and project-standard title and rich-text length limits. Rich text uses the existing editor and sanitization pipeline. Initial edit data is JSON-encoded rather than interpolated through fragile inline escaping.

Duration and prerequisite inputs are never rendered, enabled, or trusted for Interactive Activity requests. The server always assigns their neutral values to standalone hosts.

## 9. Matching Authoring

The Matching builder supports 2-12 pairs. Each row contains a left item, right item, and removal action. Authors can add, edit, remove, and reorder rows, then preview the shuffled learner experience.

Validation requires:

- at least two and at most twelve complete pairs;
- no blank items;
- project-defined text-length limits;
- unique normalized text within the left column;
- unique normalized text within the right column; and
- stable UUID preservation for unchanged existing items.

Text is trimmed for validation while intended display content is preserved. Because pairs are stored atomically in JSON, removing or editing a pair cannot leave orphaned pair records.

## 10. Matching Learner Experience

Matching uses tap-to-match on every device:

1. Select one unmatched left item.
2. Select one unmatched right item.
3. Submit the proposed pair for server evaluation.
4. Disable conflicting controls during the request.
5. Lock a correct pair or clear an incorrect proposal.

The learner receives independent left and right item IDs, item text, and shuffled order. The payload never contains pair IDs, relationships, or a client-side answer key.

Correct proposals:

- lock both items;
- persist the matched item IDs;
- update `matched X of Y`; and
- render a completed relationship.

Incorrect proposals:

- display `Not quite—try another match` in an accessible live region;
- clear the proposed connection;
- preserve all previously completed pairs; and
- allow immediate retry.

`attempt_count` increments once for each accepted pair-evaluation request. When every pair is correct, the activity becomes completed, shows its optional explanation, removes Skip, and exposes one Continue action.

### 10.1 Desktop connector lines

At wider viewports, a decorative SVG overlay draws lines between completed pairs. Lines are automatic rather than author-configurable, recalculate when item or container geometry changes, use native browser observers, and are `aria-hidden`.

Textual and visual completed-pair states remain present so lines are never the only indication. Narrow layouts replace connector lines with stacked completed-pair cards rather than compressing both columns.

## 11. Matching Shuffle and Persistence

The right column is shuffled when current-revision progress is first initialized.

The initializer:

- guarantees that the starting arrangement is not completely correct;
- reverses or rotates a two-pair arrangement when necessary;
- retries or rotates an accidentally correct larger shuffle;
- stores the unresolved right-side order in `working_state`; and
- restores the same order and completed pairs after reload.

Skipping retains working state so the learner can resume later. Only display order and completed item IDs are stored; the correct mapping remains solely in the activity definition.

## 12. Sequencing Authoring

The Sequencing builder supports 3-12 items. Each row provides a drag handle, displayed canonical position, text input, Move Up, Move Down, and Remove actions.

Authors can add, edit, remove, and reorder items through pointer, button, or keyboard controls. The server recalculates canonical positions instead of trusting submitted position values.

Validation requires:

- at least three and at most twelve items;
- no blank items;
- unique normalized item text;
- project-defined text-length limits;
- continuous canonical positions; and
- stable UUIDs through ordinary editing and reordering.

## 13. Sequencing Learner Experience

The learner receives a persisted shuffled order that is not already correct. One client-side order array powers:

- pointer-friendly drag handles;
- visible Move Up and Move Down buttons; and
- keyboard reordering.

No drag-and-drop dependency is added. Native pointer behavior provides direct manipulation while visible buttons remain the reliable touch and accessibility fallback.

Working order is persisted through debounced state updates and every Check Answer request. State-saving requests do not increment `attempt_count`.

Check Answer submits an ordered array of opaque item IDs. The server verifies that every expected item appears exactly once, rejects unknown or duplicate IDs, and compares the complete order with the canonical sequence.

An incorrect order:

- displays `Not quite—try again`;
- does not identify incorrect positions;
- does not reveal the correct order;
- preserves the current learner order;
- hides the explanation; and
- permits continued reordering and retry.

A correct order locks the controls, completes the activity, shows positive feedback and the optional explanation, removes Skip, and exposes one Continue action. `attempt_count` increments for each accepted Check Answer request.

## 14. Shared Optional Activity State Machine

Both activity types use these states:

| State | Presentation | Actions |
|---|---|---|
| New | Initial shuffled activity | Interact, Skip for now |
| In progress | Current work and feedback | Continue working, Skip for now |
| Submitting | Controls temporarily disabled | None |
| Incorrect | Retry feedback, explanation hidden | Retry, Skip for now |
| Completed | Read-only correct result and optional explanation | Continue, Practice Again |
| Skipped | Neutral skipped state, explanation hidden | Continue, Resume Activity |
| Request failed | Existing work plus accessible error | Retry request |

Skipping is available before completion, records no incorrect answer, preserves working state, and exposes one Continue action. Completion supersedes a previous skipped state for the current revision.

## 15. Revisiting and Practice Mode

Completed activities reopen as read-only summaries. Matching displays completed pairs; Sequencing displays the canonical completed order. The activity's current explanation appears when configured.

Practice Again starts a fresh shuffled practice round without erasing or downgrading completion. Practice requests remain server-evaluated but do not replace the completed progress record or need permanent working-state history.

Skipped activities display Resume Activity. Resuming restores stored working state and returns the current revision to `in_progress`.

## 16. Configuration Revisions and Editing

Every activity begins at revision one. The revision increments for answer-affecting changes:

- Matching item content or relationships;
- added or removed Matching pairs;
- Sequencing item content;
- canonical sequence order;
- added or removed sequence items; or
- any other type-specific answer configuration.

The revision does not increment for title, instruction, explanation, or display-only formatting changes.

After an answer-affecting edit, existing progress remains attached to the old revision while the current revision begins unresolved. Learners must complete or skip the new revision. Activity definition, revision, placement references, and neutral host metadata update in one transaction.

## 17. Placement, Rendering, and Navigation

### 17.1 Inside Topic

Inside activities appear in the parent Topic's `content_blocks` as references:

```json
{
  "type": "interactive_activity",
  "uuid": "block-uuid",
  "activity_id": 123
}
```

The existing Topic-type renderer remains authoritative. It renders the complete canonical video, text, worksheet, or other content first, then resolves valid checkpoint and activity blocks in authored order. The insertion position controls order among these optional interaction blocks; it does not split canonical Topic content in version one. Activity metadata never replaces the parent Topic body.

Inside activities do not appear in the Lesson sidebar or create required Topic progress. They may be ordered relative to inside-topic checkpoints and other activities but cannot contain nested activities.

### 17.2 Between Topics

A between-topic activity uses a `LessonTopic` with type `interactive`, participates in existing Topic ordering, appears in the sidebar, displays no duration or prerequisite metadata, and hosts exactly one activity. Required-progress and Lesson-duration queries exclude it.

### 17.3 Continue ownership

The existing Lesson-level checkpoint continuation coordinator is generalized to optional interactions.

For a between-topic activity, the ordinary footer forward action is suppressed until the learner completes or skips. The activity's Continue action then navigates to the next Lesson item.

For an inside-topic activity, beginning interaction temporarily suppresses the conflicting footer action. Continue moves focus to the next unresolved inside checkpoint or activity when present; otherwise it restores the ordinary Topic progression action. Only one forward action is visible at a time.

## 18. Unsaved Interactive Preview

Preview validates current unsaved form data through a validation-only endpoint, applies the same handler normalization used by persistence, and returns a sanitized preview model. An interactive modal renders the same learner component without writing an activity or learner progress.

Because the author supplied the answer configuration, the preview adapter may evaluate locally after server validation. Production learner components always use server evaluation and never receive the answer key.

Preview supports Matching selection and lines, Sequencing reordering, correct and incorrect feedback, explanation, skip state, and representative mobile, tablet, and desktop widths. Real Lesson navigation remains disabled.

## 19. Labels and Visual Consistency

Instructor and admin interfaces use:

- `Matching`
- `Sequencing`
- `Interactive Activity`
- `Inside Topic`
- `Between Topics`

Learner cards and between-topic sidebar entries use `INTERACTIVE ACTIVITY · Optional`, along with the authored title and Matching or Sequencing subtype. They never display `0m`, `Required`, or prerequisite metadata.

Interactive Activities and Quick Checks share container treatment, typography, feedback styling, button hierarchy, Skip and Continue language, and continuation coordination. Matching and Sequencing retain their distinct interaction controls.

## 20. Accessibility and Responsive Behavior

Both activity types support mouse, touch, keyboard, visible focus, accessible names and instructions, live-region feedback, reduced-motion preferences, supported color modes, stable footer behavior, and mobile safe-area spacing.

Matching selected items expose pressed state. Completed relationships remain textual in addition to decorative lines. Mobile layouts never depend on narrow side-by-side columns.

Sequencing items expose current position and total. Move buttons have explicit labels, keyboard reordering announces the new position, and dragging is never the only reordering method.

Async failures retain the learner's work and restore focus to a meaningful control.

## 21. Authorization and Security

Instructor and admin requests verify:

- the Lesson and Module exist;
- the user may update the Lesson;
- instructor ownership and Module permissions;
- admin read-only restrictions;
- the parent Topic belongs to the same Lesson; and
- activity type and placement are allowlisted.

Learner requests verify:

- the Lesson is published;
- the Module is learner-visible;
- enrollment is approved;
- the activity belongs to the requested Lesson context;
- the submitted revision is current; and
- submitted item IDs belong to the activity.

All learner mutations use CSRF protection, server-side count and length limits, duplicate and missing-ID rejection, request locking, transactional progress updates, and idempotent skip behavior. Invalid resources return `404`; unauthorized access returns `403` without exposing answers.

## 22. Error Handling and Malformed Configuration

A malformed inside-topic activity never prevents the parent Topic from rendering. The renderer logs the activity, Topic, type, and validation problem, omits only the invalid block, and leaves Lesson progression available.

A malformed between-topic activity displays an unavailable-state card with a safe Continue action because the host occupies a Lesson navigation position.

Validation, authorization, network, and server failures retain current selections or sequence order, re-enable controls, display an accessible inline error, and allow retry. They never fabricate correctness, completion, or skipped state.

Concurrent requests use row locking or equivalent atomic updates so attempts and completion cannot be double-counted.

## 23. Editing and Deletion

Editing reuses the creation builders and safely initializes stored JSON and rich text.

Deleting a between-topic activity removes its standalone host, activity, and progress, then resequences the Lesson. Deleting an inside-topic activity removes its content-block reference, activity, and progress while preserving the parent Topic. Deleting a parent Topic removes all attached activities and their progress through cascades and service cleanup.

Matching configuration updates are atomic, so removed pairs cannot leave orphaned records. Sequencing updates always rewrite normalized canonical positions.

## 24. Component Boundaries

Expected backend units:

- `InteractiveActivity` model
- `InteractiveActivityProgress` model
- `InteractiveActivityType`
- Activity-handler contract
- Matching handler
- Sequencing handler
- Authoring service
- Progress/evaluation service
- Learner presenter
- Focused instructor activity controller
- Focused learner activity controller

Expected frontend units:

- Shared Interactive Activity shell
- Matching authoring builder
- Sequencing authoring builder
- Matching learner component
- Sequencing learner component
- Generalized continuation coordination
- Pure JavaScript state utilities for automated testing

These boundaries keep unrelated Topic, Lesson, and Quiz code from accumulating activity-specific branches.

## 25. Testing Strategy

### 25.1 Unit tests

Cover registry resolution, Matching and Sequencing validation and normalization, pair evaluation, exact sequence evaluation, safe learner payloads, non-correct shuffling, revision detection, and progress transitions.

### 25.2 Instructor and admin feature tests

Cover creation and editing for both types and placements, neutral metadata, count limits, duplicates, reordering, unsaved preview, revision behavior, deletion cleanup, instructor ownership, admin restrictions, and legacy data removal.

### 25.3 Learner feature tests

Cover correct and incorrect Matching proposals, persisted shuffle and completed pairs, payload secrecy, correct and incorrect sequence checks, order persistence, skip and resume, explanation visibility, completed revisit, practice mode, revision invalidation, malformed configuration, authorization, and isolation from Quiz, shield, score, gamification, and required progress.

### 25.4 JavaScript tests

Cover Matching selection and locked-pair state, retry and failure recovery, connector-coordinate calculation, Sequencing reorder operations, button and keyboard movement, request locking, state restoration, continuation coordination, and preview adapters.

### 25.5 Regression tests

Verify unchanged video, text, worksheet, and Interactive Checkpoint Topics; Topic ordering and deletion; Lesson progress; Lesson and Module Quizzes; Quiz attempts and shields; publishing; content review; and existing preview behavior.

### 25.6 Browser verification

Exercise instructor and learner workflows around 375px, 768px, and 1440px. Verify touch and keyboard interaction, long text, maximum item counts, footer stability, safe-area spacing, connector positioning, scrolling, responsive preview, and supported color modes.

## 26. End-to-End Acceptance Flow

The feature is accepted when:

1. An authorized author creates and previews a Matching activity.
2. The author selects either placement, configures pairs and optional explanation, saves, and publishes through the existing workflow.
3. The learner sees the activity in the intended position without required-progress effects.
4. Incorrect matches allow retry without revealing relationships.
5. Correct matches persist across reloads.
6. Completion shows the optional explanation and one Continue action.
7. Skip permits progression and later resume.
8. The equivalent author-to-learner flow succeeds for Sequencing.
9. Incorrect sequences reveal no positions or answer order.
10. Editing answer configuration creates a new revision and the learner receives the updated activity.
11. Completed learners may practice without losing completion.
12. Formal Quizzes, shields, scoring, gamification, required Lesson progress, and existing Topic types remain unchanged.
13. Targeted tests, full regression, build, formatting, and responsive browser checks pass.

## 27. Out of Scope

- Required Interactive Activities
- Scores, grades, pass thresholds, and leaderboards
- Quiz attempts, shields, and daily limits
- XP, badges, and other gamification
- Timers
- Branching or conditional sequences
- Multiple correct sequences
- Image or audio activity items
- Author-configurable interaction modes
- Drag-only interactions
- Activity analytics dashboards
- Activity-specific draft and publishing state
- Nested Interactive Activities
- New frontend runtime dependencies
