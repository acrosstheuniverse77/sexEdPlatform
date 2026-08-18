<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\User;
use App\Services\Community\CommunityInteractionService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityInteractionSafetyTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_adult_connector_member_can_comment_flat_on_published_unlocked_post(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $commenter = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.manage_comments']);

        $comment = app(CommunityInteractionService::class)->comment($commenter, $post, 'This is helpful for our adult facilitators.');

        $this->assertSame(CommunityCommentStatus::Visible, $comment->status);
        $this->assertSame($post->id, $comment->community_post_id);
        $this->assertFalse(Schema::hasColumn('community_comments', 'parent_comment_id'));
    }

    public function test_adult_member_with_view_access_can_comment_from_post_page(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $commenter = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($commenter)
            ->post(route('connector.community.comments.store', [$connector, $post]), [
                'body' => 'This public comment is useful for adult members.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comments', [
            'community_post_id' => $post->id,
            'author_id' => $commenter->id,
            'body' => 'This public comment is useful for adult members.',
            'status' => CommunityCommentStatus::Visible->value,
        ]);
    }

    public function test_minor_cannot_comment(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, ['community.view_space', 'community.manage_comments']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        app(CommunityInteractionService::class)->comment($minor, $post, 'Minor comment');
    }

    public function test_minor_cannot_react(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, ['community.view_space']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        app(CommunityInteractionService::class)->react($minor, $post, 'helpful');
    }

    public function test_contact_seeking_comment_is_auto_escalated_or_blocked(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $comment = app(CommunityInteractionService::class)->comment($author, $post, 'DM me privately and meet me near your school.');

        $this->assertSame(CommunityCommentStatus::Escalated, $comment->status);
        $this->assertContains('off_platform_contact', $comment->prescreen_flags);
    }

    public function test_invalid_reaction_type_is_rejected(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $this->expectException(ValidationException::class);

        app(CommunityInteractionService::class)->react($author, $post, 'love');
    }

    public function test_reaction_route_toggles_active_reaction_without_duplicates(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $this->actingAs($author)
            ->post(route('connector.community.reactions.store', [$connector, $post]), [
                'reaction_type' => 'learned',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('community_reactions', 1);
        $this->assertDatabaseHas('community_reactions', [
            'community_post_id' => $post->id,
            'user_id' => $author->id,
            'reaction_type' => 'learned',
        ]);

        $this->actingAs($author)
            ->post(route('connector.community.reactions.store', [$connector, $post]), [
                'reaction_type' => 'learned',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('community_reactions', [
            'community_post_id' => $post->id,
            'user_id' => $author->id,
            'reaction_type' => 'learned',
        ]);
    }

    public function test_minor_direct_write_routes_are_forbidden_even_with_connector_permissions(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, [
            'community.view_space',
            'community.create_post',
            'community.manage_comments',
        ]);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->actingAs($minor)
            ->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'title' => 'Minor post',
                'body' => 'This should not publish.',
            ])
            ->assertForbidden();

        $this->actingAs($minor)
            ->post(route('connector.community.comments.store', [$connector, $post]), [
                'body' => 'Minor comment.',
            ])
            ->assertForbidden();

        $this->actingAs($minor)
            ->post(route('connector.community.reactions.store', [$connector, $post]), [
                'reaction_type' => 'helpful',
            ])
            ->assertForbidden();

        $this->actingAs($minor)
            ->post(route('connector.community.reports.store', [$connector, $post]), [
                'reason_code' => 'other',
                'details' => 'Minor report.',
            ])
            ->assertForbidden();
    }

    public function test_report_reason_must_match_configured_safety_taxonomy(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $this->actingAs($author)
            ->from(route('connector.community.show', [$connector, $post]))
            ->post(route('connector.community.reports.store', [$connector, $post]), [
                'reason_code' => 'safety_concern',
                'details' => 'This generic reason is not in the configured report taxonomy.',
            ])
            ->assertRedirect(route('connector.community.show', [$connector, $post]))
            ->assertSessionHasErrors('reason_code');
    }

    public function test_other_report_reason_requires_custom_details(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $this->actingAs($author)
            ->from(route('connector.community.show', [$connector, $post]))
            ->post(route('connector.community.reports.store', [$connector, $post]), [
                'reason_code' => 'other',
                'details' => '',
            ])
            ->assertRedirect(route('connector.community.show', [$connector, $post]))
            ->assertSessionHasErrors('details');
    }

    public function test_report_route_stores_selected_reason_and_other_details(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $this->actingAs($author)
            ->post(route('connector.community.reports.store', [$connector, $post]), [
                'reason_code' => 'other',
                'details' => '<p>This needs a custom moderator explanation.</p><script>alert("x")</script>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'community_post_id' => $post->id,
            'reporter_id' => $author->id,
            'reason_code' => 'other',
            'details' => '<p>This needs a custom moderator explanation.</p>alert("x")',
        ]);
    }

    private function publishedPostFixture(): array
    {
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'announcement',
            'title' => 'Schedule',
            'body' => 'Safe announcement.',
            'resource_url' => null,
        ]);
        $post->update(['status' => CommunityPostStatus::Published->value, 'published_at' => now()]);

        return [$connector, $author, $post->fresh(['connector', 'space'])];
    }
}
