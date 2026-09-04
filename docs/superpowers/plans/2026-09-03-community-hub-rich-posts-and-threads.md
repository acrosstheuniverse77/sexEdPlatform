# Community Hub Rich Posts And Threads Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add six-image galleries or one private video to Community posts, add consistent author avatars and connector-role labels, and add safe one-level comment replies with upvotes and reports.

**Architecture:** Keep Community Hub connector-scoped and adult-only. Normalize active and audit-retained post media into `community_post_media`, backfill legacy post media, and serve each item through the existing authorization boundary. Model replies with a nullable self-reference on `community_comments`; rank only roots and load children chronologically. Render media, authors, and thread items through reusable Blade components with server-authoritative validation.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, Blade, Alpine.js, Tailwind CSS, PHPUnit/Laravel feature tests, private local storage, Vite.

**Spec:** `docs/superpowers/specs/2026-09-03-community-hub-rich-posts-and-threads-design.md`

## Global Constraints

- Work inline in the current checkout because the user explicitly declined a separate worktree.
- Preserve unrelated dirty changes and do not commit, merge, switch branches, or stage all files.
- Keep legacy `community_posts.media_*` and `community_post_versions.media_*` columns intact.
- Store Community media on the private `local` disk and expose it only through authorized controller responses.
- Preserve adult-only access, connector scoping, global/space freeze behavior, content pre-screening, reports, and moderation audit retention.
- Permit at most six images at 5 MB each or one video at 25 MB; never mix media types on one post.
- Permit exactly one reply level; do not add DMs, private replies, or recursive threads.
- Reuse literal Tailwind classes, visible focus states, 44 px minimum action targets, Poppins/Figtree typography, and the current `brand-*` palette.
- Use tests first for every behavior change and observe each focused test fail for the intended missing behavior before implementation.

---

### Task 1: Add Normalized Media And Reply Schema

**Files:**
- Create: `app/Models/CommunityPostMedia.php`
- Modify: `app/Models/CommunityPost.php`
- Modify: `app/Models/CommunityComment.php`
- Create: `database/migrations/2026_09_03_000001_add_rich_media_and_replies_to_community_hub.php`
- Create: `tests/Feature/Community/CommunityRichPostSchemaTest.php`

**Interfaces:**
- `CommunityPost::media(): HasMany`
- `CommunityPost::activeMedia(): HasMany`
- `CommunityPostMedia::post(): BelongsTo`
- `CommunityPostMedia::uploader(): BelongsTo`
- `CommunityPostMedia::removedBy(): BelongsTo`
- `CommunityPostMedia::isRemoved(): bool`
- `CommunityComment::parent(): BelongsTo`
- `CommunityComment::replies(): HasMany`
- `CommunityComment::isReply(): bool`
- `CommunityPost::topLevelComments(): HasMany`

- [ ] **Step 1: Write failing schema and relationship tests.**

Assert both new tables/columns and model traversal:

```php
$this->assertTrue(Schema::hasTable('community_post_media'));
$this->assertTrue(Schema::hasColumns('community_post_media', [
    'community_post_id', 'uploaded_by', 'media_type', 'path', 'mime_type',
    'original_name', 'size_bytes', 'display_order', 'removed_at', 'removed_by',
]));
$this->assertTrue(Schema::hasColumn('community_comments', 'parent_id'));
$this->assertTrue($reply->isReply());
$this->assertTrue($parent->replies->contains($reply));
```

- [ ] **Step 2: Run the schema test and confirm it fails because the new schema/model is absent.**

Run: `php artisan test tests/Feature/Community/CommunityRichPostSchemaTest.php`

- [ ] **Step 3: Create the additive migration.**

Create `community_post_media` with foreign keys, audit columns, display ordering,
and indexes for active ordered retrieval. Add nullable indexed `parent_id` to
`community_comments` with `cascadeOnDelete()`. After creating the media table,
backfill every post whose legacy `media_path` is non-null:

```php
DB::table('community_posts')
    ->whereNotNull('media_path')
    ->orderBy('id')
    ->each(function (object $post): void {
        DB::table('community_post_media')->insert([
            'community_post_id' => $post->id,
            'uploaded_by' => $post->author_id,
            'media_type' => $post->media_type ?: 'image',
            'path' => $post->media_path,
            'mime_type' => $post->media_mime_type,
            'original_name' => $post->media_original_name,
            'size_bytes' => null,
            'display_order' => 0,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ]);
    });
```

