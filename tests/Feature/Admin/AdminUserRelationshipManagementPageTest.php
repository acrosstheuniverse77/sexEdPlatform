<?php

namespace Tests\Feature\Admin;

use App\Models\ParentChildAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserRelationshipManagementPageTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdminUser(): User
    {
        $permissions = ['view users', 'manage user relationships', 'access chat'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions($permissions);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_open_relationship_management_page(): void
    {
        $this->withoutVite();

        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.users.relationships.index'))
            ->assertOk()
            ->assertSee('Guardian-Dependent Relationships', false)
            ->assertSee('Attach New Relationship', false)
            ->assertSee('Existing Relationships', false);
    }

    public function test_admin_sidebar_includes_relationship_management_shortcut(): void
    {
        $this->withoutVite();

        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('User Relationships', false)
            ->assertSee(route('admin.users.relationships.index'), false);
    }

    public function test_user_profile_relationship_panel_hides_management_shortcut(): void
    {
        $this->withoutVite();

        $admin = $this->createAdminUser();
        $target = User::factory()->create(['role' => 'learner', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertDontSee('data-testid="admin-users-relationships-link"', false)
            ->assertDontSee('Manage Relationships', false);
    }

    public function test_relationship_management_page_filters_verified_relationships(): void
    {
        $admin = $this->createAdminUser();

        $verifiedParent = User::factory()->create(['name' => 'Verified Parent']);
        $verifiedChild = User::factory()->create(['name' => 'Verified Child', 'role' => 'learner']);
        $unverifiedParent = User::factory()->create(['name' => 'Unverified Parent']);
        $unverifiedChild = User::factory()->create(['name' => 'Unverified Child', 'role' => 'learner']);

        ParentChildAccount::query()->create([
            'parent_user_id' => $verifiedParent->id,
            'child_user_id' => $verifiedChild->id,
            'relationship_verified_at' => now(),
        ]);

        ParentChildAccount::query()->create([
            'parent_user_id' => $unverifiedParent->id,
            'child_user_id' => $unverifiedChild->id,
            'relationship_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.relationships.index', ['verification' => 'verified']))
            ->assertOk()
            ->assertSee('name="parent_user_id" value="'.$verifiedParent->id.'"', false)
            ->assertDontSee('name="parent_user_id" value="'.$unverifiedParent->id.'"', false);
    }
}
