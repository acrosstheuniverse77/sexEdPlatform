<?php

namespace App\Enums;

enum CommunityPostStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Hidden = 'hidden';
    case Locked = 'locked';
    case Removed = 'removed';
    case Escalated = 'escalated';
    case Archived = 'archived';

    public function label(): string
    {
        return config('community_feed.post_statuses.'.$this->value, str($this->value)->headline()->toString());
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
