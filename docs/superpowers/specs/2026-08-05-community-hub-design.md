# Connector Community Hub Design

## Status

Approved for implementation planning.

## Product Direction

Build a moderated, connector-managed education community hub for announcements, seminars, resources, Q&A, and safe discussion.

The product may borrow the familiar structure of Facebook Groups, but it must not behave like a general social media feed. Community Hub is a connector-owned education space with clear content types, moderation controls, and platform safety oversight.

## Plain-Language Model

A Connector is an approved organization space, such as a school, NGO, health organization, advocacy group, or community-based organization. Community Hub gives each approved Connector one managed place to publish updates, invite members to seminars, answer moderated questions, share educational resources, and host safe discussion prompts.

Community Hub should feel familiar to members: there is a feed, featured posts, tabs, reactions, comments, events, and resources. It should also feel more disciplined than Facebook Groups: no private replies, no viral sharing, no popularity-first design, and no open minor participation in the first version.

## Developer Model

Community Hub extends the existing Community Feed V1 safety architecture. It remains connector-scoped, adult-facing, policy-guarded, and backed by connector-local permissions plus global admin moderation.

Existing Community Feed V1 entities remain valid:

- `CommunitySpace`
- `CommunityPost`
- `CommunityComment`
- `CommunityReaction`
- `CommunityReport`
- `CommunityModerationAction`
- `CommunityPostVersion`
- `CommunityFeedSetting`

The hub layer adds stronger product taxonomy and UI contracts:

- Post type labels become user-facing hub sections.
- Seminar/event content is first-class in the feed.
- Featured/pinned posts are visible in the main hub layout.
- Resource and Q&A flows are easier to discover.
- Discussion remains connector-authored and moderation-first.

## Scope

### MVP Scope

- Rename user-facing connector pages from `Community Feed` to `Community Hub`.
- Keep route names and model names stable unless a future refactor intentionally renames the domain.
- Add hub tabs: `Featured`, `Announcements`, `Events`, `Resources`, `Q&A`, `Discussions`, `Moderation`.
- Support post types:
  - `announcement`
  - `event`
  - `resource`
  - `moderated_question`
  - `discussion_prompt`
- Keep comments flat.
- Keep reactions education-focused:
  - `learned`
  - `helpful`
  - `question`
  - `support`
- Add or refine reusable Blade components so connector community pages match seminar, connector, and admin module UI patterns.
- Add admin review screens that match existing moderation/admin table patterns.
- Preserve all Community Feed V1 safety constraints.

### Non-Goals

- No global community feed.
- No private messages, private replies, or DM-like behavior.
- No nested reply chains.
- No follower counts, trending topics, public leaderboards, or popularity-first engagement.
- No open minor posting, commenting, reacting, or adult-to-minor interaction in this version.
- No untrusted HTML rendering from user-submitted post bodies.

## Role-Specific Experience

### Connectors

Connectors need a workspace-style hub, not a noisy social page.

Connector users with the right connector-local permissions can:

- Create announcements, event posts, resources, moderated Q&A posts, and discussion prompts.
- Pin or feature important posts.
- Link a post to an existing connector seminar when relevant.
- Review pending questions and unsafe comments.
- Hide, lock, remove, restore, or escalate content within their connector space.
- See a right-side panel with upcoming seminars, pending moderation, reported content, and community rules.

### Members And Adult Learners

Eligible adult members can:

- Browse hub tabs.
- Read featured posts, announcements, events, resources, Q&A, and discussion prompts.
- React with education-focused reactions.
- Add flat comments when the post is open and their role allows it.
- Report unsafe or inappropriate content.
- Save or bookmark useful posts if that interaction is implemented in the selected phase.

### Administrators

Admins need platform-wide visibility and final safety authority.

Admins can:

- View all connector spaces.
- Filter by connector, post type, status, report count, and escalation state.
- Review post details, comments, reports, versions, and moderation history.
- Freeze all community interactions during safety incidents.
- Place individual connector spaces into read-only or hidden mode.
- Override connector moderation decisions.

## UI/UX Contract

Community Hub must match the existing Laravel Blade UI language.

Connector-facing pages should follow the visual feel of:

- `resources/views/layouts/connector-app.blade.php`
- `resources/views/connectors/seminars/index.blade.php`
- `resources/views/connectors/seminars/show.blade.php`
- `resources/views/connectors/members/index.blade.php`

Admin-facing pages should follow:

- `resources/views/layouts/admin.blade.php`
- `resources/views/admin/community/index.blade.php`
- `resources/views/admin/moderation/suspensions/index.blade.php`
- `resources/views/admin/connectors/index.blade.php`
- `resources/views/admin/partials/table-filter-bar.blade.php`
- `resources/views/admin/partials/table-pagination-footer.blade.php`

Reusable community components should remain the shared UI layer:

