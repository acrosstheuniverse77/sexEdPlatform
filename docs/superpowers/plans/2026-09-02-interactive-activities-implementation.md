# Interactive Activities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional Matching and Sequencing activities to Lesson Topics with inside-topic and between-topic placement, durable learner state, unsaved author preview, and no effect on required progress, duration, Quizzes, shields, scores, certification, or gamification.

**Architecture:** Keep `LessonTopic` as the ordered Lesson-navigation host. Store shared activity metadata and typed JSON configuration in `interactive_activities`, and current-revision learner state in `interactive_activity_progress`. Route type-specific validation, normalization, safe payload generation, shuffling, and evaluation through a two-handler registry; keep authoring writes transactional and reuse the existing Topic policies, content ownership guard, Lesson shell, optional-interaction coordinator, and publication review snapshot.

**Tech Stack:** PHP 8.2, Laravel 12, Eloquent, Blade, Alpine.js 3, Tailwind CSS 3, native Pointer Events/SVG/`ResizeObserver`, Node test runner, PHPUnit 11, Vite 7.

**Design reference:** `docs/superpowers/specs/2026-09-02-interactive-activities-design.md`

## Global Constraints

- Ship Matching and Sequencing together; register no additional activity type.
- Every activity is optional. It never gates required Topic or Lesson completion.
- Standalone activity Topics use `type = interactive`, `duration = 0`, and `is_prerequisite = false`.
- Authoring never renders or trusts duration or prerequisite input for an activity.
- Remove every pre-existing legacy `interactive` Topic before reusing that type.
- Support both `inside_topic` and `between_topics` placement.
- Matching accepts 2–12 complete text pairs; Sequencing accepts 3–12 unique text items.
- Activity titles are limited to 255 characters, each item value to 500 characters, and instructions/explanation to 10,000 characters.
- Version one stores typed text envelopes only; do not implement image or audio inputs.
- Use stable server-issued UUIDs and configuration `schema_version = 1`.
- Learner payloads never expose Matching pair relationships or Sequencing `correct_position` values.
- Matching validates one proposed pair per accepted request.
- Sequencing validates the entire ordered item-ID list without position hints.
- Initial and practice order must not already be correct.
- State-save requests do not increment `attempt_count`; accepted answer checks do.
- Completion is terminal for persisted progress. Practice is non-mutating; skip is resumable.
- Increment `revision` only when normalized answer configuration changes.
- Malformed inside activities are logged and omitted; malformed between activities render an unavailable card with Continue.
- Preserve the existing publication, review, ownership, read-only-admin, and policy behavior.
- Add no runtime dependency.
- Preserve formal Quiz attempts, shields, limits, scores, answers, points, certification, and gamification unchanged.
- Learner label: `INTERACTIVE ACTIVITY · Optional`.

---

## File Structure

### Persistence and domain

- Create `database/migrations/2026_09_02_000001_create_interactive_activity_tables.php`.
- Create `database/migrations/2026_09_02_000002_remove_legacy_interactive_topics.php`.
- Create `app/Enums/InteractiveActivityType.php`.
- Create `app/Contracts/Learning/InteractiveActivityHandler.php`.
- Create `app/Models/InteractiveActivity.php` and `app/Models/InteractiveActivityProgress.php`.
- Create `database/factories/InteractiveActivityFactory.php` and `database/factories/InteractiveActivityProgressFactory.php`.
- Modify `app/Models/LessonTopic.php` and duration/progress callers that distinguish instructional Topics from optional interactions.

### Activity services and controllers

- Create `app/Services/Learning/InteractiveActivities/InteractiveActivityRegistry.php`.
- Create `app/Services/Learning/InteractiveActivities/MatchingActivityHandler.php`.
- Create `app/Services/Learning/InteractiveActivities/SequencingActivityHandler.php`.
- Create `app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php`.
- Create `app/Services/Learning/InteractiveActivities/InteractiveActivityProgressService.php`.
- Create `app/Services/Learning/InteractiveActivities/InteractiveActivityPresenter.php`.
- Create `app/Http/Controllers/Instructor/InteractiveActivityController.php`.
- Create `app/Http/Controllers/Learner/InteractiveActivityController.php`.
- Modify `app/Http/Controllers/Instructor/TopicController.php`, `Instructor/LessonController.php`, and `Learner/LessonController.php`.
- Modify `routes/instructor.php`, `routes/admin.php`, and `routes/web.php`.

### Authoring and preview

- Create `resources/js/interactive-activity-authoring.js`.
- Create `resources/views/instructor/topics/partials/interactive-activity-fields.blade.php`.
- Create `resources/views/instructor/topics/partials/matching-builder.blade.php`.
- Create `resources/views/instructor/topics/partials/sequencing-builder.blade.php`.
- Create `resources/views/instructor/topics/edit-interactive-activity.blade.php`.
- Create `resources/views/instructor/topics/partials/interactive-activity-preview-modal.blade.php`.
- Modify `resources/views/instructor/topics/create.blade.php`, `resources/views/instructor/lessons/show.blade.php`, and `resources/js/app.js`.

### Learner experience

- Create `resources/js/interactive-activity.js` for shared lifecycle and transport behavior.
- Create `resources/js/matching-activity.js` and `resources/js/sequencing-activity.js`.
- Create `resources/views/learner/lessons/partials/interactive-activities/shell.blade.php`.
- Create `resources/views/learner/lessons/partials/interactive-activities/matching.blade.php`.
- Create `resources/views/learner/lessons/partials/interactive-activities/sequencing.blade.php`.
- Create `resources/views/learner/lessons/partials/interactive-activities/unavailable.blade.php`.
- Modify `resources/views/learner/lessons/partials/topic-page.blade.php`.
- Modify `resources/views/learner/lessons/partials/lesson-forward-action.blade.php`.
- Modify `resources/views/learner/lessons/show.blade.php`.
- Modify `resources/js/interactive-checkpoint.js` to generalize continuation coordination while retaining its current checkpoint API.

### Publishing and review

- Modify `app/Services/ContentGovernanceService.php`.
- Modify `app/Services/AdminModuleReviewWorkspaceService.php`.
- Modify `app/Http/Requests/Admin/ContentReviewPreviewRequest.php`.
- Modify `resources/views/admin/content-reviews/partials/workspace-tree.blade.php`.

### Tests

- Create `tests/Feature/Learner/InteractiveActivitySchemaTest.php`.
- Create `tests/Feature/Instructor/LegacyInteractiveTopicRemovalTest.php`.
- Create `tests/Feature/Instructor/InteractiveActivityAuthoringTest.php`.
- Create `tests/Feature/Learner/InteractiveActivityProgressTest.php`.
- Create `tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php`.
- Create `tests/Feature/Learner/MatchingActivityFlowTest.php`.
- Create `tests/Feature/Learner/SequencingActivityFlowTest.php`.
- Create `tests/Feature/Learner/InteractiveActivityRenderingTest.php`.
- Create `tests/Feature/Learner/InteractiveActivityQuizRegressionTest.php`.
- Create `tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php`.
- Create `tests/JavaScript/interactive-activity-authoring.test.mjs`.
- Create `tests/JavaScript/interactive-activity.test.mjs`.
- Create `tests/JavaScript/matching-activity.test.mjs`.
- Create `tests/JavaScript/sequencing-activity.test.mjs`.
- Extend the existing Interactive Checkpoint, Lesson management, instructor submission, and admin content-review tests where named below.

---

### Task 1: Interactive Activity Persistence Foundation

**Files:**
- Create: `database/migrations/2026_09_02_000001_create_interactive_activity_tables.php`
- Create: `app/Enums/InteractiveActivityType.php`
- Create: `app/Models/InteractiveActivity.php`
- Create: `app/Models/InteractiveActivityProgress.php`
- Create: `database/factories/InteractiveActivityFactory.php`
- Create: `database/factories/InteractiveActivityProgressFactory.php`
- Modify: `app/Models/LessonTopic.php`
- Test: `tests/Feature/Learner/InteractiveActivitySchemaTest.php`

