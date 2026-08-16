# Guardian / Dependent Existing Learner Verification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Defer an existing learner relationship until invitation acceptance, then use the current centralized admin verification process without disrupting learner access or chat.

**Architecture:** A pending invitation owns staged relationship-proof file metadata. Acceptance atomically creates the existing ParentChildAccount, moves the staged files to existing GuardianRelationshipVerificationDocument records, and calls GuardianRelationshipVerificationService to mark the relationship under review. The child-registration flow remains unchanged; its document path is the discriminator for the learner access gate.

**Tech Stack:** Laravel, Eloquent, Blade/Alpine, PHPUnit feature tests, database notifications, local storage.

## Global Constraints

- Reuse ParentChildAccount, GuardianRelationshipVerificationService, existing notifications, and the existing Guardian / Dependent / Relationship admin workspace.
- A pending invitation creates neither a relationship nor an admin review item.
- No relationship becomes active or grants relationship-only chat privileges before admin approval.
- Add no dependency, alternate verification queue, or duplicate page.

---

### Task 1: Specify pre-accept access and post-accept review behavior

**Files:**

- Modify: tests/Feature/Parent/ParentChildInvitationFlowTest.php
- Modify: tests/Feature/Chat/ChatPageRenderTest.php
- Modify: tests/Feature/Chat/ChatHttpFlowTest.php

**Interfaces:**

- Consumes: parent.invitations.store, parent.invitations.respond, learner.dashboard, chat.page, and chat.conversations.start.
- Produces: regression coverage for pending-invitation access, proof transfer, review submission, rejection, and re-invitation.

- [ ] **Step 1: Write the invitation regression tests**

Add a proof-required invitation test that posts a legal-guardian invitation and asserts:

~~~php
$this->assertDatabaseMissing('parent_child_accounts', [
    'parent_user_id' => $parent->id,
    'child_user_id' => $child->id,
]);
$this->assertDatabaseCount('guardian_relationship_verification_documents', 0);
$this->assertNotEmpty(ParentChildInvitation::query()->sole()->relationship_verification_documents);
~~~

Add a pending-invitation access test that asserts the invited learner receives 200 from both learner.dashboard and chat.page. Extend acceptance coverage to assert one relationship, relationship_verified_status = under_review, proof document rows, and the existing admin-submission notification. Extend rejection coverage to assert no relationship exists and a new invitation may be sent afterward.

- [ ] **Step 2: Add the chat eligibility regression**

Create an approved learner enrollment with a pending invitation and assert its instructor conversation starts normally:

~~~php
$this->actingAs($learner)
    ->postJson(route('chat.conversations.start'), [
        'target_user_id' => $instructor->id,
        'conversation_type' => Conversation::TYPE_DIRECT,
        'initial_message' => 'Can you help with this module?',
    ])
    ->assertCreated()
    ->assertJsonPath('requires_request', false);
~~~

- [ ] **Step 3: Run the tests to verify red**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php tests/Feature/Chat/ChatPageRenderTest.php tests/Feature/Chat/ChatHttpFlowTest.php

Expected: failures showing pre-accept relationship creation and learner redirect to child verification.

- [ ] **Step 4: Commit the red tests**

~~~bash
git add tests/Feature/Parent/ParentChildInvitationFlowTest.php tests/Feature/Chat/ChatPageRenderTest.php tests/Feature/Chat/ChatHttpFlowTest.php
git commit -m "test: cover pending guardian invitation access"
~~~

### Task 2: Persist proof on the invitation, not on a relationship

**Files:**

- Create: database/migrations/2026_08_13_100000_add_staged_relationship_documents_to_parent_child_invitations.php
- Modify: app/Models/ParentChildInvitation.php
- Modify: app/Services/ParentChildInvitationService.php
- Modify: tests/Feature/Parent/ParentChildInvitationFlowTest.php

**Interfaces:**