- `resources/views/components/community/feed-sidebar.blade.php`
- `resources/views/components/community/post-card.blade.php`
- `resources/views/components/community/post-composer.blade.php`
- `resources/views/components/community/post-type-badge.blade.php`
- `resources/views/components/community/reaction-row.blade.php`
- `resources/views/components/community/right-panel.blade.php`
- `resources/views/components/community/safety-reminder.blade.php`
- `resources/views/components/community/status-badge.blade.php`

### Visual Rules

- Use compact, module-style cards with 8px rounded corners.
- Use existing purple primary actions, amber review states, rose safety states, emerald success states, and neutral gray tables.
- Keep pages information-dense but readable.
- Use tabs and filters instead of large marketing-style sections.
- Show moderation state plainly with badges.
- Keep safety reminders visible but not alarmist.
- Avoid Facebook-style clutter: no share counters, viral prompts, follower metrics, or infinite decorative sidebars.

## Hub Navigation

Connector Community Hub should expose these tabs:

- `Featured`: pinned/featured posts and important announcements.
- `Announcements`: official connector updates.
- `Events`: seminar-linked or manually created event posts.
- `Resources`: educational links and reference materials.
- `Q&A`: moderated questions and official answers.
- `Discussions`: connector-authored discussion prompts with flat comments.
- `Moderation`: visible only to connector moderators.

The admin Community Hub should expose:

- `All Posts`
- `Pending Review`
- `Reported`
- `Escalated`
- `Connectors`
- `Settings`

## Content Types

### Announcement

Official connector update. Suitable for schedules, policy notices, reminders, and community news.

### Event

Seminar, webinar, livestream, workshop, outreach session, or physical event. Event posts may link to an existing seminar record when one exists.

### Resource

Educational post with an optional safe link. External links must be escaped and restricted by allowlist or moderation policy.

### Moderated Question

Question submitted into review before publication. Published questions should be answerable by connector staff or instructors and may receive an `Official Answer` marker.

### Discussion Prompt

Connector-authored prompt meant to guide safe, educational discussion. This is not an open peer-support thread.

## Engagement Features

MVP engagement:

- Reactions: `Learned`, `Helpful`, `Question`, `Support`.
- Flat comments.
- Reports.
- Featured/pinned posts.
- Official answer marker for Q&A responses.

Version 2 engagement:

- Polls with safe answer options and connector/admin visibility.
- Bookmarks or saved resources.
- Event RSVP/registration shortcuts when connected to seminars.
- Post-event Q&A collection.
- Member digest notifications.
- Achievement badges tied to learning actions, seminar attendance, or constructive Q&A, not popularity.

Future engagement:

- Cohort-based learning circles.
- AI-assisted connector post drafting from approved templates.
- Cross-connector campaigns controlled by platform admins.
- Multilingual post versions.
- Minor-safe question submission after a separate child-safety design.

## Safety Requirements

Community Hub inherits all Community Feed V1 safety requirements.

Required:

- Adult-facing only for this version.
- Server-side minor exclusion for create, comment, react, and report routes.
- No adult-to-minor reply surface.
- No private reply or off-platform contact prompt.
- Contact solicitation auto-hidden or blocked.
- Abuse, coercion, grooming, threats, sexual solicitation, and identifying sensitive disclosures escalated.
- Hidden, removed, edited, and escalated content preserved for authorized audit.
- Connector moderators review first; platform admins retain final authority.
- Emergency freeze blocks connector/member writes but keeps admin moderation available.
- Guardian notifications are not sent by this adult-facing version.

## Prioritization

### MVP

- Product rename to Community Hub in UI copy.
- Hub tab layout.
- Post type expansion to events and discussion prompts.
- Featured/pinned posts.
- Seminar-aware event posts.
- Connector and admin UI alignment.
- Moderation queue visibility.
- UI smoke tests for connector, admin, and minor access.

These belong in MVP because they define the product shape users will actually experience while staying inside the current safety model.

### Version 2

- Polls.
- Bookmarks/saved posts.
- Official answer workflow.
- Event RSVP shortcuts and post-event follow-up posts.
- Digest notifications.
- Connector analytics.
- Post templates.

These are valuable but depend on the core hub UI, taxonomy, and moderation contracts being stable.

### Future

- Minor-safe participation.
- Guardian-visible community summaries.
- Cohort learning circles.
- AI moderation triage.
- AI connector drafting.
- Cross-connector campaigns.
- Multilingual content.

These increase safety, governance, or architecture complexity and should be designed separately.

## Acceptance Criteria

- Connector users see `Community Hub`, not a generic social feed.
- Connector UI visually matches existing connector module pages.
- Admin UI visually matches existing admin moderation/table pages.
- Hub tabs are discoverable and stable on desktop and mobile.
- Events, resources, Q&A, announcements, and discussions are clearly distinguished.
- Moderation controls are available only to authorized connector moderators and admins.
- Minor accounts cannot interact with the hub through direct route access.
- No UI text suggests DMs, private replies, global sharing, or open social networking.
- Existing Community Feed V1 safety tests continue to pass.
