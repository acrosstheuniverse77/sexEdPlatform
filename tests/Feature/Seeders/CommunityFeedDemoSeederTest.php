<?php

namespace Tests\Feature\Seeders;

use App\Models\CommunityPost;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\CommunityFeedDemoSeeder;
use Tests\TestCase;

class CommunityFeedDemoSeederTest extends TestCase
{
    public function test_it_seeds_two_community_feed_demo_accounts_and_connector_workspace(): void
    {
        $this->seed(CommunityFeedDemoSeeder::class);

        $admin = User::query()->where('email', 'community.admin@test.local')->first();
        $moderator = User::query()->where('email', 'community.moderator@test.local')->first();
        $connector = Connector::query()->where('slug', 'community-feed-demo-connector')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($moderator);
        $this->assertNotNull($connector);

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($moderator->hasRole('learner'));
        $this->assertSame(User::ACCOUNT_TYPE_LEARNER_ADULT, $moderator->account_type);
        $this->assertSame('verified', $connector->status);

        $membership = $connector->memberships()
            ->with('role.permissions')
            ->where('user_id', $moderator->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership);
        $this->assertContains('community.view_space', $membership->role->permissionKeys());
        $this->assertContains('community.create_post', $membership->role->permissionKeys());
        $this->assertContains('community.approve_posts', $membership->role->permissionKeys());
        $this->assertContains('connector.manage_members', $membership->role->permissionKeys());
        $this->assertContains('connector.manage_roles', $membership->role->permissionKeys());
        $this->assertGreaterThanOrEqual(2, CommunityPost::query()->where('connector_id', $connector->id)->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(CommunityFeedDemoSeeder::class);
        $this->seed(CommunityFeedDemoSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'community.admin@test.local')->count());
        $this->assertSame(1, User::query()->where('email', 'community.moderator@test.local')->count());
        $this->assertSame(1, Connector::query()->where('slug', 'community-feed-demo-connector')->count());
    }
}
