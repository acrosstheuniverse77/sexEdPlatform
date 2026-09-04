<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityInteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityUpvoteController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityInteractionService $interactions,
    ) {
    }

    public function togglePost(Request $request, Connector $connector, CommunityPost $communityPost): JsonResponse|RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);

        $result = $this->interactions->togglePostUpvote($request->user(), $communityPost);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('success', $result['active'] ? 'Post upvoted.' : 'Post upvote removed.');
    }

    public function toggleComment(Request $request, Connector $connector, CommunityPost $communityPost, CommunityComment $communityComment): JsonResponse|RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        abort_unless((int) $communityComment->community_post_id === (int) $communityPost->id, 404);

        $result = $this->interactions->toggleCommentUpvote($request->user(), $communityPost, $communityComment);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('success', $result['active'] ? 'Comment upvoted.' : 'Comment upvote removed.');
    }
}