**Interfaces:**
- `InteractiveActivityType: string` has exactly `MATCHING = 'matching'` and `SEQUENCING = 'sequencing'`.
- `LessonTopic::interactiveActivities(): HasMany` owns inside activities or its one standalone activity.
- `InteractiveActivity::progress(): HasMany` preserves rows across activity revisions.
- One progress row is allowed for each `(user_id, interactive_activity_id, activity_revision)` tuple.

- [ ] **Step 1: Write the failing schema and relationship test**

Assert both tables and all columns exist, `configuration` and `working_state` cast to arrays, enum casts work, the block UUID is unique when present, a user/activity/revision duplicate is rejected, deleting an activity cascades its progress, and deleting a Topic cascades its activities.

Use these invariant assertions:

```php
$this->assertSame(InteractiveActivityType::MATCHING, $activity->activity_type);
$this->assertSame(1, $activity->revision);
$this->assertSame($activity->id, $topic->interactiveActivities()->firstOrFail()->id);

$this->expectException(QueryException::class);
InteractiveActivityProgress::factory()->create([
    'user_id' => $progress->user_id,
    'interactive_activity_id' => $progress->interactive_activity_id,
    'activity_revision' => $progress->activity_revision,
]);
```

- [ ] **Step 2: Run the test and verify failure**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivitySchemaTest.php
```

Expected: FAIL because the tables, enum, models, factories, and Topic relationship do not exist.

- [ ] **Step 3: Create the schema**

The first table must use these definitions:

```php
Schema::create('interactive_activities', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('lesson_topic_id')->constrained('lesson_topics')->cascadeOnDelete();
    $table->string('placement', 32);
    $table->uuid('block_uuid')->nullable()->unique();
    $table->string('activity_type', 32);
    $table->string('title');
    $table->text('instructions')->nullable();
    $table->text('explanation')->nullable();
    $table->json('configuration');
    $table->unsignedInteger('revision')->default(1);
    $table->timestamps();
    $table->index(['lesson_topic_id', 'placement']);
});
```

The progress table must use:

```php
Schema::create('interactive_activity_progress', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('interactive_activity_id')->constrained('interactive_activities')->cascadeOnDelete();
    $table->unsignedInteger('activity_revision');
    $table->string('status', 32)->default('in_progress');
    $table->json('working_state')->nullable();
    $table->unsignedInteger('attempt_count')->default(0);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('skipped_at')->nullable();
    $table->timestamps();
    $table->unique(
        ['user_id', 'interactive_activity_id', 'activity_revision'],
        'activity_progress_user_activity_revision_unique',
    );
});
```

Drop progress before activities in `down()`.

- [ ] **Step 4: Add the enum, models, factories, and relations**

Cast `InteractiveActivity::$activity_type` to `InteractiveActivityType`, cast JSON and integers, and use typed Eloquent relationships. Factory states must cover `matching()`, `sequencing()`, `insideTopic()`, and `betweenTopics()` so later tests do not repeat configuration fixtures.

Add to `LessonTopic`:

```php
public function interactiveActivities(): HasMany
{
    return $this->hasMany(InteractiveActivity::class);
}

public function standaloneInteractiveActivity(): HasOne
{
    return $this->hasOne(InteractiveActivity::class)
        ->where('placement', 'between_topics');
}
```

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivitySchemaTest.php
vendor/bin/pint --dirty
git add database/migrations/2026_09_02_000001_create_interactive_activity_tables.php app/Enums/InteractiveActivityType.php app/Models/InteractiveActivity.php app/Models/InteractiveActivityProgress.php database/factories/InteractiveActivityFactory.php database/factories/InteractiveActivityProgressFactory.php app/Models/LessonTopic.php tests/Feature/Learner/InteractiveActivitySchemaTest.php
git commit -m "feat: add interactive activity persistence"
```

Expected: schema tests PASS, casts return the declared types, and cascade assertions pass.

---

### Task 2: Registry, Configuration Validation, and Native Shuffling

**Files:**
- Create: `app/Contracts/Learning/InteractiveActivityHandler.php`
- Create: `app/Services/Learning/InteractiveActivities/InteractiveActivityRegistry.php`
- Create: `app/Services/Learning/InteractiveActivities/MatchingActivityHandler.php`
- Create: `app/Services/Learning/InteractiveActivities/SequencingActivityHandler.php`
- Test: `tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php`

**Interfaces:**

```php
interface InteractiveActivityHandler
{
    public function type(): InteractiveActivityType;
    public function rules(string $prefix = 'configuration'): array;
    public function normalize(array $configuration, ?array $existingConfiguration = null): array;
    public function initialWorkingState(array $configuration, Randomizer $randomizer): array;
    public function learnerPayload(array $configuration, array $workingState): array;
    public function evaluate(array $configuration, array $answer, array $workingState): array;
    public function answerFingerprint(array $configuration): string;
    public function previewPayload(array $configuration, array $workingState): array;
}
```

`evaluate()` returns this common envelope:

```php
[
    'accepted' => true,
    'is_correct' => false,
    'is_complete' => false,
    'working_state' => [],
]
```

The concrete handler supplies the real working state. Do not create a DTO for this fixed internal shape.

- [ ] **Step 1: Write failing registry and handler tests**

Cover:

- registry resolution for enum and string values, plus rejection of an unregistered value;
- Matching 2/12 acceptance and 1/13 rejection;
- normalized duplicate rejection in each Matching column;
- Sequencing 3/12 acceptance and 2/13 rejection;
- normalized duplicate sequence-item rejection;
- stable existing UUID preservation and server UUID creation for new rows;
- continuous one-based sequence positions;
- deterministic non-correct two-item and larger shuffles using `new Randomizer(new Mt19937(1234))`;
- Matching safe payload omitting pair IDs and relationships;
- Sequencing safe payload omitting `correct_position`;
- unknown, missing, or duplicate learner IDs rejected before evaluation;
- equal fingerprints for display metadata changes and different fingerprints for answer changes.

The secrecy assertions must inspect encoded JSON:

```php
$encoded = json_encode($handler->learnerPayload($configuration, $workingState), JSON_THROW_ON_ERROR);

$this->assertStringNotContainsString('pair-uuid', $encoded);
$this->assertStringNotContainsString('correct_position', $encoded);
```

- [ ] **Step 2: Run the test and verify failure**

```powershell
php artisan test tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php
```

Expected: FAIL because the registry and handlers are missing.

- [ ] **Step 3: Implement the registry**

Inject the two handlers and index them by backed-enum value:

```php
public function __construct(
    MatchingActivityHandler $matching,
    SequencingActivityHandler $sequencing,
) {
    $this->handlers = [
        $matching->type()->value => $matching,
        $sequencing->type()->value => $sequencing,
    ];
}

public function for(InteractiveActivityType|string $type): InteractiveActivityHandler
{
    $value = $type instanceof InteractiveActivityType ? $type->value : $type;

    return $this->handlers[$value]
        ?? throw new InvalidArgumentException("Unsupported interactive activity type: {$value}");
}
```

- [ ] **Step 4: Implement normalization and evaluation**

Normalize duplicate comparisons with trimmed, case-folded, collapsed whitespace while preserving trimmed display text. On update, preserve an ID only if it exists in the current stored configuration; otherwise issue `Str::uuid()`.

Every normalized configuration sets `schema_version = 1`; reject any other submitted or stored schema version. Version one accepts only item envelopes whose `kind` is `text`.

Use `Random\Randomizer` only. After shuffling, compare the proposed display order with the canonical order; rotate once when they match. For two items, reverse them.

Matching working state:

