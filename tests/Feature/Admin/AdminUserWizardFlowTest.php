<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserWizardFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdminUser(): User
    {
        $adminRole = Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_create_page_renders_shared_wizard_steps(): void
    {
        $this->withoutVite();
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route('admin.users.create'))
            ->assertOk()

            ->assertSee('Others (Create New Role)', false)
            ->assertDontSee('Counselor', false)
            ->assertDontSee('Clinic', false)
            ->assertDontSee('Organization', false)
            ->assertSee('I confirm these changes are accurate', false)
            ->assertSee('Continue', false);
    }

    public function test_edit_page_renders_shared_wizard_steps_with_existing_user_context(): void
    {
        $this->withoutVite();
        $admin = $this->createAdminUser();
        $target = User::factory()->create([
            'role' => 'learner',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('Edit User:', false)
            ->assertSee(e($target->name), false)
            ->assertSee('Update User', false);
    }
}
