<?php

namespace Tests\Feature\Community;

use App\Models\CommunityComment;
use App\Models\User;
use App\Services\Community\CommunityModerationService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityHubModerationUiTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_moderator_can_feature_post_and_mark_official_answer(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'How should we prepare for the seminar?',
            'body' => 'What should adult members review before attending?',
            'resource_url' => null,
        ]);

        $comment = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Please review the consent basics module before the session.',
            'status' => 'visible',
            'prescreen_decision' => 'allow',
        ]);

        $moderation = app(CommunityModerationService::class);
        $featured = $moderation->featurePost($owner, $post, 'Important question for this week.');
        $answered = $moderation->markOfficialAnswer($owner, $featured, $comment, 'Connector-approved answer.');

        $this->assertTrue($featured->fresh()->isFeatured());
        $this->assertSame($comment->id, $answered->fresh()->official_answer_comment_id);
    }
}