```php
[
    'right_order' => ['right-item-uuid-b', 'right-item-uuid-a'],
    'matched' => [
        ['left_id' => 'left-item-uuid-a', 'right_id' => 'right-item-uuid-a'],
    ],
]
```

Sequencing working state:

```php
[
    'item_order' => ['item-uuid-2', 'item-uuid-1', 'item-uuid-3'],
]
```

`answerFingerprint()` must hash only canonical answer material. Matching hashes normalized left/right relationships independent of authoring row order; Sequencing hashes normalized item values in canonical order. Exclude UUIDs and display-only metadata:

```php
return hash('sha256', json_encode($this->answerMaterial($configuration), JSON_THROW_ON_ERROR));
```

The authoring service will compare fingerprints before persisting an update.

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php
vendor/bin/pint --dirty
git add app/Contracts/Learning/InteractiveActivityHandler.php app/Services/Learning/InteractiveActivities/InteractiveActivityRegistry.php app/Services/Learning/InteractiveActivities/MatchingActivityHandler.php app/Services/Learning/InteractiveActivities/SequencingActivityHandler.php tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php
git commit -m "feat: validate interactive activity configurations"
```

Expected: validation, normalization, evaluation, secrecy, UUID, and deterministic-shuffle tests PASS.

---

### Task 3: Remove Legacy Interactive Topics and Isolate Optional Progress

**Files:**
- Create: `database/migrations/2026_09_02_000002_remove_legacy_interactive_topics.php`
- Modify: `app/Models/LessonTopic.php`
- Modify: `app/Http/Controllers/Instructor/LessonController.php`
- Modify: `app/Http/Controllers/Instructor/TopicController.php`
- Modify: `app/Http/Controllers/Learner/LessonController.php`
- Test: `tests/Feature/Instructor/LegacyInteractiveTopicRemovalTest.php`
- Test: `tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php`
- Test: `tests/Feature/Instructor/LessonManagementTest.php`

**Interfaces:**
- `LessonTopic::scopeInstructional()` excludes `interactive` and `interactive_checkpoint`.
- `LessonTopic::isOptionalInteraction(): bool` recognizes both optional host types.
- Existing `interactive` rows are deleted, not transformed.

- [ ] **Step 1: Write the failing cleanup and isolation tests**

Create two Lessons in one Module. In the first Lesson, create ordered text, legacy `interactive`, checkpoint, and worksheet Topics. Attach `lesson_topic_progress`, a conversation, a checkpoint question, and checkpoint progress to the legacy Topic. Require the migration and call `up()`.

Assert:

```php
$this->assertDatabaseMissing('lesson_topics', ['id' => $legacy->id]);
$this->assertDatabaseMissing('lesson_topic_progress', ['lesson_topic_id' => $legacy->id]);
$this->assertDatabaseMissing('quiz_questions', ['checkpoint_topic_id' => $legacy->id]);
$this->assertDatabaseHas('conversations', [
    'id' => $conversation->id,
    'lesson_topic_id' => null,
]);
$this->assertSame([1, 2, 3], $lesson->topics()->orderBy('order')->pluck('order')->all());
$this->assertSame(12, $lesson->fresh()->duration);
$this->assertSame(20, $module->fresh()->duration_minutes);
```

Also assert ordinary and checkpoint Topics remain, unrelated Lessons are unchanged, and the migration is idempotent. Add progress tests proving standalone activities are excluded from completion percentage, prerequisite locks, `completeTopic`, points, and Lesson completion.

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/LegacyInteractiveTopicRemovalTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php tests/Feature/Instructor/LessonManagementTest.php
```

Expected: FAIL because legacy rows remain and instructional queries still include `interactive`.

- [ ] **Step 3: Implement the irreversible cleanup migration**

In `up()`:

1. Collect distinct affected Lesson IDs from `lesson_topics.type = interactive`.
2. Delete those Topic rows. Existing cascades remove Topic progress, checkpoint questions, checkpoint progress, and future activity rows; `conversations.lesson_topic_id` becomes null through its existing foreign key.
3. For each affected Lesson, sort remaining Topics by `order`, then `id`, and rewrite order as a continuous one-based sequence.
4. Recalculate Lesson duration from rows whose type is neither `interactive` nor `interactive_checkpoint`.
5. Recalculate each affected Module's `duration_minutes` from its Lessons.
6. Leave `down()` empty with a comment explaining that deleted authored content cannot be reconstructed.

Do not delete storage files from a data migration; the approved destructive scope is database records and their dependent rows.

- [ ] **Step 4: Centralize optional Topic semantics in the model**

```php
public function scopeInstructional($query)
{
    return $query->whereNotIn('type', ['interactive', 'interactive_checkpoint']);
}

public function isOptionalInteraction(): bool
{
    return in_array($this->type, ['interactive', 'interactive_checkpoint'], true);
}
```

Replace direct `type != interactive_checkpoint` progress and duration filters in the touched Lesson and Topic controller paths with this scope or method. `completeTopic()` and `uncompleteTopic()` must reject either optional host type without writing progress or points.

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/LegacyInteractiveTopicRemovalTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php tests/Feature/Instructor/LessonManagementTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
vendor/bin/pint --dirty
git add database/migrations/2026_09_02_000002_remove_legacy_interactive_topics.php app/Models/LessonTopic.php app/Http/Controllers/Instructor/LessonController.php app/Http/Controllers/Instructor/TopicController.php app/Http/Controllers/Learner/LessonController.php tests/Feature/Instructor/LegacyInteractiveTopicRemovalTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php tests/Feature/Instructor/LessonManagementTest.php
git commit -m "refactor: isolate optional activity progress"
```

Expected: cleanup and isolation tests PASS; checkpoint isolation remains unchanged.

---

### Task 4: Create Activities Through the Existing Topic Workflow

**Files:**
- Create: `app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php`
- Modify: `app/Http/Controllers/Instructor/TopicController.php`
- Test: `tests/Feature/Instructor/InteractiveActivityAuthoringTest.php`

**Interfaces:**

```php
public function validate(Request $request, Lesson $lesson, ?InteractiveActivity $activity = null): array;
public function create(Lesson $lesson, array $data): InteractiveActivity;
public function update(InteractiveActivity $activity, array $data): InteractiveActivity;
public function delete(InteractiveActivity $activity): void;
public function preview(array $data): array;
```

Creation requests use this stable boundary:

```php
[
    'lesson_id' => $lesson->id,
    'title' => 'Match the concepts',
    'type' => 'interactive',
    'activity_type' => 'matching',
    'placement' => 'between_topics',
    'parent_topic_id' => null,
    'insert_after_block' => null,
    'instructions' => '<p>Select each related pair.</p>',
    'explanation' => '<p>These concepts belong together.</p>',
    'configuration' => [
        'pairs' => [
            ['left' => ['value' => 'Consent'], 'right' => ['value' => 'Freely given agreement']],
            ['left' => ['value' => 'Boundary'], 'right' => ['value' => 'A personal limit']],
        ],
    ],
]
```

- [ ] **Step 1: Add failing create and authorization tests**

Cover Matching and Sequencing creation in both placements, neutral standalone metadata, server-generated UUIDs, one standalone activity per host, parent block reference shape, eligible same-Lesson parent enforcement, rejection of activity/checkpoint parents, count and duplicate validation, instructor ownership, admin mutation restrictions, and omission of `duration` from requests.

Assert an inside reference exactly:

```php
$this->assertContains([
    'type' => 'interactive_activity',
    'uuid' => $activity->block_uuid,
    'activity_id' => $activity->id,
], $parent->fresh()->content_blocks);
```

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
```

Expected: FAIL because the generic legacy branch requires duration and stores `interactive_config` on the Topic.

- [ ] **Step 3: Add the early TopicController delegation**

After Lesson authorization and the admin mutation guard, branch before generic Topic validation:

