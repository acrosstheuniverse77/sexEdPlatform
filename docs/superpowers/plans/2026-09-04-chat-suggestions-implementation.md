# Chat Conversation Starters And Suggested Questions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add learner-side, age-appropriate, context-aware chat suggestions that prefill the existing composer without creating messages or notifications until the learner sends.

**Architecture:** Keep messaging authoritative in the existing ChatService, controllers, events, authorization, moderation, notifications, and realtime channels. Add a configuration-backed ChatSuggestionCatalog for audience-filtered content, extend the Alpine chat store with context selection and temporary pre-conversation drafts, and render the same suggestion behavior in the full inbox and global popup.

**Tech Stack:** Laravel, PHP, Eloquent, Blade, Alpine.js, Tailwind CSS, PHPUnit/Laravel feature tests, Vite.

**Spec:** `docs/superpowers/specs/2026-09-04-chat-suggestions-design.md`

## Global Constraints

- Work inline in the current checkout on `main`; do not create a worktree.
- Do not commit, merge, push, or stage unrelated files.
- Preserve all existing dirty files and generated build changes unless directly required by this feature.
- Do not add a suggestions table, FAQ dashboard, analytics events, message type, message field, or suggestion-specific moderation path.
- Suggestions are learner-side assistance for instructor conversations only.
- A suggestion click changes local composer state only; it must not send HTTP, create a record, emit an event, or notify a user.
- Non-enrolled direct learner-to-instructor requests remain request-gated and use the existing `initial_message` flow on submit.
- Existing active sends continue through `ChatService::sendMessage`, `MessageSent`, notification, moderation, unread, and realtime behavior.
- Use the existing learner age classification cache with birthdate fallback; unknown classifications receive only generic safe prompts.
- Use existing Poppins/Figtree, `brand-*` classes, focus states, and mobile touch-target conventions.
- Every production behavior change requires a failing test before implementation and a focused passing test afterward.
- Do not add commit steps to this local-only change.

---

## File Map

Create:

- `config/chat_suggestions.php` — shared suggestion catalog content and future-compatible fields.
- `app/Services/Chat/ChatSuggestionCatalog.php` — audience filtering, context normalization, and deterministic selection helpers.
- `tests/Unit/Chat/ChatSuggestionCatalogTest.php` — catalog audience, context, ordering, and duplicate behavior.
- `tests/Feature/Chat/ChatSuggestionBootstrapTest.php` — server bootstrap audience contract and request-gated start response.
- `tests/Feature/Chat/ChatSuggestionWorkflowTest.php` — request/active send behavior and side-effect boundaries.
- `tests/Feature/Chat/ChatSuggestionUiContractTest.php` — full inbox and popup rendering hooks.

Modify:

- `app/Http/Controllers/Chat/ConversationController.php` — return a non-mutating response when request-gated starts lack initial text.
- `resources/views/chat/index.blade.php` — include audience-filtered suggestions in full-page bootstrap.
- `resources/views/chat/partials/global-popup.blade.php` — include audience-filtered suggestions in popup bootstrap and render popup suggestions.
- `resources/views/chat/partials/conversation-panel.blade.php` — render empty/compact suggestions and support draft submission.
- `resources/js/chat/store.js` — normalize catalog entries, select contextual subsets, and manage temporary drafts.
- `resources/js/chat/global-popup.js` — open draft windows and promote them to real conversations after submit.
- Contextual learner chat entry views that lack target-role metadata — pass internal `target_role: 'instructor'` metadata without changing backend authorization.

Do not modify:

- `app/Models/Message.php` or message migrations.
- `app/Events/Chat/MessageSent.php` or existing notification listener semantics.
- `ChatAuthorizationService`, `ChatService::sendMessage`, reporting, moderation, or broadcast channel authorization except where regression tests expose an existing issue.

---

### Task 1: Build The Suggestion Catalog

**Files:**

- Create: `config/chat_suggestions.php`
- Create: `app/Services/Chat/ChatSuggestionCatalog.php`
- Test: `tests/Unit/Chat/ChatSuggestionCatalogTest.php`

**Interfaces:**

- `ChatSuggestionCatalog::forUser(User $user): array` returns only active entries for the resolved audience.
- `ChatSuggestionCatalog::select(array $suggestions, string $context, int $limit, array $excludedKeys = []): array` returns context-prioritized, deduplicated entries.
- Each returned entry contains `key`, `text`, `category`, `audience`, `context`, `active`, and `display_order`.

- [ ] **Step 1: Write failing catalog tests.**

Use real `User` and `LearnerProfile` models. Cover kids, teens, adults, unknown classification, inactive entries, context priority, display order, excluded keys, and duplicate removal.

