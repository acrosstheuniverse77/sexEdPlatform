<?php

namespace App\Services\Community;

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
    ) {
    }

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
            && $connector->status === 'verified'
            && $this->connectorAccess->hasPermission($user, $connector, 'community.create_post');
    }

    public function canEditPost(User $user, CommunityPost $post): bool
    {
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
}