The down migration drops `parent_id` first, then `community_post_media`; it does
not touch legacy columns.

- [ ] **Step 4: Add fillable fields, casts, and ordered relationships.**

`activeMedia()` filters `removed_at IS NULL` and orders by `display_order`, then
`id`. `topLevelComments()` filters `parent_id IS NULL`. `replies()` orders by
`created_at`, then `id`.

- [ ] **Step 5: Re-run the schema test and confirm it passes.**

---

### Task 2: Validate And Persist Galleries Safely

**Files:**
- Modify: `app/Http/Requests/Community/StoreCommunityPostRequest.php`
- Modify: `app/Services/Community/CommunityPostService.php`
- Modify: `tests/Feature/Community/CommunityRedditHubTest.php`

**Interfaces:**
- `StoreCommunityPostRequest::MAX_IMAGES = 6`
- request fields: `images[]`, `video`, `remove_media_ids[]`
- `CommunityPostService::syncMedia(User $actor, CommunityPost $post, array $payload): void`

- [ ] **Step 1: Replace the single-upload tests with failing rich-media tests.**

Cover three images stored in order, six-image maximum, invalid seventh image,
per-image 5,120 KB limit, one 25,600 KB video, oversized video rejection,
mixed image/video rejection, and edit removal/replacement. Assert removed rows
receive `removed_at`/`removed_by` and their private files remain present.

- [ ] **Step 2: Run only the rich-media tests and confirm expected failures.**

Run: `php artisan test tests/Feature/Community/CommunityRedditHubTest.php --filter=media`

- [ ] **Step 3: Implement request validation.**

Use server-detected MIME validation and explicit messages:

```php
'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES, 'prohibited_with:video'],
'images.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::IMAGE_MAX_KILOBYTES],
'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.self::VIDEO_MAX_KILOBYTES, 'prohibited_with:images'],
'remove_media_ids' => ['nullable', 'array'],
'remove_media_ids.*' => ['integer', 'distinct'],
```

Add friendly messages for count, type, mixed mode, and size. Treat absent arrays
as empty; do not trust media IDs merely because they exist globally.

- [ ] **Step 4: Implement transactional media synchronization.**

Inside the existing create/update database transaction:

1. Lock the post's media rows.
2. Reject any requested removal ID not owned by that post.
3. Mark owned active rows removed by the actor without deleting the file.
4. Calculate the retained active set plus incoming files.
5. Reject mixed final types, more than six images, or more than one video with
   `ValidationException` keyed to `images`, `video`, or `remove_media_ids`.
6. Store incoming files below `community-post-media/{connector_id}/{post_id}`.
7. Create ordered media rows with sanitized names and byte sizes.

Track newly stored paths and delete only those new files if the transaction
throws. Never delete an existing/audit file. Eager-load `activeMedia` on the
returned post.

- [ ] **Step 5: Re-run the focused media tests and confirm they pass.**

---

### Task 3: Authorize Per-Item Private Media Delivery

**Files:**
- Modify: `routes/connector.php`
- Modify: `app/Http/Controllers/Connector/CommunityFeedController.php`
- Modify: `tests/Feature/Community/CommunityRedditHubTest.php`

**Interface:**
- Route name: `connector.community.media.show`
- URL: `/connector/{connector}/community/{communityPost}/media/{communityPostMedia}`
- Controller: `CommunityFeedController::media(Request, Connector, CommunityPost, CommunityPostMedia)`

- [ ] **Step 1: Write failing delivery tests.**

Prove an eligible adult can fetch active media. Prove a minor gets 403, a media
row from another post/connector gets 404, hidden post media gets 404 for a
member, removed media gets 404 for a member, missing files get 404, and an
authorized moderator may fetch a removed audit item.

- [ ] **Step 2: Run the delivery test and confirm the missing route signature or checks fail.**

- [ ] **Step 3: Replace the legacy post-only media route with the per-item route.**

The action must run these checks in order:

```php
$this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
$this->access->abortUnlessCanViewSpace($request->user(), $connector);
abort_unless($communityPostMedia->community_post_id === $communityPost->id, 404);
$canModerate = $this->access->canModerateSpace($request->user(), $connector);
abort_if(! $canModerate && (! $communityPost->isVisibleToMembers() || $communityPostMedia->isRemoved()), 404);
abort_unless(Storage::disk('local')->exists($communityPostMedia->path), 404);
```

Return `Storage::disk('local')->response()` with the stored MIME and sanitized
original filename.

- [ ] **Step 4: Re-run delivery tests and confirm they pass.**