- Consumes: an array with document_type, document, and optional supporting_document.
- Produces: ParentChildInvitation relationship_verification_documents as an array of document metadata.

- [ ] **Step 1: Run the proof-staging test alone**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php --filter=proof_required_invitation

Expected: FAIL because the invitation has no staged document storage.

- [ ] **Step 2: Add one JSON metadata column and cast**

Create the migration:

~~~php
Schema::table('parent_child_invitations', function (Blueprint $table): void {
    if (! Schema::hasColumn('parent_child_invitations', 'relationship_verification_documents')) {
        $table->json('relationship_verification_documents')->nullable()->after('relationship_custom');
    }
});
~~~

The reverse migration conditionally drops that field. Add it to fillable and add this model cast:

~~~php
'relationship_verification_documents' => 'array',
~~~

- [ ] **Step 3: Stage uploads within sendInvitation**

After creating the invitation, store each supplied upload to guardian-relationship-invitations/invitation-id on the local disk and persist this normalized entry in the JSON field:

~~~php
[
    'document_type' => $documentType,
    'disk' => 'local',
    'path' => $file->store("guardian-relationship-invitations/{$invitation->id}", 'local'),
    'original_name' => $file->getClientOriginalName(),
    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
    'size_bytes' => $file->getSize() ?: 0,
]
~~~

Use supporting_legal_document for optional proof. Remove ParentChildAccount creation, relationship-service submission, and admin notification from this method. Preserve current request validation and learner invitation notification.

- [ ] **Step 4: Verify green and commit**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php --filter=proof_required_invitation

Expected: PASS.

~~~bash
git add database/migrations/2026_08_13_100000_add_staged_relationship_documents_to_parent_child_invitations.php app/Models/ParentChildInvitation.php app/Services/ParentChildInvitationService.php tests/Feature/Parent/ParentChildInvitationFlowTest.php
git commit -m "feat: stage guardian invitation proof"
~~~

### Task 3: Submit an accepted invitation through the existing relationship service

**Files:**

- Modify: app/Services/GuardianRelationshipVerificationService.php
- Modify: app/Services/ParentChildInvitationService.php
- Modify: tests/Feature/Parent/ParentChildInvitationFlowTest.php

**Interfaces:**

- Produces: submitStaged(ParentChildAccount relationship, User guardian, array documents): ParentChildAccount.

- [ ] **Step 1: Run the acceptance regression**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php --filter=accept

Expected: FAIL because staged proof cannot become relationship proof.

- [ ] **Step 2: Add a shared staged-document submission path**

Refactor GuardianRelationshipVerificationService submit so uploaded and staged proof share one private under-review transition. Add submitStaged, which verifies each staged local file exists, moves it to guardian-relationship-verifications/relationship-id, creates GuardianRelationshipVerificationDocument, then calls the shared transition that sets under_review, audits submission, and notifies the guardian and admins. A missing staged file throws InvalidArgumentException before state changes.

- [ ] **Step 3: Make invitation acceptance atomic**

In respondToInvitation, create or restore the same ParentChildAccount payload currently used on acceptance. For proof-required relationships invoke:

~~~php
$this->relationshipVerificationService->submitStaged(
    $relationship,
    $invitation->inviterParent()->firstOrFail(),
    $invitation->relationship_verification_documents ?? [],
);
~~~

Set relationship_status and verification_status to pending, and leave relationship_verified_at null until approval. Only mark the invitation accepted after relationship creation/submission succeeds; clear its staged metadata on success. Keep the no-proof relationship behavior and the existing invitation-response notification.

- [ ] **Step 4: Verify green and commit**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php

Expected: PASS, including proof transfer, under-review queue state, rejection, and re-invitation.

~~~bash
git add app/Services/GuardianRelationshipVerificationService.php app/Services/ParentChildInvitationService.php tests/Feature/Parent/ParentChildInvitationFlowTest.php
git commit -m "feat: submit accepted guardian relationships"
~~~

