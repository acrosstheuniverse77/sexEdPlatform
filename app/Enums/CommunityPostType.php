<?php

namespace App\Enums;

enum CommunityPostType: string
{
    case Announcement = 'announcement';
    case Event = 'event';
    case Resource = 'resource';
    case ModeratedQuestion = 'moderated_question';
    case DiscussionPrompt = 'discussion_prompt';

    public function label(): string
    {
        return config('community_feed.post_types.'.$this->value, str($this->value)->headline()->toString());
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
