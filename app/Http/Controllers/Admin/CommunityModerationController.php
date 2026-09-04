<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ModerateCommunityContentRequest;
use App\Models\CommunityPost;
use App\Services\Community\CommunityModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityModerationController extends Controller
{
    public function __construct(private readonly CommunityModerationService $moderation) {}

    public function approve(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse|JsonResponse
    {
        $post = $this->moderation->approvePost($request->user(), $communityPost, $request->validated('reason'));

        return $this->respond($request, $post, 'Post approved and published.');
    }

    public function reject(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse|JsonResponse
    {
        $post = $this->moderation->rejectPost($request->user(), $communityPost, $request->validated('reason'));

        return $this->respond($request, $post, 'Post rejected.');
    }

    public function hide(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse|JsonResponse
    {
        $post = $this->moderation->hidePost($request->user(), $communityPost, $request->validated('reason'));

        return $this->respond($request, $post, 'Post hidden.');
    }

    public function restore(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse|JsonResponse
    {
        $post = $this->moderation->restorePost($request->user(), $communityPost, $request->validated('reason'));

        return $this->respond($request, $post, 'Post restored.');
    }

    public function remove(ModerateCommunityContentRequest $request, CommunityPost $communityPost): RedirectResponse|JsonResponse
    {
        $post = $this->moderation->removePost($request->user(), $communityPost, $request->validated('reason'));

        return $this->respond($request, $post, 'Post removed.');
    }

    private function respond(Request $request, CommunityPost $post, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'post_id' => $post->id,
                'status' => $post->status?->value ?? (string) $post->status,
                'status_label' => $post->status?->label() ?? str($post->status)->headline()->toString(),
            ]);
        }

        return back()->with('success', $message);
    }
}
