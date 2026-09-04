<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ModerateCommunityContentRequest;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityModerationService;
use Illuminate\Http\RedirectResponse;

class CommunityModerationController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityModerationService $moderation,
    ) {
    }

    public function approve(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'approvePost', 'Post approved.');
    }

    public function reject(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'rejectPost', 'Post rejected.');
    }

    public function hide(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'hidePost', 'Post hidden.');
    }

    public function lock(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'lockPost', 'Thread locked.');
    }

    public function unlock(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'unlockPost', 'Thread unlocked.');
    }

    public function restore(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'restorePost', 'Post restored.');
    }

    public function remove(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'removePost', 'Post removed.');
    }

    public function escalate(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        return $this->transition($request, $connector, $communityPost, 'escalatePost', 'Post escalated to platform admins.');
    }

    private function transition(ModerateCommunityContentRequest $request, Connector $connector, CommunityPost $communityPost, string $method, string $message): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->moderation->{$method}($request->user(), $communityPost, $request->validated('reason'));

        return back()->with('success', $message);
    }
}