---

### Task 4: Build The Add/Preview/Remove Composer And Gallery

**Files:**
- Modify: `resources/views/connectors/community/partials/form.blade.php`
- Modify: `resources/views/connectors/community/create.blade.php`
- Modify: `resources/views/connectors/community/edit.blade.php`
- Create: `resources/views/components/community/media-gallery.blade.php`
- Modify: `resources/views/components/community/post-card.blade.php`
- Modify: `tests/Feature/Community/CommunityHubUiSmokeTest.php`
- Modify: `tests/Feature/Community/CommunityRedditHubTest.php`

- [ ] **Step 1: Write failing UI contract tests.**

Assert multipart forms, `Add images`, `Add video`, six-image/5 MB/25 MB copy,
multiple file input, preview/remove hooks, existing edit-media removal inputs,
and gallery URLs containing media IDs. Assert the old singular `name="media"`
input and post-only media URL are absent.

- [ ] **Step 2: Run the focused UI tests and confirm expected failures.**

- [ ] **Step 3: Implement an Alpine media picker.**

Use hidden image/video inputs, explicit 44 px buttons, `URL.createObjectURL`,
and a `DataTransfer` rebuild when an image selection is removed. Revoke blob
URLs when replaced or removed. Model existing items separately and emit one
hidden `remove_media_ids[]` input per removed existing item. Choosing images
clears a selected video; choosing video clears selected images. On edit, the UI
marks incompatible existing media removed before previewing the new mode.

- [ ] **Step 4: Implement the reusable gallery.**

Render one contained image, a two-column split, or a responsive two/three-column
mosaic using finite literal classes. Render one native video with controls and
no autoplay. Source every item from `connector.community.media.show`.

- [ ] **Step 5: Replace singular media rendering on post cards and load active media on create/edit/index/show queries.**

- [ ] **Step 6: Re-run UI and media tests and confirm they pass.**

---

### Task 5: Add Reusable Author Profiles And Connector Roles

**Files:**
- Create: `resources/views/components/community/author.blade.php`
- Modify: `app/Http/Controllers/Connector/CommunityFeedController.php`
- Modify: `resources/views/components/community/post-card.blade.php`
- Modify: `resources/views/connectors/community/show.blade.php`
- Create: `tests/Feature/Community/CommunityAuthorPresentationTest.php`

- [ ] **Step 1: Write failing presentation and query tests.**

Create authors with learner, instructor, and general-profile avatars; create an
owner membership, a custom-role membership, and a missing-membership fallback.
Assert the rendered avatar URL or initials plus `Connector Owner`, the custom
role name, or `Member`. Assert author names are not links to public profiles.

- [ ] **Step 2: Run the author presentation tests and confirm the missing component/data fails.**

- [ ] **Step 3: Eager-load author presentation relations.**

For post, comment, and reply authors load:

```php
'author.learnerProfile:id,user_id,avatar_path',
'author.instructorProfile:id,user_id,profile_photo_path',
'author.profile:id,user_id,avatar',
'author.connectorMemberships' => fn ($query) => $query
    ->where('connector_id', $connector->id)
    ->where('status', 'active')
    ->with('role:id,name,is_owner'),
```

- [ ] **Step 4: Implement the anonymous author component.**

Resolve avatar in the approved order, normalize local storage paths with
`asset('storage/...')`, use two-character initials as fallback, and derive the
role from the already-loaded active connector membership. Render a decorative
empty-alt avatar, visible name and compact role badge, supplied timestamp, and
optional moderator status. Do not wrap identity in an anchor.

- [ ] **Step 5: Use the component in post cards and every thread item, then re-run tests.**

---

### Task 6: Add One-Level Reply Domain Rules

**Files:**
- Modify: `app/Http/Requests/Community/StoreCommunityCommentRequest.php`
- Modify: `app/Http/Controllers/Connector/CommunityCommentController.php`
- Modify: `app/Services/Community/CommunityInteractionService.php`
- Modify: `app/Services/Community/CommunityAccessService.php`
- Create: `tests/Feature/Community/CommunityReplyTest.php`

**Interface:**
- optional request field `parent_id`
- `CommunityInteractionService::comment(User $author, CommunityPost $post, string $body, ?CommunityComment $parent = null): CommunityComment`

- [ ] **Step 1: Write failing reply safety tests.**

Cover valid reply creation, parent persistence, the same pre-screening result as
a root comment, cross-post parent 404/validation failure, reply-to-reply
rejection, hidden/removed/escalated parent rejection, locked post, frozen space,
unverified connector, and minor denial.

