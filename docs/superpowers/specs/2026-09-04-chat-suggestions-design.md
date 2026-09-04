# Chat Conversation Starters And Suggested Questions Design

## Status

Approved by the user on September 4, 2026. This design adds learner-side
conversation guidance while preserving the existing chat authorization,
message, moderation, notification, and realtime contracts.

## Goal

Help learners begin educational conversations with instructors by showing
age-appropriate, context-aware prompts that populate the composer without
sending or storing a message until the learner submits it.

## Locked Product Decisions

- Suggestions appear in the full chat inbox and the global popup chat.
- Suggestions are primarily for learners messaging instructors.
- Instructors do not receive onboarding or compact suggestion UI.
- Existing direct, module, lesson, lesson-topic, and quiz contexts are used.
- Direct instructor conversations use instructor/general fallback content.
- Suggestions are stored in a configuration-backed catalog, not a new table.
- The catalog has stable keys and future-compatible fields: key, text,
  category, audience, context, active, and display_order.
- The server filters catalog entries by the current learner audience and sends
  the audience-safe catalog through existing chat bootstrap data.
- Context selection prioritizes exact context, then broader context, then
  general content, and removes duplicate keys and text.
- Learner audience resolution reuses the existing age classification cache with
  birthdate fallback. Unknown classification receives only conservative generic
  educational prompts.
- Empty conversations show a full starter state with approximately three or
  four prompts.
- Conversations containing messages show approximately two or three compact
  prompts above the composer.
- A suggestion click only prefills the composer.
- If the composer already contains text, replacement requires confirmation.
- Non-enrolled learner-to-instructor requests remain request-gated. The
  frontend holds a temporary draft and submits the existing initial_message
  flow only when the learner sends.
- No suggestion selection creates a conversation, message, notification,
  realtime event, or analytics event.
- No analytics, FAQ dashboard, new message type, message-model field, or
  suggestion-specific moderation path is added in this phase.
- Work is performed inline in the current checkout on main. Existing dirty
  changes are preserved and unrelated files are not staged.

## Existing Chat Contracts To Preserve

The implementation builds on these existing components:

- `resources/views/chat/index.blade.php` bootstraps the shared chat store.
- `resources/views/chat/partials/conversation-panel.blade.php` renders the
  inbox conversation stream and composer.
- `resources/views/chat/partials/global-popup.blade.php` renders popup chat.
- `resources/js/chat/store.js` owns conversations, messages, sends, reads,
  requests, realtime subscriptions, and reconnect backfill.
- `resources/js/chat/global-popup.js` opens and persists popup windows.
- `POST /chat/conversations/start` creates active conversations or the
  request-gated initial message flow.
- `POST /chat/conversations/{conversation}/messages` sends ordinary messages.
- `MessageSent` and the existing notification listener handle active message
  delivery and recipient notification.
- `MessageRequestCreated` remains the request-gated event contract.
- `ChatAuthorizationService`, `ChatService`, and `ChatContextResolver` remain
  authoritative for authorization, persistence, and context lineage.

## Architecture

### Suggestion Catalog

Create a focused catalog service backed by configuration:

```text
config/chat_suggestions.php
app/Services/Chat/ChatSuggestionCatalog.php
```

The configuration entries use this shape:

```php
[
    'key' => 'lesson.explain.topic',
    'text' => 'Can you explain this topic in simpler terms?',
    'category' => 'understanding',
    'audience' => ['kids', 'teens', 'adults'],
    'context' => ['lesson', 'general'],
    'active' => true,
    'display_order' => 10,
]
```

The service exposes audience-filtered entries and a stable selection contract.
The service does not persist selections and does not inspect message history.
Its field names intentionally match a future managed content table.

### Bootstrap Data

The current learner-safe suggestion catalog is added to the existing chat
bootstrap payload. The global popup receives the same audience-filtered set
through its layout bootstrap. The browser selects the best context subset when
an existing conversation or contextual `open-global-chat` event is active.

No new API endpoint is required for this phase. The server remains responsible
for audience filtering; the browser performs presentation-level context
selection using the already validated conversation type/context metadata.

### Temporary Conversation Drafts

