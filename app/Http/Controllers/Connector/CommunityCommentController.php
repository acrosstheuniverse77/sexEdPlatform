<?php

namespace App\Http\Controllers\Connector;

use App\Enums\CommunityCommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityCommentRequest;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityInteractionService;
use Illuminate\Http\RedirectResponse;

class CommunityCommentController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityInteractionService $interactions,
    ) {}

    public function store(StoreCommunityCommentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $parentId = $request->validated('parent_id');
        $parent = $parentId !== null
            ? $communityPost->comments()
                ->whereNull('parent_id')
                ->where('status', CommunityCommentStatus::Visible->value)
                ->findOrFail($parentId)
            : null;
        $this->interactions->comment($request->user(), $communityPost, $request->validated('body'), $parent);

        return redirect()
            ->to(route('connector.community.show', [$connector, $communityPost]).'#comments')
            ->with('success', $parent ? 'Reply posted.' : 'Comment submitted.');
    }
}
