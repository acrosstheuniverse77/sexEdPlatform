<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\CommunitySpace;
use App\Services\Community\CommunityFeedSettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function __construct(private readonly CommunityFeedSettingsService $settings)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('community.view_any'), 403);

        return view('admin.community.index', [
            'isGloballyFrozen' => $this->settings->isGloballyFrozen(),
            'stats' => [
                'spaces' => CommunitySpace::query()->count(),
                'reported' => CommunityReport::query()->whereIn('status', ['open', 'under_review'])->count(),
                'escalated' => CommunityPost::query()->where('status', 'escalated')->count(),
                'pending' => CommunityPost::query()->where('status', 'pending_review')->count(),
                'featured' => CommunityPost::query()->whereNotNull('featured_at')->count(),
            ],
            'posts' => CommunityPost::query()
                ->with(['connector', 'author', 'reports'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->filled('type'), fn ($query) => $query->where('post_type', $request->string('type')))
                ->when($request->filled('connector_id'), fn ($query) => $query->where('connector_id', $request->integer('connector_id')))
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = $request->string('search')->toString();
                    $query->where(fn ($inner) => $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%"));
                })
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, CommunityPost $communityPost): View
    {
        abort_unless($request->user()->can('community.view_any'), 403);

        return view('admin.community.show', [
            'post' => $communityPost->load(['connector', 'space', 'author', 'comments.author', 'reports.moderationCase', 'moderationActions.actor']),
        ]);
    }
}
