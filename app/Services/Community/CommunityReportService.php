<?php

namespace App\Services\Community;

use App\Enums\CommunityReportStatus;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\User;
use App\Services\Moderation\SourceAdapters\CommunityFeedModerationAdapter;
use Illuminate\Support\Facades\DB;

class CommunityReportService
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityFeedModerationAdapter $adapter,
    ) {}

    public function reportPost(User $reporter, CommunityPost $post, string $reasonCode, ?string $details = null): CommunityReport
    {
        $post->loadMissing('connector');
        $this->access->abortUnlessCanViewPost($reporter, $post);

        return DB::transaction(function () use ($reporter, $post, $reasonCode, $details): CommunityReport {
            $report = CommunityReport::query()->firstOrNew([
                'community_post_id' => $post->id,
                'community_comment_id' => null,
                'reporter_id' => $reporter->id,
                'status' => CommunityReportStatus::Open,
            ]);

            $report->fill([
                'reported_user_id' => $post->author_id,
                'reason_code' => $reasonCode,
                'details' => $details,
                'status' => CommunityReportStatus::Open,
            ])->save();

            $this->adapter->syncReport($report);

            return $report->fresh(['moderationCase']);
        });
    }

    public function reportComment(User $reporter, CommunityComment $comment, string $reasonCode, ?string $details = null): CommunityReport
    {
        $comment->loadMissing('post.connector');
        $this->access->abortUnlessCanViewComment($reporter, $comment->post, $comment);

        return DB::transaction(function () use ($reporter, $comment, $reasonCode, $details): CommunityReport {
            $report = CommunityReport::query()->firstOrNew([
                'community_post_id' => $comment->community_post_id,
                'community_comment_id' => $comment->id,
                'reporter_id' => $reporter->id,
                'status' => CommunityReportStatus::Open,
            ]);

            $report->fill([
                'reported_user_id' => $comment->author_id,
                'reason_code' => $reasonCode,
                'details' => $details,
                'status' => CommunityReportStatus::Open,
            ])->save();

            $this->adapter->syncReport($report);

            return $report->fresh(['moderationCase']);
        });
    }
}
