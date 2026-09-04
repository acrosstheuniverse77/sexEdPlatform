<?php

namespace App\Services\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Connector;
use App\Models\User;
use App\Models\UserSuspension;
use App\Services\Connectors\ConnectorAccessService;

class CommunityAccessService
{
    public function __construct(
        private readonly ConnectorAccessService $connectorAccess,
        private readonly CommunityFeedSettingsService $settings,
    ) {}

    public function canUseCommunity(User $user): bool
    {
        return ! $user->isMinorForCommunityFeed()
            && $user->status !== User::STATUS_SUSPENDED
            && ! $this->hasActiveSuspension($user);
    }

    public function canViewSpace(User $user, Connector $connector): bool
    {
        if ($user->hasRole('admin') && $user->can('community.view_any')) {
            return true;
        }

        return $this->canUseCommunity($user)
            && $connector->status === 'verified'
            && $this->connectorAccess->hasPermission($user, $connector, 'community.view_space');
    }

    public function canCreatePost(User $user, Connector $connector): bool
    {
        return $this->canUseCommunity($user)
            && ! $this->settings->isGloballyFrozen()
            && ! $this->isConnectorSpaceFrozen($connector)
            && $connector->status === 'verified'
            && $this->connectorAccess->hasPermission($user, $connector, 'community.create_post');
    }

    public function canEditPost(User $user, CommunityPost $post): bool
    {
        $post->loadMissing(['connector', 'space']);

        if (! in_array($post->status, [
            CommunityPostStatus::Draft,
            CommunityPostStatus::PendingReview,
            CommunityPostStatus::Published,
        ], true)) {
            return false;
        }

        if ($post->space === null || $this->settings->isSpaceFrozen($post->space)) {
            return false;
        }

        if ($this->canModerateSpace($user, $post->connector)) {
            return true;
        }

        return (int) $post->author_id === (int) $user->id
            && $this->canUseCommunity($user)
            && ! $this->settings->isGloballyFrozen()
            && $post->connector?->status === 'verified'
            && $this->connectorAccess->hasPermission($user, $post->connector, 'community.edit_own_post');
    }

    public function canModerateSpace(User $user, Connector $connector): bool
    {
        if ($user->hasRole('admin') && $user->can('community.moderate_any')) {
            return true;
        }

        return $this->canUseCommunity($user)
            && $connector->status === 'verified'
            && (
                $this->connectorAccess->hasPermission($user, $connector, 'community.manage_posts')
                || $this->connectorAccess->hasPermission($user, $connector, 'community.approve_posts')
            );
    }

    public function canModerateWithPermission(User $user, Connector $connector, string $permission): bool
    {
        if ($user->hasRole('admin') && $user->can('community.moderate_any')) {
            return true;
        }

        return $this->canUseCommunity($user)
            && $connector->status === 'verified'
            && $this->connectorAccess->hasPermission($user, $connector, $permission);
    }

    public function canManageComments(User $user, Connector $connector): bool
    {
        if ($user->hasRole('admin') && $user->can('community.moderate_any')) {
            return true;
        }

        return $this->canUseCommunity($user)
            && $connector->status === 'verified'
            && $this->connectorAccess->hasPermission($user, $connector, 'community.manage_comments');
    }

    public function canReact(User $user, CommunityPost $post): bool
    {
        return $this->canUseCommunity($user)
            && $post->connector?->status === 'verified'
            && $post->space !== null
            && ! $this->settings->isSpaceFrozen($post->space)
            && $post->isVisibleToMembers()
            && ! $post->isLocked()
            && $this->connectorAccess->hasPermission($user, $post->connector, 'community.view_space');
    }

    public function canUpvotePost(User $user, CommunityPost $post): bool
    {
        $post->loadMissing(['connector', 'space']);

        return $this->canUseCommunity($user)
            && $post->connector?->status === 'verified'
            && $post->space !== null
            && ! $this->settings->isSpaceFrozen($post->space)
            && $post->isVisibleToMembers()
            && $this->connectorAccess->hasPermission($user, $post->connector, 'community.view_space');
    }

    public function canCommentOnPost(User $user, CommunityPost $post): bool
    {
        $post->loadMissing(['connector', 'space']);

        return $post->connector?->status === 'verified'
            && $post->space !== null
            && ! $this->settings->isSpaceFrozen($post->space)
            && $post->status === CommunityPostStatus::Published
            && ! $post->isLocked()
            && $this->canViewSpace($user, $post->connector);
    }

    public function canUpvoteComment(User $user, CommunityPost $post, CommunityComment $comment): bool
    {
        $comment->loadMissing(['post', 'parent']);

        return (int) $comment->community_post_id === (int) $post->id
            && ($comment->status?->value ?? $comment->status) === CommunityCommentStatus::Visible->value
            && $this->hasVisibleParentWhenRequired($comment)
            && $this->canUpvotePost($user, $post);
    }

    public function canViewComment(User $user, CommunityPost $post, CommunityComment $comment): bool
    {
        $post->loadMissing('connector');

        if ((int) $comment->community_post_id !== (int) $post->id
            || ! $this->canViewSpace($user, $post->connector)) {
            return false;
        }

        if ($this->canManageComments($user, $post->connector)
            || $this->canModerateSpace($user, $post->connector)) {
            return true;
        }

        $comment->loadMissing('parent');

        return $post->isVisibleToMembers()
            && ($comment->status?->value ?? $comment->status) === CommunityCommentStatus::Visible->value
            && $this->hasVisibleParentWhenRequired($comment);
    }

    public function canViewPost(User $user, CommunityPost $post): bool
    {
        $post->loadMissing('connector');

        if (! $this->canViewSpace($user, $post->connector)) {
            return false;
        }

        return $this->canModerateSpace($user, $post->connector)
            || $post->isVisibleToMembers();
    }

    public function abortUnlessCanViewSpace(User $user, Connector $connector): void
    {
        abort_unless($this->canViewSpace($user, $connector), 403);
    }

    public function abortUnlessCanCreatePost(User $user, Connector $connector): void
    {
        abort_unless($this->canCreatePost($user, $connector), 403);
    }

    public function abortUnlessCanModerateSpace(User $user, Connector $connector): void
    {
        abort_unless($this->canModerateSpace($user, $connector), 403);
    }

    public function abortUnlessCanViewComment(User $user, CommunityPost $post, CommunityComment $comment): void
    {
        abort_unless($this->canViewComment($user, $post, $comment), 403);
    }

    public function abortUnlessCanViewPost(User $user, CommunityPost $post): void
    {
        abort_unless($this->canViewPost($user, $post), 403);
    }

    public function abortUnlessConnectorOwnsPost(Connector $connector, CommunityPost $post): void
    {
        abort_unless((int) $post->connector_id === (int) $connector->id, 404);
    }

    private function hasActiveSuspension(User $user): bool
    {
        return UserSuspension::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    private function isConnectorSpaceFrozen(Connector $connector): bool
    {
        $space = $connector->communitySpaces()->first();

        return $space !== null && $this->settings->isSpaceFrozen($space);
    }

    private function hasVisibleParentWhenRequired(CommunityComment $comment): bool
    {
        if ($comment->parent_id === null) {
            return true;
        }

        return $comment->parent !== null
            && (int) $comment->parent->community_post_id === (int) $comment->community_post_id
            && $comment->parent->parent_id === null
            && ($comment->parent->status?->value ?? $comment->parent->status) === CommunityCommentStatus::Visible->value;
    }
}
