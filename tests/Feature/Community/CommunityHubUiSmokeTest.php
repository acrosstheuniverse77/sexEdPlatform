<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityComment;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityHubUiSmokeTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_hub_shows_module_matched_tabs_and_no_social_media_controls(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'event',
            'title' => 'Consent seminar this Friday',
            'body' => 'Adult members are invited to join the connector seminar.',
            'resource_url' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('Featured')
            ->assertSee('Announcements')
            ->assertSee('Events')
            ->assertSee('Resources')
            ->assertSee('Q&A')
            ->assertSee('Discussions')
            ->assertSee('Consent seminar this Friday')
            ->assertDontSee('Message privately')
            ->assertDontSee('Share to feed')
            ->assertDontSee('Followers');
    }

    public function test_minor_direct_access_does_not_render_hub_controls(): void
    {
        $adult = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $adult->assignRole('learner');
        $connector = $this->createVerifiedConnector($adult);
        $minor = $this->createMinorLearner(13);

        $this->actingAs($minor)
            ->get(route('connector.community.index', $connector))
            ->assertForbidden();
    }

    public function test_view_only_member_only_sees_member_visible_posts(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $published = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Published adult update',
            'body' => 'This published update is safe for adult connector members.',
            'resource_url' => null,
        ]);

        $pending = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Pending moderator question',
            'body' => 'This question should wait for connector review.',
            'resource_url' => null,
        ]);

        $hidden = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Hidden safety update',
            'body' => 'This post was hidden by moderation.',
            'resource_url' => null,
        ]);
        $hidden->update(['status' => CommunityPostStatus::Hidden->value]);

        $this->actingAs($viewer)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($pending->title)
            ->assertDontSee($hidden->title);

        $this->actingAs($viewer)
            ->get(route('connector.community.show', [$connector, $pending]))
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get(route('connector.community.show', [$connector, $hidden]))
            ->assertNotFound();
    }

    public function test_member_post_detail_only_renders_visible_comments(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Visible discussion post',
            'body' => 'Adult connector members can read this post.',
            'resource_url' => null,
        ]);

        CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Visible educational comment.',
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);

        CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Hidden moderation comment.',
            'status' => CommunityCommentStatus::Hidden->value,
            'prescreen_decision' => 'auto_hide_and_escalate',
        ]);

        $this->actingAs($viewer)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('Visible educational comment.')
            ->assertDontSee('Hidden moderation comment.');
    }
}
