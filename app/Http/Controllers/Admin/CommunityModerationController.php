<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ModerateCommunityContentRequest;
use App\Models\CommunityPost;
use App\Services\Community\CommunityModerationService;
use Illuminate\Http\RedirectResponse;

class CommunityModerationController extends Controller
{
    public function __construct(private readonly CommunityModerationService $moderation)
    {
    }

    public function hide(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse
    {
        $this->moderation->hidePost($request->user(), $communityPost, $request->validated('reason'));

        return back()->with('success', 'Post hidden.');
    }

    public function restore(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse
    {
        $this->moderation->restorePost($request->user(), $communityPost, $request->validated('reason'));

        return back()->with('success', 'Post restored.');
    }

    public function remove(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse
    {
        $this->moderation->removePost($request->user(), $communityPost, $request->validated('reason'));

        return back()->with('success', 'Post removed.');
    }
}
