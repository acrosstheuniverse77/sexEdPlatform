<?php

namespace Tests\Feature\Auth;

use App\Models\ParentChildAccount;
use App\Models\User;
use App\Notifications\Admin\ChildVerificationRequestSubmittedNotification;
use App\Notifications\Admin\ParentVerificationRequestSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentChildVerificationResubmissionTest extends TestCase
{
    public function test_legacy_parent_resubmission_post_redirects_to_guardian_verification_form(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = $this->createAdmin();

        $parent = User::factory()->create([
            'first_name' => 'Rejected',
            'last_name' => 'Parent',
            'email_verified_at' => now(),
            'is_parent_registration' => true,
            'parent_verification_status' => 'rejected',
            'parent_verification_rejection_reason' => 'Uploaded ID is blurred.',
            'parent_id_document_path' => 'parent-verifications/original/rejected-parent-id.pdf',
            'parent_verification_reviewed_by' => $admin->id,
            'parent_verification_reviewed_at' => now()->subDay(),
            'parent_verification_approved_at' => null,
        ]);
        $parent->assignRole('learner');

        Storage::disk('public')->put('parent-verifications/original/rejected-parent-id.pdf', 'old-content');

        $this->actingAs($parent)
            ->post(route('parent.verification.resubmit'), [
                'government_id' => UploadedFile::fake()->create('corrected-government-id.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('guardian.verification.create'));

        $parent->refresh();

        $this->assertSame('rejected', $parent->parent_verification_status);
        $this->assertSame('Uploaded ID is blurred.', $parent->parent_verification_rejection_reason);
        $this->assertSame($admin->id, $parent->parent_verification_reviewed_by);
        $this->assertNotNull($parent->parent_verification_reviewed_at);
        $this->assertNull($parent->parent_verification_approved_at);
        $this->assertSame('parent-verifications/original/rejected-parent-id.pdf', $parent->parent_id_document_path);

        Storage::disk('public')->assertExists('parent-verifications/original/rejected-parent-id.pdf');
        Notification::assertNotSentTo($admin, ParentVerificationRequestSubmittedNotification::class);
    }

    public function test_rejected_child_can_be_resubmitted_by_owning_parent_and_move_back_to_pending(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = $this->createAdmin();
        $parent = $this->createApprovedParentWithProfile();

        $child = User::factory()->create([
            'first_name' => 'Rejected',
            'last_name' => 'Child',
            'email_verified_at' => now(),
        ]);
        $child->assignRole('learner');

        $verification = ParentChildAccount::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'rejected',
            'verification_document_path' => 'child-verifications/original/rejected-child-doc.pdf',
            'verification_rejection_reason' => 'PSA document is cropped.',
            'verification_reviewed_by' => $admin->id,
            'verification_reviewed_at' => now()->subDay(),
            'verification_approved_at' => null,
            'relationship_verified_at' => null,
        ]);

        Storage::disk('public')->put('child-verifications/original/rejected-child-doc.pdf', 'old-content');

        $this->actingAs($parent)
            ->post(route('parent.children.verification.resubmit', $child), [
                'verification_document' => UploadedFile::fake()->create('corrected-birth-certificate.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('parent.children.index'))
            ->assertSessionHas('success');

        $verification->refresh();

        $this->assertSame('pending', $verification->verification_status);
        $this->assertSame('PSA document is cropped.', $verification->verification_rejection_reason);
        $this->assertNull($verification->verification_reviewed_by);
        $this->assertNull($verification->verification_reviewed_at);
        $this->assertNull($verification->verification_approved_at);
        $this->assertNull($verification->relationship_verified_at);
        $this->assertNotSame('child-verifications/original/rejected-child-doc.pdf', $verification->verification_document_path);
        $this->assertStringStartsWith('child-verifications/' . $parent->id . '/', (string) $verification->verification_document_path);

        Storage::disk('public')->assertMissing('child-verifications/original/rejected-child-doc.pdf');
        Storage::disk('public')->assertExists((string) $verification->verification_document_path);

        Notification::assertSentTo(
            [$admin],
            ChildVerificationRequestSubmittedNotification::class,
            fn (ChildVerificationRequestSubmittedNotification $notification) => data_get($notification->toDatabase($admin), 'status') === 'pending'
                && (int) data_get($notification->toDatabase($admin), 'parent_child_account_id') === $verification->id
        );
    }

    public function test_parent_resubmission_route_only_updates_authenticated_parent_record(): void
    {
        Storage::fake('public');

        $firstParent = User::factory()->create([
            'email_verified_at' => now(),
            'is_parent_registration' => true,
            'parent_verification_status' => 'rejected',
            'parent_id_document_path' => 'parent-verifications/original/first-parent-id.pdf',
            'parent_verification_rejection_reason' => 'Old reason first parent.',
        ]);
        $firstParent->assignRole('learner');

        $secondParent = User::factory()->create([
            'email_verified_at' => now(),
            'is_parent_registration' => true,
            'parent_verification_status' => 'rejected',
            'parent_id_document_path' => 'parent-verifications/original/second-parent-id.pdf',
            'parent_verification_rejection_reason' => 'Old reason second parent.',
        ]);
        $secondParent->assignRole('learner');

        Storage::disk('public')->put('parent-verifications/original/first-parent-id.pdf', 'old-first');
        Storage::disk('public')->put('parent-verifications/original/second-parent-id.pdf', 'old-second');

        $this->actingAs($secondParent)
            ->post(route('parent.verification.resubmit'), [
                'government_id' => UploadedFile::fake()->create('second-parent-corrected-id.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('guardian.verification.create'));

        $firstParent->refresh();
        $secondParent->refresh();

        $this->assertSame('rejected', $firstParent->parent_verification_status);
        $this->assertSame('parent-verifications/original/first-parent-id.pdf', $firstParent->parent_id_document_path);

        $this->assertSame('rejected', $secondParent->parent_verification_status);
        $this->assertSame('parent-verifications/original/second-parent-id.pdf', $secondParent->parent_id_document_path);
    }

    public function test_parent_cannot_resubmit_child_verification_for_non_owned_link(): void
    {
        Storage::fake('public');

        $owningParent = $this->createApprovedParentWithProfile();
        $otherParent = $this->createApprovedParentWithProfile('otherapprovedparent');

        $child = User::factory()->create([
            'first_name' => 'Linked',
            'last_name' => 'Child',
            'email_verified_at' => now(),
        ]);
        $child->assignRole('learner');

        $verification = ParentChildAccount::create([
            'parent_user_id' => $owningParent->id,
            'child_user_id' => $child->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'rejected',
            'verification_document_path' => 'child-verifications/original/non-owned-child-doc.pdf',
            'verification_rejection_reason' => 'Needs a clearer copy.',
        ]);

        Storage::disk('public')->put('child-verifications/original/non-owned-child-doc.pdf', 'old-doc');

        $this->actingAs($otherParent)
            ->post(route('parent.children.verification.resubmit', $child), [
                'verification_document' => UploadedFile::fake()->create('attempted-unauthorized-resubmission.pdf', 120, 'application/pdf'),
            ])
            ->assertStatus(403);

        $verification->refresh();

        $this->assertSame('rejected', $verification->verification_status);
        $this->assertSame('child-verifications/original/non-owned-child-doc.pdf', $verification->verification_document_path);
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

    private function createApprovedParentWithProfile(string $usernamePrefix = 'approvedparent'): User
    {
        $this->seedLocationRows();

        $parent = User::factory()->create([
            'first_name' => 'Approved',
            'last_name' => 'Parent',
            'birthdate' => now()->subYears(30)->toDateString(),
            'email_verified_at' => now(),
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
        ]);
        $parent->assignRole('learner');

        $parent->learnerProfile()->create([
            'username' => $usernamePrefix . $parent->id,
            'birthdate' => now()->subYears(30)->toDateString(),
            'gender' => 'female',
            'city_code' => '402101000',
            'barangay_code' => '402101001',
            'barangay' => 'Sample Barangay',
            'province_code' => '402100000',
            'is_parent_account' => true,
            'requires_parental_consent' => false,
        ]);

        return $parent;
    }

    private function seedLocationRows(): void
    {
        DB::table('provinces')->updateOrInsert(
            ['code' => '402100000'],
            [
                'name' => 'Sample Province',
                'region_code' => '040000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('cities')->updateOrInsert(
            ['code' => '402101000'],
            [
                'name' => 'Sample City',
                'region_code' => '040000000',
                'province_code' => '402100000',
                'is_city' => true,
                'city_class' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('barangays')->updateOrInsert(
            ['code' => '402101001'],
            [
                'name' => 'Sample Barangay',
                'city_code' => '402101000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
