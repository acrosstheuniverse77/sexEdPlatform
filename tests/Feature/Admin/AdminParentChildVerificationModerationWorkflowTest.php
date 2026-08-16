<?php

namespace Tests\Feature\Admin;

use App\Models\ParentChildAccount;
use App\Models\User;
use Tests\TestCase;

class AdminParentChildVerificationModerationWorkflowTest extends TestCase
{
    public function test_pending_parent_approval_endpoint_still_approves_successfully(): void
    {
        $admin = $this->createAdmin();
        $parentApplicant = $this->createParentApplicant('pending');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.parents.approve', $parentApplicant))
            ->assertOk()
            ->assertJson([
                'status' => 'approved',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $parentApplicant->id,
            'parent_verification_status' => 'approved',
        ]);
    }

    public function test_pending_child_approval_endpoint_still_approves_successfully(): void
    {
        $admin = $this->createAdmin();
        $verification = $this->createChildVerification('pending');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.children.approve', $verification))
            ->assertOk()
            ->assertJson([
                'status' => 'approved',
            ]);

        $this->assertDatabaseHas('parent_child_accounts', [
            'id' => $verification->id,
            'verification_status' => 'approved',
        ]);
    }

    public function test_invitation_relationships_are_excluded_from_child_moderation(): void
    {
        $admin = $this->createAdmin();
        $parent = User::factory()->create([
            'first_name' => 'Invitation',
            'last_name' => 'Guardian',
        ]);
        $parent->assignRole('learner');

        $child = User::factory()->create([
            'first_name' => 'Invitation',
            'last_name' => 'Dependent',
        ]);
        $child->assignRole('learner');

        $relationship = ParentChildAccount::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'relationship_type' => 'legal_guardian',
            'relationship_status' => 'pending',
            'relationship_verified_status' => 'under_review',
            'relationship_verification_submitted_at' => now(),
            'verification_status' => 'pending',
            'verification_document_path' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', [
                'type' => 'children',
                'status' => 'pending',
            ]));

        $response->assertOk();
        $this->assertFalse($response->viewData('childApplications')->contains('id', $relationship->id));
        $this->assertTrue($response->viewData('relationshipApplications')->contains('id', $relationship->id));

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.children.approve', $relationship))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Invitation relationships must be reviewed through the relationship verification queue.',
            ]);

        $this->assertDatabaseHas('parent_child_accounts', [
            'id' => $relationship->id,
            'verification_status' => 'pending',
            'relationship_verified_at' => null,
        ]);
    }

    public function test_relationship_approval_notifies_guardian_and_dependent(): void
    {
        $admin = $this->createAdmin();
        $relationship = $this->createRelationshipVerification('under_review');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.relationships.approve', $relationship))
            ->assertOk()
            ->assertJson([
                'status' => 'approved',
            ]);

        $guardianNotification = $relationship->parent->fresh()->notifications()
            ->where('data->type', 'guardian_relationship_verification_approved')
            ->latest()
            ->first();
        $dependentNotification = $relationship->child->fresh()->notifications()
            ->where('data->type', 'guardian_relationship_verification_approved')
            ->latest()
            ->first();

        $this->assertNotNull($guardianNotification);
        $this->assertSame(route('parent.relationship-verifications.show', $relationship), data_get($guardianNotification->data, 'action_url'));
        $this->assertNotNull($dependentNotification);
        $this->assertSame(route('learner.parent.index'), data_get($dependentNotification->data, 'action_url'));
    }

    public function test_non_pending_approval_returns_conflict_as_before(): void
    {
        $admin = $this->createAdmin();
        $approvedParent = $this->createParentApplicant('approved');
        $approvedChildVerification = $this->createChildVerification('approved');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.parents.approve', $approvedParent))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Decision already finalized. Only pending records can be moderated.',
            ]);

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.children.approve', $approvedChildVerification))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Decision already finalized. Only pending records can be moderated.',
            ]);
    }

    public function test_guardian_index_links_to_details_instead_of_review_modal(): void
    {
        $admin = $this->createAdmin();
        $parentApplicant = $this->createParentApplicant('pending');
        $this->createChildVerification('pending');

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.index', ['type' => 'parents']))
            ->assertOk()
            ->assertSee(route('admin.parent-verifications.parents.show', $parentApplicant), false)
            ->assertSee('View Details', false)
            ->assertDontSee('Guardian Verification - '.$parentApplicant->full_name, false);
    }

    public function test_guardian_details_page_uses_approval_and_rejection_confirmation_modals(): void
    {
        $admin = $this->createAdmin();
        $parentApplicant = $this->createParentApplicant('pending');

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.parents.show', $parentApplicant))
            ->assertOk()
            ->assertSee('data-testid="guardian-approval-confirm-modal"', false)
            ->assertSee('data-testid="guardian-rejection-confirm-modal"', false)
            ->assertSee('Confirm Guardian Approval', false)
            ->assertSee('Confirm Guardian Rejection', false)
            ->assertSee('Identity matches submitted account details', false)
            ->assertSee('Invalid Government ID', false)
            ->assertSee('Cancel', false);
    }

    public function test_reject_parent_with_others_requires_meaningful_custom_reason_content(): void
    {
        $admin = $this->createAdmin();
        $parentApplicant = $this->createParentApplicant('pending');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.parents.reject', $parentApplicant), [
                'reason_code' => 'others',
                'custom_reason' => '<p><br></p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_reason']);
    }

    public function test_reject_child_with_others_accepts_non_empty_rich_text_content(): void
    {
        $admin = $this->createAdmin();
        $verification = $this->createChildVerification('pending');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.children.reject', $verification), [
                'reason_code' => 'others',
                'custom_reason' => '<p>Uploaded file is unreadable and cropped.</p>',
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'rejected',
            ]);
    }

    public function test_rejection_reason_no_longer_appends_warning_suffix_when_warning_flag_is_present(): void
    {
        $admin = $this->createAdmin();
        $parentApplicant = $this->createParentApplicant('pending');

        $this->actingAs($admin)
            ->postJson(route('admin.parent-verifications.parents.reject', $parentApplicant), [
                'reason_code' => 'inaccurate_information',
                'issue_warning' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $parentApplicant->id,
            'parent_verification_rejection_reason' => "Inaccurate or misleading information\n\nAdministrative warning issued to account holder.",
        ]);
    }

    public function test_rejection_modal_uses_rich_text_editor_and_hides_warning_checkbox(): void
    {
        $admin = $this->createAdmin();
        $this->createParentApplicant('pending');
        $this->createChildVerification('pending');

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.index'))
            ->assertOk()
            ->assertSee('js-guardian-child-moderation-editor', false)
            ->assertSee('build/tinymce/tinymce.min.js', false)
            ->assertDontSee('Issue warning to account holder', false);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function createParentApplicant(string $status): User
    {
        $parent = User::factory()->create([
            'first_name' => 'Parent',
            'last_name' => 'Applicant',
            'is_parent_registration' => true,
            'parent_verification_status' => $status,
            'parent_id_document_path' => 'parent-verifications/temp/parent-id.pdf',
        ]);
        $parent->assignRole('learner');

        return $parent;
    }

    private function createChildVerification(string $status): ParentChildAccount
    {
        $parent = User::factory()->create([
            'first_name' => 'Linked',
            'last_name' => 'Parent',
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'parent_id_document_path' => 'parent-verifications/approved/linked-parent-id.pdf',
        ]);
        $parent->assignRole('learner');

        $child = User::factory()->create([
            'first_name' => 'Linked',
            'last_name' => 'Child',
        ]);
        $child->assignRole('learner');

        return ParentChildAccount::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => $status,
            'verification_document_path' => 'child-verifications/temp/linked-child-doc.pdf',
        ]);
    }

    private function createRelationshipVerification(string $status): ParentChildAccount
    {
        $relationship = $this->createChildVerification('pending');

        $relationship->update([
            'relationship_type' => 'legal_guardian',
            'relationship_status' => 'pending',
            'relationship_verified_status' => $status,
        ]);

        return $relationship;
    }
}