```php
if ($request->input('type') === 'interactive') {
    $data = $this->activityAuthoring->validate($request, $lessonForAuthorization);
    $this->activityAuthoring->create($lessonForAuthorization, $data);

    return redirect()
        ->route($this->routeName('lessons.show'), $lessonForAuthorization)
        ->with('success', 'Interactive activity created successfully.');
}
```

Remove `interactive_type`, `interactive_instructions`, and legacy `activity|simulation|exercise` handling from the generic validator and persistence branch.

- [ ] **Step 4: Implement transactional placement creation**

For `between_topics`, create a standalone host at the next Topic order with the activity title, `type = interactive`, zero duration, and false prerequisite, then create its one activity.

For `inside_topic`, resolve the parent through `$lesson->topics()`, authorize update on that parent, reject optional-interaction parents, generate the block UUID on the server, create the activity against the parent, and insert this reference at the bounded requested block index:

```php
[
    'type' => 'interactive_activity',
    'uuid' => $blockUuid,
    'activity_id' => $activity->id,
]
```

Sanitize instructions and explanation with this existing project allowlist inside the authoring service:

```php
strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><code>')
```

Validate title at 255 characters, each typed item value at 500, and instructions/explanation at 10,000. Recalculate Lesson and Module durations from instructional Topics before the transaction returns.

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php --filter=create
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php --filter=authorization
vendor/bin/pint --dirty
git add app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php app/Http/Controllers/Instructor/TopicController.php tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
git commit -m "feat: create interactive activities"
```

Expected: create and authorization cases PASS for both types and placements.

---

### Task 5: Revision-Aware Editing, Placement Changes, and Deletion

**Files:**
- Create: `app/Http/Controllers/Instructor/InteractiveActivityController.php`
- Modify: `app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php`
- Modify: `routes/instructor.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/instructor/lessons/show.blade.php`
- Test: `tests/Feature/Instructor/InteractiveActivityAuthoringTest.php`

**Interfaces:**
- Activity type is immutable.
- Placement, block reference, host metadata, definition, and revision change in one transaction.
- Answer fingerprint change increments revision once; wording-only changes preserve it.

- [ ] **Step 1: Add failing edit, move, revision, and deletion tests**

Cover:

- title/instructions/explanation-only edit stays on revision 1;
- Matching pair edit/add/remove/relation change increments to 2;
- Sequencing text/order/add/remove increments to 2;
- an identical normalized resubmission does not increment again;
- submitted activity type change is rejected;
- inside activity can move to another eligible parent;
- inside can become between and between can become inside;
- old block references are removed and new references are valid;
- moving from between reassigns the activity before deleting its old host;
- deleting inside preserves its parent and removes only its block/activity/progress;
- deleting between removes its host/activity/progress and resequences the Lesson;
- deleting a parent Topic cascades its activities and progress;
- read-only admin actions remain blocked.

- [ ] **Step 2: Run the focused tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php --filter="edit|move|revision|delete"
```

Expected: FAIL because update and delete behavior is not complete.

- [ ] **Step 3: Implement revision comparison and immutable type**

Resolve the stored handler from `$activity->activity_type`. Do not resolve a replacement handler from request input. Normalize with the existing configuration so valid stored UUIDs survive. Compare:

```php
$answerChanged = $handler->answerFingerprint($activity->configuration)
    !== $handler->answerFingerprint($normalizedConfiguration);

$nextRevision = $answerChanged
    ? ((int) $activity->revision) + 1
    : (int) $activity->revision;
```

Update shared wording without revision changes. Old progress rows remain attached to their old revision.

- [ ] **Step 4: Implement safe placement transitions**

Use this order inside one transaction:

1. Validate and lock the current activity and current host.
2. Create or lock the destination host/parent.
3. Add the destination block reference when moving inside.
4. Update `lesson_topic_id`, `placement`, and `block_uuid` on the activity.
5. Remove the prior inside block reference.
6. Delete the old standalone host only after the activity no longer references it.
7. Force neutral metadata on any standalone destination.
8. Resequence affected Lesson Topics and recalculate durations.

- [ ] **Step 5: Implement deletion and Lesson-page actions**

The service removes an inside block by matching all three fields: type, UUID, and activity ID. Between deletion removes the standalone host and relies on its cascade. Add edit and remove actions to the existing Lesson details page using `ContentPanelContext` route names and the page's existing accessible removal modal pattern. The warning must distinguish removal of an activity from removal of its parent Topic.

Create the focused controller with `edit`, `update`, and `destroy`, reusing Topic policy authorization, `ContentPanelContext`, `ContentOwnershipGuard`, and the parent Lesson's read-only-admin mutation check. Register these routes under both existing instructor and admin middleware groups:

```php
Route::get('interactive-activities/{interactiveActivity}/edit', [Instructor\InteractiveActivityController::class, 'edit'])
    ->name('interactive-activities.edit');
Route::put('interactive-activities/{interactiveActivity}', [Instructor\InteractiveActivityController::class, 'update'])
    ->name('interactive-activities.update');
Route::delete('interactive-activities/{interactiveActivity}', [Instructor\InteractiveActivityController::class, 'destroy'])
    ->name('interactive-activities.destroy');
```

- [ ] **Step 6: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php
vendor/bin/pint --dirty
git add app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php app/Http/Controllers/Instructor/InteractiveActivityController.php routes/instructor.php routes/admin.php resources/views/instructor/lessons/show.blade.php tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
git commit -m "feat: edit interactive activity placements"
```

Expected: authoring and checkpoint regression tests PASS, including cross-placement moves and cleanup.

---

### Task 6: Matching and Sequencing Authoring Builders

**Files:**
- Create: `resources/js/interactive-activity-authoring.js`
- Create: `resources/views/instructor/topics/partials/interactive-activity-fields.blade.php`
- Create: `resources/views/instructor/topics/partials/matching-builder.blade.php`
- Create: `resources/views/instructor/topics/partials/sequencing-builder.blade.php`
- Create: `resources/views/instructor/topics/edit-interactive-activity.blade.php`
- Modify: `resources/views/instructor/topics/create.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/JavaScript/interactive-activity-authoring.test.mjs`
- Test: `tests/Feature/Instructor/InteractiveActivityAuthoringTest.php`

**Interfaces:**
- `createInteractiveActivityAuthoring(config)` owns selected subtype, placement, builder rows, reorder actions, and serialized preview form data.
- The form submits `type = interactive` plus `activity_type = matching|sequencing`.
- Existing IDs are JSON-initialized with `@js`; new IDs remain absent until server normalization.

- [ ] **Step 1: Write failing pure JavaScript tests**

Cover add/remove bounds, reorder, Matching left/right values remaining paired, Sequencing order serialization, stored UUID preservation, placement field enablement, and safe initial data containing quotes and line breaks.

Use direct state assertions:

```javascript
const authoring = createInteractiveActivityAuthoring({
    activityType: 'sequencing',
    items: [
        { id: 'one', value: 'First' },
        { id: 'two', value: 'Second' },
        { id: 'three', value: 'Third' },
    ],
});

