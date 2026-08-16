<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Admin\ParentVerificationRequestSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardianIdentityVerificationFlowTest extends TestCase
{
    public function test_email_verified_guardian_is_sent_to_identity_verification_before_profile_completion(): void
    {
        $guardian = User::factory()->create([
            'role' => 'learner',
            'is_parent_registration' => true,
            'email_verified_at' => now(),
            'parent_verification_status' => null,
        ]);
        $guardian->assignRole('learner');

        $this->actingAs($guardian)
            ->get(route('verification.notice'))
            ->assertRedirect(route('guardian.verification.create'));
    }

    public function test_guardian_verification_form_has_document_preview_controls(): void
    {
        $guardian = User::factory()->create([
            'role' => 'learner',
            'is_parent_registration' => true,
            'email_verified_at' => now(),
            'parent_verification_status' => null,
        ]);
        $guardian->assignRole('learner');

        $this->actingAs($guardian)
            ->get(route('guardian.verification.create'))
            ->assertOk()
            ->assertSee('data-testid="guardian-id-front-preview"', false)
            ->assertSee('data-testid="guardian-id-back-preview"', false)
            ->assertSee('removeDocument', false);
    }

    public function test_guardian_submits_private_identity_documents_and_admins_are_notified(): void
    {
        Storage::fake('local');
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $guardian = User::factory()->create([
            'role' => 'learner',
            'is_parent_registration' => true,
            'email_verified_at' => now(),
            'parent_verification_status' => null,
        ]);
        $guardian->assignRole('learner');

        $this->actingAs($guardian)
            ->post(route('guardian.verification.store'), [
                'government_id_type' => 'passport',
                'government_id_front' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'),
                'government_id_back' => UploadedFile::fake()->create('back.webp', 100, 'image/webp'),
                'confirm_submission' => '1',
            ])
            ->assertRedirect(route('guardian.verification.status'));

        $guardian->refresh();

        $this->assertSame('pending', $guardian->parent_verification_status);
        $this->assertSame('passport', $guardian->parent_id_type);
        $this->assertNotNull($guardian->parent_verification_submitted_at);
        $this->assertStringStartsWith('guardian-verifications/'.$guardian->id.'/', (string) $guardian->parent_id_document_path);
        $this->assertStringStartsWith('guardian-verifications/'.$guardian->id.'/', (string) $guardian->parent_id_document_back_path);
        Storage::disk('local')->assertExists((string) $guardian->parent_id_document_path);
        Storage::disk('local')->assertExists((string) $guardian->parent_id_document_back_path);

        Notification::assertSentTo($admin, ParentVerificationRequestSubmittedNotification::class);
    }

    public function test_pending_guardian_cannot_open_guardian_features(): void
    {
        $guardian = User::factory()->create([
            'role' => 'learner',
            'is_parent_registration' => true,
            'email_verified_at' => now(),
            'parent_verification_status' => 'pending',
        ]);
        $guardian->assignRole('learner');

        $this->actingAs($guardian)
            ->get(route('parent.children.index'))
            ->assertRedirect(route('guardian.verification.status'));

        $this->actingAs($guardian)
            ->get(route('chat.page'))
            ->assertRedirect(route('guardian.verification.status'));
    }
}
