# Community Feed V1 Baseline Design

> Current status: superseded as the active product target by
> `docs/superpowers/specs/2026-08-05-community-hub-design.md`.
> Keep this document as the backend safety baseline only. The current feed
> experience is the Connector Community Hub: connector-scoped, adult-facing,
> moderation-first, with tabs for Featured, Announcements, Events, Resources,
> Q&A, and Discussions. Do not implement this older V1 document as a standalone
> generic feed.

## Status

Superseded by the Community Hub spec for active implementation. Retained for
backend safety contracts, entity definitions, moderation rules, and minor
exclusion requirements.

## Context

Conscious Connections is an age-aware Laravel education platform for sexual health, consent, relationships, and community education in the Philippines. The platform already has global RBAC, Laravel policies, connector-local roles, parent-child safety workflows, centralized moderation, suspensions, appeals, connector accounts, seminars, livestreams, and notifications.

Community Feed V1 defined the original safety and governance baseline. The
current Community Hub still reuses those patterns and still must not become an
open social network or an adult-to-minor contact surface.

## V1 Scope

The current Community Hub is a connector-scoped, adult-facing education space.
Approved connectors can publish announcements, seminar/event posts, educational
resources, moderated Q&A, and connector-authored discussion prompts.

Minors cannot create posts, comment, react, or receive replies in V1. Guardian notifications are reserved for future minor participation or child feed visibility features. In V1, guardian alerts are not required because child accounts do not directly interact with the feed.

Connector moderators handle first-level moderation. Platform admins keep final authority, escalation control, and emergency freeze powers.

## Explicit Non-Goals

- No minor posting, commenting, reacting, or direct feed interaction.
- No private messaging, direct replies, or DM-like feature.
- No global feed promotion in V1.
- No full nested discussion threads.
- No open social feed for peer support in V1.
- No unmanaged external-link sharing.

## Domain Safety Position

Even when V1 is adult-facing, the feed exists in a sensitive sexual-health education domain. The design must prevent contact-seeking, off-platform solicitation, harassment, unsafe advice, and unmanaged disclosure of personal sexual-health situations.

Future minor participation must require a separate design pass covering age-aware visibility, guardian notification or approval rules, educator-mediated Q&A, stricter pre-moderation, and adult-to-minor interaction controls.

## Core Entities

### CommunitySpace

Represents a connector-owned feed space.

Required relationships:

- Belongs to a connector.
- Has many posts.
- Has moderation settings.

Important state:

- Active when the connector is approved and not suspended.
- Read-only or hidden when the connector is suspended, depending on admin setting.
- Frozen when platform or space-level emergency controls are enabled.

### CommunityPost

Represents a connector feed item.

Required relationships:

- Belongs to a community space.
- Belongs to a connector.
- Belongs to an author user.
- Has many comments.
- Has many reactions.
- Has many reports or report-source records.
- Has many moderation action logs.

Current supported post types:

- Announcement
- Event
- Educational resource
- Moderated question
- Discussion prompt

Post statuses:

- `draft`
- `pending_review`
- `published`
- `hidden`
- `locked`
- `removed`
- `escalated`
- `archived`

### CommunityComment

Represents flat comments on posts.

V1 comments are flat only. There are no nested replies because nested replies can create targeted, DM-like interaction patterns. If comments are enabled, they must follow the same suspension, connector, moderation, pre-screening, and audit rules as posts.

### CommunityReaction

Represents low-risk educational reactions.

Current reaction set:

- `learned`
- `helpful`
- `question`
- `support`

Avoid standard social reactions such as flirtatious, popularity-coded, mocking, or emotionally ambiguous reactions.

### CommunityReport

Represents a community-specific report source that feeds into the existing moderation pipeline through a community source adapter. Reports must create or update centralized moderation cases instead of creating a parallel moderation system.

### CommunityModerationAction

Records local moderation actions for community content.

Every action must log:

- Actor
- Target type and target id
- Previous status
- New status
- Reason
- Timestamp
- Connector scope, when applicable
- Escalation or central moderation case reference, when applicable

## Permissions And Access

V1 uses both global platform permissions and connector-local permissions.

Global/admin permissions:

- `community.view_any`
- `community.moderate_any`
- `community.freeze`
- `community.manage_settings`

Connector-local permissions:

- `community.view_space`
- `community.create_post`
- `community.edit_own_post`
- `community.manage_posts`
- `community.approve_posts`
- `community.lock_threads`
- `community.manage_comments`
- `community.escalate_to_platform`

Access rules:

- Only approved connectors can have active community spaces.
- Suspended connectors lose posting and commenting immediately.
- Suspended users lose all feed interaction immediately.
- Removed connector members lose future connector-feed permissions immediately.
- Historical posts remain attributed and preserved for audit.
- Minors are excluded from V1 interaction routes by policy or middleware, not only by hidden UI.
- Platform admins can moderate any connector space.
- Connector moderators can moderate only spaces belonging to their connector.

## Moderation And Safety

V1 moderation is strict by default.

Publishing rules:

