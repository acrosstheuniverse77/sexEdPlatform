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

        $post->forceFill(['status' => 'published', 'published_at' => now(), 'published_by' => $owner->id])->save();

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

    public function test_official_answer_must_be_a_visible_top_level_comment(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'body' => 'Which public resource should members review?',
        ]);
        $post->forceFill(['status' => 'published', 'published_at' => now(), 'published_by' => $owner->id])->save();

        $parent = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'A visible top-level answer.',
            'status' => 'visible',
            'prescreen_decision' => 'allow',
        ]);
        $reply = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'parent_id' => $parent->id,
            'author_id' => $owner->id,
            'body' => 'A reply cannot be the official answer.',
            'status' => 'visible',
            'prescreen_decision' => 'allow',
        ]);
        $hidden = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'A hidden root cannot be the official answer.',
            'status' => 'hidden',
            'prescreen_decision' => 'allow',
            'hidden_at' => now(),
            'hidden_by' => $owner->id,
        ]);

        foreach ([$reply, $hidden] as $invalidAnswer) {
            try {
                app(CommunityModerationService::class)->markOfficialAnswer(
                    $owner,
                    $post,
                    $invalidAnswer,
                    'Attempted invalid official answer.',
                );

                $this->fail('An invalid official answer was accepted.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }

        $this->assertNull($post->fresh()->official_answer_comment_id);
    }

    public function test_hiding_or_removing_the_official_answer_clears_the_post_reference(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'body' => 'Which visible response should be official?',
        ]);
        $post->forceFill(['status' => 'published', 'published_at' => now(), 'published_by' => $owner->id])->save();
        $moderation = app(CommunityModerationService::class);

        foreach (['hideComment', 'removeComment'] as $transition) {
            $comment = CommunityComment::query()->create([
                'community_post_id' => $post->id,
                'author_id' => $owner->id,
                'body' => 'A visible answer before moderation.',
                'status' => 'visible',
                'prescreen_decision' => 'allow',
            ]);

            $moderation->markOfficialAnswer($owner, $post->fresh(), $comment, 'Choose official answer.');
            $this->assertSame($comment->id, $post->fresh()->official_answer_comment_id);

            $moderation->{$transition}($owner, $comment, 'No longer member-visible.');
            $this->assertNull($post->fresh()->official_answer_comment_id);
        }
    }
}