~~~php
public function test_kids_receive_only_active_kids_or_shared_prompts(): void
{
    $learner = User::factory()->create([
        'role' => 'learner',
        'age_bracket_cached' => 'kids',
    ]);

    $suggestions = app(ChatSuggestionCatalog::class)->forUser($learner);

    $this->assertNotEmpty($suggestions);
    $this->assertTrue(collect($suggestions)->every(
        fn (array $entry): bool => $entry['active'] === true
            && in_array('kids', $entry['audience'], true)
    ));
}
~~~

- [ ] **Step 2: Run the unit test and confirm the expected missing-class/configuration failure.**

Run: `php artisan test tests/Unit/Chat/ChatSuggestionCatalogTest.php`

Expected: FAIL because the catalog class and configuration do not exist.

- [ ] **Step 3: Add safe configuration entries.**

Include shared and audience-specific prompts for understanding, review, lesson, module, quiz, and general instructor contexts. Use stable keys such as `lesson.explain.topic`. Do not include diagnosis requests, symptom prompts, emergency instructions, or wording that asks learners to disclose private medical information.

- [ ] **Step 4: Implement minimal filtering and selection.**

Resolve audience in this order:

1. `User::age_bracket_cached` when it is `kids`, `teens`, or `adults`.
2. `User::calculateAge()` fallback mapped to those categories.
3. Generic shared entries only when neither source resolves safely.

Map `direct` to `instructor/general`, `lesson_topic_chat` to `lesson`, and `quiz_help` to `quiz` with lesson fallback. Sort by `display_order`, then `key`, and remove duplicate keys and text.

- [ ] **Step 5: Run the unit tests and confirm they pass.**

Run: `php artisan test tests/Unit/Chat/ChatSuggestionCatalogTest.php`

- [ ] **Step 6: Refactor only after green.**

Keep the catalog independent from Blade, Alpine, HTTP requests, and message persistence.

---

### Task 2: Add Bootstrap Data And The Request-Gated Start Contract

**Files:**

- Modify: `app/Http/Controllers/Chat/ConversationController.php`
- Modify: `resources/views/chat/index.blade.php`
- Modify: `resources/views/chat/partials/global-popup.blade.php`
- Test: `tests/Feature/Chat/ChatSuggestionBootstrapTest.php`

**Interfaces:**

- Full-page bootstrap includes `suggestions` containing only the current learner’s audience-safe catalog.
- Popup bootstrap includes the same `suggestions` payload.
- `POST /chat/conversations/start` returns HTTP `428` with `requires_initial_message: true` and no database mutation when a request-gated start has no initial text.

- [ ] **Step 1: Write failing feature tests.**

Test that kids and adults receive their audience-safe bootstrap entries, unknown audiences receive only generic entries, and a non-enrolled learner receives `428` without a conversation or message request. Test that an enrolled learner retains the existing `201` behavior.

~~~php
$response = $this->actingAs($learner)->get(route('chat.page'));

$response->assertOk()
    ->assertSee('suggestions', false)
    ->assertSee('lesson.explain.topic', false);

$this->assertDatabaseCount('conversations', 0);
$this->assertDatabaseCount('message_requests', 0);
~~~

- [ ] **Step 2: Run the feature test and verify it fails for the missing bootstrap and `428` behavior.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionBootstrapTest.php`

- [ ] **Step 3: Inject the catalog into both existing bootstrap payloads.**

Resolve the catalog from the authenticated user. Send the audience-filtered entries for all supported presentation contexts so popup events can select context later without a new request. Do not include birthdate, age, or profile details.

- [ ] **Step 4: Add the non-mutating request-gated response.**

Inside the existing request-required branch in `ConversationController::start`, return `428` when trimmed `initial_message` is empty:

~~~php
return response()->json([
    'requires_initial_message' => true,
    'target_user_id' => (int) $request->validated('target_user_id'),
    'conversation_type' => $conversationType,
], 428);
~~~

Only this empty-initial-message branch may return `428`. Submitted text must continue through `createOrGetPendingRequestConversation` and the existing request event.

- [ ] **Step 5: Run focused bootstrap and request-gate tests.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionBootstrapTest.php tests/Feature/Chat/ChatRequestGateFlowTest.php tests/Feature/Chat/ChatHttpFlowTest.php`

- [ ] **Step 6: Refactor only after green.**

Keep the response additive and avoid changing authorization or request persistence rules.

---

### Task 3: Extend The Alpine Store For Suggestions And Drafts

**Files:**

- Modify: `resources/js/chat/store.js`
- Test: `tests/Feature/Chat/ChatSuggestionWorkflowTest.php`

**Interfaces:**

