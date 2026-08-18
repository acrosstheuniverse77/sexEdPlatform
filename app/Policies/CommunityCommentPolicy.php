<?php

namespace App\Policies;

use App\Models\CommunityComment;
use App\Models\User;
use App\Services\Community\CommunityAccessService;

class CommunityCommentPolicy
{
    public function view(User $user, CommunityComment $comment): bool
    {
        return app(CommunityAccessService::class)->canViewSpace($user, $comment->post->connector);
    }

    public function moderate(User $user, CommunityComment $comment): bool
    {
        return app(CommunityAccessService::class)->canManageComments($user, $comment->post->connector);
    }
}
