<?php

namespace App\Enums;

enum ModerationCaseSource: string
{
    case ModuleReview = 'module_review';
    case ChatReport = 'chat_report';
    case LearnerReport = 'learner_report';
    case InstructorApplication = 'instructor_application';
    case CommunityFeed = 'community_feed';
    case AdminManual = 'admin_manual';
    case SystemEvent = 'system_event';

    public function label(): string
    {
        return match ($this) {
            self::ModuleReview => 'Module Review',
            self::ChatReport => 'Chat Report',
            self::LearnerReport => 'Learner Report',
            self::InstructorApplication => 'Instructor Application',
            self::CommunityFeed => 'Community Feed',
            self::AdminManual => 'Admin Manual',
            self::SystemEvent => 'System Event',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