authoring.moveItem(2, -1);
assert.deepEqual(authoring.configuration().items.map((item) => item.id), ['one', 'three', 'two']);
```

- [ ] **Step 2: Add failing feature-view assertions**

Assert the create page has direct `Matching` and `Sequencing` cards, no legacy Activity/Simulation/Exercise controls, hides and disables Topic metadata for either activity card, exposes placement and eligible parent choices, and initializes edit data with JSON rather than interpolated JavaScript strings. Assert the activity edit page contains no duration or prerequisite field.

- [ ] **Step 3: Run tests and verify failure**

```powershell
node --test tests/JavaScript/interactive-activity-authoring.test.mjs
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php --filter="page|form|edit_view"
```

Expected: FAIL because the builders and direct type cards do not exist.

- [ ] **Step 4: Implement the direct cards and common fields**

Both cards may submit the same radio value `interactive`; distinguish them with `data-activity-type` and a hidden `activity_type` input. Extend the existing create-page type-switch function to track both Topic family and activity subtype. Disable the duration/prerequisite fieldset when the type is `interactive_checkpoint` or `interactive`.

Render parent choices only from the same Lesson's instructional Topics. Render insertion choices from their existing checkpoint/activity blocks without splitting canonical Topic body content.

- [ ] **Step 5: Implement accessible builders**

Matching rows expose left text, right text, Move Up, Move Down, and Remove; enforce UI bounds while leaving security validation to PHP. Sequencing rows expose a drag handle, current position, text, Move Up, Move Down, and Remove. Use native pointer events for authoring drag and keep buttons fully functional for touch and keyboard users.

Register only one factory in `app.js`:

```javascript
window.interactiveActivityAuthoring = createInteractiveActivityAuthoring;
```

- [ ] **Step 6: Verify and commit**

```powershell
node --test tests/JavaScript/interactive-activity-authoring.test.mjs
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
pnpm.cmd build
git add resources/js/interactive-activity-authoring.js resources/views/instructor/topics/partials/interactive-activity-fields.blade.php resources/views/instructor/topics/partials/matching-builder.blade.php resources/views/instructor/topics/partials/sequencing-builder.blade.php resources/views/instructor/topics/edit-interactive-activity.blade.php resources/views/instructor/topics/create.blade.php resources/js/app.js tests/JavaScript/interactive-activity-authoring.test.mjs tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
git commit -m "feat: add interactive activity builders"
```

Expected: authoring JS, feature tests, and Vite build PASS.

---

### Task 7: Current-Revision Progress, Presenter, and Learner Authorization

**Files:**
- Create: `app/Services/Learning/InteractiveActivities/InteractiveActivityProgressService.php`
- Create: `app/Services/Learning/InteractiveActivities/InteractiveActivityPresenter.php`
- Create: `app/Http/Controllers/Learner/InteractiveActivityController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learner/InteractiveActivityProgressTest.php`

**Interfaces:**

```php
public function stateFor(User $user, InteractiveActivity $activity): InteractiveActivityProgress;
public function evaluate(User $user, InteractiveActivity $activity, array $answer, bool $practice = false): array;
public function saveWorkingState(User $user, InteractiveActivity $activity, array $state): InteractiveActivityProgress;
public function skip(User $user, InteractiveActivity $activity): InteractiveActivityProgress;
public function resume(User $user, InteractiveActivity $activity): InteractiveActivityProgress;
public function practice(InteractiveActivity $activity): array;
```

```php
public function present(
    InteractiveActivity $activity,
    ?InteractiveActivityProgress $progress,
    bool $practice = false,
): array;
```

- [ ] **Step 1: Add failing lifecycle and access tests**

Cover first-view state creation, persisted shuffle restoration, unique current-revision rows, current revision after answer-affecting edit, idempotent skip, resume retaining state, completion superseding skip, completion never downgrading, state save without attempt increment, evaluation with one attempt increment, practice returning a new non-correct shuffle without DB mutation, published Lesson requirement, visible Module requirement, approved enrollment, wrong learner, wrong activity revision, unknown activity, and CSRF-protected routes.

- [ ] **Step 2: Run the test and verify failure**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivityProgressTest.php
```

Expected: FAIL because progress orchestration and learner routes are missing.

- [ ] **Step 3: Implement atomic current-revision state**

Lock the activity row at the start of each mutation, then load or create progress by user, activity, and the activity's current revision. This serializes first-state creation and prevents duplicate increments. Initialize `started_at` and handler-generated working state on first creation.

Transitions:

```text
new -> in_progress
in_progress -> skipped
skipped -> in_progress
in_progress|skipped -> completed
completed -> completed
```

Only accepted `evaluate()` calls increment `attempt_count`. `saveWorkingState()`, `skip()`, `resume()`, and `practice()` never increment it. Practice never writes a progress row.

Practice starts with an unresolved presentation even when persisted progress is completed. Hide the explanation until the local practice round completes, then return it for that response while leaving the stored completed row unchanged.

- [ ] **Step 4: Implement the safe presenter and malformed fallback**

The presenter calls only the registered handler's learner-payload method and returns common metadata, status, counts, explanation only for completed persisted state, and the safe type payload. Catch malformed configuration, log activity ID, Topic ID, type, and validation message, and return `available = false` without the stored configuration.

- [ ] **Step 5: Add learner routes and one authorization boundary**

Register:

```php
Route::post('/interactive-activities/{interactiveActivity}/match', [InteractiveActivityController::class, 'match'])
    ->name('interactive-activities.match');
Route::post('/interactive-activities/{interactiveActivity}/check-sequence', [InteractiveActivityController::class, 'checkSequence'])
    ->name('interactive-activities.check-sequence');
Route::put('/interactive-activities/{interactiveActivity}/state', [InteractiveActivityController::class, 'saveState'])
    ->name('interactive-activities.state');
Route::post('/interactive-activities/{interactiveActivity}/skip', [InteractiveActivityController::class, 'skip'])
    ->name('interactive-activities.skip');
Route::post('/interactive-activities/{interactiveActivity}/resume', [InteractiveActivityController::class, 'resume'])
    ->name('interactive-activities.resume');
Route::post('/interactive-activities/{interactiveActivity}/practice', [InteractiveActivityController::class, 'practice'])
    ->name('interactive-activities.practice');
```

In one private controller method, verify the host Lesson is published, its Module is learner-visible, enrollment is approved, and request `revision` equals the activity's current revision. Return 404 for invalid ownership/resources, 403 for access denial, and 409 with `Activity changed. Reload to continue.` for stale revision.

- [ ] **Step 6: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivityProgressTest.php
vendor/bin/pint --dirty
git add app/Services/Learning/InteractiveActivities/InteractiveActivityProgressService.php app/Services/Learning/InteractiveActivities/InteractiveActivityPresenter.php app/Http/Controllers/Learner/InteractiveActivityController.php routes/web.php tests/Feature/Learner/InteractiveActivityProgressTest.php
git commit -m "feat: persist optional activity state"
```

Expected: lifecycle, revision, practice, idempotence, and access tests PASS.

---

### Task 8: Matching Learner API and Component

**Files:**
- Create: `resources/js/interactive-activity.js`
- Create: `resources/js/matching-activity.js`
- Create: `resources/views/learner/lessons/partials/interactive-activities/shell.blade.php`
- Create: `resources/views/learner/lessons/partials/interactive-activities/matching.blade.php`
- Modify: `app/Http/Controllers/Learner/InteractiveActivityController.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Learner/MatchingActivityFlowTest.php`
- Test: `tests/JavaScript/interactive-activity.test.mjs`
- Test: `tests/JavaScript/matching-activity.test.mjs`

**Interfaces:**
- `createInteractiveActivity(config, request)` owns common submitting/error/skip/resume/practice/continue state.
- `createMatchingActivity(config, request)` owns selected IDs, completed pairs, feedback, and connector geometry.
- `calculateConnectorLines(leftRects, rightRects, containerRect)` is pure and independently tested.

- [ ] **Step 1: Write failing Matching endpoint tests**

Cover safe initial payload, persisted right order, one correct pair lock, one incorrect proposal clearing only the proposal, retained previous correct pairs, one attempt per accepted pair request, malformed/unknown IDs returning 422 without an increment, completion after all pairs, explanation only after completion, skip/resume, completed revisit, practice evaluation with unchanged persisted completion, stale revision, and absence of Quiz/shield/score/gamification writes.

Assert response secrecy on every state:

```php
$response->assertJsonMissingPath('configuration');
$response->assertJsonMissingPath('pair_id');
$this->assertStringNotContainsString('correct_mapping', $response->getContent());
```

- [ ] **Step 2: Write failing Matching JavaScript tests**

Cover left/right selection, `aria-pressed` state source, request lock, correct locking, incorrect clearing, network failure retaining completed pairs, restored state, completed read-only state, non-mutating practice reset, and connector centers before and after simulated resize geometry.

- [ ] **Step 3: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Learner/MatchingActivityFlowTest.php
node --test tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/matching-activity.test.mjs
```