- `chat.suggestions` stores normalized server entries.
- `chat.suggestionsForContext(context, limit, excludedKeys = [])` returns a stable subset.
- `chat.isSuggestionEligible(targetRole, conversationType)` returns true only for learner-to-instructor learning conversations.
- `chat.prepareConversationDraft(payload)` stores a non-persisted draft.
- `chat.sendConversationDraft(draft, messageBody)` submits text through `/chat/conversations/start` as `initial_message`.
- `chat.clearConversationDraft()` removes local draft state.

- [ ] **Step 1: Write failing store contract tests.**

Assert that the rendered chat application includes suggestion state and methods, handles the `428` request response without creating a message, and retains the existing message-send endpoint. Use source/bootstrap assertions because this repository has no Alpine unit-test harness.

- [ ] **Step 2: Run the workflow test and confirm it fails for the missing store contract.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionWorkflowTest.php`

- [ ] **Step 3: Normalize bootstrap entries and add contextual selection.**

Normalize keys, text, contexts, audiences, active state, and order. Map conversation objects and popup details to `general`, `module`, `lesson`, `quiz`, or `instructor`. Exclude inactive, duplicate, and explicitly consumed keys.

- [ ] **Step 4: Add temporary draft state.**

Store target and context payload locally when `/start` returns `428`. Do not add the draft to `conversations`, `messagesByConversation`, unread state, subscriptions, or popup localStorage conversation persistence.

- [ ] **Step 5: Add draft submission.**

Call the existing start endpoint with the original target/context fields and `initial_message`. On `202`, refresh conversations, load the resulting conversation, and clear the draft. On validation or authorization errors, keep draft text and expose the existing composer error state.

- [ ] **Step 6: Run workflow and existing realtime contract tests.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionWorkflowTest.php tests/Feature/Chat/ChatRealtimeUiContractTest.php tests/Feature/Chat/ChatReconnectBackfillTest.php`

- [ ] **Step 7: Refactor only after green.**

Keep suggestion selection local and separate from message mutation, realtime, notification, and moderation methods.

---

### Task 4: Implement Full Inbox Suggestion UI

**Files:**

- Modify: `resources/views/chat/partials/conversation-panel.blade.php`
- Test: `tests/Feature/Chat/ChatSuggestionUiContractTest.php`

**Interfaces:**

- Empty state marker: `data-chat-suggestion-empty-state`.
- Compact row marker: `data-chat-suggestion-compact`.
- Suggestion controls expose `data-chat-suggestion-key` and accessible labels.
- Existing composer, attachment, reporting, edit/delete, and send controls remain available.

- [ ] **Step 1: Write failing UI contract tests.**

Assert the response contains the starter heading, compact-state marker, suggestion key binding, replacement-confirmation hook, and normal composer. Assert that no FAQ message model or message-type reference is introduced.

- [ ] **Step 2: Run the UI test and verify it fails for missing markers/content.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionUiContractTest.php`

- [ ] **Step 3: Add local suggestion application behavior.**

Add an Alpine method that preserves a non-empty draft unless the learner confirms replacement:

~~~js
applySuggestion(suggestion) {
    if (!suggestion?.text) return;
    if (this.composer.trim() && !window.confirm('Replace your current draft with this suggestion?')) return;
    this.composer = suggestion.text;
    this.$nextTick(() => this.$refs.composerInput?.focus());
}
~~~

- [ ] **Step 4: Replace the empty message text with the guided starter state.**

Render the large starter panel only for an eligible learner-to-instructor context with no messages. Keep custom typing available without selecting a suggestion.

- [ ] **Step 5: Add the compact row above the composer.**

Render two or three deduplicated chips after messages exist. Do not render the large starter panel simultaneously.

- [ ] **Step 6: Render and submit a temporary request-gated draft.**

Use draft target/context data to show the instructor header and starter state. Route submit to `sendConversationDraft`. Attachments remain unavailable before a database conversation exists because the current request contract accepts text only.

- [ ] **Step 7: Run UI and existing chat page tests.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionUiContractTest.php tests/Feature/Chat/ChatPageRenderTest.php tests/Feature/Chat/ChatWorkflowEntryLinksTest.php`

- [ ] **Step 8: Refactor only after green.**

Preserve message stream scrolling, disabled states, error handling, and accessibility behavior.

---

### Task 5: Implement Global Popup Suggestions And Draft Windows

**Files:**

- Modify: `resources/js/chat/global-popup.js`
- Modify: `resources/views/chat/partials/global-popup.blade.php`
- Modify: learner contextual views that dispatch `open-global-chat`
- Test: `tests/Feature/Chat/ChatSuggestionUiContractTest.php`

**Interfaces:**

- Popup windows may contain either `conversationId` or a stable local `draftKey`.
- Real conversations continue to use numeric IDs for message state and persistence.
- Draft windows are never written to popup localStorage as conversations.
- `sendMessage(windowItem)` routes drafts to `chat.sendConversationDraft` and real conversations to the existing message endpoint.

