<?php

namespace App\Enums;

enum CommunityReactionType: string
{
    case Learned = 'learned';
    case Helpful = 'helpful';
    case Question = 'question';
    case Support = 'support';
    case Bookmark = 'bookmark';

    public function label(): string
    {
        return config('community_feed.reactions.'.$this->value, str($this->value)->headline()->toString());
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
