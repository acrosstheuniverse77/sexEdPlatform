<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\ParentVerificationApprovedNotification;
use App\Notifications\ParentVerificationRejectedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardianIdentityVerificationAdminTest extends TestCase
{
    public function test_admin_reviews_private_guardian_identity_details_and_approves(): void
    {
        Storage::fake('local');
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $guardian = $this->pendingGuardian();
        Storage::disk('local')->put($guardian->parent_id_document_path, 'front-image');

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.parents.show', $guardian))
            ->assertOk()
            ->assertSee('Guardian Identity Review', false)
            ->assertSee('Passport', false)
            ->assertSee(route('admin.parent-verifications.parents.document', [$guardian, 'front']), false);

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.parents.document', [$guardian, 'front']))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($admin)
            ->post(route('admin.parent-verifications.parents.approve', $guardian))
            ->assertRedirect();

        $guardian->refresh();
        $this->assertSame('approved', $guardian->parent_verification_status);
        Notification::assertSentTo($guardian, ParentVerificationApprovedNotification::class);
    }

    public function test_admin_rejects_with_builtin_reason_and_guardian_can_resubmit(): void
    {
        Storage::fake('local');
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $guardian = $this->pendingGuardian();
        Storage::disk('local')->put($guardian->parent_id_document_path, 'front-image');

        $this->actingAs($admin)
            ->post(route('admin.parent-verifications.parents.reject', $guardian), [
                'reason_code' => 'blurry_document',
            ])
            ->assertRedirect();

        $guardian->refresh();
        $this->assertSame('rejected', $guardian->parent_verification_status);
        $this->assertSame('Blurry document', $guardian->parent_verification_rejection_reason);
        Notification::assertSentTo($guardian, ParentVerificationRejectedNotification::class);
    }

    private function pendingGuardian(): User
    {
        $guardian = User::factory()->create([
            'role' => 'learner',
            'is_parent_registration' => true,
            'email_verified_at' => now(),
            'parent_verification_status' => 'pending',
            'parent_id_type' => 'passport',
            'parent_id_document_path' => 'guardian-verifications/999/front.jpg',
            'parent_verification_submitted_at' => now(),
        ]);
        $guardian->assignRole('learner');

        return $guardian;
    }
}
