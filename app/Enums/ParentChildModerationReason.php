<?php

namespace App\Enums;

enum ParentChildModerationReason: string
{
    case BlurryDocument = 'blurry_document';
    case InvalidGovernmentId = 'invalid_government_id';
    case ExpiredGovernmentId = 'expired_government_id';
    case IncompleteSubmission = 'incomplete_submission';
    case DuplicateVerification = 'duplicate_verification';
    case IdentityCannotBeVerified = 'identity_cannot_be_verified';
    case InaccurateInformation = 'inaccurate_information';
    case GuidelineViolation = 'platform_guideline_violation';
    case Others = 'others';

    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::BlurryDocument => 'Blurry document',
            self::InvalidGovernmentId => 'Invalid Government ID',
            self::ExpiredGovernmentId => 'Expired Government ID',
            self::IncompleteSubmission => 'Incomplete submission',
            self::DuplicateVerification => 'Duplicate verification',
            self::IdentityCannotBeVerified => 'Identity cannot be verified',
            self::InaccurateInformation => 'Inaccurate or misleading information',
            self::GuidelineViolation => 'Violates platform guidelines',
            self::Others => 'Others',
        };
    }
}
