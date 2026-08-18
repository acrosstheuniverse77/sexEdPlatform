<?php

namespace App\Http\Controllers\Connector;

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
    ) {
    }

    public function store(StoreCommunityCommentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->interactions->comment($request->user(), $communityPost, $request->validated('body'));

        return back()->with('success', 'Comment submitted.');
    }
}