Expected: FAIL because the Matching controller adapter and components are missing.

- [ ] **Step 4: Implement the Matching endpoint adapter**

Validate `left_id`, `right_id`, `revision`, and optional `practice`. Reject non-Matching activities. Delegate to the generic progress service and return the presenter's safe updated state. The controller must not compare IDs itself.

- [ ] **Step 5: Implement shared lifecycle and Matching state**

Use the existing Axios/CSRF setup through an injected request function. During a pair request, disable conflicting choices. Announce `Not quite—try another match` in a polite live region on incorrect state. Keep correct pairs textual and locked. Completed state removes Skip, shows the optional explanation, and provides Continue and Practice Again.

Use `ResizeObserver`, scroll/resize listeners, and a decorative `aria-hidden="true"` SVG only at the desktop breakpoint. On narrow layouts, render completed-pair cards and do not depend on lines.

- [ ] **Step 6: Register the factories and render the partial in isolation tests**

```javascript
window.interactiveActivity = createInteractiveActivity;
window.matchingActivity = createMatchingActivity;
```

The shell displays the exact learner family label and supplies common status/actions. The Matching partial supplies item controls and connector layers.

- [ ] **Step 7: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/MatchingActivityFlowTest.php tests/Feature/Learner/InteractiveActivityQuizRegressionTest.php
node --test tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/matching-activity.test.mjs
pnpm.cmd build
git add resources/js/interactive-activity.js resources/js/matching-activity.js resources/views/learner/lessons/partials/interactive-activities/shell.blade.php resources/views/learner/lessons/partials/interactive-activities/matching.blade.php app/Http/Controllers/Learner/InteractiveActivityController.php resources/js/app.js tests/Feature/Learner/MatchingActivityFlowTest.php tests/Feature/Learner/InteractiveActivityQuizRegressionTest.php tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/matching-activity.test.mjs
git commit -m "feat: add matching learner flow"
```

Expected: Matching API, state, secrecy, isolation, JS, and build checks PASS.

---

### Task 9: Sequencing Learner API and Component

**Files:**
- Create: `resources/js/sequencing-activity.js`
- Create: `resources/views/learner/lessons/partials/interactive-activities/sequencing.blade.php`
- Modify: `app/Http/Controllers/Learner/InteractiveActivityController.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Learner/SequencingActivityFlowTest.php`
- Test: `tests/JavaScript/sequencing-activity.test.mjs`

**Interfaces:**
- `createSequencingActivity(config, request)` owns one item-ID order array.
- `moveItem(order, index, delta)` is the shared pure reorder primitive for buttons and keyboard.
- Debounced state persistence submits the full current item order and never changes attempt count.

- [ ] **Step 1: Write failing Sequencing endpoint tests**

Cover non-correct persisted initial order, state-save restoration with zero attempts, exact-order success, incorrect order with no position hints, missing/duplicate/unknown IDs rejected without increment, one increment per accepted Check Answer, explanation only after completion, skip/resume, completed read-only revisit, practice without persisted mutation, stale revision, and Quiz/gamification isolation.

- [ ] **Step 2: Write failing Sequencing JavaScript tests**

Cover Move Up/Down bounds, keyboard reordering, pointer reorder using the same array, position announcements, debounced save, check-request locking, incorrect order preservation, request-error preservation, completed controls locked, skipped resume, and practice reset.

- [ ] **Step 3: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Learner/SequencingActivityFlowTest.php
node --test tests/JavaScript/sequencing-activity.test.mjs
```

Expected: FAIL because the Sequencing adapter and UI are missing.

- [ ] **Step 4: Implement the Sequencing endpoint adapter**

Validate `item_ids` as an array of strings, `revision`, and optional `practice`. Reject non-Sequencing activities. The state endpoint accepts only the current exact item set; it stores order but never evaluates it or increments attempts. The check endpoint delegates complete-order evaluation to the handler.

- [ ] **Step 5: Implement native reordering and accessible feedback**

One order array drives pointer, buttons, and keyboard. Every row exposes current position and total, plus explicit `Move [item] up` and `Move [item] down` labels. Announce changed positions. Debounce PUT state calls, flush current state with Check Answer, and retain the order after failures.

Incorrect feedback is exactly `Not quite—try again`; do not mark positions or show explanation. Correct state locks controls and shows explanation, Continue, and Practice Again.

- [ ] **Step 6: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/SequencingActivityFlowTest.php tests/Feature/Learner/InteractiveActivityQuizRegressionTest.php
node --test tests/JavaScript/sequencing-activity.test.mjs
pnpm.cmd build
git add resources/js/sequencing-activity.js resources/views/learner/lessons/partials/interactive-activities/sequencing.blade.php app/Http/Controllers/Learner/InteractiveActivityController.php resources/js/app.js tests/Feature/Learner/SequencingActivityFlowTest.php tests/JavaScript/sequencing-activity.test.mjs
git commit -m "feat: add sequencing learner flow"
```

Expected: Sequencing API, persistence, accessibility state, isolation, JS, and build checks PASS.

---

### Task 10: Lesson Composition, Sidebar State, and Single Continue Ownership

**Files:**
- Create: `resources/views/learner/lessons/partials/interactive-activities/unavailable.blade.php`
- Modify: `app/Http/Controllers/Learner/LessonController.php`
- Modify: `resources/views/learner/lessons/partials/topic-page.blade.php`
- Modify: `resources/views/learner/lessons/partials/lesson-forward-action.blade.php`
- Modify: `resources/views/learner/lessons/show.blade.php`
- Modify: `resources/js/interactive-checkpoint.js`
- Test: `tests/Feature/Learner/InteractiveActivityRenderingTest.php`
- Test: `tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php`
- Test: `tests/JavaScript/interactive-activity.test.mjs`
- Test: `tests/JavaScript/interactive-checkpoint.test.mjs`

**Interfaces:**
- Only activities belonging to the current Topic are initialized on a Lesson GET.
- Resolved standalone activity host IDs come only from current-revision `completed|skipped` activity progress.
- Required completion continues to use only `LessonTopic::instructional()` and `lesson_topic_progress`.
- `createOptionalInteractionCoordinator()` accepts string tokens; `createCheckpointCoordinator()` remains a compatibility alias.

- [ ] **Step 1: Add failing rendering and navigation tests**

Cover:

- canonical video/text/worksheet body renders before inside optional blocks;
- multiple inside checkpoints and activities follow `content_blocks` order after canonical body;
- inside activities do not appear in the sidebar;
- between activities appear in ordered navigation and use `INTERACTIVE ACTIVITY · Optional` without `0m` or `Required`;
- completed and skipped current revisions resolve a standalone host;
- old-revision completion does not resolve the new revision;
- ordinary required Topic counts and locks ignore standalone activities;
- direct Topic completion rejects activities;
- malformed inside activity is absent while the Topic body and footer remain;
- malformed between activity shows unavailable state and Continue;
- only current-Topic activity progress is initialized;
- exactly one forward action is visible at each activity/checkpoint state.

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivityRenderingTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php
node --test tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
```

Expected: FAIL because the Lesson controller, Topic renderer, sidebar, and coordinator do not know Interactive Activities.

- [ ] **Step 3: Resolve optional activity navigation separately**

Query current-revision progress for standalone activity IDs across the Lesson and derive:

