# Guardian / Dependent Existing Learner Verification Design

## Goal

Make every guardian-to-dependent relationship pass the existing centralized admin verification workflow without interrupting an existing learner's account, dashboard, or chat access.

## Current Root Causes

- `ParentChildInvitationService::sendInvitation()` creates a `ParentChildAccount` and, for relationships requiring proof, submits it to admin review before the learner accepts.
- `EnsureProfileCompleted` redirects any learner with a pending or rejected `ParentChildAccount` to child-verification status. The pre-accept record therefore locks an existing learner out of learner routes.
- The same access disruption explains the reported chat failure in the affected workflow; chat's own role and conversation authorization already allows eligible learners.

## Chosen Architecture

The invitation remains the only persisted relationship state until the learner accepts. The guardian's relationship proof is staged on the invitation, not submitted to the admin queue. Acceptance atomically creates the existing `ParentChildAccount`, transfers the staged proof to the existing `GuardianRelationshipVerificationDocument` records, and calls `GuardianRelationshipVerificationService` to submit the relationship for centralized admin review.

No new verification queue, page, approval path, or role is introduced. Existing guardian-created child registration continues to create and submit a `ParentChildAccount` immediately because no existing learner must consent to that account creation.

## Data and Lifecycle

1. A verified guardian invites an existing learner. `ParentChildInvitation` stores the invited relationship metadata and staged proof metadata/files when the relationship type requires proof. No `ParentChildAccount` exists yet.
2. The learner can view, accept, reject, or ignore the pending invitation while continuing to use learner routes and chat.
3. Rejecting marks the invitation rejected, leaves no relationship record, preserves the existing guardian notification, and permits a later invitation.
4. Accepting creates or restores the existing `ParentChildAccount` in a pending state. For proof-required relationships, staged files become `GuardianRelationshipVerificationDocument` entries and the existing verification service changes the relationship to `under_review` and notifies admins.
5. Admins review the existing centralized relationship queue. Approval marks the relationship verified and active. Rejection or resubmission remains handled by `GuardianRelationshipVerificationService`.
6. Both the guardian and dependent receive the final approval or rejection notification. The dependent is added to the existing relationship status notification recipient list; no separate notification system is created.

## Access Controls

`EnsureProfileCompleted` continues to gate newly guardian-created child accounts using their required child verification document, but does not gate an established learner because of a pending, rejected, or under-review invited relationship. It must select the child-registration verification record rather than the first arbitrary relationship record.

Chat retains its existing role-permission, learner/instructor enrollment, approved guardian/dependent relationship, participant, and broadcast-channel checks. Regression tests prove that a learner with a pending invitation can open chat, discover contacts, start an eligible instructor conversation, and use their dashboard. A pending relationship never grants guardian/dependent chat privileges; those remain conditional on admin approval.

## Admin Verification UI

The existing Guardian / Dependent / Relationship tables gain avatar cells using the platform's existing learner/profile image fallbacks. The existing relationship review page/modal gains a guardian identity-document comparison section using the guardian's submitted ID front/back files.

Each document is served through authorized admin-only routes. Images render inline; PDFs render in an embedded preview; unsupported formats retain a download action. Relationship proof and guardian identity documents remain visually distinct.

## Testing

Feature tests will cover:

- invitation submission with required proof creates no relationship or admin-review record before acceptance;
- the invited learner can open the learner dashboard and chat while pending;
- acceptance creates one pending relationship, transfers proof, and submits the relationship to the existing admin queue;
- rejection creates no relationship and permits a later invitation;
- centralized admin approval/rejection keeps the existing statuses and notifies both parties;
- table avatars and identity/proof preview routes render with correct authorization;
- existing child registration verification and instructor/learner chat behavior remain unchanged.

## Constraints

- Reuse `ParentChildAccount`, `GuardianRelationshipVerificationService`, centralized admin verification views, and existing notifications where possible.
- Do not activate a relationship, grant relationship-only access, or bypass admin review before approval.
- Do not add dependencies, a duplicate verification system, or duplicate administrative pages.
- Preserve the current UI patterns and existing learner behavior.
