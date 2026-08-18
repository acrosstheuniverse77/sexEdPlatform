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
            ->assertSee('Create structured post')
            ->assertSee('Community Feed')
            ->assertSee('Safety center')
            ->assertSee('Upcoming seminars')
            ->assertSee('Moderation')
            ->assertSee('href="'.route('connector.community.show', [$connector, $post]).'"', false)
            ->assertSee('href="'.route('connector.community.show', [$connector, $post]).'#comments"', false)
            ->assertSee('aria-label="Open comments"', false)
            ->assertSee('src="https://example.com/community-update.jpg"', false)
            ->assertDontSee('aria-label="Open post"', false);

        $this->actingAs($member)->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('Community Post')
            ->assertSee('Adult community update')
            ->assertSee('id="comments"', false)
            ->assertSee('Flat comments only')
            ->assertSee('Report comment')
            ->assertSee('name="reason_code"', false)
            ->assertSee('Community Guidelines Violation')
            ->assertSee('data-community-report-other-editor', false)
            ->assertSee('tinymce.min.js');

        $this->actingAs($member)->get(route('connector.community.create', $connector))
            ->assertOk()
            ->assertSee('New Community Post')
            ->assertSee('Safety reminder')
            ->assertSee('for="community-post-type-announcement"', false)
            ->assertSee('id="community-post-type-announcement"', false)
            ->assertSee('for="community-post-type-event"', false)
            ->assertSee('id="community-post-type-event"', false)
            ->assertSee('for="community-post-type-resource"', false)
            ->assertSee('id="community-post-type-resource"', false)
            ->assertSee('for="community-post-type-moderated_question"', false)
            ->assertSee('id="community-post-type-moderated_question"', false)
            ->assertSee('for="community-post-type-discussion_prompt"', false)
            ->assertSee('id="community-post-type-discussion_prompt"', false)
            ->assertSee('Submit for Review')
            ->assertSee('Publish');

        $this->actingAs($member)->get(route('connector.community.moderation.index', $connector))
            ->assertOk()
            ->assertSee('Review center')
            ->assertSee('Pending')
            ->assertSee('Reported')
            ->assertSee('Escalated')
            ->assertSee('Needs moderator review')
            ->assertSee('href="'.route('connector.community.show', [$connector, $pendingPost]).'"', false)
            ->assertSee('aria-label="Review Post"', false)
            ->assertDontSee('aria-label="Approve"', false)
            ->assertDontSee('aria-label="Hide"', false)
            ->assertDontSee('aria-label="Lock comments"', false)
            ->assertDontSee('aria-label="Escalate"', false)
            ->assertDontSee('aria-label="Reject"', false);
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
            ->assertSee('All connector spaces')
            ->assertSee('Reported')
            ->assertSee('Escalated')
            ->assertSee('Global safety controls');

        $this->actingAs($admin)->get(route('admin.community.settings'))
            ->assertOk()
            ->assertSee('Emergency Freeze')
            ->assertSee('Freeze Community Hub posting and comments globally');
    }
}
