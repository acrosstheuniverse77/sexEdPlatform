<?php

namespace Tests\Feature\Connectors;

use App\Models\User;
use Tests\DatabaseTestCase;

class ConnectorMemberDirectoryAccessTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;

    public function test_default_connector_member_sees_community_hub_nav_without_management_actions(): void
    {
        $owner = User::factory()->create([
            'name' => 'Connector Owner',
            'role' => 'learner',
            'birthdate' => now()->subYears(35)->toDateString(),
        ]);
        $owner->assignRole('learner');

        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createCompletedLearner([
            'birthdate' => now()->subYears(24)->toDateString(),
            'age' => 24,
            'account_type' => User::ACCOUNT_TYPE_LEARNER_ADULT,
        ]);
        $role = app(\App\Services\Connectors\ConnectorRoleService::class)->defaultMemberRole($connector);

        $connector->memberships()->create([
            'user_id' => $member->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('connector.members.index', $connector))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertDontSee('Roles & Permissions')
            ->assertDontSee('Members Management')
            ->assertDontSee('Invite Member');
    }

    public function test_regular_member_only_sees_member_directory_without_management_actions(): void
    {
        $owner = User::factory()->create([
            'name' => 'Connector Owner',
            'role' => 'learner',
            'birthdate' => now()->subYears(35)->toDateString(),
        ]);
        $owner->assignRole('learner');

        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($member)
            ->get(route('connector.members.index', $connector))
            ->assertOk()
            ->assertSee('Members Directory')
            ->assertSee($owner->name)
            ->assertSee($member->name)
            ->assertDontSee('Members Management')
            ->assertDontSee('Invite Member')
            ->assertDontSee('Manage Roles')
            ->assertDontSee('Recommended access levels')
            ->assertDontSee('Pending Invitations')
            ->assertDontSee('Membership Requests')
            ->assertDontSee('Edit role');
    }

    public function test_member_manager_keeps_management_surface(): void
    {
        $owner = User::factory()->create([
            'name' => 'Connector Owner',
            'role' => 'learner',
            'birthdate' => now()->subYears(35)->toDateString(),
        ]);
        $owner->assignRole('learner');

        $connector = $this->createVerifiedConnector($owner);
        $manager = $this->createAdultConnectorMember($connector, ['connector.manage_members', 'connector.manage_roles']);

        $this->actingAs($manager)
            ->get(route('connector.members.index', $connector))
            ->assertOk()
            ->assertSee('Members Management')
            ->assertSee('Invite Member')
            ->assertSee('Pending Invitations')
            ->assertSee('Membership Requests')
            ->assertSee('Edit role');
    }
}