- Announcement and resource posts may publish directly if the author has connector permission and pre-screening returns low risk.
- Moderated questions enter `pending_review` before publication.
- Edited published posts rerun pre-screening and may return to `pending_review`.
- Hidden, removed, and escalated content remains visible to authorized moderators and platform admins for audit.

Interaction rules:

- Comments are flat only.
- No private-message feature is added or implied.
- No nested reply chains.
- No adult-to-minor feed interaction exists in V1.

Blocked or escalated content includes:

- Phone numbers, personal email addresses, and social handles.
- "DM me", "message me privately", or equivalent off-platform contact language.
- Meet-up requests.
- School, exact location, or child-targeting details.
- Sexual solicitation.
- Threats, harassment, spam, or duplicate flooding.
- Abuse disclosures with identifying details.
- External contact instructions.

External links should either be disabled in V1 or restricted to allowlisted trusted education, health, government, or connector-owned sources.

Pre-screening outcomes:

- `auto_hide_and_escalate`: contact solicitation, sexual solicitation, explicit targeting, threats, or abuse disclosures with identifying details.
- `pending_review`: moderated questions, sensitive terms, first post from a new connector member, or external links.
- `allow`: low-risk announcements and educational resources from trusted connector roles.
- `block_with_feedback`: obvious contact information, spam, duplicate flooding, or prohibited formatting.

## Data Flow

1. A connector member drafts a post.
2. The system checks connector status, user suspension status, connector membership, connector-local permission, post type, and global emergency freeze state.
3. Pre-screening runs before save or publication.
4. Low-risk announcements and resources may publish.
5. Moderated questions enter `pending_review`.
6. Connector moderators approve, reject, hide, lock, or escalate.
7. Reports route to the existing centralized moderation system through a community source adapter.
8. Platform admins can override connector decisions, enforce violations, suspend users or connectors, freeze spaces, or freeze the whole community module.

## Lifecycle Edge Cases

Connector suspended:

- The connector's community spaces become read-only or hidden depending on admin setting.
- New posts, comments, edits, and reactions stop immediately.
- Existing content remains preserved for audit.

User suspended:

- The user cannot post, comment, react, edit, approve, reject, hide, lock, escalate, or moderate.
- Existing content remains preserved and can be moderated by authorized users.

Connector member removed:

- The member loses future connector-feed access and permissions.
- Historical content remains attributed to the original author.

Post edited after publication:

- The edited content reruns pre-screening.
- Risky edits can move the post back to `pending_review`, `hidden`, or `escalated`.
- Previous versions remain available to authorized moderators and admins.

Post locked:

- New comments and reactions stop.
- Content remains visible unless separately hidden or removed.

Emergency freeze enabled:

- Create, update, comment, and reaction actions stop for affected spaces.
- Admin moderation actions remain available.

## Notifications

V1 notifications:

- Connector moderators are notified for pending questions and connector-space escalations.
- Platform admins are notified for escalations, severe auto-hides, and emergency safety events.
- Authors are notified when posts are approved, rejected, hidden, locked, restored, removed, or escalated.

Guardian notifications:

- Not required in Community Feed V1.
- Reserved for future child feed visibility or minor participation.
- Must be reconsidered before minors can view, post, comment, react, or receive replies.

## Testing And Acceptance Criteria

Core tests:

- Adult connector member with permission can create announcement and resource posts.
- Moderated question posts enter `pending_review`.
- Connector moderator can approve, reject, hide, lock, and escalate posts in their own connector space.
- Connector moderator cannot moderate another connector's space.
- Platform admin can moderate any space.
- Platform admin can activate emergency freeze.
- Suspended users cannot interact with the feed.
- Suspended connectors cannot publish or accept new interaction.
- Removed connector members lose feed management access.
- Minors cannot create posts, comment, react, or receive feed interaction in V1.
- Reported posts create centralized moderation cases through the community adapter.
- Edited published posts rerun pre-screening.
- Hidden and removed content remains visible to authorized moderators and admins for audit.
- Guardian notifications are not sent in V1 feed workflows.

Acceptance criteria:

- No adult-to-minor feed interaction exists in V1.
- No private-contact feature is created by posts, comments, reactions, or notifications.
- All moderation actions are auditable.
- Platform admins have final override authority.
- Feed permissions respect global RBAC, connector-local roles, suspension state, and connector status.
- The module can be frozen during a safety incident.

## Current Implementation Planning Notes

Active implementation work should follow the Community Hub spec and plan, while
preserving this document's service-layer, policy, connector-permission,
notification, and moderation contracts. It should not introduce a parallel
moderation or suspension system.

The active Community Hub implementation should prioritize:

- Stable backend safety contracts from this baseline.
- User-facing Community Hub copy instead of generic Community Feed copy.
- Hub tabs: Featured, Announcements, Events, Resources, Q&A, Discussions, and authorized Moderation.
- Post types: announcement, event, resource, moderated_question, and discussion_prompt.
- Featured/pinned posts and seminar-aware event posts.
- Admin and connector moderation workspaces matching the existing module UI.
- Tests for minor exclusion, suspension behavior, connector scoping, moderation routing, audit retention, emergency freeze, and absence of guardian notifications in the adult-facing version.
