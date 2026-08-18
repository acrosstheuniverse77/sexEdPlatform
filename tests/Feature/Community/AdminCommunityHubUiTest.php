<?php

namespace Tests\Feature\Community;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;

class AdminCommunityHubUiTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public function test_admin_hub_index_matches_moderation_workspace_language(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('Platform moderation')
            ->assertSee('Pending')
            ->assertSee('Reported')
            ->assertSee('Escalated')
            ->assertSee('Global safety controls')
            ->assertDontSee('Trending')
            ->assertDontSee('Followers');
    }

    public function test_admin_settings_show_emergency_freeze_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.settings'))
            ->assertOk()
            ->assertSee('Emergency Freeze')
            ->assertSee('Read-only')
            ->assertSee('Hidden');
    }
}
