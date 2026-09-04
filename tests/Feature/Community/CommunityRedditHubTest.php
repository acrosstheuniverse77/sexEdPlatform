<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityComment;
use App\Models\CommunityReaction;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityRedditHubTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_member_feed_uses_clean_toolbar_and_hides_legacy_panels_and_reactions(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture('Consent education', 'Consent education update');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        CommunityReaction::query()->create([
            'community_post_id' => $post->id,
            'user_id' => $owner->id,
            'reaction_type' => 'helpful',
        ]);

        $this->actingAs($viewer)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee('Search posts')
            ->assertSee('Filters')
            ->assertSee('Top')
            ->assertSee('Consent education')
            ->assertDontSee('Connector Information')
            ->assertDontSee('Workspace')
            ->assertDontSee('Moderation health')
            ->assertDontSee('Connector member')
            ->assertDontSee('Helpful')
            ->assertDontSee('Learned')
            ->assertDontSee('Question')
            ->assertDontSee('Support')
            ->assertDontSee('Bookmark')
            ->assertDontSee('Published');
    }

    public function test_post_upvote_is_grouped_with_the_footer_actions(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture('Consent education', 'Vote placement target');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($viewer)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSeeInOrder([
                '<footer',
                'data-testid="community-post-upvote"',
                'Open comments',
            ], false);
    }

    public function test_feed_offers_a_link_to_browse_all_connector_seminars(): void
    {
        [$connector] = $this->publishedPostFixture('Community seminar', 'Seminar browse target');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($viewer)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee('Browse all seminars')
            ->assertSee(route('connector.seminars.index', $connector), false);
    }

    public function test_post_create_stores_an_image_or_video_attachment_within_its_limit(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $this->actingAs($member)
            ->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => 'An image attachment is available for the adult community.',
                'media' => UploadedFile::fake()->image('community-update.jpg')->size(5_000),
            ])
            ->assertRedirect();

        $imagePost = $connector->communityPosts()->latest('id')->firstOrFail();
        $this->assertSame('image', $imagePost->media_type);
        Storage::disk('local')->assertExists($imagePost->media_path);

        $this->actingAs($member)
            ->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => 'A video attachment is available for the adult community.',
                'media' => UploadedFile::fake()->create('community-update.mp4', 25_000, 'video/mp4'),
            ])
            ->assertRedirect();

        $videoPost = $connector->communityPosts()->latest('id')->firstOrFail();
        $this->assertSame('video', $videoPost->media_type);
        Storage::disk('local')->assertExists($videoPost->media_path);
    }

    public function test_post_create_rejects_media_above_the_configured_limits(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $payload = [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'This attachment exceeds the configured image limit.',
        ];

        $this->actingAs($member)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $payload + [
                'media' => UploadedFile::fake()->image('too-large.jpg')->size(5_121),
            ])
            ->assertRedirect(route('connector.community.create', $connector))
            ->assertSessionHasErrors('media');

        $this->actingAs($member)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $payload + [
                'media' => UploadedFile::fake()->create('too-large.mp4', 25_601, 'video/mp4'),
            ])
            ->assertRedirect(route('connector.community.create', $connector))
            ->assertSessionHasErrors('media');
    }

    public function test_visible_post_media_is_served_only_through_the_community_access_route(): void
    {
        Storage::fake('local');
        [$connector, , $post] = $this->publishedPostFixture('Connector announcement', 'Media access target');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);
        Storage::disk('local')->put('community-post-media/example.jpg', 'image-content');
        $post->forceFill([
            'media_path' => 'community-post-media/example.jpg',
            'media_type' => 'image',
        ])->save();
        $media = $post->media()->create([
            'uploaded_by' => $post->author_id,
            'media_type' => 'image',
            'path' => 'community-post-media/example.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'example.jpg',
            'size_bytes' => strlen('image-content'),
            'display_order' => 0,
        ]);

        $this->actingAs($viewer)
            ->get(route('connector.community.media.show', [$connector, $post, $media]))
            ->assertOk();

        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, ['community.view_space']);
        $connector->memberships()->create(['user_id' => $minor->id, 'connector_role_id' => $role->id, 'status' => 'active', 'accepted_at' => now()]);

        $this->actingAs($minor)
            ->get(route('connector.community.media.show', [$connector, $post, $media]))
            ->assertForbidden();
    }

    public function test_topic_filter_and_top_sort_rank_by_upvotes_without_legacy_reactions(): void
    {
        [$connector, $owner, $lowPost] = $this->publishedPostFixture('Healthy relationships', 'Low vote post');
        $highPost = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => 'Healthy relationships',
            'body' => 'High vote post body.',
        ]);
        $resourcePost = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'resource',
            'topic_choice' => 'Sexual health resource',
            'body' => 'Resource body.',
        ]);

        $voterA = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $voterB = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $this->actingAs($voterA)->post(route('connector.community.posts.upvote', [$connector, $highPost]))->assertRedirect();
        $this->actingAs($voterB)->post(route('connector.community.posts.upvote', [$connector, $highPost]))->assertRedirect();
        CommunityReaction::query()->create(['community_post_id' => $lowPost->id, 'user_id' => $owner->id, 'reaction_type' => 'helpful']);

        $response = $this->actingAs($voterA)
            ->get(route('connector.community.index', [$connector, 'topic' => 'Healthy relationships', 'sort' => 'top']))
            ->assertOk()
            ->assertSeeInOrder(['High vote post body.', 'Low vote post'])
            ->assertDontSee('Resource body.');

        $loadedHighPost = $response->viewData('posts')->getCollection()->firstWhere('id', $highPost->id);
        $this->assertSame(2, $loadedHighPost->upvotes_count);
        $this->assertCount(1, $loadedHighPost->upvotes);
        $this->assertSame($voterA->id, $loadedHighPost->upvotes->sole()->user_id);
        $this->assertFalse($loadedHighPost->relationLoaded('reports'));
    }

    public function test_post_upvote_toggles_and_rejects_hidden_pending_cross_connector_and_minor_votes(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture('Consent education', 'Vote target');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($viewer)
            ->post(route('connector.community.posts.upvote', [$connector, $post]), [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()
            ->assertJson(['active' => true, 'count' => 1]);
        $this->assertDatabaseCount('community_post_upvotes', 1);

        $this->actingAs($viewer)
            ->post(route('connector.community.posts.upvote', [$connector, $post]), [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()
            ->assertJson(['active' => false, 'count' => 0]);
        $this->assertDatabaseCount('community_post_upvotes', 0);

        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, ['community.view_space']);
        $connector->memberships()->create(['user_id' => $minor->id, 'connector_role_id' => $role->id, 'status' => 'active', 'accepted_at' => now()]);
        $this->actingAs($minor)->post(route('connector.community.posts.upvote', [$connector, $post]))->assertForbidden();

        $hidden = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => 'Consent education',
            'body' => 'Hidden vote target.',
        ]);
        $hidden->update(['status' => CommunityPostStatus::Hidden->value]);
        $this->actingAs($viewer)->post(route('connector.community.posts.upvote', [$connector, $hidden]))->assertForbidden();

        $otherOwner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $otherOwner->assignRole('learner');
        $otherConnector = $this->createVerifiedConnector($otherOwner);
        $this->actingAs($viewer)->post(route('connector.community.posts.upvote', [$otherConnector, $post]))->assertNotFound();
    }

    public function test_owner_can_pin_unpin_and_member_cannot_pin(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture('Connector announcement', 'Pin target');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($viewer)
            ->post(route('connector.community.posts.pin', [$connector, $post]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('connector.community.posts.pin', [$connector, $post]))
            ->assertRedirect();
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'featured_by' => $owner->id,
        ]);
        $this->assertNotNull($post->fresh()->featured_at);

        $this->actingAs($owner)
            ->delete(route('connector.community.posts.pin', [$connector, $post]))
            ->assertRedirect();
        $this->assertNull($post->fresh()->featured_at);
    }

    public function test_create_form_and_store_generate_topic_title_and_ignore_removed_fields(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $this->actingAs($member)
            ->get(route('connector.community.create', $connector))
            ->assertOk()
            ->assertSee('Choose a post type')
            ->assertSee('Publish')
            ->assertSee('Cancel')
            ->assertDontSee('name="title"', false)
            ->assertDontSee('Attachment or link')
            ->assertDontSee('Safety reminder')
            ->assertDontSee('Submit for Review')
            ->assertDontSee('Save Draft');

        $this->actingAs($member)
            ->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'topic_choice' => 'Other',
                'custom_topic' => '  Local parent dialogue  ',
                'title' => 'Client title should be ignored',
                'body' => 'Adults are invited to a safe public discussion.',
                'resource_url' => 'https://example.com/ignore-me',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_posts', [
            'connector_id' => $connector->id,
            'author_id' => $member->id,
            'title' => 'Local parent dialogue',
            'topic' => 'Local parent dialogue',
            'resource_url' => null,
        ]);
    }

    public function test_detail_page_removes_safety_copy_and_orders_comments_by_upvotes(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture('Consent education', 'Detail target');
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $first = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Low voted visible comment.',
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);
        $second = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $viewer->id,
            'body' => 'Top voted visible comment.',
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);
        $this->actingAs($owner)->post(route('connector.community.comments.upvote', [$connector, $post, $second]))->assertRedirect();

        $this->actingAs($viewer)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSeeInOrder(['Top voted visible comment.', 'Low voted visible comment.'])
            ->assertDontSee('Post safety')
            ->assertDontSee('Safety reminder')
            ->assertDontSee('Flat comments only')
            ->assertDontSee('Comments should stay educational');
    }

    private function publishedPostFixture(string $topic, string $body): array
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => $topic,
            'body' => $body,
        ]);
        $post->update(['status' => CommunityPostStatus::Published->value, 'published_at' => now()]);

        return [$connector, $owner, $post->fresh(['connector', 'space'])];
    }
}
