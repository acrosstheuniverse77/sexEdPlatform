<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Admin\ParentVerificationRequestSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentRegistrationUploadPersistenceTest extends TestCase
{
    public function test_parent_account_submission_waits_for_post_email_guardian_verification(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->postJson(route('parent.register.temp-upload'), [
            'government_id' => UploadedFile::fake()->create('government-id.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->post(route('parent.register.store'), $this->validParentPayload())
            ->assertRedirect(route('parent.register.account'));

        $this->post(route('parent.register.account.store'), [
            'email' => 'newparent@gmail.com',
            'password' => 'UltraSecureParent#2026A',
            'password_confirmation' => 'UltraSecureParent#2026A',
        ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $parent = User::query()->where('email', 'newparent@gmail.com')->firstOrFail();

        $this->assertNull($parent->parent_verification_status);
        $this->assertNull($parent->parent_id_document_path);
        Notification::assertNotSentTo($admin, ParentVerificationRequestSubmittedNotification::class);
    }

    public function test_parent_temp_upload_endpoint_stores_preview_ready_metadata(): void
    {
        Storage::fake('public');

        $response = $this->postJson(route('parent.register.temp-upload'), [
            'government_id' => UploadedFile::fake()->create('government-id.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'upload' => [
                    'path',
                    'original_name',
                    'mime_type',
                    'size',
                    'preview_url',
                ],
            ]);

        $this->assertNotNull(session('registration_temp_uploads.parent.government_id'));
    }

    public function test_parent_register_page_rehydrates_uploaded_preview_from_temp_session(): void
    {
        Storage::fake('public');

        $uploadResponse = $this->postJson(route('parent.register.temp-upload'), [
            'government_id' => UploadedFile::fake()->create('government-id.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $path = $uploadResponse->json('upload.path');

        $this->assertSame($path, session('registration_temp_uploads.parent.government_id.path'));

        $this->get(route('parent.register'))
            ->assertOk()
            ->assertDontSee('data-testid="parent-government-id-preview"', false)
            ->assertDontSee('government-id.pdf', false);
    }

    public function test_parent_temp_upload_remove_endpoint_clears_session_and_temp_file(): void
    {
        Storage::fake('public');

        $uploadResponse = $this->postJson(route('parent.register.temp-upload'), [
            'government_id' => UploadedFile::fake()->create('government-id.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $path = $uploadResponse->json('upload.path');

        $this->deleteJson(route('parent.register.temp-upload.remove'))
            ->assertOk()
            ->assertJson([
                'message' => 'Temporary upload removed.',
            ]);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(session('registration_temp_uploads.parent.government_id'));
    }

    public function test_parent_personal_info_submit_uses_existing_temp_upload_without_new_file(): void
    {
        Storage::fake('public');

        $uploadResponse = $this->postJson(route('parent.register.temp-upload'), [
            'government_id' => UploadedFile::fake()->create('government-id.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->post(route('parent.register.store'), $this->validParentPayload())
            ->assertRedirect(route('parent.register.account'));

        $this->assertNull(session('pending_parent_info.government_id_path'));
    }

    public function test_parent_back_navigation_preserves_uploaded_preview(): void
    {
        Storage::fake('public');

        $uploadResponse = $this->postJson(route('parent.register.temp-upload'), [
            'government_id' => UploadedFile::fake()->create('government-id.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->post(route('parent.register.store'), $this->validParentPayload())
            ->assertRedirect(route('parent.register.account'));

        $this->assertNull(session('pending_parent_info.government_id_path'));

        $this->get(route('parent.register'))
            ->assertOk()
            ->assertDontSee('data-testid="parent-government-id-preview"', false)
            ->assertDontSee('government-id.pdf', false);
    }

    private function validParentPayload(): array
    {
        return [
            'first_name' => 'Juan',
            'middle_initial' => 'D',
            'last_name' => 'Santos',
            'suffix' => '',
            'birthdate' => now()->subYears(30)->format('Y-m-d'),
        ];
    }
}
