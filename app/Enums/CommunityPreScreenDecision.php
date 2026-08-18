<?php

namespace App\Enums;

enum CommunityPreScreenDecision: string
{
    case Allow = 'allow';
    case PendingReview = 'pending_review';
    case BlockWithFeedback = 'block_with_feedback';
    case AutoHideAndEscalate = 'auto_hide_and_escalate';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