The Alpine chat store gains a temporary draft state for a target that has not
yet produced a database conversation. A draft contains:

```text
target_user_id
target_role
conversation_type
module_id
lesson_id
lesson_topic_id
quiz_id
context_label
composer text
```

The target role is used only to decide whether learner-side suggestions should
be rendered. Backend authorization remains authoritative and does not trust
this client value.

For an authorized active/context start, the existing conversation-start call
continues to create or retrieve the conversation. For a non-enrolled learner
starting a direct instructor conversation, the store keeps a draft until the
learner submits text. The submit action passes that text as `initial_message`
to the existing start endpoint. Closing the draft creates no database row.

## Suggestion Content And Safety

Content is educational, non-diagnostic, and designed to avoid prompting
unnecessary personal disclosures. Initial entries should include wording such
as:

- `Can you explain this topic?`
- `Can you explain this in simpler terms?`
- `What is an important idea to remember from this lesson?`
- `What should I review next?`
- `Can you help me understand this section?`
- `How does this topic connect to the module?`

Audience-specific wording may vary in vocabulary and reading level, but all
audiences use the same catalog and selection service. Suggestions must not ask
the learner to describe symptoms, request a diagnosis, or provide private
medical details.

## User Experience

### Full Inbox Empty State

When the viewer is a learner and the target is an instructor, an empty active
conversation shows:

```text
Start a conversation
Have a question about your lessons or learning progress?
Choose a suggestion below or type your own message.
```

The normal composer remains available. The state disappears naturally when a
message is present.

### Existing Conversation

When messages already exist, the large onboarding panel is hidden. A compact
`Suggested questions` row appears above the composer. It is deduplicated from
the empty-state selection and remains optional.

### Global Popup

The popup uses the same catalog and store selection behavior. Chips remain
compact and horizontally scrollable on narrow screens. A `More suggestions`
control is used only when the selected list exceeds the compact display limit.
Suggestion controls retain visible focus states and touch targets suitable for
mobile use.

### Instructor And Non-Instructor Conversations

Instructor viewers do not see suggestion UI. Parent, support, admin, and
learner-to-learner conversations do not receive instructor-learning prompts.
Their current chat behavior remains unchanged.

## Data Flow

```text
Server bootstrap
    -> audience-filtered suggestion catalog
    -> Alpine chat store
    -> context-prioritized subset
    -> learner selects a suggestion
    -> composer is populated locally
    -> learner edits or replaces text
    -> existing message or initial request send
    -> existing moderation, realtime, unread, and notification flow
```

Suggestion selection itself has no server side effect.

## Notifications And Realtime

Active conversation sends continue through `MessageController::store`,
`ChatService::sendMessage`, `MessageSent`, and the existing notification
listener. Request-gated initial text continues through the existing
`MessageRequestCreated` path. The implementation must not emit any event when
the learner merely selects a prompt.

The request-gated path receives explicit regression coverage because its
initial message is persisted during request creation rather than through the
ordinary message endpoint.

## Non-Goals

- No AI-generated prompts or answers
- No medical advice or support workflow
- No FAQ messages inserted into conversations
- No database-backed FAQ management
- No analytics instrumentation
- No changes to authorization rules
- No changes to message schema or message moderation semantics
- No changes to realtime channel structure
- No replacement of the existing composer or attachment system

## Acceptance Criteria

1. A learner opening an empty instructor conversation sees appropriate starter
   prompts and can still type a custom message.
2. A contextual entry from a module, lesson, lesson topic, or quiz produces
   context-relevant prompts.
3. Kids, teens, adults, and unknown-audience learners receive the correct
   audience-safe content.
4. Selecting a prompt only changes the composer value.
5. The learner can edit, clear, replace, or ignore the populated text.
6. Non-enrolled direct starts remain request-gated until submission.
7. Existing conversations show compact, deduplicated prompts without a large
   onboarding panel.
8. Instructors do not see learner onboarding prompts.
9. No prompt selection creates a message or notification.
10. Actual sends use the existing authorization, moderation, realtime, unread,
    and notification behavior.
11. Full inbox, popup, desktop, mobile, reporting, and reconnect behavior
    remain regression-safe.
