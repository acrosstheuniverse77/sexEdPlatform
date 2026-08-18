<?php

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminDoesNotEnterInstructorSubmissionQueueTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_admin_created_module_does_not_create_review_request(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('admin.modules.store'), [
                'title' => 'Admin Owned Module',
                'description' => 'Platform module bypasses instructor review queue.',
                'age_bracket' => 'teens',
                'enrollment_mode' => 'auto',
                'enrollment_limit' => 50,
                'action' => 'publish',
            ])
            ->assertRedirect();

        $module = Module::query()->where('title', 'Admin Owned Module')->firstOrFail();

        $this->assertSame('admin', (string) $module->content_owner_type);
        $this->assertTrue((bool) $module->is_published);
        $this->assertDatabaseCount('module_review_requests', 0);
        $this->assertDatabaseMissing('module_review_requests', [
            'module_id' => $module->id,
        ]);
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
}
