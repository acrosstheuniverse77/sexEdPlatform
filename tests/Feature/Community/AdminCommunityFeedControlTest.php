<?php

namespace Tests\Feature\Community;

use App\Models\CommunityFeedSetting;
use App\Models\User;
use App\Services\Community\CommunityFeedSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class AdminCommunityFeedControlTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_admin_can_freeze_and_unfreeze_community_feed_from_routes(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $freeze = $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.community.freeze'), ['reason' => 'Safety incident review.']);

        $freeze->assertRedirect(route('admin.dashboard'));
        $this->assertTrue((bool) data_get(CommunityFeedSetting::query()->first()?->settings, 'frozen'));

        $unfreeze = $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.community.unfreeze'));

        $unfreeze->assertRedirect(route('admin.dashboard'));
        $this->assertFalse((bool) data_get(CommunityFeedSetting::query()->first()?->settings, 'frozen'));
    }

    public function test_global_freeze_blocks_connector_post_route(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(34)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        app(CommunityFeedSettingsService::class)->freezeGlobal($admin, 'Safety incident review.');

        $response = $this->actingAs($member)->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'title' => 'Blocked announcement',
            'body' => 'This should not be accepted while frozen.',
            'resource_url' => null,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('community_posts', ['title' => 'Blocked announcement']);
    }
}