```php
$resolvedActivityTopicIds = $activityProgress
    ->filter(fn (InteractiveActivityProgress $progress) =>
        in_array($progress->status, ['completed', 'skipped'], true)
        && $progress->activity_revision === $progress->activity->revision
        && $progress->activity->placement === 'between_topics')
    ->pluck('activity.lesson_topic_id')
    ->map(fn ($id) => (int) $id)
    ->all();
```

Combine these IDs with completed instructional Topics and resolved standalone checkpoints only for current ordered-item selection/sidebar state. Never feed them into required completion percentage, locks, Quiz eligibility, points, or certification.

After selecting the current Topic, load and initialize only its activity definitions. Build presentations keyed by activity ID for the renderer.

- [ ] **Step 4: Render valid blocks and safe fallback states**

Keep the existing Topic-type body first. Then iterate `content_blocks` and:

- render checkpoint blocks through their current partial;
- render an activity only when activity ID, block UUID, placement, and parent Topic all match;
- omit and log an invalid inside reference;
- render the standalone host's one activity for `type = interactive`;
- render the unavailable partial with a safe Continue when the standalone definition is absent or malformed.

Remove the legacy `Interactive content coming soon` branch.

- [ ] **Step 5: Generalize Continue coordination**

Export `createOptionalInteractionCoordinator()` with `activate(token)`, `release(token)`, and `footerForwardVisible()`. Keep:

```javascript
export function createCheckpointCoordinator() {
    return createOptionalInteractionCoordinator();
}
```

Use tokens such as `checkpoint:42` and `activity:17`. Beginning any inside optional interaction suppresses the ordinary footer. Continue focuses the next unresolved inside interaction in DOM order; if none remains, release the footer. A between-topic activity Continue uses the controller-provided next Lesson item URL. Checkpoint behavior and tests must remain green.

- [ ] **Step 6: Verify and commit**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivityRenderingTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php
node --test tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
pnpm.cmd build
vendor/bin/pint --dirty
git add resources/views/learner/lessons/partials/interactive-activities/unavailable.blade.php app/Http/Controllers/Learner/LessonController.php resources/views/learner/lessons/partials/topic-page.blade.php resources/views/learner/lessons/partials/lesson-forward-action.blade.php resources/views/learner/lessons/show.blade.php resources/js/interactive-checkpoint.js tests/Feature/Learner/InteractiveActivityRenderingTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
git commit -m "feat: compose activities in lessons"
```

Expected: rendering, navigation, checkpoint regression, JS, and build checks PASS.

---

### Task 11: Unsaved Interactive Author Preview

**Files:**
- Create: `resources/views/instructor/topics/partials/interactive-activity-preview-modal.blade.php`
- Modify: `app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php`
- Modify: `app/Http/Controllers/Instructor/InteractiveActivityController.php`
- Modify: `resources/js/interactive-activity-authoring.js`
- Modify: `resources/views/instructor/topics/partials/interactive-activity-fields.blade.php`
- Modify: `resources/views/instructor/topics/create.blade.php`
- Modify: `resources/views/instructor/topics/edit-interactive-activity.blade.php`
- Modify: `routes/instructor.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Instructor/InteractiveActivityAuthoringTest.php`
- Test: `tests/JavaScript/interactive-activity-authoring.test.mjs`

**Interfaces:**
- POST preview uses the same validation and normalization as save, but performs no database write.
- Preview response is `{ html: string }`.
- Preview embeds an answer key only in its authorized local adapter; production learner views never receive it.

- [ ] **Step 1: Add failing preview endpoint tests**

Cover instructor and writable-admin authorization, cross-Lesson parent rejection, validation parity for both types, no activity/Topic/progress rows created, sanitized rich text, generated temporary IDs, interactive correct/incorrect behavior configuration, and response support for mobile/tablet/desktop preview widths.

- [ ] **Step 2: Add failing preview JavaScript tests**

Cover `FormData` submission, 422 field error display, modal open/close, viewport selection, injected Alpine tree initialization, local Matching evaluation, local Sequencing evaluation, local skip/practice state, and disabled real navigation.

- [ ] **Step 3: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php --filter=preview
node --test tests/JavaScript/interactive-activity-authoring.test.mjs
```

Expected: FAIL because preview returns no learner component.

- [ ] **Step 4: Implement validation-only preview**

The controller authorizes the submitted Lesson, calls the same authoring validator and handler normalization, creates an in-memory preview model, obtains handler `previewPayload()`, and renders the shared learner shell with `preview = true`. Do not call `create()`, `update()`, or the progress service.

Register under both authoring route groups:

```php
Route::post('interactive-activities/preview', [Instructor\InteractiveActivityController::class, 'preview'])
    ->name('interactive-activities.preview');
```

The preview-only payload may contain an answer key for the local adapter. Keep that value scoped inside the returned authorized HTML and never add it to `InteractiveActivityPresenter::present()`.

- [ ] **Step 5: Implement the modal and Alpine initialization**

Add Interactive Preview to create and edit builders. Inject the returned fragment, then initialize it with Alpine's supported `initTree` API. Viewport buttons constrain the preview frame near 375, 768, and 1440 pixels without navigating. Close restores focus to the Preview button.