- [ ] **Step 1: Extend UI contract tests for popup suggestions and draft metadata.**

Assert popup bootstrap includes the catalog, popup suggestion controls exist, and popup persistence serializes only real conversation IDs.

- [ ] **Step 2: Run the test and verify it fails for missing popup behavior.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionUiContractTest.php`

- [ ] **Step 3: Update popup bootstrap and event metadata.**

Include the server-filtered catalog in popup bootstrap. Add `target_role: 'instructor'` to learner-originated contextual triggers that currently lack target-role metadata. Treat this as presentation metadata only; server authorization remains authoritative.

- [ ] **Step 4: Add draft window creation for `428`.**

When opening a target returns `requires_initial_message`, create a local popup window with a stable `draftKey`, target/context fallback data, empty composer, and no conversation subscription. Preserve the existing three-window limit.

- [ ] **Step 5: Add popup starter and compact suggestion rendering.**

Render a compact starter state for a draft or empty conversation and compact chips for conversations with messages. Use horizontal scrolling and a `More suggestions` disclosure when the selected list exceeds the compact limit. Apply the same replacement confirmation and focus behavior as the full inbox.

- [ ] **Step 6: Promote the draft window after successful send.**

Replace the local draft key with the returned conversation ID, load messages, mark read, subscribe to the existing channel, and persist only the real conversation.

- [ ] **Step 7: Run popup, notification, and realtime tests.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionUiContractTest.php tests/Feature/Chat/ChatRealtimeUiContractTest.php tests/Feature/Chat/ChatNotificationBadgeTest.php`

- [ ] **Step 8: Refactor only after green.**

Keep popup draft logic isolated from real conversation persistence and existing unread/realtime behavior.

---

### Task 6: Verify Side Effects And Full Regression Coverage

**Files:**

- Modify: `tests/Feature/Chat/ChatInAppMessageNotificationTest.php` only for additive assertions.
- Modify: `tests/Feature/Chat/ChatRequestGateFlowTest.php` only for additive assertions.
- Modify: `tests/Feature/Chat/ChatSuggestionWorkflowTest.php`.

**Interfaces:**

- Suggestion selection has no database or notification side effect.
- Active sends retain the existing `MessageSent` notification path.
- Request-gated sends retain the existing request-created path.
- Existing moderation/reporting and centralized suspension checks remain authoritative.

- [ ] **Step 1: Write failing side-effect assertions.**

Cover the no-record boundary and actual sends:

~~~php
// Selection is browser-local; no HTTP selection endpoint exists.
$this->assertDatabaseCount('messages', 0);
$this->assertDatabaseCount('message_requests', 0);

// Submitted active text still creates one ordinary message.
$this->actingAs($learner)
    ->postJson(route('chat.messages.store', ['conversation' => $conversation]), [
        'message_body' => 'Can you explain this topic?',
    ])
    ->assertCreated();
~~~

Assert recipient notification behavior with the existing notification pattern. Assert request-gated submission creates exactly one request and initial message without a duplicate ordinary send.

- [ ] **Step 2: Run focused workflow and notification tests and verify new assertions fail before implementation.**

Run: `php artisan test tests/Feature/Chat/ChatSuggestionWorkflowTest.php tests/Feature/Chat/ChatInAppMessageNotificationTest.php tests/Feature/Chat/ChatRequestGateFlowTest.php`

- [ ] **Step 3: Add only the minimum integration fixes required by failing tests.**

Do not add suggestion events or notification types. If the request-created path lacks an expected recipient notification, document the observed behavior and preserve the established request event contract rather than silently reclassifying the initial message as an active send.

- [ ] **Step 4: Run the complete chat regression suite.**

Run:

~~~powershell
php artisan test tests/Feature/Chat tests/Unit/Chat
~~~

- [ ] **Step 5: Run the frontend build.**

Run: `npm.cmd run build`

- [ ] **Step 6: Perform manual desktop/mobile verification.**

Verify:

1. Enrolled learner opens an empty instructor conversation, selects, edits, and sends a suggestion.
2. Non-enrolled learner opens an instructor, sees a draft, selects/edits a suggestion, and submits a request.
3. Module, lesson, lesson-topic, and quiz entries receive relevant prompts.
4. Existing conversations show compact deduplicated prompts.
5. Instructors see no learner suggestion UI.
6. Custom text sends without selecting a suggestion.
7. Realtime, unread, notification, reporting, suspension, and reconnect behavior remain intact.

- [ ] **Step 7: Verify local-only worktree state.**

Run:

~~~powershell
git status --short
git diff --stat
~~~

Confirm no commit was created, no unrelated file was staged, and all pre-existing dirty changes remain identifiable.

