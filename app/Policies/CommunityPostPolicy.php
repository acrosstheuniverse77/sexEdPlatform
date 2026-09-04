<?php

namespace App\Policies;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Community\CommunityAccessService;

class CommunityPostPolicy
{
    public function view(User $user, CommunityPost $post): bool
    {
        return app(CommunityAccessService::class)->canViewSpace($user, $post->connector);
    }

    public function update(User $user, CommunityPost $post): bool
    {
        return app(CommunityAccessService::class)->canEditPost($user, $post);
    }

    public function moderate(User $user, CommunityPost $post): bool
    {
        return app(CommunityAccessService::class)->canModerateSpace($user, $post->connector);
    }
}
