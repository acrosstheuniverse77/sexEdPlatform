<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\CommunitySpace;
use App\Services\Community\CommunityFeedSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function __construct(private readonly CommunityFeedSettingsService $settings) {}

    public function index(Request $request): View
    {
        $this->authorizeHub($request);

        return view('admin.community.index', [
            'isGloballyFrozen' => $this->settings->isGloballyFrozen(),
            'stats' => [
                'spaces' => CommunitySpace::query()->where('status', 'active')->count(),
                'reported' => CommunityReport::query()->whereIn('status', ['open', 'under_review'])->count(),
                'pending' => CommunityPost::query()->where('status', 'pending_review')->count(),
                'published' => CommunityPost::query()->where('status', 'published')->count(),
            ],
            'communities' => $this->communitiesQuery()->get(),
            'posts' => $this->postsQuery($request)->latest('created_at')->paginate(25)->withQueryString(),
            'activeSection' => 'overview',
        ]);
    }

    public function communities(Request $request): View
    {
        $this->authorizeHub($request);

        return view('admin.community.workspace', [
            'activeSection' => 'communities',
            'workspaceTitle' => 'Communities',
            'communities' => $this->communitiesQuery()->get(),
            'posts' => null,
            'queue' => null,
        ]);
    }

    public function moderation(Request $request): View
    {
        return $this->moderationWorkspace($request, 'all');
    }

    public function moderationPending(Request $request): View
    {
        return $this->moderationWorkspace($request, 'pending');
    }

    public function moderationReports(Request $request): View
    {
        return $this->moderationWorkspace($request, 'reports');
    }

    public function content(Request $request): View
    {
        return $this->contentWorkspace($request, 'posts');
    }

    public function contentFeatured(Request $request): View
    {
        return $this->contentWorkspace($request, 'featured');
    }

    public function contentAnnouncements(Request $request): View
    {
        return $this->contentWorkspace($request, 'announcements');
    }

    public function contentDrafts(Request $request): View
    {
        return $this->contentWorkspace($request, 'drafts');
    }

    public function contentArchived(Request $request): View
    {
        return $this->contentWorkspace($request, 'archived');
    }

    public function show(Request $request, CommunityPost $communityPost): View
    {
        $this->authorizeHub($request);

        return view('admin.community.show', [
            'post' => $communityPost->load(['connector', 'space', 'author', 'activeMedia', 'comments.author', 'reports.moderationCase', 'moderationActions.actor']),
        ]);
    }

    public function communityShow(Request $request, CommunitySpace $communitySpace): View
    {
        $this->authorizeHub($request);

        $communitySpace->load('connector');
        $communitySpace->loadCount('posts');
        $communitySpace->members_count = $communitySpace->connector?->memberships()->where('status', 'active')->count() ?? 0;

        return view('admin.community.community-show', [
            'community' => $communitySpace,
            'activeSection' => 'communities',
        ]);
    }

    public function communityPosts(Request $request, CommunitySpace $communitySpace): View
    {
        $this->authorizeHub($request);

        return view('admin.community.community-posts', [
            'community' => $communitySpace->load('connector'),
            'posts' => $this->postsQuery($request)
                ->where('community_space_id', $communitySpace->id)
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
            'activeSection' => 'posts',
        ]);
    }

    public function communityMembers(Request $request, CommunitySpace $communitySpace): View
    {
        $this->authorizeHub($request);

        $communitySpace->load('connector');
        $memberships = $communitySpace->connector?->memberships()
            ->where('status', 'active')
            ->with('user')
            ->latest('accepted_at')
            ->get() ?? collect();
        $postCounts = CommunityPost::query()
            ->where('community_space_id', $communitySpace->id)
            ->selectRaw('author_id, count(*) as posts_count')
            ->groupBy('author_id')
            ->pluck('posts_count', 'author_id');
        $memberships->each(fn ($membership) => $membership->posts_count = (int) ($postCounts[$membership->user_id] ?? 0));

        return view('admin.community.community-members', [
            'community' => $communitySpace,
            'memberships' => $memberships,
            'activeSection' => 'members',
        ]);
    }

    public function editCommunity(Request $request, CommunitySpace $communitySpace): View
    {
        $this->authorizeManage($request);

        return view('admin.community.community-edit', [
            'community' => $communitySpace->load('connector'),
            'activeSection' => 'settings',
        ]);
    }

    public function updateCommunity(Request $request, CommunitySpace $communitySpace): RedirectResponse
    {
        $this->authorizeManage($request);

        $communitySpace->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]));

        return redirect()->route('admin.community.communities.show', $communitySpace)
            ->with('success', 'Community updated.');
    }

    public function deactivateCommunity(Request $request, CommunitySpace $communitySpace): RedirectResponse
    {
        $this->authorizeManage($request);
        $communitySpace->update(['status' => 'inactive']);

        return redirect()->route('admin.community.communities.show', $communitySpace)
            ->with('success', 'Community deactivated.');
    }

    private function moderationWorkspace(Request $request, string $queue): View
    {
        $this->authorizeHub($request);

        $posts = $this->postsQuery($request);

        match ($queue) {
            'pending' => $posts->where('status', 'pending_review'),
            'reports' => $posts->whereHas('reports', fn (Builder $query) => $query->whereIn('status', ['open', 'under_review'])),
            default => $posts->where(function (Builder $query): void {
                $query->where('status', 'pending_review')
                    ->orWhereHas('reports', fn (Builder $reports) => $reports->whereIn('status', ['open', 'under_review']));
            }),
        };

        return view('admin.community.workspace', [
            'activeSection' => 'moderation',
            'workspaceTitle' => match ($queue) {
                'pending' => 'Pending Review',
                'reports' => 'Reports',
                default => 'Moderation',
            },
            'communities' => null,
            'communityOptions' => $this->communitiesQuery()->get(),
            'posts' => $posts->latest('created_at')->paginate(25)->withQueryString(),
            'queue' => $queue,
        ]);
    }

    private function contentWorkspace(Request $request, string $section): View
    {
        $this->authorizeHub($request);

        $posts = $this->postsQuery($request);

        match ($section) {
            'featured' => $posts->whereNotNull('featured_at'),
            'drafts' => $posts->where('status', 'draft'),
            'archived' => $posts->whereIn('status', ['archived', 'removed']),
            default => null,
        };

        if ($section === 'announcements') {
            $posts->where('post_type', 'announcement');
        }

        return view('admin.community.workspace', [
            'activeSection' => 'content',
            'workspaceTitle' => match ($section) {
                'featured' => 'Featured Content',
                'drafts' => 'Drafts',
                'archived' => 'Archived',
                'announcements' => 'Announcements',
                default => 'Posts',
            },
            'communities' => null,
            'communityOptions' => $this->communitiesQuery()->get(),
            'posts' => $posts->latest('created_at')->paginate(25)->withQueryString(),
            'queue' => null,
        ]);
    }

    private function communitiesQuery()
    {
        return CommunitySpace::query()
            ->where('status', 'active')
            ->with([
                'connector:id,name,status',
                'connector.memberships' => fn ($query) => $query->where('status', 'active'),
            ])
            ->withCount('posts')
            ->latest('created_at');
    }

    private function postsQuery(Request $request): Builder
    {
        return CommunityPost::query()
            ->with(['connector', 'space', 'author'])
            ->withCount(['reports as open_reports_count' => fn (Builder $query) => $query->whereIn('status', ['open', 'under_review'])])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('post_type', $request->string('type')->toString()))
            ->when($request->filled('connector_id'), fn (Builder $query) => $query->where('connector_id', $request->integer('connector_id')))
            ->when($request->filled('date'), function (Builder $query) use ($request): void {
                $days = max(1, min(365, $request->integer('date')));
                $query->where('created_at', '>=', now()->subDays($days));
            })
            ->withCount(['comments', 'upvotes'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%"));
            });
    }

    private function authorizeHub(Request $request): void
    {
        abort_unless($request->user()->can('community.view_any'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('community.manage_settings'), 403);
    }
}
