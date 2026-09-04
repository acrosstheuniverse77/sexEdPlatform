<?php

namespace Database\Seeders;

use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPostType;
use App\Models\CommunityPost;
use App\Models\CommunityPostVersion;
use App\Models\CommunitySpace;
use App\Models\Connector;
use App\Models\ConnectorRole;
use App\Models\LearnerProfile;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Connectors\ConnectorRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CommunityFeedDemoSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        $this->ensurePrerequisites();

        $admin = $this->upsertAdmin();
        $moderator = $this->upsertModerator();
        $connector = $this->upsertConnector($moderator, $admin);
        $role = $this->upsertCommunityModeratorRole($connector);

        $connector->memberships()->updateOrCreate(
            ['user_id' => $moderator->id],
            [
                'connector_role_id' => $role->id,
                'status' => 'active',
                'accepted_at' => now(),
                'removed_at' => null,
            ],
        );

        $space = CommunitySpace::query()->updateOrCreate(
            ['connector_id' => $connector->id],
            [
                'name' => 'Community Feed Demo',
                'status' => 'active',
                'settings' => [
                    'seeded_by' => self::class,
                    'description' => 'Adult-facing connector feed for local Community Feed testing.',
                    'visibility' => 'connector_members',
                ],
            ],
        );

        $this->upsertPost(
            space: $space,
            connector: $connector,
            author: $moderator,
            title: 'Welcome to the adult community feed',
            body: 'This connector feed is for adult announcements, educational resources, and moderated questions only.',
            type: CommunityPostType::Announcement,
            status: CommunityPostStatus::Published,
        );

        $this->upsertPost(
            space: $space,
            connector: $connector,
            author: $moderator,
            title: 'Moderated question awaiting review',
            body: 'How can facilitators explain consent education in a public, age-appropriate learning setting?',
            type: CommunityPostType::ModeratedQuestion,
            status: CommunityPostStatus::PendingReview,
        );

        $moderator->refreshClassificationCache();

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('Community Feed demo accounts seeded.');
            $this->command->line('Admin: community.admin@test.local');
            $this->command->line('Connector moderator: community.moderator@test.local');
            $this->command->line('Password for both: '.self::PASSWORD);
        }
    }

    private function ensurePrerequisites(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(CavitePSGCSeeder::class);

        foreach (['admin', 'learner'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        DB::table('barangays')->where('code', '402101001')->exists() || DB::table('barangays')->insert([
            'code' => '402101001',
            'name' => 'Barangay Test',
            'city_code' => '402101000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertAdmin(): User
    {
        $admin = User::updateOrCreate(
            ['email' => 'community.admin@test.local'],
            [
                'name' => 'Community Feed Admin',
                'first_name' => 'Community',
                'last_name' => 'Admin',
                'birthdate' => now()->subYears(35)->toDateString(),
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'role' => 'admin',
                'status' => User::STATUS_ACTIVE,
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'age_bracket_cached' => 'adults',
                'verified' => true,
            ],
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        UserProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'bio' => 'Seeded platform admin for Community Feed moderation testing.',
                'birthdate' => $admin->birthdate,
                'gender' => 'prefer_not_to_say',
                'location' => 'Cavite, Philippines',
                'contact' => '09920000001',
            ],
        );

        return $admin->refresh();
    }

    private function upsertModerator(): User
    {
        $moderator = User::updateOrCreate(
            ['email' => 'community.moderator@test.local'],
            [
                'name' => 'Community Connector Moderator',
                'first_name' => 'Community',
                'last_name' => 'Moderator',
                'birthdate' => now()->subYears(30)->toDateString(),
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'role' => 'learner',
                'status' => User::STATUS_ACTIVE,
                'account_type' => User::ACCOUNT_TYPE_LEARNER_ADULT,
                'age_bracket_cached' => 'adults',
                'verified' => true,
            ],
        );

        if (! $moderator->hasRole('learner')) {
            $moderator->assignRole('learner');
        }

        LearnerProfile::updateOrCreate(
            ['user_id' => $moderator->id],
            [
                'username' => 'community_moderator_demo',
                'birthdate' => $moderator->birthdate,
                'gender' => 'prefer_not_to_say',
                'province_code' => '402100000',
                'city_code' => '402101000',
                'barangay_code' => '402101001',
                'barangay' => 'Barangay Test',
                'bio' => 'Adult connector moderator seeded for Community Feed testing.',
                'requires_parental_consent' => false,
                'is_parent_account' => false,
            ],
        );

        return $moderator->refresh();
    }

    private function upsertConnector(User $moderator, User $admin): Connector
    {
        return Connector::query()->updateOrCreate(
            ['slug' => 'community-feed-demo-connector'],
            [
                'name' => 'Community Feed Demo Connector',
                'category' => 'ngo',
                'organization_email' => 'community.connector@test.local',
                'contact_number' => '09920000002',
                'description' => 'Verified connector workspace for Community Feed V1 local testing.',
                'website_url' => null,
                'verification_notes' => 'Seeded verified connector for local development.',
                'city_code' => '402101000',
                'barangay_code' => '402101001',
                'address_line' => '123 Community Feed Demo Street',
                'status' => 'verified',
                'created_by' => $moderator->id,
                'primary_representative_user_id' => $moderator->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'suspended_at' => null,
            ],
        );
    }

    private function upsertCommunityModeratorRole(Connector $connector): ConnectorRole
    {
        $role = $connector->roles()->updateOrCreate(
            ['name' => 'Community Moderator'],
            [
                'description' => 'Seeded role with Community Feed V1 moderation permissions.',
                'is_owner' => false,
                'is_protected' => false,
            ],
        );

        app(ConnectorRoleService::class)->syncPermissions($role, [
            'connector.manage_members',
            'connector.invite_members',
            'connector.manage_roles',
            'community.view_space',
            'community.create_post',
            'community.edit_own_post',
            'community.manage_posts',
            'community.approve_posts',
            'community.lock_threads',
            'community.manage_comments',
            'community.escalate_to_platform',
        ]);

        return $role->fresh('permissions');
    }

    private function upsertPost(
        CommunitySpace $space,
        Connector $connector,
        User $author,
        string $title,
        string $body,
        CommunityPostType $type,
        CommunityPostStatus $status,
    ): CommunityPost {
        $post = CommunityPost::query()->updateOrCreate(
            [
                'connector_id' => $connector->id,
                'title' => $title,
            ],
            [
                'community_space_id' => $space->id,
                'author_id' => $author->id,
                'post_type' => $type,
                'status' => $status,
                'body' => $body,
                'resource_url' => null,
                'prescreen_decision' => $status === CommunityPostStatus::Published ? 'allow' : 'pending_review',
                'prescreen_flags' => [],
                'submitted_at' => now(),
                'published_at' => $status === CommunityPostStatus::Published ? now() : null,
                'published_by' => $status === CommunityPostStatus::Published ? $author->id : null,
            ],
        );

        CommunityPostVersion::query()->updateOrCreate(
            [
                'community_post_id' => $post->id,
                'version_number' => 1,
            ],
            [
                'edited_by' => $author->id,
                'title' => $post->title,
                'body' => $post->body,
                'resource_url' => $post->resource_url,
                'post_type' => $post->post_type?->value ?? $post->post_type,
                'prescreen_decision' => $post->prescreen_decision,
                'prescreen_flags' => $post->prescreen_flags ?? [],
            ],
        );

        return $post->fresh();
    }
}