- [ ] **Step 2: Run reply safety tests and confirm the missing `parent_id` behavior fails.**

- [ ] **Step 3: Validate and resolve the parent inside the scoped post.**

Validate `parent_id` as nullable integer. In the controller, when present, load
it through `$communityPost->comments()->findOrFail($id)` so another post's ID is
never accepted. Pass the resolved model to the service.

- [ ] **Step 4: Enforce one-level and visible-parent rules in the service.**

After the existing connector/space/post access checks and before screening:

```php
if ($parent) {
    abort_unless($parent->community_post_id === $post->id, 404);
    abort_if($parent->parent_id !== null, 422);
    abort_unless(($parent->status?->value ?? $parent->status) === CommunityCommentStatus::Visible->value, 403);
}
```

Persist `parent_id` with the new comment. Keep every existing screening and
escalation field unchanged.

- [ ] **Step 5: Prevent interaction with orphan-visible replies.**

Update `canUpvoteComment()` so a visible reply also requires its parent to be
visible. Load `parent` in that method; do not change root-comment behavior.

- [ ] **Step 6: Re-run reply and existing interaction safety tests.**

---

### Task 7: Render Ranked Roots And Chronological Replies

**Files:**
- Modify: `app/Http/Controllers/Connector/CommunityFeedController.php`
- Create: `resources/views/components/community/comment.blade.php`
- Modify: `resources/views/connectors/community/show.blade.php`
- Modify: `tests/Feature/Community/CommunityReplyTest.php`
- Modify: `tests/Feature/Community/CommunityRedditHubTest.php`

- [ ] **Step 1: Write failing thread rendering and ordering tests.**

Assert higher-voted root first, roots never duplicate as replies, replies oldest
first beneath their parent regardless of their vote count, reply controls appear
only on roots, reply upvotes toggle, replies can be reported, and member views
hide children of non-visible parents while moderators retain the audit thread.

- [ ] **Step 2: Run thread tests and confirm the flat query/view fails.**

- [ ] **Step 3: Replace `comments` page loading with `topLevelComments`.**

For members, filter roots and replies to `visible`; for moderators retain all
statuses. Rank root query by `upvotes_count DESC`, `created_at DESC`, `id DESC`.
Load reply authors/upvotes/reports and order replies `created_at ASC`, `id ASC`.
Keep `visible_comments_count` counting visible roots and visible replies whose
parent is visible.

- [ ] **Step 4: Implement a reusable comment component.**

Render author profile, body, upvote, report, moderator status, and optional root
`Reply` disclosure. Nest reply items under a subtle brand-gray guide. Give each
reply form a unique textarea ID and submit the root `parent_id`. Do not render a
reply action for a reply.

- [ ] **Step 5: Re-run reply, Reddit Hub, report, and moderation UI tests.**

---

### Task 8: Final Verification And Handoff

**Files:**
- Review all task-owned files only.

- [ ] **Step 1: Run formatter/static syntax checks on task-owned PHP files if configured.**

Run: `vendor/bin/pint --test app/Models/CommunityPostMedia.php app/Models/CommunityPost.php app/Models/CommunityComment.php app/Http/Requests/Community/StoreCommunityPostRequest.php app/Http/Requests/Community/StoreCommunityCommentRequest.php app/Http/Controllers/Connector/CommunityFeedController.php app/Http/Controllers/Connector/CommunityCommentController.php app/Services/Community/CommunityPostService.php app/Services/Community/CommunityInteractionService.php app/Services/Community/CommunityAccessService.php database/migrations/2026_09_03_000001_add_rich_media_and_replies_to_community_hub.php`

- [ ] **Step 2: Run the focused Community suite.**

Run: `php artisan test tests/Feature/Community`

- [ ] **Step 3: Run the broader automated test suite.**

Run: `php artisan test`

- [ ] **Step 4: Build frontend assets.**

Run: `npm.cmd run build`

- [ ] **Step 5: Apply the additive migration to the configured local database and inspect status.**

Run: `php artisan migrate --force` and `php artisan migrate:status`.

- [ ] **Step 6: Review the scoped diff and ensure unrelated dirty files were not altered.**

Run explicit `git diff -- <task-owned paths>` and `git status --short`. Do not
stage or commit.

- [ ] **Step 7: Report files changed, user-visible behavior, exact test/build/migration evidence, current branch, and no-commit status.**

