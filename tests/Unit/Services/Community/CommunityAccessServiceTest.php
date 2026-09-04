<?php

namespace Tests\Unit\Services\Community;

use App\Models\User;
use App\Models\UserSuspension;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityFeedSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityAccessServiceTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_minor_learners_are_excluded_even_with_connector_membership(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $minor = $this->createMinorLearner(15);
        $role = $this->createCustomRole($connector, ['community.view_space', 'community.create_post']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $service = app(CommunityAccessService::class);

        $this->assertFalse($service->canUseCommunity($minor));
        $this->assertFalse($service->canViewSpace($minor, $connector));
        $this->assertFalse($service->canCreatePost($minor, $connector));
    }

    public function test_adult_connector_member_requires_connector_local_permission(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $poster = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $service = app(CommunityAccessService::class);

        $this->assertTrue($service->canViewSpace($viewer, $connector));
        $this->assertFalse($service->canCreatePost($viewer, $connector));
        $this->assertTrue($service->canCreatePost($poster, $connector));
    }

    public function test_suspended_connector_and_global_freeze_block_writes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $service = app(CommunityAccessService::class);

        $this->assertTrue($service->canCreatePost($owner, $connector));

        $connector->update(['status' => 'suspended']);
        $this->assertFalse($service->canCreatePost($owner, $connector->fresh()));

        $connector->update(['status' => 'verified']);
        app(CommunityFeedSettingsService::class)->freezeGlobal($admin, 'Safety incident.');
        $this->assertFalse($service->canCreatePost($owner, $connector->fresh()));
    }

    public function test_removed_membership_and_active_user_suspension_block_access(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $this->assertTrue(app(CommunityAccessService::class)->canCreatePost($member, $connector));

        $connector->memberships()->where('user_id', $member->id)->update([
            'status' => 'removed',
            'removed_at' => now(),
        ]);

        $this->assertFalse(app(CommunityAccessService::class)->canCreatePost($member, $connector->fresh()));

        $connector->memberships()->where('user_id', $member->id)->update([
            'status' => 'active',
            'removed_at' => null,
        ]);

        UserSuspension::query()->create([
            'user_id' => $member->id,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'appeal_status' => 'none',
        ]);

        $this->assertFalse(app(CommunityAccessService::class)->canCreatePost($member->fresh(), $connector->fresh()));
    }
}
