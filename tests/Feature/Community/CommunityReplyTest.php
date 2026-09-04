<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityComment;
use App\Models\User;
use App\Services\Community\CommunityInteractionService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityReplyTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_adult_member_can_reply_once_and_reply_uses_comment_prescreening(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $parent = $this->visibleComment($post->id, $owner->id, 'Top-level comment for replies.');

        $this->actingAs($member)
            ->post(route('connector.community.comments.store', [$connector, $post]), [
                'parent_id' => $parent->id,
                'body' => 'A safe public reply for adult members.',
            ])
            ->assertRedirect(route('connector.community.show', [$connector, $post]).'#comments');

        $this->assertDatabaseHas('community_comments', [
            'community_post_id' => $post->id,
            'parent_id' => $parent->id,
            'author_id' => $member->id,
            'body' => 'A safe public reply for adult members.',
            'status' => CommunityCommentStatus::Visible->value,
        ]);

        $unsafeReply = app(CommunityInteractionService::class)->comment(
            $member,
            $post,
            'DM me privately and meet me near your school.',
            $parent,
        );

        $this->assertSame($parent->id, $unsafeReply->parent_id);
        $this->assertSame(CommunityCommentStatus::Escalated, $unsafeReply->status);
        $this->assertContains('off_platform_contact', $unsafeReply->prescreen_flags);
    }

    public function test_route_rejects_cross_post_nested_and_non_visible_reply_parents(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $otherPost = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Another published adult post.',
        ]);
        $parent = $this->visibleComment($post->id, $owner->id, 'Valid top-level parent.');
        $nested = $this->visibleComment($post->id, $owner->id, 'Existing reply.', $parent->id);
        $otherParent = $this->visibleComment($otherPost->id, $owner->id, 'Other post parent.');
        $hidden = $this->visibleComment($post->id, $owner->id, 'Hidden parent.');
        $hidden->update(['status' => CommunityCommentStatus::Hidden->value, 'hidden_at' => now()]);

        foreach ([$nested, $otherParent, $hidden] as $invalidParent) {
            $this->actingAs($member)
                ->post(route('connector.community.comments.store', [$connector, $post]), [
                    'parent_id' => $invalidParent->id,
                    'body' => 'This reply must not be created.',
                ])
                ->assertNotFound();
        }

        $this->actingAs($member)
            ->post(route('connector.community.comments.store', [$connector, $post]), [
                'parent_id' => 0,
                'body' => 'This reply must not be created.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', [
            'author_id' => $member->id,
            'body' => 'This reply must not be created.',
        ]);
    }

    public function test_reply_is_blocked_for_locked_or_frozen_posts_and_for_minors(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $parent = $this->visibleComment($post->id, $owner->id, 'Reply safety parent.');
        $payload = ['parent_id' => $parent->id, 'body' => 'Blocked reply attempt.'];

        $post->update([
            'status' => CommunityPostStatus::Locked->value,
            'locked_at' => now(),
            'locked_by' => $owner->id,
        ]);
        $this->actingAs($member)
            ->post(route('connector.community.comments.store', [$connector, $post]), $payload)
            ->assertForbidden();

        $post->update(['status' => CommunityPostStatus::Published->value, 'locked_at' => null, 'locked_by' => null]);
        $post->space->update(['frozen_at' => now(), 'frozen_by' => $owner->id]);
        $this->actingAs($member)
            ->post(route('connector.community.comments.store', [$connector, $post]), $payload)
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertDontSee('id="comment-body"', false)
            ->assertDontSee('data-reply-for="'.$parent->id.'"', false);

        $post->space->update(['frozen_at' => null, 'frozen_by' => null]);
        $minor = $this->createMinorLearner(14);
        $minorRole = $this->createCustomRole($connector, ['community.view_space']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $minorRole->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        $this->actingAs($minor)
            ->post(route('connector.community.comments.store', [$connector, $post]), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('community_comments', [
            'body' => 'Blocked reply attempt.',
        ]);
    }

    public function test_visible_reply_cannot_be_upvoted_or_reported_after_its_parent_is_hidden(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $parent = $this->visibleComment($post->id, $owner->id, 'Parent later hidden.');
        $reply = $this->visibleComment($post->id, $owner->id, 'Reply hidden by its parent.', $parent->id);
        $parent->update(['status' => CommunityCommentStatus::Hidden->value, 'hidden_at' => now()]);

        $this->actingAs($member)
            ->post(route('connector.community.comments.upvote', [$connector, $post, $reply]))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('connector.community.reports.store', [$connector, $post]), [
                'community_comment_id' => $reply->id,
                'reason_code' => 'other',
                'details' => 'A guessed hidden reply should not be reportable.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('community_comment_upvotes', ['community_comment_id' => $reply->id]);
        $this->assertDatabaseMissing('community_reports', ['community_comment_id' => $reply->id]);
    }

    public function test_thread_ranks_roots_by_upvotes_and_keeps_replies_chronological(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $secondVoter = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $lowRoot = $this->visibleComment($post->id, $owner->id, 'Low voted root comment.');
        $highRoot = $this->visibleComment($post->id, $viewer->id, 'Highest voted root comment.');
        $oldestReply = $this->visibleComment($post->id, $owner->id, 'Oldest reply stays first.', $highRoot->id);
        $newestReply = $this->visibleComment($post->id, $viewer->id, 'Newer highly voted reply stays second.', $highRoot->id);

        $this->actingAs($viewer)->post(route('connector.community.comments.upvote', [$connector, $post, $highRoot]))->assertRedirect();
        $this->actingAs($secondVoter)->post(route('connector.community.comments.upvote', [$connector, $post, $highRoot]))->assertRedirect();
        $this->actingAs($owner)->post(route('connector.community.comments.upvote', [$connector, $post, $lowRoot]))->assertRedirect();
        $this->actingAs($owner)->post(route('connector.community.comments.upvote', [$connector, $post, $newestReply]))->assertRedirect();
        $this->actingAs($secondVoter)->post(route('connector.community.comments.upvote', [$connector, $post, $newestReply]))->assertRedirect();
        $this->actingAs($viewer)->post(route('connector.community.comments.upvote', [$connector, $post, $newestReply]))->assertRedirect();

        $response = $this->actingAs($viewer)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSeeInOrder([
                'Highest voted root comment.',
                'Oldest reply stays first.',
                'Newer highly voted reply stays second.',
                'Low voted root comment.',
            ])
            ->assertSee('data-testid="community-comment-reply"', false)
            ->assertSee('name="parent_id" value="'.$highRoot->id.'"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-reply-for='));
        $this->assertSame(1, substr_count($response->getContent(), 'Oldest reply stays first.'));
        $this->assertSame(1, substr_count($response->getContent(), 'Newer highly voted reply stays second.'));
        $this->assertTrue($oldestReply->isReply());

        $loadedHighRoot = $response->viewData('post')->topLevelComments->firstWhere('id', $highRoot->id);
        $loadedNewestReply = $loadedHighRoot->replies->firstWhere('id', $newestReply->id);
        $this->assertSame(2, $loadedHighRoot->upvotes_count);
        $this->assertCount(1, $loadedHighRoot->upvotes);
        $this->assertSame($viewer->id, $loadedHighRoot->upvotes->sole()->user_id);
        $this->assertSame(3, $loadedNewestReply->upvotes_count);
        $this->assertCount(1, $loadedNewestReply->upvotes);
        $this->assertSame($viewer->id, $loadedNewestReply->upvotes->sole()->user_id);
    }

    public function test_visible_reply_supports_upvote_and_report(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $parent = $this->visibleComment($post->id, $owner->id, 'Visible reportable parent.');
        $reply = $this->visibleComment($post->id, $owner->id, 'Visible reportable reply.', $parent->id);

        $this->actingAs($member)
            ->post(
                route('connector.community.comments.upvote', [$connector, $post, $reply]),
                [],
                ['HTTP_ACCEPT' => 'application/json'],
            )
            ->assertOk()
            ->assertJson(['active' => true, 'count' => 1]);

        $this->actingAs($member)
            ->post(route('connector.community.reports.store', [$connector, $post]), [
                'community_comment_id' => $reply->id,
                'reason_code' => 'other',
                'details' => 'This visible reply needs moderator review.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comment_upvotes', [
            'community_comment_id' => $reply->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('community_reports', [
            'community_comment_id' => $reply->id,
            'reported_user_id' => $owner->id,
            'reporter_id' => $member->id,
        ]);
    }

    public function test_member_hides_non_visible_parent_thread_while_moderator_keeps_audit_view(): void
    {
        [$connector, $owner, $post] = $this->publishedPostFixture();
        $member = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $parent = $this->visibleComment($post->id, $owner->id, 'Hidden root audit text.');
        $reply = $this->visibleComment($post->id, $member->id, 'Child retained for audit.', $parent->id);
        $parent->update([
            'status' => CommunityCommentStatus::Hidden->value,
            'hidden_at' => now(),
            'hidden_by' => $owner->id,
        ]);

        $this->actingAs($member)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertDontSee('Hidden root audit text.')
            ->assertDontSee('Child retained for audit.');

        $this->actingAs($owner)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('Hidden root audit text.')
            ->assertSee('Child retained for audit.')
            ->assertSee('Hidden');

        $this->assertTrue($reply->isReply());
    }

    private function publishedPostFixture(): array
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(32)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'discussion_prompt',
            'topic_choice' => 'Healthy relationships',
            'body' => 'A published discussion prompt for adult members.',
        ]);
        $post->update(['status' => CommunityPostStatus::Published->value, 'published_at' => now()]);

        return [$connector, $owner, $post->fresh(['connector', 'space'])];
    }

    private function visibleComment(int $postId, int $authorId, string $body, ?int $parentId = null): CommunityComment
    {
        return CommunityComment::query()->create([
            'community_post_id' => $postId,
            'parent_id' => $parentId,
            'author_id' => $authorId,
            'body' => $body,
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);
    }
}
