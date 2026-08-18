<?php

namespace App\Enums;

enum CommunityCommentStatus: string
{
    case Visible = 'visible';
    case PendingReview = 'pending_review';
    case Hidden = 'hidden';
    case Removed = 'removed';
    case Escalated = 'escalated';

    public function label(): string
    {
        return config('community_feed.comment_statuses.'.$this->value, str($this->value)->headline()->toString());
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
