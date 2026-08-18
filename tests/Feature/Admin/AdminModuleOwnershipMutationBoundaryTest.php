<?php

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminModuleOwnershipMutationBoundaryTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_admin_cannot_mutate_instructor_owned_module(): void
    {
        $admin = $this->createUser('admin');
        $instructor = $this->createUser('instructor');

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'title' => 'Instructor Owned Module',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), $this->modulePayload('Updated Instructor Module'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.modules.destroy', $module))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.modules.deactivate', $module))
            ->assertForbidden();

        $module->delete();

        $this->actingAs($admin)
            ->patch(route('admin.modules.restore', $module->id))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.modules.force-delete', $module->id))
            ->assertForbidden();
    }

    public function test_admin_can_mutate_platform_owned_module(): void
    {
        $admin = $this->createUser('admin');

        $module = Module::factory()->create([
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
            'title' => 'Platform Owned Module',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), $this->modulePayload('Platform Updated'))
            ->assertRedirect(route('admin.modules.show', $module));

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'title' => 'Platform Updated',
            'content_owner_type' => 'admin',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.modules.destroy', $module))
            ->assertRedirect(route('admin.modules.index'));

        $this->assertSoftDeleted('modules', ['id' => $module->id]);

        $this->actingAs($admin)
            ->patch(route('admin.modules.restore', $module->id))
            ->assertRedirect();

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function modulePayload(string $title): array
    {
        return [
            'title' => $title,
            'description' => 'Updated module description',
            'age_bracket' => 'teens',
            'enrollment_mode' => 'auto',
            'enrollment_limit' => 50,
            'action' => 'publish',
        ];
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
