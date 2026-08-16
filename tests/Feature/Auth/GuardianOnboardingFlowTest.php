<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardianOnboardingFlowTest extends TestCase
{
    public function test_approved_guardian_without_onboarding_is_redirected_to_onboarding(): void
    {
        $guardian = $this->approvedGuardian();

        $this->actingAs($guardian)
            ->get(route('parent.children.index'))
            ->assertRedirect(route('guardian.onboarding.show'));

        $this->actingAs($guardian)
            ->get(route('guardian.onboarding.show'))
            ->assertOk()
            ->assertSee('Welcome to Conscious Connections', false)
            ->assertSee('data-testid="guardian-onboarding-stepper"', false);
    }

    public function test_approved_guardian_cannot_use_legacy_profile_completion_page(): void
    {
        $guardian = $this->approvedGuardian();

        $this->actingAs($guardian)
            ->get(route('profile.complete'))
            ->assertRedirect(route('guardian.onboarding.show'));
    }

    public function test_guardian_completes_onboarding_and_explores_dashboard(): void
    {
        Storage::fake('public');
        $this->seedLocationRows();

        $guardian = $this->approvedGuardian();

        $this->actingAs($guardian)
            ->post(route('guardian.onboarding.complete'), [
                'username' => 'guardian_setup',
                'city_code' => '402101000',
                'barangay_code' => '402101001',
                'bio' => 'Here to guide my family through safe learning.',
                'avatar' => UploadedFile::fake()->create('guardian.png', 100, 'image/png'),
                'next_action' => 'dashboard',
            ])
            ->assertRedirect(route('learner.dashboard'))
            ->assertSessionHas('success');

        $guardian->refresh();

        $this->assertSame('completed', $guardian->guardian_onboarding_status);
        $this->assertNotNull($guardian->guardian_onboarding_completed_at);
        $this->assertTrue($guardian->hasCompletedProfile());
        $this->assertSame('guardian_setup', $guardian->learnerProfile->username);
        $this->assertSame('Here to guide my family through safe learning.', $guardian->learnerProfile->bio);
        $this->assertNotNull($guardian->learnerProfile->avatar_path);
        Storage::disk('public')->assertExists($guardian->learnerProfile->avatar_path);
    }

    public function test_guardian_can_finish_onboarding_by_creating_dependent(): void
    {
        $this->seedLocationRows();

        $guardian = $this->approvedGuardian();

        $this->actingAs($guardian)
            ->post(route('guardian.onboarding.complete'), [
                'username' => 'guardian_dependent',
                'city_code' => '402101000',
                'barangay_code' => '402101001',
                'bio' => '',
                'next_action' => 'dependent',
            ])
            ->assertRedirect(route('parent.create-child'));

        $this->assertSame('completed', $guardian->refresh()->guardian_onboarding_status);
    }

    public function test_admin_can_see_and_reset_guardian_onboarding_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $guardian = $this->approvedGuardian([
            'guardian_onboarding_status' => 'completed',
            'guardian_onboarding_started_at' => now()->subHour(),
            'guardian_onboarding_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.parent-verifications.parents.show', $guardian))
            ->assertOk()
            ->assertSee('Guardian Onboarding', false)
            ->assertSee('Completed', false);

        $this->actingAs($admin)
            ->post(route('admin.parent-verifications.parents.reset-onboarding', $guardian))
            ->assertRedirect();

        $guardian->refresh();

        $this->assertSame('not_started', $guardian->guardian_onboarding_status);
        $this->assertNull($guardian->guardian_onboarding_started_at);
        $this->assertNull($guardian->guardian_onboarding_completed_at);
    }

    private function approvedGuardian(array $overrides = []): User
    {
        $guardian = User::factory()->create(array_merge([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
            'is_parent_registration' => true,
            'email_verified_at' => now(),
            'parent_verification_status' => 'approved',
            'guardian_onboarding_status' => 'not_started',
        ], $overrides));
        $guardian->assignRole('learner');

        return $guardian;
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
