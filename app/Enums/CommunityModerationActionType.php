<?php

namespace App\Enums;

enum CommunityModerationActionType: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Hide = 'hide';
    case Lock = 'lock';
    case Unlock = 'unlock';
    case Restore = 'restore';
    case Remove = 'remove';
    case Escalate = 'escalate';
    case Freeze = 'freeze';
    case Unfreeze = 'unfreeze';
    case Feature = 'feature';
    case Unfeature = 'unfeature';
    case MarkOfficialAnswer = 'mark_official_answer';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
