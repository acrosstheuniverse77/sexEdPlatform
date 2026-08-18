<?php

namespace App\Services\Moderation\SourceAdapters;

use App\Enums\CommunityReportStatus;
use App\Enums\ModerationCaseSource;
use App\Enums\ModerationCaseStatus;
use App\Models\CommunityReport;
use App\Services\Moderation\ModerationCaseIntakeService;

class CommunityFeedModerationAdapter
{
    public function __construct(private readonly ModerationCaseIntakeService $moderationCaseIntakeService)
    {
    }

    public function syncReport(CommunityReport $report): void
    {
        $report->loadMissing([
            'post:id,connector_id,community_space_id,author_id,title,status,post_type',
            'comment:id,community_post_id,author_id,status',
            'reporter:id,name,email,role',
            'reportedUser:id,name,email,role',
        ]);

        $moderationCase = $this->moderationCaseIntakeService->upsertFromSource(
            source: ModerationCaseSource::CommunityFeed,
            contentType: 'community_report',
            contentId: (int) $report->id,
            reportedUserId: (int) $report->reported_user_id,
            reporterId: (int) $report->reporter_id,
            status: $this->resolveStatus($report),
            decision: $this->resolveDecision($report),
            metadata: [
                'source_trace' => [
                    'source_record_id' => (int) $report->id,
                    'target_type' => $report->community_comment_id ? 'community_comment' : 'community_post',
                    'post_id' => (int) $report->community_post_id,
                    'comment_id' => $report->community_comment_id ? (int) $report->community_comment_id : null,
                    'connector_id' => $report->post?->connector_id,
                    'community_space_id' => $report->post?->community_space_id,
                    'post_type' => $report->post?->post_type?->value ?? $report->post?->post_type,
                    'post_status' => $report->post?->status?->value ?? $report->post?->status,
                    'comment_status' => $report->comment?->status?->value ?? $report->comment?->status,
                    'reason_code' => $report->reason_code,
                    'details' => $report->details,
                    'report_status' => $this->resolveReportStatus($report)->value,
                    'reported_at' => optional($report->created_at)?->toDateTimeString(),
                    'updated_at' => optional($report->updated_at)?->toDateTimeString(),
                ],
            ],
        );

        if ((int) $report->moderation_case_id !== (int) $moderationCase->id) {
            $report->forceFill(['moderation_case_id' => $moderationCase->id])->save();
        }
    }

    private function resolveStatus(CommunityReport $report): ModerationCaseStatus
    {
        return match ($this->resolveReportStatus($report)) {
            CommunityReportStatus::UnderReview => ModerationCaseStatus::Investigating,
            CommunityReportStatus::Resolved, CommunityReportStatus::Dismissed => ModerationCaseStatus::Resolved,
            default => ModerationCaseStatus::Reported,
        };
    }

    private function resolveDecision(CommunityReport $report): ?string
    {
        $status = $this->resolveReportStatus($report);

        return in_array($status, [CommunityReportStatus::Resolved, CommunityReportStatus::Dismissed], true)
            ? $status->value
            : null;
    }

    private function resolveReportStatus(CommunityReport $report): CommunityReportStatus
    {
        return $report->status instanceof CommunityReportStatus
            ? $report->status
            : CommunityReportStatus::from((string) $report->status);
    }
}
