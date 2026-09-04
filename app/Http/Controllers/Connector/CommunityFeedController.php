<?php

namespace App\Http\Controllers\Connector;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPostType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityPostRequest;
use App\Http\Requests\Community\UpdateCommunityPostRequest;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\CommunityReport;
use App\Models\Connector;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityPostService;
use App\Services\Community\CommunitySpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunitySpaceService $spaces,
        private readonly CommunityPostService $posts,
    ) {}

    public function index(Request $request, Connector $connector): View
    {
        $this->access->abortUnlessCanViewSpace($request->user(), $connector);

        $space = $this->spaces->spaceForConnector($connector);
        $type = (string) $request->query('type', '');
        $topic = trim((string) $request->query('topic', ''));
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'top');
        $viewerId = (int) $request->user()->id;
        $canModerate = $this->access->canModerateSpace($request->user(), $connector);

        $postQuery = $connector->communityPosts()
            ->with([
                ...$this->communityAuthorRelations($connector),
                'activeMedia',
                'upvotes' => fn ($query) => $query->where('user_id', $viewerId),
                'seminar',
                'officialAnswerComment',
            ])
            ->withCount([
                'upvotes',
                'comments as visible_comments_count' => fn ($query) => $query->memberVisible(),
            ])
            ->when($type !== '', fn ($query) => $query->where('post_type', $type))
            ->when($topic !== '', fn ($query) => $query->where('topic', $topic))
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
                    ->orWhere('topic', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'like', '%'.$search.'%'));
            }));

        if ($sort === 'newest') {
            $postQuery
                ->orderByRaw('featured_at is null')
                ->latest('created_at')
                ->orderByDesc('id');
        } else {
            $postQuery
                ->orderByRaw('featured_at is null')
                ->latest('featured_at')
                ->orderByDesc('upvotes_count')
                ->orderByDesc('visible_comments_count')
                ->latest('created_at')
                ->orderByDesc('id');
        }

        $topicQuery = $connector->communityPosts()->whereNotNull('topic');

        if (! $canModerate) {
            $topicQuery->whereIn('status', [
                CommunityPostStatus::Published->value,
                CommunityPostStatus::Locked->value,
            ]);
        }

        $topics = collect(StoreCommunityPostRequest::TOPICS)
            ->reject(fn (string $item) => $item === 'Other')
            ->merge($topicQuery->distinct()->pluck('topic'))
            ->filter()
            ->unique()
            ->values();

        return view('connectors.community.index', [
            'connector' => $connector,
            'space' => $space,
            'posts' => $postQuery->paginate(10)->withQueryString(),
            'postTypes' => CommunityPostType::cases(),
            'topics' => $topics,
            'activeType' => $type,
            'activeTopic' => $topic,
            'activeStatus' => $status,
            'activeSearch' => $search,
            'activeSort' => in_array($sort, ['top', 'newest'], true) ? $sort : 'top',
            'statusOptions' => [
                CommunityPostStatus::Published,
                CommunityPostStatus::PendingReview,
                CommunityPostStatus::Locked,
                CommunityPostStatus::Hidden,
                CommunityPostStatus::Escalated,
            ],
            'upcomingSeminars' => $connector->seminars()->upcoming()->orderByRaw('COALESCE(starts_at, schedule) asc')->limit(3)->get(),
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
            'topics' => StoreCommunityPostRequest::TOPICS,
            'postTypes' => CommunityPostType::cases(),
        ]);
    }

    public function store(StoreCommunityPostRequest $request, Connector $connector): RedirectResponse
    {
        $post = $this->posts->create($request->user(), $connector, $request->validated());

        $message = match ($post->status) {
            CommunityPostStatus::Published => 'Post published.',
            CommunityPostStatus::PendingReview => 'Post received and is being checked.',
            CommunityPostStatus::Escalated => 'Post received and is being checked.',
            default => 'Post received.',
        };

        return redirect()
            ->route('connector.community.show', [$connector, $post])
            ->with('success', $message);
    }

    public function show(Request $request, Connector $connector, CommunityPost $communityPost): View
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->access->abortUnlessCanViewSpace($request->user(), $connector);
        $viewerId = (int) $request->user()->id;
        $canModerate = $this->access->canModerateSpace($request->user(), $connector);
        $canAuditComments = $canModerate || $this->access->canManageComments($request->user(), $connector);

        abort_if(! $canModerate && ! $communityPost->isVisibleToMembers(), 404);

        return view('connectors.community.show', [
            'connector' => $connector,
            'post' => $communityPost->load([
                ...$this->communityAuthorRelations($connector),
                'activeMedia',
                'upvotes' => fn ($query) => $query->where('user_id', $viewerId),
                'topLevelComments' => function ($query) use ($canAuditComments, $connector, $viewerId): void {
                    if (! $canAuditComments) {
                        $query->where('status', CommunityCommentStatus::Visible->value);
                    }

                    $query
                        ->with([
                            ...$this->communityAuthorRelations($connector),
                            'upvotes' => fn ($upvoteQuery) => $upvoteQuery->where('user_id', $viewerId),
                            'replies' => function ($replyQuery) use ($canAuditComments, $connector, $viewerId): void {
                                if (! $canAuditComments) {
                                    $replyQuery->where('status', CommunityCommentStatus::Visible->value);
                                }

                                $replyQuery
                                    ->with([
                                        ...$this->communityAuthorRelations($connector),
                                        'upvotes' => fn ($upvoteQuery) => $upvoteQuery->where('user_id', $viewerId),
                                    ])
                                    ->withCount('upvotes')
                                    ->oldest('created_at')
                                    ->oldest('id');
                            },
                        ])
                        ->withCount('upvotes')
                        ->orderByDesc('upvotes_count')
                        ->latest('created_at')
                        ->orderByDesc('id');
                },
            ])->loadCount([
                'upvotes',
                'comments as visible_comments_count' => fn ($query) => $query->memberVisible(),
            ]),
            'canModerate' => $canModerate,
            'canAuditComments' => $canAuditComments,
            'canComment' => $this->access->canCommentOnPost($request->user(), $communityPost),
        ]);
    }

    public function media(
        Request $request,
        Connector $connector,
        CommunityPost $communityPost,
        CommunityPostMedia $communityPostMedia,
    ) {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $this->access->abortUnlessCanViewSpace($request->user(), $connector);
        abort_unless((int) $communityPostMedia->community_post_id === (int) $communityPost->id, 404);

        $canModerate = $this->access->canModerateSpace($request->user(), $connector);
        abort_if(! $canModerate && (! $communityPost->isVisibleToMembers() || $communityPostMedia->isRemoved()), 404);
        abort_unless(Storage::disk('local')->exists($communityPostMedia->path), 404);

        return Storage::disk('local')->response(
            $communityPostMedia->path,
            $communityPostMedia->original_name,
            [
                'Content-Type' => $communityPostMedia->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function edit(Request $request, Connector $connector, CommunityPost $communityPost): View
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        abort_unless($this->access->canEditPost($request->user(), $communityPost), 403);

        return view('connectors.community.edit', [
            'connector' => $connector,
            'post' => $communityPost->load('activeMedia'),
            'seminars' => $this->seminarOptions($connector),
            'topics' => StoreCommunityPostRequest::TOPICS,
            'postTypes' => CommunityPostType::cases(),
        ]);
    }

    public function update(UpdateCommunityPostRequest $request, Connector $connector, CommunityPost $communityPost): RedirectResponse
    {
        $this->access->abortUnlessConnectorOwnsPost($connector, $communityPost);
        $post = $this->posts->update($request->user(), $communityPost, $request->validated());

        return redirect()
            ->route('connector.community.show', [$connector, $post])
            ->with('success', $post->status === CommunityPostStatus::Published ? 'Post published.' : 'Post received and is being checked.');
    }

    public function moderation(Request $request, Connector $connector): View
    {
        $this->access->abortUnlessCanModerateSpace($request->user(), $connector);

        $actionableStatuses = ['pending_review', 'published', 'locked', 'hidden', 'removed', 'escalated'];

        $tab = (string) $request->query('tab', 'pending');
        $tab = in_array($tab, ['all', 'pending', 'reported', 'hidden', 'rejected', 'escalated'], true) ? $tab : 'pending';
        $query = $connector->communityPosts()
            ->with(['author', 'reports.reporter', 'comments.author', 'moderationActions.actor'])
            ->withCount(['reports', 'comments']);

        match ($tab) {
            'all' => $query->whereIn('status', $actionableStatuses),
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
                'all' => $connector->communityPosts()->whereIn('status', $actionableStatuses)->count(),
                'pending' => $connector->communityPosts()->where('status', 'pending_review')->count(),
                'reported' => CommunityReport::query()
                    ->whereHas('post', fn ($postQuery) => $postQuery->where('connector_id', $connector->id))
                    ->whereIn('status', ['open', 'under_review'])
                    ->count(),
                'hidden' => $connector->communityPosts()->where('status', 'hidden')->count(),
                'rejected' => $connector->communityPosts()->where('status', 'removed')->count(),
                'escalated' => $connector->communityPosts()->where('status', 'escalated')->count(),
            ],
            'moderationPermissions' => [
                'approve' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.approve_posts'),
                'reject' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.manage_posts'),
                'hide' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.manage_posts'),
                'restore' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.manage_posts'),
                'remove' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.manage_posts'),
                'lock' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.lock_threads'),
                'unlock' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.lock_threads'),
                'escalate' => $this->access->canModerateWithPermission($request->user(), $connector, 'community.escalate_to_platform'),
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

    /**
     * @return array<int|string, mixed>
     */
    private function communityAuthorRelations(Connector $connector): array
    {
        return [
            'author.learnerProfile:id,user_id,avatar_path',
            'author.instructorProfile:id,user_id,profile_photo_path',
            'author.profile:id,user_id,avatar',
            'author.connectorMemberships' => fn ($query) => $query
                ->where('connector_id', $connector->id)
                ->where('status', 'active')
                ->with('role:id,name,is_owner'),
        ];
    }
}
