# Community Hub Rich Posts And Threads Design

## Status

Approved on September 3, 2026. This document extends the current Community Hub
design and preserves its connector-scoped, adult-only moderation boundary.

## Goal

Make post creation and discussion feel complete without turning Community Hub
into a general social network. Authors can publish an image gallery or one
video, members can recognize authors and their connector roles, and comments
can have one visible level of replies with the existing upvote and report
controls.

## Approved Product Decisions

- A post accepts up to six images, each no larger than 5 MB.
- A post may instead accept one video no larger than 25 MB.
- Images and video cannot be mixed on the same post.
- Authors can preview newly selected media and remove individual selections
  before submitting.
- Edit screens show active existing media, allow individual removal, and allow
  replacement media within the same limits.
- Posts, top-level comments, and replies show the author's avatar, display name,
  and connector-local role.
- Author identity is display-only. This phase does not add public profile pages
  or links from author names and avatars.
- Connector owners display as `Connector Owner`; otherwise the active local
  role name is displayed, with `Member` as the fallback.
- Comments allow one reply level. Replies cannot themselves receive replies.
- Top-level comments rank by upvotes, then recency. Replies remain chronological
  within their parent thread.
- Replies reuse comment upvotes and reports.

## Data Design

### Post Media

Create `community_post_media` as the normalized source for new post media. Each
row stores:

- `community_post_id`
- nullable `uploaded_by`
- `media_type` (`image` or `video`)
- private-disk `path`
- detected `mime_type`
- sanitized `original_name`
- nullable `size_bytes`
- `display_order`
- nullable `removed_at`
- nullable `removed_by`
- timestamps

`community_posts.media_*` and `community_post_versions.media_*` remain in place
for compatibility and audit history. The additive migration backfills an active
normalized row for every existing post with a legacy `media_path`. Backfilled
size may be null when it cannot be derived safely.

Removing media is an audit-preserving state change. The row and private file are
retained, `removed_at` and `removed_by` are recorded, and normal member views no
longer expose the item.

### Replies

Add nullable `parent_id` to `community_comments` as a self-referencing foreign
key. A null parent identifies a top-level comment. A non-null parent identifies
a reply. Application rules enforce one level only; a reply cannot be selected
as another reply's parent.

## Server-Side Media Contract

- Request validation accepts `images[]` or `video`, never both.
- `images` has at most six entries and every item must be a JPEG, PNG, or WebP
  file no larger than 5,120 KB.
- `video` must be MP4, WebM, or MOV and no larger than 25,600 KB.
- The service validates the final active set during edit, after requested
  removals and before additions. The final set is either one to six images, one
  video, or empty.
- Files are stored on the private `local` disk below a connector-specific path.
- A media item is served only by a connector route that verifies connector/post
  ownership, Community Hub access, post visibility, media/post ownership, media
  availability, and private file existence.
- Members receive a not-found response for removed media. Authorized moderators
  retain audit access to removed media.

## Creation And Editing Experience

The media field is a compact composer section with two explicit actions:

- `Add images` opens a multiple-image chooser.
- `Add video` opens a single-video chooser.

The client shows local previews, names and sizes, an image counter, and a remove
control for every selection. Choosing one media mode clears incompatible new
selections. On edit, choosing the other mode also marks incompatible existing
media for removal. The browser UI is a convenience only; every rule remains
enforced on the server.

If validation redirects back, browsers cannot safely restore local file
selections. The form retains text input and displays a clear prompt to reselect
media.

## Feed Media Presentation

Use a reusable gallery component:

- One image uses a contained full-width frame.
- Two images use an equal split.
- Three to six images use a compact responsive mosaic.
- A video uses one contained native player with controls, metadata preload, and
  no autoplay.
- Every image has contextual alternative text and every control is keyboard
  accessible.

## Author Presentation

Use a reusable Community author component. It resolves the avatar in this
order, matching existing connector views:

1. learner profile `avatar_path`
2. instructor profile `profile_photo_path`
3. general profile `avatar`
4. initials fallback

The component receives already eager-loaded relations so post and thread lists
do not introduce per-row profile or membership queries. It displays only the
name, connector role, avatar, and supplied timestamp/status metadata.

## Thread Behavior

- The existing comment endpoint accepts optional `parent_id`.
- The service verifies the parent belongs to the same post, is top-level, and
  is visible before creating a reply.
- Posting a reply requires the same adult access, verified connector, unfrozen
  space, published/unlocked post, and pre-screening as a top-level comment.
- Members see only visible top-level comments and visible replies whose parent
  remains visible.
- Moderators may see non-visible records for audit, including children grouped
  under their parent.
- A hidden, removed, or escalated parent makes its replies unavailable to
  members even if a child row is still marked visible.
- Comment upvotes require both a visible comment and, for replies, a visible
  parent.

## UI Contract

- Preserve the existing Poppins/Figtree typography, `brand-*` palette, compact
  connector cards, 44 px minimum interactive targets, focus rings, and literal
  Tailwind class names.
- A reply is indented beneath its parent with a subtle left guide, not rendered
  as another full-size feed card.
- `Reply` reveals a small inline form and includes the author's name in the
  accessible label.
- No private reply, direct-message, second-level nesting, share counter,
  follower metric, or public profile navigation is introduced.

## Verification Contract

Automated coverage must prove:

- normalized schema and model relationships;
- multiple-image success, image count/type/size rejection, single-video success,
  video size rejection, and mixed-media rejection;
- edit removal/replacement behavior and retained audit rows/files;
- authorized active-media delivery plus cross-connector, minor, hidden-post,
  removed-media, and missing-file denial;
- avatar fallback and connector owner/custom/member role rendering on posts,
  comments, and replies;
- one-level reply creation and rejection for cross-post, nested, hidden-parent,
  locked-post, frozen-space, and minor cases;
- member versus moderator visibility and thread ordering;
- reply upvote and report compatibility;
- composer/gallery/thread UI smoke contracts;
- focused Community tests, broader relevant tests, and the Vite production build.