- [ ] **Step 6: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/InteractiveActivityAuthoringTest.php
node --test tests/JavaScript/interactive-activity-authoring.test.mjs tests/JavaScript/matching-activity.test.mjs tests/JavaScript/sequencing-activity.test.mjs
pnpm.cmd build
vendor/bin/pint --dirty
git add resources/views/instructor/topics/partials/interactive-activity-preview-modal.blade.php app/Services/Learning/InteractiveActivities/InteractiveActivityAuthoringService.php app/Http/Controllers/Instructor/InteractiveActivityController.php resources/js/interactive-activity-authoring.js resources/views/instructor/topics/partials/interactive-activity-fields.blade.php resources/views/instructor/topics/create.blade.php resources/views/instructor/topics/edit-interactive-activity.blade.php routes/instructor.php routes/admin.php tests/Feature/Instructor/InteractiveActivityAuthoringTest.php tests/JavaScript/interactive-activity-authoring.test.mjs
git commit -m "feat: preview unsaved interactive activities"
```

Expected: preview authorization, validation, non-persistence, JS, and build checks PASS.

---

### Task 12: Publication Snapshot and Admin Review Integration

**Files:**
- Modify: `app/Services/ContentGovernanceService.php`
- Modify: `app/Services/AdminModuleReviewWorkspaceService.php`
- Modify: `app/Http/Requests/Admin/ContentReviewPreviewRequest.php`
- Modify: `resources/views/admin/content-reviews/partials/workspace-tree.blade.php`
- Modify: `tests/Feature/Instructor/InstructorModuleReviewSubmissionTest.php`
- Modify: `tests/Feature/Admin/AdminContentReviewWorkspaceDataTest.php`
- Modify: `tests/Feature/Admin/AdminContentReviewPreviewEndpointTest.php`
- Modify: `tests/Feature/Admin/AdminContentReviewWorkspaceUiTest.php`

**Interfaces:**
- Revision snapshots nest full activity definitions under their host Topic and include `content_blocks`.
- Admin review preview accepts `node_type = activity` in addition to `topic|quiz`.
- Review data comes from the frozen revision snapshot before falling back to live Module data.

- [ ] **Step 1: Add failing snapshot and review tests**

Submit a Module with one inside Matching activity and one between Sequencing activity. Assert the snapshot contains activity metadata, revision, placement, block UUID, and full answer configuration under the correct Topics. Edit the live activities after submission and assert admin review still shows the submitted revision.

Add endpoint assertions for `node_type=activity`, admin-only access, Matching pair review, Sequencing canonical-order review, sanitized instructions/explanation, and 404 for an activity absent from the frozen snapshot.

- [ ] **Step 2: Run tests and verify failure**

```powershell
php artisan test tests/Feature/Instructor/InstructorModuleReviewSubmissionTest.php tests/Feature/Admin/AdminContentReviewWorkspaceDataTest.php tests/Feature/Admin/AdminContentReviewPreviewEndpointTest.php tests/Feature/Admin/AdminContentReviewWorkspaceUiTest.php
```

Expected: FAIL because snapshots and review nodes omit related activities.

- [ ] **Step 3: Extend frozen snapshot creation**

Eager-load `lessons.topics.interactiveActivities` in `createRevisionSnapshot()`. Add `content_blocks` to Topic attributes and nest:

```php
'interactive_activities' => $topic->interactiveActivities->map(fn ($activity) => $activity->only([
    'id',
    'lesson_topic_id',
    'placement',
    'block_uuid',
    'activity_type',
    'title',
    'instructions',
    'explanation',
    'configuration',
    'revision',
]))->values()->all(),
```

Apply the same shape to `AdminModuleReviewWorkspaceService` live-data fallback.

- [ ] **Step 4: Add activity hierarchy and preview nodes**

Allow `activity` in `ContentReviewPreviewRequest`. Resolve it from nested snapshot activities first. Return full authored mapping/order only because this endpoint is admin-authorized review tooling. Sanitize rich text before returning it.

Show inside activities nested under their parent Topic and between activities as optional ordered Topic entries. Add `previewActivity` state to the existing Alpine workspace, and render a read-only Matching relationship list or canonical Sequencing order. Do not initialize learner progress or learner endpoints in admin review.

- [ ] **Step 5: Verify and commit**

```powershell
php artisan test tests/Feature/Instructor/InstructorModuleReviewSubmissionTest.php tests/Feature/Admin/AdminContentReviewWorkspaceDataTest.php tests/Feature/Admin/AdminContentReviewPreviewEndpointTest.php tests/Feature/Admin/AdminContentReviewWorkspaceUiTest.php tests/Feature/Admin/AdminContentReviewWorkflowTest.php
vendor/bin/pint --dirty
git add app/Services/ContentGovernanceService.php app/Services/AdminModuleReviewWorkspaceService.php app/Http/Requests/Admin/ContentReviewPreviewRequest.php resources/views/admin/content-reviews/partials/workspace-tree.blade.php tests/Feature/Instructor/InstructorModuleReviewSubmissionTest.php tests/Feature/Admin/AdminContentReviewWorkspaceDataTest.php tests/Feature/Admin/AdminContentReviewPreviewEndpointTest.php tests/Feature/Admin/AdminContentReviewWorkspaceUiTest.php
git commit -m "feat: review interactive activities"
```

Expected: frozen snapshot, admin preview, workflow regression, and sanitization tests PASS.

---

### Task 13: Full Regression and Responsive End-to-End Verification

**Files:**
- Verify: `tests/Feature/Learner/InteractiveActivityQuizRegressionTest.php` created in Task 8.
- Verify: every file changed in Tasks 1–12.
- Verify against: `docs/superpowers/specs/2026-09-02-interactive-activities-design.md`.

**Interfaces:**
- Consumes the complete authoring, learner, preview, navigation, publication, and review workflow.
- Produces a verified implementation with no targeted, full-suite, build, formatting, whitespace, or responsive-browser failures.

- [ ] **Step 1: Run all Interactive Activity backend tests**

```powershell
php artisan test tests/Feature/Learner/InteractiveActivitySchemaTest.php tests/Feature/Instructor/LegacyInteractiveTopicRemovalTest.php tests/Feature/Instructor/InteractiveActivityAuthoringTest.php tests/Feature/Learner/InteractiveActivityProgressTest.php tests/Feature/Learner/MatchingActivityFlowTest.php tests/Feature/Learner/SequencingActivityFlowTest.php tests/Feature/Learner/InteractiveActivityRenderingTest.php tests/Feature/Learner/InteractiveActivityProgressIsolationTest.php tests/Feature/Learner/InteractiveActivityQuizRegressionTest.php tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php
```

Expected: all listed tests PASS.

- [ ] **Step 2: Run checkpoint, Topic, Quiz, and review regression tests**

```powershell
php artisan test tests/Feature/Instructor/InteractiveCheckpointAuthoringTest.php tests/Feature/Learner/InteractiveCheckpointSchemaTest.php tests/Feature/Learner/InteractiveCheckpointFlowTest.php tests/Feature/Learner/InteractiveCheckpointProgressIsolationTest.php tests/Feature/Learner/InteractiveCheckpointRenderingTest.php tests/Feature/Learner/InteractiveCheckpointQuizRegressionTest.php tests/Feature/Instructor/LessonManagementTest.php tests/Feature/Instructor/QuizQuestionAuthoringRegressionTest.php tests/Feature/Learner/LearnerQuizAttemptLimitTest.php tests/Feature/Learner/LearnerQuizResultShieldPopupTest.php tests/Feature/Instructor/InstructorModuleReviewSubmissionTest.php tests/Feature/Admin/AdminContentReviewWorkflowTest.php tests/Feature/Admin/AdminContentReviewWorkspaceDataTest.php tests/Feature/Admin/AdminContentReviewPreviewEndpointTest.php tests/Feature/Admin/AdminContentReviewWorkspaceUiTest.php
```

Expected: existing checkpoint, instructional Topic, formal Quiz, shield, publication, and review behavior remains PASSING.

- [ ] **Step 3: Run all JavaScript tests**

```powershell
node --test tests/JavaScript/interactive-activity-authoring.test.mjs tests/JavaScript/interactive-activity.test.mjs tests/JavaScript/matching-activity.test.mjs tests/JavaScript/sequencing-activity.test.mjs tests/JavaScript/interactive-checkpoint.test.mjs
```

Expected: all JavaScript tests PASS.

- [ ] **Step 4: Run the full suite, production build, and formatter**

```powershell
php artisan test
pnpm.cmd build
vendor/bin/pint --test
```

Expected: zero PHPUnit failures, a successful Vite build, and no Pint violations.

- [ ] **Step 5: Exercise the author-to-learner browser workflow**

As an instructor or writable admin:

1. Create and interactively preview Matching inside an ordinary Topic.
2. Create and preview Sequencing between Topics without duration/prerequisite fields.
3. Edit wording and confirm learner completion remains current.
4. Edit answer configuration and confirm a new learner revision begins unresolved.
5. Move each type between placements and verify block references and ordering.
6. Submit the Module for review and verify the frozen admin review mapping/order.

As an approved learner:

1. Confirm inside activities follow canonical Topic content and authored optional-block order.
2. Confirm between activities appear in the sidebar as `INTERACTIVE ACTIVITY · Optional` without duration or required metadata.
3. Match by tap, verify an incorrect proposal reveals no relationship, then complete all pairs.
4. Reload mid-Matching and confirm right order and correct pairs persist.
5. Reorder Sequencing with pointer, buttons, and keyboard; reload and confirm the order persists.
6. Verify an incorrect sequence reveals no positions, then complete it.
7. Skip and resume each type.
8. Revisit completed activities as read-only summaries and use Practice Again without losing completion.
9. Confirm exactly one Continue/footer action is visible throughout.
10. Confirm formal Quiz attempts, shields, scoring, and learner points remain unchanged.

- [ ] **Step 6: Repeat responsive and accessibility verification**

At approximately 375px, 768px, and 1440px widths, verify long text and maximum item counts, touch targets, visible focus, live-region announcements, reduced motion, supported color modes, safe-area footer spacing, no horizontal overflow, stable scrolling, and connector recalculation. Confirm Matching uses stacked completed-pair cards on narrow screens and lines are never the only completion indicator.

- [ ] **Step 7: Inspect the final workspace**

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors. Preserve unrelated pre-existing build assets, uploaded media, storage artifacts, and untracked documents.

- [ ] **Step 8: Commit verification corrections only if verification changed files**

Stage only the exact correction files, rerun their focused tests, then run:

```powershell
git commit -m "test: verify interactive activity workflows"
```

If verification required no corrections, finish without an empty commit.
