<?php

namespace App\Http\Controllers\Connector;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityPostRequest;
use App\Http\Requests\Community\UpdateCommunityPostRequest;
use App\Models\CommunityReport;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityPostService;
use App\Services\Community\CommunitySpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunitySpaceService $spaces,
        private readonly CommunityPostService $posts,
    ) {
    }

    public function index(Request $request, Connector $connector): View
    {
        $this->access->abortUnlessCanViewSpace($request->user(), $connector);
        $space = $this->spaces->spaceForConnector($connector);
        $type = (string) $request->query('type', '');
        $status = (string) $request->query('status', '');
        $tab = (string) $request->query('tab', '');
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'newest');
        $author = trim((string) $request->query('author', ''));
        $date = (string) $request->query('date', '');
        $tag = trim((string) $request->query('tag', ''));
        $canModerate = $this->access->canModerateSpace($request->user(), $connector);
        $postQuery = $connector->communityPosts()
            ->with(['author', 'comments.author', 'reactions', 'reports', 'seminar', 'officialAnswerComment'])
            ->withCount([
                'comments' => fn ($query) => $query->where('status', CommunityCommentStatus::Visible->value),
                'reactions',
            ])
            ->when($type !== '', fn ($query) => $query->where('post_type', $type))
            ->when(
                $status !== '' && $canModerate,
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [
                    CommunityPostStatus::Published->value,
                    CommunityPostStatus::Locked->value,
                ])
            )
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'like', '%'.$search.'%'));
            }))
            ->when($author !== '', fn ($query) => $query->whereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'like', '%'.$author.'%')))
            ->when($tag !== '', fn ($query) => $query->where(function ($tagQuery) use ($tag) {
                $tagQuery->where('title', 'like', '%'.$tag.'%')->orWhere('body', 'like', '%'.$tag.'%');
            }))
            ->when($date !== '', fn ($query) => $query->whereDate('created_at', $date));

        $featuredPosts = $connector->communityPosts()
            ->with(['author', 'comments.author', 'reactions', 'reports', 'seminar', 'officialAnswerComment'])
            ->withCount(['comments', 'reactions'])
            ->whereNotNull('featured_at')
            ->whereIn('status', ['published', 'locked'])
            ->latest('featured_at')
            ->limit(3)
            ->get();

        match ($sort) {
            'trending' => $postQuery->orderByDesc('reactions_count')->orderByDesc('comments_count')->latest('created_at'),
            'most_helpful' => $postQuery
                ->withCount(['reactions as helpful_reactions_count' => fn ($query) => $query->where('reaction_type', 'helpful')])
                ->orderByDesc('helpful_reactions_count')
                ->latest('created_at'),
            'bookmarked' => $postQuery
                ->withCount(['reactions as bookmark_reactions_count' => fn ($query) => $query->where('reaction_type', 'bookmark')])
                ->orderByDesc('bookmark_reactions_count')
                ->latest('created_at'),
            default => $postQuery->latest('created_at'),
        };

        return view('connectors.community.index', [
            'connector' => $connector->loadCount(['memberships as active_members_count' => fn ($query) => $query->where('status', 'active')]),
            'space' => $space,
            'posts' => $postQuery->paginate(10)->withQueryString(),
            'pinnedPost' => $featuredPosts->first(),
            'featuredPosts' => $featuredPosts,
            'hubTabs' => [
                'featured' => 'Featured',
                'announcement' => 'Announcements',
                'event' => 'Events',
                'resource' => 'Resources',
                'moderated_question' => 'Q&A',
                'discussion_prompt' => 'Discussions',
            ],
            'activeType' => $type,
            'activeStatus' => $status,
            'activeTab' => $tab,
            'activeSearch' => $search,
            'activeSort' => $sort,
            'activeAuthor' => $author,
            'activeDate' => $date,
            'activeTag' => $tag,
            'upcomingSeminars' => $connector->seminars()->upcoming()->orderByRaw('COALESCE(starts_at, schedule) asc')->limit(3)->get(),
            'pendingCount' => $connector->communityPosts()->where('status', 'pending_review')->count(),
            'reportedCount' => CommunityReport::query()
                ->whereHas('post', fn ($query) => $query->where('connector_id', $connector->id))
                ->whereIn('status', ['open', 'under_review'])
                ->count(),
            'escalatedCount' => $connector->communityPosts()->where('status', 'escalated')->count(),
            'canCreatePost' => $this->access->canCreatePost($request->user(), $connector),
            'canModerate' => $canModerate,
        ]);
    }

    public function create(Request $request, Connector $connector): View
    {
        $this->access->abortUnlessCanCreatePost($request->user(), $connector);

        return view('connectors.community.create', [
            'connector' => $connector,
            'seminars' => $this->seminarOptions($connector),
        ]);
    }

    public function store(StoreCommunityPostRequest $request, Connector $connector): RedirectResponse
    {
        $post = $this->posts->create($request->user(), $connector, $request->validated());

        return redirect()
            ->route('connector.community.show', [$connector, $post])
            ->with('success', 'Community post submitted.');
    }

    public function show(Request $request, Connector $connector, CommunityPost $communityPost): View
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->access->abortUnlessCanViewSpace($request->user(), $connector);
        $canModerate = $this->access->canModerateSpace($request->user(), $connector);

        abort_if(! $canModerate && ! $communityPost->isVisibleToMembers(), 404);

        return view('connectors.community.show', [
            'connector' => $connector,
            'post' => $communityPost->load([
                'author',
                'comments' => fn ($query) => $canModerate
                    ? $query
                    : $query->where('status', CommunityCommentStatus::Visible->value),
                'comments.author',
                'reactions',
                'reports',
            ]),
            'canModerate' => $canModerate,
            'canComment' => $this->access->canViewSpace($request->user(), $connector) && $communityPost->isVisibleToMembers() && ! $communityPost->isLocked(),
        ]);
    }

    public function edit(Request $request, Connector $connector, CommunityPost $communityPost): View
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        abort_unless($this->access->canEditPost($request->user(), $communityPost), 403);

        return view('connectors.community.edit', [
            'connector' => $connector,
            'post' => $communityPost,
            'seminars' => $this->seminarOptions($connector),
        ]);
    }

    public function update(UpdateCommunityPostRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $post = $this->posts->update($request->user(), $communityPost, $request->validated());

        return redirect()
            ->route('connector.community.show', [$connector, $post])
            ->with('success', 'Community post updated.');
    }

    public function moderation(Request $request, Connector $connector): View
    {
        $this->access->abortUnlessCanModerateSpace($request->user(), $connector);

        $tab = (string) $request->query('tab', 'pending');
        $query = $connector->communityPosts()
            ->with(['author', 'reports.reporter', 'comments.author', 'moderationActions.actor'])
            ->withCount(['reports', 'comments']);

        match ($tab) {
            'reported' => $query->whereHas('reports', fn ($reportQuery) => $reportQuery->whereIn('status', ['open', 'under_review'])),
            'hidden' => $query->where('status', 'hidden'),
            'rejected' => $query->where('status', 'removed'),
            'escalated' => $query->where('status', 'escalated'),
            default => $query->where('status', 'pending_review'),
        };

        return view('connectors.community.moderation.index', [
            'connector' => $connector,
            'tab' => $tab,
            'items' => $query->latest('created_at')->paginate(12)->withQueryString(),
            'counts' => [
                'pending' => $connector->communityPosts()->where('status', 'pending_review')->count(),
                'reported' => CommunityReport::query()
                    ->whereHas('post', fn ($postQuery) => $postQuery->where('connector_id', $connector->id))
                    ->whereIn('status', ['open', 'under_review'])
                    ->count(),
                'hidden' => $connector->communityPosts()->where('status', 'hidden')->count(),
                'rejected' => $connector->communityPosts()->where('status', 'removed')->count(),
                'escalated' => $connector->communityPosts()->where('status', 'escalated')->count(),
            ],
        ]);
    }

    private function seminarOptions(Connector $connector): Collection
    {
        return $connector->seminars()
            ->latest('created_at')
            ->limit(25)
            ->get(['id', 'title', 'status', 'starts_at', 'schedule']);
    }
}
