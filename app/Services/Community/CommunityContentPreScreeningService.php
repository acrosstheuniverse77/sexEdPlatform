<?php

namespace App\Services\Community;

use App\Data\Community\CommunityPreScreenResult;
use App\Enums\CommunityPostType;
use App\Enums\CommunityPreScreenDecision;

class CommunityContentPreScreeningService
{
    private const EMAIL_PATTERN = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';
    private const PHONE_PATTERN = '/(?:\+?63|0)\s?9\d{2}[\s.-]?\d{3}[\s.-]?\d{4}/';
    private const SOCIAL_HANDLE_PATTERN = '/(?:^|\s)@[A-Z0-9._-]{3,}/i';
    private const DM_PATTERN = '/\b(dm|pm|private message|message me privately|chat me|telegram|viber|whatsapp|messenger)\b/i';
    private const MEETUP_PATTERN = '/\b(meet me|meetup|meet up|near your school|after class|send your location|where do you live)\b/i';
    private const THREAT_PATTERN = '/\b(threaten|hurt you|kill|blackmail|expose you)\b/i';
    private const SEXUAL_SOLICITATION_PATTERN = '/\b(send pics|nudes|hook up|sexual favor|sext|sexy photo)\b/i';

    public function screenPost(array $payload): CommunityPreScreenResult
    {
        $text = trim(implode(' ', [
            $payload['title'] ?? '',
            $payload['body'] ?? '',
            $payload['resource_url'] ?? '',
        ]));

        $flags = $this->flagsForText($text);

        if ($this->hasSevereFlag($flags)) {
            return new CommunityPreScreenResult(
                CommunityPreScreenDecision::AutoHideAndEscalate,
                $flags,
                'This content requires platform safety review.',
            );
        }

        if (in_array('contact_information', $flags, true)) {
            return new CommunityPreScreenResult(
                CommunityPreScreenDecision::BlockWithFeedback,
                $flags,
                'Remove personal contact information before posting.',
            );
        }

        if (($payload['post_type'] ?? null) === CommunityPostType::ModeratedQuestion->value) {
            return new CommunityPreScreenResult(
                CommunityPreScreenDecision::PendingReview,
                array_values(array_unique([...$flags, 'moderated_question'])),
                'Questions are reviewed before publication.',
            );
        }

        if ($this->requiresLinkReview((string) ($payload['resource_url'] ?? ''))) {
            return new CommunityPreScreenResult(
                CommunityPreScreenDecision::PendingReview,
                array_values(array_unique([...$flags, 'external_link_review'])),
                'External links require moderator review.',
            );
        }

        return new CommunityPreScreenResult(CommunityPreScreenDecision::Allow, $flags);
    }

    public function screenComment(string $body): CommunityPreScreenResult
    {
        $flags = $this->flagsForText($body);

        if ($this->hasSevereFlag($flags)) {
            return new CommunityPreScreenResult(
                CommunityPreScreenDecision::AutoHideAndEscalate,
                $flags,
                'This content requires platform safety review.',
            );
        }

        if (in_array('contact_information', $flags, true)) {
            return new CommunityPreScreenResult(
                CommunityPreScreenDecision::BlockWithFeedback,
                $flags,
                'Remove personal contact information before posting.',
            );
        }

        return new CommunityPreScreenResult(CommunityPreScreenDecision::Allow, $flags);
    }

    private function flagsForText(string $text): array
    {
        $flags = [];

        if ($this->matches($text, self::EMAIL_PATTERN)
            || $this->matches($text, self::PHONE_PATTERN)
            || $this->matches($text, self::SOCIAL_HANDLE_PATTERN)) {
            $flags[] = 'contact_information';
        }

        if ($this->matches($text, self::DM_PATTERN)) {
            $flags[] = 'off_platform_contact';
        }

        if ($this->matches($text, self::MEETUP_PATTERN)) {
            $flags[] = 'meetup_or_location_targeting';
        }

        if ($this->matches($text, self::THREAT_PATTERN)) {
            $flags[] = 'threat_or_blackmail';
        }

        if ($this->matches($text, self::SEXUAL_SOLICITATION_PATTERN)) {
            $flags[] = 'sexual_solicitation';
        }

        return array_values(array_unique($flags));
    }

    private function hasSevereFlag(array $flags): bool
    {
        return count(array_intersect($flags, [
            'off_platform_contact',
            'meetup_or_location_targeting',
            'threat_or_blackmail',
            'sexual_solicitation',
        ])) > 0;
    }

    private function requiresLinkReview(string $url): bool
    {
        if (trim($url) === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return true;
        }

        $host = strtolower($host);

        foreach ((array) config('community_feed.link_allowlist_hosts', []) as $allowedHost) {
            $allowedHost = strtolower((string) $allowedHost);

            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return false;
            }
        }

        return true;
    }

    private function matches(string $text, string $pattern): bool
    {
        return preg_match($pattern, $text) === 1;
    }
}