### Task 4: Keep existing learners active while preserving child-account verification

**Files:**

- Modify: app/Http/Middleware/EnsureProfileCompleted.php
- Modify: app/Http/Controllers/Auth/ParentRegistrationController.php
- Modify: tests/Feature/Auth/ParentChildVerificationResubmissionTest.php
- Modify: tests/Feature/Parent/ParentChildInvitationFlowTest.php

**Interfaces:**

- Consumes: the child-registration-only verification_document_path.
- Produces: learner access/status checks that ignore invitation-created relationships.

- [ ] **Step 1: Run the access and child-verification tests**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php --filter=pending_existing_learner tests/Feature/Auth/ParentChildVerificationResubmissionTest.php

Expected: the first test FAILS before the narrow lookup; child-registration tests remain green.

- [ ] **Step 2: Use the child-registration query in both places**

Replace each broad first-match relationship lookup with:

~~~php
ParentChildAccount::query()
    ->where('child_user_id', $user->id)
    ->whereNotNull('verification_document_path')
    ->latest('id')
    ->first();
~~~

Use it in EnsureProfileCompleted handle and ParentRegistrationController childVerificationStatus. Retain the existing pending/rejected redirect and profile-completion behavior.

- [ ] **Step 3: Verify green and commit**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php --filter=pending_existing_learner tests/Feature/Auth/ParentChildVerificationResubmissionTest.php

Expected: PASS.

~~~bash
git add app/Http/Middleware/EnsureProfileCompleted.php app/Http/Controllers/Auth/ParentRegistrationController.php tests/Feature/Parent/ParentChildInvitationFlowTest.php tests/Feature/Auth/ParentChildVerificationResubmissionTest.php
git commit -m "fix: keep invited learners active"
~~~

### Task 5: Notify guardian and dependent about final review decisions

**Files:**

- Modify: app/Services/GuardianRelationshipVerificationService.php
- Modify: app/Notifications/RelationshipVerificationStatusNotification.php
- Modify: tests/Feature/Admin/AdminParentChildVerificationModerationWorkflowTest.php
- Modify: tests/Feature/Parent/ParentChildInvitationFlowTest.php

**Interfaces:**

- Produces: approval/rejection database notifications for both relationship users.

- [ ] **Step 1: Write failing notification assertions**

After admin approval and final rejection, assert each parent and child has the matching guardian_relationship_verification_approved or guardian_relationship_verification_rejected notification.

- [ ] **Step 2: Run red**

Run: php artisan test tests/Feature/Admin/AdminParentChildVerificationModerationWorkflowTest.php tests/Feature/Parent/ParentChildInvitationFlowTest.php

Expected: FAIL because only the guardian is notified.

- [ ] **Step 3: Notify both users with correct destinations**

After auditing a transition, refresh once and notify both related users:

~~~php
$freshRelationship = $relationship->fresh(['parent', 'child', 'verificationDocuments']);
$freshRelationship->parent?->notify(new RelationshipVerificationStatusNotification($freshRelationship, $action));
$freshRelationship->child?->notify(new RelationshipVerificationStatusNotification($freshRelationship, $action));
~~~

In toDatabase, use route('learner.parent.index') for the dependent and retain parent.relationship-verifications.show for the guardian.

- [ ] **Step 4: Verify green and commit**

Run: php artisan test tests/Feature/Admin/AdminParentChildVerificationModerationWorkflowTest.php tests/Feature/Parent/ParentChildInvitationFlowTest.php

Expected: PASS.

~~~bash
git add app/Services/GuardianRelationshipVerificationService.php app/Notifications/RelationshipVerificationStatusNotification.php tests/Feature/Admin/AdminParentChildVerificationModerationWorkflowTest.php tests/Feature/Parent/ParentChildInvitationFlowTest.php
git commit -m "feat: notify both relationship parties"
~~~

