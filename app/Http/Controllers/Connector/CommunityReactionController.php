<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityInteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\CommunityReactionType;

class CommunityReactionController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityInteractionService $interactions,
    ) {
    }

    public function store(Request $request, Connector $connector, CommunityPost $communityPost): JsonResponse|RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $data = $request->validate(['reaction_type' => ['required', Rule::in(CommunityReactionType::values())]]);
        $result = $this->interactions->toggleReaction($request->user(), $communityPost, $data['reaction_type']);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('success', $result['active'] ? 'Reaction added.' : 'Reaction removed.');
    }

    public function destroy(Request $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $data = $request->validate(['reaction_type' => ['required', Rule::in(CommunityReactionType::values())]]);
        $this->interactions->removeReaction($request->user(), $communityPost, $data['reaction_type']);

        return back()->with('success', 'Reaction removed.');
    }
}
