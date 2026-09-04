<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPostType;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\CommunityReport;
use App\Models\CommunitySpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class AdminCommunityHubUiTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_admin_hub_overview_separates_attention_communities_and_recent_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertDontSee('Monitor communities, review content, and manage safety.')
            ->assertDontSee('Needs Attention')
            ->assertSee('Pending Review')
            ->assertSee('Reports')
            ->assertSee('Published Posts')
            ->assertSee('Communities')
            ->assertSee('Recent Activity')
            ->assertSee('Safety Controls')
            ->assertSee('rounded-[28px]', false)
            ->assertSee('bg-gradient-to-br', false)
            ->assertSee('bg-[radial-gradient', false)
            ->assertSee('shadow-theme-xs', false)
            ->assertDontSee('Platform moderation')
            ->assertDontSee('Community moderation stream')
            ->assertDontSee('All connector spaces')
            ->assertDontSee('Trending')
            ->assertDontSee('Followers');
    }

    public function test_admin_hub_shows_community_members_and_post_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Adult Community',
            'status' => 'active',
            'settings' => ['visibility' => 'connector_members'],
        ]);
        CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Consent education',
            'body' => 'A published educational announcement.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Adult Community')
            ->assertSee('Members')
            ->assertSee('Posts')
            ->assertSee('>1<', false)
            ->assertSee('Active');
    }

    public function test_recent_activity_uses_status_specific_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Activity Community',
            'status' => 'active',
            'settings' => [],
        ]);

        $posts = [];
        foreach ([
            ['title' => 'Awaiting review', 'status' => CommunityPostStatus::PendingReview],
            ['title' => 'Reported post', 'status' => CommunityPostStatus::Published],
            ['title' => 'Escalated post', 'status' => CommunityPostStatus::Escalated],
            ['title' => 'Removed post', 'status' => CommunityPostStatus::Removed],
        ] as $post) {
            $posts[] = CommunityPost::query()->create([
                'community_space_id' => $space->id,
                'connector_id' => $connector->id,
                'author_id' => $owner->id,
                'post_type' => CommunityPostType::Announcement,
                'status' => $post['status'],
                'title' => $post['title'],
                'body' => 'Activity post body.',
            ]);
        }
        CommunityReport::query()->create([
            'community_post_id' => $posts[1]->id,
            'reporter_id' => $owner->id,
            'reported_user_id' => $owner->id,
            'reason_code' => 'other',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('Investigate')
            ->assertSee('View Decision');
    }

    public function test_admin_community_workspace_routes_are_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        foreach ([
            'admin.community.communities',
            'admin.community.moderation.index',
            'admin.community.moderation.pending',
            'admin.community.moderation.reports',
            'admin.community.content.index',
            'admin.community.content.featured',
            'admin.community.content.drafts',
            'admin.community.content.archived',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('rounded-[30px]', false)
                ->assertSee('bg-[radial-gradient', false);
        }
    }

    public function test_admin_can_approve_a_pending_post_from_review_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Review Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::ModeratedQuestion,
            'status' => CommunityPostStatus::PendingReview,
            'title' => 'Moderated question awaiting review',
            'body' => 'A question requiring an admin decision.',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.community.show', $post))
            ->post(route('admin.community.moderation.approve', $post), [
                'reason' => 'Approved after review.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Post approved and published.');

        $this->assertSame(CommunityPostStatus::Published, $post->fresh()->status);
    }

    public function test_reports_queue_excludes_resolved_reports_and_content_tabs_filter_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Queue Community',
            'status' => 'active',
            'settings' => [],
        ]);

        $openReported = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Open report post',
            'body' => 'Needs investigation.',
        ]);
        $resolvedReported = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Resolved report post',
            'body' => 'Already reviewed.',
        ]);
        CommunityReport::query()->create([
            'community_post_id' => $openReported->id,
            'reporter_id' => $owner->id,
            'reported_user_id' => $owner->id,
            'reason_code' => 'other',
            'status' => 'open',
        ]);
        CommunityReport::query()->create([
            'community_post_id' => $resolvedReported->id,
            'reporter_id' => $owner->id,
            'reported_user_id' => $owner->id,
            'reason_code' => 'other',
            'status' => 'resolved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.moderation.reports'))
            ->assertOk()
            ->assertSee('Open report post')
            ->assertDontSee('Resolved report post');

        CommunityPost::query()->whereKey($openReported)->update(['featured_at' => now()]);
        CommunityPost::query()->whereKey($resolvedReported)->update(['status' => CommunityPostStatus::Draft]);

        $this->actingAs($admin)
            ->get(route('admin.community.content.featured'))
            ->assertOk()
            ->assertSee('Open report post')
            ->assertDontSee('Resolved report post');

        $this->actingAs($admin)
            ->get(route('admin.community.content.drafts'))
            ->assertOk()
            ->assertSee('Resolved report post')
            ->assertDontSee('Open report post');
    }

    public function test_admin_post_detail_uses_community_language_and_explicit_decisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Detail Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::ModeratedQuestion,
            'status' => CommunityPostStatus::PendingReview,
            'title' => 'Review this question',
            'body' => 'Question detail.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.show', $post))
            ->assertOk()
            ->assertSee('Detail Community')
            ->assertSee('Moderation Decision')
            ->assertSee('Approve')
            ->assertSee('Restrict')
            ->assertSee('Remove')
            ->assertDontSee('Reject')
            ->assertDontSee('Escalate')
            ->assertSee('Back to Moderation')
            ->assertDontSee('Platform Actions')
            ->assertDontSee('Connector</p>', false);
    }

    public function test_admin_activity_filters_by_community_and_date_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Filter Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $recent = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Recent filtered post',
            'body' => 'Recent activity.',
        ]);
        $old = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Old filtered post',
            'body' => 'Old activity.',
        ]);
        CommunityPost::query()->whereKey($old)->update([
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.index', [
                'connector_id' => $connector->id,
                'date' => '30',
            ]))
            ->assertOk()
            ->assertSee($recent->title)
            ->assertDontSee('Old filtered post');
    }

    public function test_admin_post_detail_shows_moderation_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'History Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::ModeratedQuestion,
            'status' => CommunityPostStatus::PendingReview,
            'title' => 'History question',
            'body' => 'Question with a recorded decision.',
        ]);
        app(\App\Services\Community\CommunityModerationService::class)->approvePost(
            $admin,
            $post,
            'Approved after reviewing context.',
        );

        $this->actingAs($admin)
            ->get(route('admin.community.show', $post))
            ->assertOk()
            ->assertSee('History')
            ->assertSee('Approved after reviewing context.')
            ->assertSee('approve');
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
            ->assertSee('Hidden')
            ->assertSee('Overview')
            ->assertSee('Communities')
            ->assertSee('Content')
            ->assertSee('Moderation')
            ->assertSee('Safety');
    }

    public function test_admin_sidebar_links_to_community_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('href="'.route('admin.community.index').'"', false);
    }

    public function test_admin_workspace_sections_start_with_navigation_and_context_control(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.content.index'))
            ->assertOk()
            ->assertSee('aria-label="Content sections"', false)
            ->assertSee('>Content</span>', false)
            ->assertSee('Posts')
            ->assertDontSee('Manage published and retained Community Hub content separately from moderation queues.')
            ->assertDontSee('Back to Overview');

        $this->actingAs($admin)
            ->get(route('admin.community.moderation.index'))
            ->assertOk()
            ->assertSee('aria-label="Moderation queues"', false)
            ->assertDontSee('Review Community Hub activity, its context, and the next decision required.')
            ->assertDontSee('Back to Overview');

        $this->actingAs($admin)
            ->get(route('admin.community.communities'))
            ->assertOk()
            ->assertDontSee('See where members are active and open a community workspace when you need more context.')
            ->assertDontSee('Back to Overview');
    }

    public function test_admin_can_open_community_details_and_nested_posts_and_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Details Community',
            'status' => 'active',
            'settings' => [],
            'created_at' => now()->subDays(4),
        ]);
        CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $member->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Details community post',
            'body' => 'A post listed in the community workspace.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.communities.show', $space))
            ->assertOk()
            ->assertSee('Details Community')
            ->assertSee($connector->name)
            ->assertSee('Overview')
            ->assertSee('Members')
            ->assertSee('Posts')
            ->assertSee('Created')
            ->assertSee('Edit Community')
            ->assertSee('Manage Members')
            ->assertSee('Deactivate');

        $this->actingAs($admin)
            ->get(route('admin.community.communities.posts', $space))
            ->assertOk()
            ->assertSee('Details community post')
            ->assertSee('Post')
            ->assertSee('Author')
            ->assertSee('Engagement');

        $this->actingAs($admin)
            ->get(route('admin.community.communities.members', $space))
            ->assertOk()
            ->assertSee($owner->name)
            ->assertSee($member->name)
            ->assertSee('Joined')
            ->assertSee('Posts');
    }

    public function test_admin_can_update_and_deactivate_a_community_without_deleting_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Editable Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Retained post',
            'body' => 'The post remains after deactivation.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.communities.edit', $space))
            ->assertOk()
            ->assertSee('Edit Community')
            ->assertSee('Editable Community');

        $this->actingAs($admin)
            ->put(route('admin.community.communities.update', $space), [
                'name' => 'Renamed Community',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.community.communities.show', $space));

        $this->actingAs($admin)
            ->post(route('admin.community.communities.deactivate', $space))
            ->assertRedirect(route('admin.community.communities.show', $space));

        $this->assertSame('inactive', $space->fresh()->status);
        $this->assertDatabaseHas('community_posts', ['id' => $post->id]);
    }

    public function test_admin_community_ui_hides_escalation_workflow_but_keeps_safety_data_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Safety UI Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Escalated,
            'title' => 'Safety review record',
            'body' => 'Backend safety lifecycle remains available for audit.',
            'escalated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertDontSee('Escalations')
            ->assertDontSee('Escalate')
            ->assertDontSee('Open Case')
            ->assertDontSee('escalated')
            ->assertDontSee('Escalated');

        $this->actingAs($admin)
            ->get(route('admin.community.show', $post))
            ->assertOk()
            ->assertDontSee('Escalate')
            ->assertDontSee('Escalated');
    }

    public function test_admin_post_review_renders_image_and_video_posts(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Media Review Community',
            'status' => 'active',
            'settings' => [],
        ]);

        $imagePost = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Image post for review',
            'body' => 'Image post body.',
        ]);
        $image = CommunityPostMedia::query()->create([
            'community_post_id' => $imagePost->id,
            'uploaded_by' => $owner->id,
            'media_type' => 'image',
            'path' => 'community-posts/image-review.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'image-review.jpg',
            'size_bytes' => 1024,
            'display_order' => 0,
        ]);
        Storage::disk('local')->put($image->path, 'image');

        $videoPost = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Video post for review',
            'body' => 'Video post body.',
        ]);
        $video = CommunityPostMedia::query()->create([
            'community_post_id' => $videoPost->id,
            'uploaded_by' => $owner->id,
            'media_type' => 'video',
            'path' => 'community-posts/video-review.mp4',
            'mime_type' => 'video/mp4',
            'original_name' => 'video-review.mp4',
            'size_bytes' => 2048,
            'display_order' => 0,
        ]);
        Storage::disk('local')->put($video->path, 'video');

        $this->actingAs($admin)
            ->get(route('admin.community.show', $imagePost))
            ->assertOk()
            ->assertSee('data-testid="community-media-gallery"', false)
            ->assertSee('<img', false)
            ->assertSee(route('connector.community.media.show', [$connector, $imagePost, $image]), false);

        $this->actingAs($admin)
            ->get(route('admin.community.show', $videoPost))
            ->assertOk()
            ->assertSee('data-testid="community-media-gallery"', false)
            ->assertSee('<video', false)
            ->assertSee('<source', false)
            ->assertSee('video/mp4');
    }

    public function test_moderation_decision_actions_are_icon_only_and_have_alert_feedback(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Action Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::PendingReview,
            'title' => 'Icon action post',
            'body' => 'Post for icon action review.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.show', $post))
            ->assertOk()
            ->assertSee('aria-label="Approve post"', false)
            ->assertSee('aria-label="Restrict post"', false)
            ->assertSee('aria-label="Restore post"', false)
            ->assertSee('aria-label="Remove post"', false)
            ->assertSee('title="Approve post"', false)
            ->assertSee('title="Restrict post"', false)
            ->assertSee('title="Restore post"', false)
            ->assertSee('title="Remove post"', false)
            ->assertDontSee('>Approve</span>', false)
            ->assertDontSee('>Restrict</span>', false)
            ->assertDontSee('>Restore</span>', false)
            ->assertDontSee('>Remove</span>', false);

        $this->actingAs($admin)
            ->post(route('admin.community.moderation.hide', $post), [
                'reason' => 'Restricted while reviewing.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Post hidden.');
    }

    public function test_admin_moderation_actions_support_ajax_responses_and_in_place_status_updates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => 'Ajax Community',
            'status' => 'active',
            'settings' => [],
        ]);
        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::PendingReview,
            'title' => 'AJAX action post',
            'body' => 'Post for AJAX review.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.show', $post))
            ->assertOk()
            ->assertSee('data-community-moderation-form', false)
            ->assertSee('data-community-post-status', false)
            ->assertSee('data-community-moderation-feedback', false)
            ->assertSee('axios.post', false);

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.community.moderation.approve', $post), [
                'reason' => 'Approved through AJAX.',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Post approved and published.',
                'status' => CommunityPostStatus::Published->value,
                'status_label' => CommunityPostStatus::Published->label(),
                'post_id' => $post->id,
            ]);
    }
}
