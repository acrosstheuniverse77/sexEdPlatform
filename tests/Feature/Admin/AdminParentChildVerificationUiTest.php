<?php

namespace Tests\Feature\Admin;

use App\Models\GuardianRelationshipVerificationDocument;
use App\Models\LearnerProfile;
use App\Models\ParentChildAccount;
use App\Models\User;
use Tests\TestCase;

class AdminParentChildVerificationUiTest extends TestCase
{
    public function test_parent_tab_uses_server_side_status_filtering_for_pending_records(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $pendingParent = User::factory()->create([
            'first_name' => 'Pending',
            'last_name' => 'Guardian',
            'is_parent_registration' => true,
            'parent_verification_status' => 'pending',
            'parent_id_document_path' => 'parent-verifications/temp/pending-id.pdf',
        ]);
        $pendingParent->assignRole('learner');

        $approvedParent = User::factory()->create([
            'first_name' => 'Approved',
            'last_name' => 'Guardian',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/temp/approved-id.pdf',
        ]);
        $approvedParent->assignRole('learner');

        $rejectedParent = User::factory()->create([
            'first_name' => 'Rejected',
            'last_name' => 'Guardian',
            'is_parent_registration' => true,
            'parent_verification_status' => 'rejected',
            'parent_id_document_path' => 'parent-verifications/temp/rejected-id.pdf',
        ]);
        $rejectedParent->assignRole('learner');

        $response = $this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', [
                'type' => 'parents',
                'status' => 'pending',
            ]));

        $response->assertOk()
            ->assertSee($pendingParent->full_name, false)
            ->assertDontSee($approvedParent->full_name, false)
            ->assertDontSee($rejectedParent->full_name, false);
    }

    public function test_child_tab_uses_server_side_status_filtering_for_pending_records(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $linkedParent = User::factory()->create([
            'first_name' => 'Linked',
            'last_name' => 'Parent',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/approved/linked-id.pdf',
        ]);
        $linkedParent->assignRole('learner');

        $pendingChild = User::factory()->create([
            'first_name' => 'QueuePending',
            'last_name' => 'Learner',
        ]);
        $pendingChild->assignRole('learner');

        $approvedChild = User::factory()->create([
            'first_name' => 'HiddenApproved',
            'last_name' => 'Learner',
        ]);
        $approvedChild->assignRole('learner');

        $rejectedChild = User::factory()->create([
            'first_name' => 'HiddenRejected',
            'last_name' => 'Learner',
        ]);
        $rejectedChild->assignRole('learner');

        ParentChildAccount::create([
            'parent_user_id' => $linkedParent->id,
            'child_user_id' => $pendingChild->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
            'verification_document_path' => 'child-verifications/temp/pending-doc.pdf',
        ]);

        ParentChildAccount::create([
            'parent_user_id' => $linkedParent->id,
            'child_user_id' => $approvedChild->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'approved',
            'verification_document_path' => 'child-verifications/temp/approved-doc.pdf',
        ]);

        ParentChildAccount::create([
            'parent_user_id' => $linkedParent->id,
            'child_user_id' => $rejectedChild->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'rejected',
            'verification_document_path' => 'child-verifications/temp/rejected-doc.pdf',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', [
                'type' => 'children',
                'status' => 'pending',
            ]));

        $response->assertOk()
            ->assertSee($pendingChild->full_name, false)
            ->assertDontSee($approvedChild->full_name, false)
            ->assertDontSee($rejectedChild->full_name, false);
    }

    public function test_default_verification_view_surfaces_pending_child_requests_without_notification_context(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $linkedParent = User::factory()->create([
            'first_name' => 'Visible',
            'last_name' => 'Parent',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/approved/visible-parent-id.pdf',
        ]);
        $linkedParent->assignRole('learner');

        $pendingChild = User::factory()->create([
            'first_name' => 'Visible',
            'last_name' => 'PendingChild',
        ]);
        $pendingChild->assignRole('learner');

        ParentChildAccount::create([
            'parent_user_id' => $linkedParent->id,
            'child_user_id' => $pendingChild->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
            'verification_document_path' => 'child-verifications/temp/visible-pending-doc.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.index'))
            ->assertOk()
            ->assertSee('Child Verifications', false)
            ->assertSee($pendingChild->full_name, false);
    }

    public function test_verification_preview_details_use_standardized_copy_and_hide_removed_fields(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $parentApplicant = User::factory()->create([
            'first_name' => 'Pat',
            'last_name' => 'Guardian',
            'is_parent_registration' => true,
            'parent_verification_status' => 'pending',
            'parent_id_document_path' => 'parent-verifications/temp/pat-id.pdf',
        ]);
        $parentApplicant->assignRole('learner');

        $childParent = User::factory()->create([
            'first_name' => 'Mara',
            'last_name' => 'Parent',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/approved/mara-id.pdf',
        ]);
        $childParent->assignRole('learner');

        $childApplicant = User::factory()->create([
            'first_name' => 'Nico',
            'last_name' => 'Kid',
        ]);
        $childApplicant->assignRole('learner');

        ParentChildAccount::create([
            'parent_user_id' => $childParent->id,
            'child_user_id' => $childApplicant->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
            'verification_document_path' => 'child-verifications/temp/nico-birth-cert.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.parent-verifications.index'));

        $response->assertOk()
            ->assertSee(route('admin.parent-verifications.parents.show', $parentApplicant), false)
            ->assertDontSee('Guardian Verification - '.$parentApplicant->full_name, false)
            ->assertSee('Child Verification - '.$childApplicant->full_name, false)
            ->assertSee('Verification Details', false)
            ->assertDontSee('Verification Transparency Details', false)
            ->assertDontSee('Reviewed At', false)
            ->assertDontSee('Document Type', false)
            ->assertDontSee('Guardian Document Available', false);
    }

    public function test_parent_and_child_rows_render_shared_moderation_modal_landmarks(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $parentApplicant = User::factory()->create([
            'first_name' => 'Ari',
            'last_name' => 'Parent',
            'is_parent_registration' => true,
            'parent_verification_status' => 'pending',
            'parent_id_document_path' => 'parent-verifications/temp/ari-id.pdf',
        ]);
        $parentApplicant->assignRole('learner');

        $childParent = User::factory()->create([
            'first_name' => 'Lani',
            'last_name' => 'Guardian',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/approved/lani-id.pdf',
        ]);
        $childParent->assignRole('learner');

        $childApplicant = User::factory()->create([
            'first_name' => 'Mico',
            'last_name' => 'Kid',
        ]);
        $childApplicant->assignRole('learner');

        ParentChildAccount::create([
            'parent_user_id' => $childParent->id,
            'child_user_id' => $childApplicant->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
            'verification_document_path' => 'child-verifications/temp/mico-birth-cert.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.parent-verifications.index'));

        $response->assertOk()
            ->assertSee('data-testid="verification-moderation-modal-shell"', false)
            ->assertSee('data-testid="verification-rejection-form-fields"', false);
    }

    public function test_page_uses_unified_stats_and_standardized_pagination_controls(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.parent-verifications.index'));

        $response->assertOk()
            ->assertSee('Verifications Table', false)
            ->assertSee('Pending', false)
            ->assertSee('Approved', false)
            ->assertSee('Rejected', false)
            ->assertDontSee('Pending Guardians', false)
            ->assertDontSee('Approved Guardians', false)
            ->assertDontSee('Rejected Guardians', false)
            ->assertDontSee('Pending Children', false)
            ->assertDontSee('Approved Children', false)
            ->assertDontSee('Rejected Children', false);
    }

    public function test_relationship_review_page_is_decision_oriented_with_unified_document_viewer(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $guardian = User::factory()->create([
            'name' => 'Em Fushiguro',
            'email' => 'em@example.test',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
        ]);
        $guardian->assignRole('learner');

        $dependent = User::factory()->create([
            'name' => 'Vanilove Fushiguro',
            'email' => 'vanilove@example.test',
        ]);
        $dependent->assignRole('learner');

        $relationship = ParentChildAccount::create([
            'parent_user_id' => $guardian->id,
            'child_user_id' => $dependent->id,
            'relationship_type' => 'adoptive_parent',
            'relationship_status' => 'pending',
            'relationship_verified_status' => 'under_review',
            'relationship_verification_submitted_at' => now(),
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
        ]);

        GuardianRelationshipVerificationDocument::query()->create([
            'parent_child_account_id' => $relationship->id,
            'uploaded_by_user_id' => $guardian->id,
            'document_type' => 'adoption_order',
            'disk' => 'local',
            'path' => 'guardian-relationship-verifications/'.$relationship->id.'/internal-test-image.jpg',
            'original_name' => 'internal-test-image.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 4096,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.parent-verifications.relationships.show', $relationship));

        $response->assertOk()
            ->assertSee('Verification Status', false)
            ->assertSee('Guardian + Dependent Identity', false)
            ->assertSee('Relationship', false)
            ->assertSee('Submitted Documents', false)
            ->assertSee('Verification Requirements', false)
            ->assertSee('Administrative Decision', false)
            ->assertSee('Em Fushiguro', false)
            ->assertSee('Vanilove Fushiguro', false)
            ->assertSee('Guardian verification', false)
            ->assertSee('Dependent validation', false)
            ->assertSee('Relationship verification', false)
            ->assertSee('Overall relationship', false)
            ->assertSee('Adoption Order', false)
            ->assertSee('Submitted by', false)
            ->assertSee('Front', false)
            ->assertSee('Preview', false)
            ->assertSee('Download', false)
            ->assertSee('Zoom in', false)
            ->assertSee('Zoom out', false)
            ->assertSee('Reset zoom', false)
            ->assertSee('Fit to screen', false)
            ->assertSee('Request Resubmission', false)
            ->assertDontSee('internal-test-image.jpg', false)
            ->assertDontSee('Approve Relationship', false)
            ->assertDontSee('Approve Guardian', false);
    }

    public function test_review_tables_show_profile_avatars_and_relationship_view_compares_guardian_ids(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $guardian = User::factory()->create([
            'first_name' => 'Ari',
            'last_name' => 'Guardian',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/approved/ari-front.pdf',
            'parent_id_document_back_path' => 'parent-verifications/approved/ari-back.png',
        ]);
        $guardian->assignRole('learner');
        LearnerProfile::query()->create([
            'user_id' => $guardian->id,
            'username' => 'ari_guardian_'.$guardian->id,
            'avatar_path' => 'avatars/ari-guardian.jpg',
        ]);

        $dependent = User::factory()->create([
            'first_name' => 'Dina',
            'last_name' => 'Dependent',
        ]);
        $dependent->assignRole('learner');

        $relationship = ParentChildAccount::create([
            'parent_user_id' => $guardian->id,
            'child_user_id' => $dependent->id,
            'relationship_type' => 'adoptive_parent',
            'relationship_status' => 'pending',
            'relationship_verified_status' => 'under_review',
            'relationship_verification_submitted_at' => now(),
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
        ]);

        $frontUrl = route('admin.parent-verifications.parents.document', [$guardian, 'front']);
        $backUrl = route('admin.parent-verifications.parents.document', [$guardian, 'back']);

        $parentTableMarkup = str($this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', ['type' => 'parents', 'status' => 'approved']))
            ->assertOk()
            ->getContent())
            ->after('x-show="activeType === \'parents\'"')
            ->before('x-show="activeType === \'children\'"')
            ->toString();
        $childTableMarkup = str($this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', ['type' => 'children', 'status' => 'pending']))
            ->assertOk()
            ->getContent())
            ->after('x-show="activeType === \'children\'"')
            ->before('x-show="activeType === \'relationships\'"')
            ->toString();
        $relationshipTableMarkup = str($this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', ['type' => 'relationships', 'status' => 'pending']))
            ->assertOk()
            ->getContent())
            ->after('x-show="activeType === \'relationships\'"')
            ->before('x-show="previewOpen"')
            ->toString();

        self::assertStringContainsString('>Guardian</th>', $parentTableMarkup);
        self::assertStringContainsString('alt="Ari Guardian avatar"', $parentTableMarkup);
        self::assertStringContainsString('h-9 w-9 rounded-full object-cover', $parentTableMarkup);

        self::assertStringContainsString('>Child</th>', $childTableMarkup);
        self::assertStringContainsString('aria-label="Dina Dependent avatar fallback"', $childTableMarkup);
        self::assertStringContainsString('inline-flex h-9 w-9', $childTableMarkup);

        self::assertStringContainsString('>Dependent</th>', $relationshipTableMarkup);
        self::assertStringContainsString('alt="Ari Guardian avatar"', $relationshipTableMarkup);
        self::assertStringContainsString('aria-label="Dina Dependent avatar fallback"', $relationshipTableMarkup);

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.relationships.show', $relationship))
            ->assertOk()
            ->assertSee('Guardian identity documents', false)
            ->assertSee('Front of guardian ID', false)
            ->assertSee('Back of guardian ID', false)
            ->assertSee($frontUrl, false)
            ->assertSee($backUrl, false)
            ->assertSee('<iframe', false)
            ->assertSee('alt="Back of guardian ID"', false)
            ->assertSee('Preview', false)
            ->assertSee('Download', false);
    }

    public function test_relationship_identity_document_cards_mark_missing_guardian_id_sides_not_submitted(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $guardian = User::factory()->create([
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
        ]);
        $guardian->assignRole('learner');

        $dependent = User::factory()->create();
        $dependent->assignRole('learner');

        $relationship = ParentChildAccount::create([
            'parent_user_id' => $guardian->id,
            'child_user_id' => $dependent->id,
            'relationship_type' => 'legal_guardian',
            'relationship_status' => 'pending',
            'relationship_verified_status' => 'under_review',
            'relationship_verification_submitted_at' => now(),
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.relationships.show', $relationship))
            ->assertOk()
            ->assertSee('Front of guardian ID', false)
            ->assertSee('Back of guardian ID', false)
            ->assertSee('Not submitted', false);
    }
}
