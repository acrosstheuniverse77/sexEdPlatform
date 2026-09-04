<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityPinController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityModerationService $moderation,
    ) {
    }

    public function store(Request $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->moderation->featurePost($request->user(), $communityPost, 'Pinned in Community Hub.');

        return back()->with('success', 'Post pinned.');
    }

    public function destroy(Request $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->moderation->unfeaturePost($request->user(), $communityPost, 'Unpinned in Community Hub.');

        return back()->with('success', 'Post unpinned.');
    }
}