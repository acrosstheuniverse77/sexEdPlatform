<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminModuleAuthoringTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_platform_owned_module(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), [
                'title' => 'Platform Module',
                'description' => 'Admin-owned content',
                'age_bracket' => 'teens',
                'enrollment_mode' => 'auto',
                'enrollment_limit' => 50,
                'is_published' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('modules', [
            'title' => 'Platform Module',
            'created_by' => $admin->id,
            'content_owner_type' => 'admin',
            'is_published' => true,
            'current_review_status' => 'approved',
        ]);
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
