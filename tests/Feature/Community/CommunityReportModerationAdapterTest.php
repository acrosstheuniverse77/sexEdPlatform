<?php

namespace Tests\Feature\Community;

use App\Enums\ModerationCaseSource;
use App\Models\CommunityReport;
use App\Models\ModerationCase;
use App\Models\User;
use App\Services\Community\CommunityInteractionService;
use App\Services\Community\CommunityPostService;
use App\Services\Community\CommunityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityReportModerationAdapterTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_community_post_report_creates_central_moderation_case(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $reporter = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $report = app(CommunityReportService::class)->reportPost(
            $reporter,
            $post,
            'unsafe_contact',
            'This post asks people to move to private chat.',
        );

        $case = $report->moderationCase;

        $this->assertNotNull($case);
        $this->assertSame(ModerationCaseSource::CommunityFeed, $case->case_source);
        $this->assertSame('community_report', $case->content_type);
        $this->assertSame($report->id, $case->content_id);
        $this->assertSame($author->id, $case->reported_user_id);
        $this->assertSame('community_post', $case->metadata['source_trace']['target_type']);
        $this->assertSame($post->id, $case->metadata['source_trace']['post_id']);
    }

    public function test_repeated_open_report_by_same_reporter_updates_same_case(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $reporter = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $service = app(CommunityReportService::class);

        $first = $service->reportPost($reporter, $post, 'unsafe_contact', 'First detail.');
        $second = $service->reportPost($reporter, $post, 'unsafe_contact', 'Updated detail.');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->moderation_case_id, $second->moderation_case_id);
        $this->assertSame(1, CommunityReport::query()->count());
        $this->assertSame(1, ModerationCase::query()->where('case_source', ModerationCaseSource::CommunityFeed->value)->count());
        $this->assertSame('Updated detail.', $second->moderationCase->metadata['source_trace']['details']);
    }

    public function test_member_cannot_report_posts_hidden_from_the_member_feed(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $reporter = $this->createAdultConnectorMember($connector, ['community.view_space']);

        foreach (['draft', 'pending_review', 'hidden', 'removed', 'escalated', 'archived'] as $status) {
            $post->forceFill(['status' => $status])->save();

            try {
                app(CommunityReportService::class)->reportPost($reporter, $post, 'other', 'Guessed non-public post.');
                $this->fail("A member was allowed to report a {$status} post.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertDatabaseCount('community_reports', 0);
        $this->assertDatabaseCount('moderation_cases', 0);
    }

    public function test_comment_report_tracks_comment_target_and_reported_author(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $commenter = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $reporter = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $parent = app(CommunityInteractionService::class)->comment($author, $post, 'Helpful public response.');
        $comment = app(CommunityInteractionService::class)->comment($commenter, $post, 'Helpful public reply.', $parent);

        $report = app(CommunityReportService::class)->reportComment($reporter, $comment, 'harassment', 'This reply is not appropriate.');

        $this->assertSame($commenter->id, $report->moderationCase->reported_user_id);
        $this->assertSame('community_comment', $report->moderationCase->metadata['source_trace']['target_type']);
        $this->assertSame($comment->id, $report->moderationCase->metadata['source_trace']['comment_id']);
        $this->assertSame($parent->id, $report->moderationCase->metadata['source_trace']['parent_comment_id']);
    }

    private function publishedPostFixture(): array
    {
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'announcement',
            'title' => 'Community reminder',
            'body' => 'Join the public webinar about consent education.',
            'resource_url' => null,
        ]);

        return [$connector, $author, $post];
    }
}
