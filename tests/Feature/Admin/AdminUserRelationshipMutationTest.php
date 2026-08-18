<?php

namespace Tests\Feature\Admin;

use App\Models\ParentChildAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserRelationshipMutationTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdminUser(): User
    {
        $permissions = ['view users', 'manage user relationships'];

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

    public function test_admin_can_attach_verify_and_detach_parent_child_relationship(): void
    {
        $admin = $this->createAdminUser();
        $parent = User::factory()->create(['role' => 'learner', 'status' => 'active']);
        $child = User::factory()->create(['role' => 'learner', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.users.relationships.attach'), [
                'parent_user_id' => $parent->id,
                'child_user_id' => $child->id,
                'relationship_type' => 'aunt',
                'is_verified' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'relationship_type' => 'aunt',
            'relationship_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.relationships.verification'), [
                'parent_user_id' => $parent->id,
                'child_user_id' => $child->id,
                'is_verified' => true,
            ])
            ->assertRedirect();

        $relationship = ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->firstOrFail();

        $this->assertNotNull($relationship->relationship_verified_at);

        $this->actingAs($admin)
            ->delete(route('admin.users.relationships.detach'), [
                'parent_user_id' => $parent->id,
                'child_user_id' => $child->id,
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);
    }
}
