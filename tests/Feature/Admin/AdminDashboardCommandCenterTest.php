<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDashboardCommandCenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_renders_command_center_sections(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Learners Demographic', false)
            ->assertSee('Monthly Subscribers', false)
            ->assertSee('Moderation Queue', false)
            ->assertSee('Quick Actions', false)
            ->assertSee('Recent System Activity', false)
            ->assertSee('Instructor Applications', false)
            ->assertSee('Manage Modules', false);
    }
}
