<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPostType;
use App\Enums\CommunityReactionType;
use App\Models\CommunityComment;
use App\Models\CommunityModerationAction;
use App\Models\CommunityPost;
use App\Models\CommunityPostVersion;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\CommunitySpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunitySchemaTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_community_feed_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('community_spaces', [
            'connector_id', 'name', 'status', 'settings', 'frozen_at', 'frozen_by', 'freeze_reason',
        ]));

        $this->assertTrue(Schema::hasColumns('community_posts', [
            'community_space_id', 'connector_id', 'author_id', 'post_type', 'status', 'title', 'body',
            'resource_url', 'prescreen_decision', 'prescreen_flags', 'published_at', 'published_by',
            'locked_at', 'locked_by', 'hidden_at', 'hidden_by', 'removed_at', 'removed_by',
            'escalated_at', 'escalated_by', 'moderation_case_id',
        ]));

        $this->assertTrue(Schema::hasColumns('community_comments', [
            'community_post_id', 'author_id', 'body', 'status', 'prescreen_decision', 'prescreen_flags',
            'hidden_at', 'hidden_by', 'removed_at', 'removed_by', 'escalated_at', 'escalated_by',
        ]));

        $this->assertTrue(Schema::hasColumns('community_reactions', [
            'community_post_id', 'user_id', 'reaction_type',
        ]));

        $this->assertTrue(Schema::hasColumns('community_reports', [
            'community_post_id', 'community_comment_id', 'reporter_id', 'reported_user_id',
            'reason_code', 'details', 'status', 'moderation_case_id',
        ]));

        $this->assertTrue(Schema::hasColumns('community_post_versions', [
            'community_post_id', 'edited_by', 'version_number', 'title', 'body', 'resource_url',
            'post_type', 'prescreen_decision', 'prescreen_flags',
        ]));

        $this->assertTrue(Schema::hasColumns('community_moderation_actions', [
            'connector_id', 'community_space_id', 'actor_id', 'target_type', 'target_id',
            'action_type', 'previous_status', 'new_status', 'reason', 'metadata',
        ]));

        $this->assertTrue(Schema::hasColumns('community_feed_settings', [
            'scope_type', 'scope_id', 'settings', 'updated_by',
        ]));
    }

    public function test_relationships_connect_space_post_comments_reactions_reports_and_versions(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => $connector->name.' Community',
            'status' => 'active',
        ]);

        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Clinic schedule',
            'body' => 'A resource announcement for adults.',
            'prescreen_decision' => 'allow',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $comment = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Helpful.',
            'status' => 'visible',
            'prescreen_decision' => 'allow',
        ]);

        $reaction = CommunityReaction::query()->create([
            'community_post_id' => $post->id,
            'user_id' => $owner->id,
            'reaction_type' => CommunityReactionType::Helpful,
        ]);

        $report = CommunityReport::query()->create([
            'community_post_id' => $post->id,
            'reporter_id' => $owner->id,
            'reported_user_id' => $owner->id,
            'reason_code' => 'safety_concern',
            'details' => 'Needs a second look.',
            'status' => 'open',
        ]);

        $version = CommunityPostVersion::query()->create([
            'community_post_id' => $post->id,
            'edited_by' => $owner->id,
            'version_number' => 1,
            'title' => $post->title,
            'body' => $post->body,
            'post_type' => $post->post_type,
            'prescreen_decision' => 'allow',
        ]);

        CommunityModerationAction::query()->create([
            'connector_id' => $connector->id,
            'community_space_id' => $space->id,
            'actor_id' => $owner->id,
            'target_type' => CommunityPost::class,
            'target_id' => $post->id,
            'action_type' => 'approve',
            'previous_status' => 'pending_review',
            'new_status' => 'published',
            'reason' => 'Approved for publication.',
        ]);

        $this->assertTrue($connector->communitySpaces()->whereKey($space)->exists());
        $this->assertSame($space->id, $post->space->id);
        $this->assertSame($connector->id, $post->connector->id);
        $this->assertSame($owner->id, $post->author->id);
        $this->assertSame($comment->id, $post->comments->first()->id);
        $this->assertSame($reaction->id, $post->reactions->first()->id);
        $this->assertSame($report->id, $post->reports->first()->id);
        $this->assertSame($version->id, $post->versions->first()->id);
        $this->assertTrue($post->isPublished());
        $this->assertFalse($post->isLocked());
        $this->assertTrue($post->isVisibleToMembers());
    }
}
