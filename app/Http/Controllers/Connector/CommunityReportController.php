<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityReportRequest;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityReportService;
use Illuminate\Http\RedirectResponse;

class CommunityReportController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityReportService $reports,
    ) {
    }

    public function store(StoreCommunityReportRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $data = $request->validated();

        if (! empty($data['community_comment_id'])) {
            $comment = CommunityComment::query()->where('community_post_id', $communityPost->id)->findOrFail($data['community_comment_id']);
            $this->reports->reportComment($request->user(), $comment, $data['reason_code'], $data['details'] ?? null);
        } else {
            $this->reports->reportPost($request->user(), $communityPost, $data['reason_code'], $data['details'] ?? null);
        }

        return back()->with('success', 'Report sent to moderation.');
    }
}