### Task 6: Enhance the existing centralized review UI

**Files:**

- Modify: resources/views/admin/parent-verifications/index.blade.php
- Modify: resources/views/admin/parent-verifications/show-relationship.blade.php
- Modify: tests/Feature/Admin/AdminParentChildVerificationUiTest.php

**Interfaces:**

- Consumes: existing eager-loaded learner profiles and the existing parent-document route.
- Produces: avatars in Guardian, Dependent, and Relationship rows plus identity-document comparison in the existing relationship view.

- [ ] **Step 1: Add failing markup assertions**

Assert the three tables render descriptive avatar images or initial fallbacks. Assert relationship detail renders Guardian identity documents, an existing parent-document URL, and a PDF iframe branch.

- [ ] **Step 2: Run red**

Run: php artisan test tests/Feature/Admin/AdminParentChildVerificationUiTest.php

Expected: FAIL because the relationship screen lacks guardian identity comparison.

- [ ] **Step 3: Implement avatars and comparison cards**

Replace table name-only blocks with the existing learner-profile avatar path where present and an initial fallback otherwise. Use h-9 w-9 rounded-full object-cover and descriptive alt text.

Before relationship-proof cards, render front/back guardian ID cards when the corresponding parent path exists. Build URLs with:

~~~php
route('admin.parent-verifications.parents.document', [$relationship->parent, $side])
~~~

Render an iframe for PDF paths and an image otherwise; add Preview and Download links to the same authorized route. Render Not submitted when a side is absent. Do not add another route, controller, or page.

- [ ] **Step 4: Verify green and commit**

Run: php artisan test tests/Feature/Admin/AdminParentChildVerificationUiTest.php

Expected: PASS.

~~~bash
git add resources/views/admin/parent-verifications/index.blade.php resources/views/admin/parent-verifications/show-relationship.blade.php tests/Feature/Admin/AdminParentChildVerificationUiTest.php
git commit -m "feat: compare guardian identity documents"
~~~

### Task 7: Verify chat authorization and the complete workflow

**Files:**

- Modify: tests/Feature/Chat/ChatChannelAuthorizationTest.php
- Modify only scoped production files if verification exposes a reproducible defect.

**Interfaces:**

- Consumes: existing ChatAuthorizationService and broadcast channel policy.
- Produces: evidence that pending relationships grant no relationship chat access while learner/instructor chat stays available.

- [ ] **Step 1: Add the relationship chat-boundary test**

Assert a learner-to-learner direct conversation is forbidden for a pending ParentChildAccount, then update that relationship to approved with relationship_verified_at = now and assert it succeeds.

- [ ] **Step 2: Run affected suites**

Run: php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php tests/Feature/Auth/ParentChildVerificationResubmissionTest.php tests/Feature/Admin/AdminParentChildVerificationModerationWorkflowTest.php tests/Feature/Admin/AdminParentChildVerificationUiTest.php tests/Feature/Chat

Expected: PASS with zero failures.

- [ ] **Step 3: Run complete verification**

Run: php artisan test

Expected: PASS with zero failures.

Run: vendor/bin/pint --test

Expected: exit code 0.

Run: vendor/bin/phpstan analyse --level=9

Expected: exit code 0; if PHPStan is unavailable, record that result without claiming static-analysis coverage.

- [ ] **Step 4: Inspect the final scoped diff**

Run: git diff --check and git status --short

Expected: no whitespace errors. If verification identifies a defect, return to its owning task, add a failing focused regression, and make only the corresponding scoped correction.

## Plan Self-Review

- Tasks 1–5 cover invitation deferral, acceptance, centralized review, access, and notifications.
- Task 6 covers the requested admin avatars and document preview.
- Task 7 covers chat authorization and end-to-end verification.
- Staged-document metadata and its transfer use one existing relationship verification service, preventing duplicate workflow logic.
