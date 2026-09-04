<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityComment;
use App\Models\CommunityPostMedia;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityRichPostSchemaTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_rich_post_media_and_reply_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasTable('community_post_media'));
        $this->assertTrue(Schema::hasColumns('community_post_media', [
            'community_post_id',
            'uploaded_by',
            'media_type',
            'path',
            'mime_type',
            'original_name',
            'size_bytes',
            'display_order',
            'removed_at',
            'removed_by',
        ]));
        $this->assertTrue(Schema::hasColumn('community_comments', 'parent_id'));
    }

    public function test_post_media_and_one_level_comment_relationships_are_traversable(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Schema relationship test for adult members.',
        ]);
        $post->update(['status' => CommunityPostStatus::Published->value]);

        $media = CommunityPostMedia::query()->create([
            'community_post_id' => $post->id,
            'uploaded_by' => $owner->id,
            'media_type' => 'image',
            'path' => 'community-post-media/example.webp',
            'mime_type' => 'image/webp',
            'original_name' => 'example.webp',
            'size_bytes' => 1234,
            'display_order' => 5,
        ]);
        $firstMedia = CommunityPostMedia::query()->create([
            'community_post_id' => $post->id,
            'uploaded_by' => $owner->id,
            'media_type' => 'image',
            'path' => 'community-post-media/first.webp',
            'mime_type' => 'image/webp',
            'original_name' => 'first.webp',
            'size_bytes' => 1000,
            'display_order' => 1,
        ]);
        $removedMedia = CommunityPostMedia::query()->create([
            'community_post_id' => $post->id,
            'uploaded_by' => $owner->id,
            'media_type' => 'image',
            'path' => 'community-post-media/removed.webp',
            'display_order' => 1,
            'removed_at' => now(),
            'removed_by' => $owner->id,
        ]);

        $parent = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Top-level comment.',
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);
        $firstReply = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'parent_id' => $parent->id,
            'author_id' => $owner->id,
            'body' => 'Earlier one-level reply.',
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);
        $reply = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'parent_id' => $parent->id,
            'author_id' => $owner->id,
            'body' => 'One-level reply.',
            'status' => CommunityCommentStatus::Visible->value,
            'prescreen_decision' => 'allow',
        ]);

        $this->assertTrue($post->media()->whereKey($media)->exists());
        $this->assertTrue($post->media()->whereKey($removedMedia)->exists());
        $this->assertSame([$firstMedia->id, $media->id], $post->activeMedia()->pluck('id')->all());
        $this->assertSame($parent->id, $post->topLevelComments()->sole()->id);
        $this->assertTrue($reply->isReply());
        $this->assertSame($parent->id, $reply->parent->id);
        $this->assertSame([$firstReply->id, $reply->id], $parent->replies->pluck('id')->all());
        $this->assertTrue($removedMedia->isRemoved());
    }

    public function test_legacy_post_media_is_backfilled_idempotently(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Legacy media backfill contract.',
        ]);
        $post->forceFill([
            'media_path' => 'community-post-media/legacy.jpg',
            'media_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_original_name' => 'legacy.jpg',
        ])->save();

        $migration = require database_path('migrations/2026_09_03_000001_add_rich_media_and_replies_to_community_hub.php');
        $migration->backfillLegacyMedia();
        $migration->backfillLegacyMedia();

        $this->assertDatabaseCount('community_post_media', 1);
        $this->assertDatabaseHas('community_post_media', [
            'community_post_id' => $post->id,
            'uploaded_by' => $owner->id,
            'media_type' => 'image',
            'path' => 'community-post-media/legacy.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'legacy.jpg',
            'display_order' => 0,
        ]);
    }
}
