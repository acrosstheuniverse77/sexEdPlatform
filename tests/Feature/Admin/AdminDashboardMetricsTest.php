<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_includes_command_center_metric_blocks(): void
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
            ->assertSee('Learners', false)
            ->assertSee('Instructors', false)
            ->assertSee('Total Modules', false)
            ->assertSee('Total Revenue', false)
            ->assertSee('Learners Demographic', false)
            ->assertSee('Monthly Subscribers', false)
            ->assertSee('Moderation Queue', false)
            ->assertSee('Quick Actions', false);
    }

    public function test_admin_sidebar_hides_unimplemented_navigation_items(): void
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
            ->assertDontSee('Calendar', false)
            ->assertDontSee('Organizations', false)
            ->assertDontSee('Communication', false);
    }

    public function test_admin_sidebar_includes_moderation_shortcuts(): void
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
            ->assertSee('Instructor Applications', false)
            ->assertSee('Module Published Review', false)
            ->assertSee(route('admin.instructor-applications.index'), false)
            ->assertSee(route('admin.content-reviews.index'), false);
    }
}
