<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostType;
use App\Enums\CommunityReactionType;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityHubTaxonomyTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_hub_post_types_and_fields_are_available(): void
    {
        $this->assertContains('event', CommunityPostType::values());
        $this->assertContains('discussion_prompt', CommunityPostType::values());
        $this->assertTrue(Schema::hasColumns('community_posts', [
            'featured_at',
            'featured_by',
            'seminar_id',
            'official_answer_comment_id',
        ]));
    }

    public function test_configured_reaction_labels_match_existing_reaction_types(): void
    {
        $this->assertSame(
            CommunityReactionType::values(),
            array_keys(config('community_feed.reactions', []))
        );
    }

    public function test_post_helpers_identify_featured_event_and_question_posts(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $space = $connector->communitySpaces()->firstOrCreate([
            'connector_id' => $connector->id,
        ], [
            'name' => $connector->name.' Community',
            'status' => 'active',
        ]);

        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => 'event',
            'status' => 'published',
            'title' => 'Community health seminar',
            'body' => 'Join the seminar for verified adult members.',
            'prescreen_decision' => 'allow',
            'featured_at' => now(),
            'featured_by' => $owner->id,
        ]);

        $this->assertTrue($post->isFeatured());
        $this->assertTrue($post->isEvent());
        $this->assertFalse($post->isQuestion());
    }
}
