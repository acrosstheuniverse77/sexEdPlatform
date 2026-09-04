<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Services\Community\CommunityInteractionService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityUiSmokeTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_community_pages_render_for_adult_member(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(32)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, [
            'community.view_space',
            'community.create_post',
            'community.manage_comments',
            'community.manage_posts',
            'community.approve_posts',
            'community.lock_threads',
            'community.escalate_to_platform',
        ]);
        $post = app(CommunityPostService::class)->create($member, $connector, [
            'post_type' => 'announcement',
            'title' => 'Adult community update',
            'body' => 'This is a public adult-facing connector update.',
            'resource_url' => 'https://example.com/community-update.jpg',
        ]);
        $post->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $member->id,
        ])->save();
        app(CommunityInteractionService::class)->comment($member, $post, 'This is helpful for facilitators.');
        $pendingPost = app(CommunityPostService::class)->create($member, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Needs moderator review',
            'body' => 'Please review this education-focused question before it appears.',
            'resource_url' => null,
        ]);
        $pendingPost->forceFill(['status' => 'pending_review'])->save();

        $this->actingAs($member)->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('Adult community update')
            ->assertSee('Create post')
            ->assertSee('Filters')
            ->assertDontSee('Safety center')
            ->assertDontSee('Moderation health')
            ->assertSee('Open moderation')
            ->assertDontSee('aria-label="Open moderation"', false)
            ->assertSee('href="'.route('connector.community.show', [$connector, $post]).'"', false)
            ->assertSee('href="'.route('connector.community.show', [$connector, $post]).'#comments"', false)
            ->assertSee('aria-label="Open comments"', false)
            ->assertDontSee('aria-label="Open post"', false);

        $this->actingAs($member)->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('Community Post')
            ->assertSee('Adult community update')
            ->assertSee('id="comments"', false)
            ->assertDontSee('Manage post')
            ->assertDontSee('action="'.route('connector.community.moderation.approve', [$connector, $post]).'"', false)
            ->assertDontSee('Flat comments only')
            ->assertSee('Report comment')
            ->assertSee('name="reason_code"', false)
            ->assertSee('Community Guidelines Violation')
            ->assertSee('data-community-report-other-editor', false)
            ->assertSee('tinymce.min.js');

        $this->actingAs($member)->get(route('connector.community.create', $connector))
            ->assertOk()
            ->assertSee('New Community Post')
            ->assertDontSee('Safety reminder')
            ->assertSee('id="post_type"', false)
            ->assertSee('Choose a post type')
            ->assertSee('id="topic_choice"', false)
            ->assertDontSee('Submit for Review')
            ->assertDontSee('Save Draft')
            ->assertSee('Publish');

        $this->actingAs($member)->get(route('connector.community.moderation.index', $connector))
            ->assertOk()
            ->assertSee('Review center')
            ->assertSee('Pending')
            ->assertSee('Reported')
            ->assertSee('Escalated')
            ->assertSee('All posts')
            ->assertSee('Needs moderator review')
            ->assertSee('href="'.route('connector.community.show', [$connector, $pendingPost]).'"', false)
            ->assertSee('View post')
            ->assertSee('Manage post')
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSee('action="'.route('connector.community.moderation.approve', [$connector, $pendingPost]).'"', false)
            ->assertSee('action="'.route('connector.community.moderation.reject', [$connector, $pendingPost]).'"', false)
            ->assertDontSee('Hide')
            ->assertDontSee('Lock');
    }

    public function test_review_center_all_posts_uses_status_appropriate_text_actions(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(32)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $published = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement', 'title' => 'Published review item', 'body' => 'Published post for the all-posts moderation queue.',
        ]);
        $published->forceFill(['status' => 'published', 'published_at' => now(), 'published_by' => $owner->id])->save();

        $locked = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement', 'title' => 'Locked review item', 'body' => 'Locked post for the all-posts moderation queue.',
        ]);
        $locked->forceFill(['status' => 'locked', 'locked_at' => now(), 'locked_by' => $owner->id])->save();

        foreach (['draft' => 'Draft review item', 'archived' => 'Archived review item'] as $status => $title) {
            $inactive = app(CommunityPostService::class)->create($owner, $connector, [
                'post_type' => 'announcement', 'title' => $title, 'body' => 'This post is not actionable in connector moderation.',
            ]);
            $inactive->forceFill(['status' => $status])->save();
        }

        $response = $this->actingAs($owner)->get(route('connector.community.moderation.index', [$connector, 'tab' => 'all']));

        $response->assertOk()
            ->assertSee('All posts')
            ->assertSee('Published review item')
            ->assertSee('Locked review item')
            ->assertDontSee('Draft review item')
            ->assertDontSee('Archived review item')
            ->assertSee('Manage post')
            ->assertSee('View post')
            ->assertSee('action="'.route('connector.community.moderation.hide', [$connector, $published]).'"', false)
            ->assertSee('action="'.route('connector.community.moderation.lock', [$connector, $published]).'"', false)
            ->assertDontSee('action="'.route('connector.community.moderation.unlock', [$connector, $published]).'"', false)
            ->assertSee('action="'.route('connector.community.moderation.unlock', [$connector, $locked]).'"', false)
            ->assertDontSee('action="'.route('connector.community.moderation.lock', [$connector, $locked]).'"', false);
    }

    public function test_review_center_only_shows_actions_allowed_by_the_moderator_role(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(32)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $approver = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.approve_posts']);

        $pending = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question', 'title' => 'Approver pending item', 'body' => 'This item needs approval.',
        ]);
        $pending->forceFill(['status' => 'pending_review'])->save();

        $response = $this->actingAs($approver)->get(route('connector.community.moderation.index', $connector));

        $response->assertOk()
            ->assertSee('Manage post')
            ->assertSee('action="'.route('connector.community.moderation.approve', [$connector, $pending]).'"', false)
            ->assertDontSee('action="'.route('connector.community.moderation.reject', [$connector, $pending]).'"', false)
            ->assertDontSee('action="'.route('connector.community.moderation.escalate', [$connector, $pending]).'"', false);
    }

    public function test_admin_community_pages_render_for_platform_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(35)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Admin visible community post',
            'body' => 'Platform administrators can inspect this post.',
            'resource_url' => null,
        ]);

        $this->actingAs($admin)->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('Admin visible community post')
            ->assertDontSee('Needs Attention')
            ->assertSee('Communities')
            ->assertSee('Recent Activity')
            ->assertSee('Safety Controls')
            ->assertDontSee('All connector spaces')
            ->assertDontSee('Community moderation stream');

        $this->actingAs($admin)->get(route('admin.community.settings'))
            ->assertOk()
            ->assertSee('Emergency Freeze')
            ->assertSee('Freeze Community Hub posting and comments globally');
    }
}
