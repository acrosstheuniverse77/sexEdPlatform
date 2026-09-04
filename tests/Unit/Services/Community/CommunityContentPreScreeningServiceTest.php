<?php

namespace Tests\Unit\Services\Community;

use App\Enums\CommunityPreScreenDecision;
use App\Services\Community\CommunityContentPreScreeningService;
use Tests\TestCase;

class CommunityContentPreScreeningServiceTest extends TestCase
{
    public function test_allows_low_risk_announcement(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'announcement',
            'title' => 'Clinic schedule update',
            'body' => 'The community session starts at 9 AM in the connector webinar room.',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPreScreenDecision::Allow, $result->decision);
        $this->assertTrue($result->allowsPublication());
    }

    public function test_moderated_question_goes_to_pending_review(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'moderated_question',
            'title' => 'Question for educators',
            'body' => 'How should families discuss consent in a values-based way?',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPreScreenDecision::PendingReview, $result->decision);
        $this->assertContains('moderated_question', $result->flags);
    }

    public function test_contact_information_is_blocked_with_feedback(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'announcement',
            'title' => 'Message me',
            'body' => 'Email me at adult@example.com or text 09171234567.',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPreScreenDecision::BlockWithFeedback, $result->decision);
        $this->assertContains('contact_information', $result->flags);
    }

    public function test_dm_and_meetup_language_auto_hides_and_escalates(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenComment('DM me privately and meet me near your school.');

        $this->assertSame(CommunityPreScreenDecision::AutoHideAndEscalate, $result->decision);
        $this->assertContains('off_platform_contact', $result->flags);
        $this->assertContains('meetup_or_location_targeting', $result->flags);
    }

    public function test_external_link_not_on_allowlist_goes_to_pending_review(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'resource',
            'title' => 'External resource',
            'body' => 'Please read this resource.',
            'resource_url' => 'https://example.com/article',
        ]);

        $this->assertSame(CommunityPreScreenDecision::PendingReview, $result->decision);
        $this->assertContains('external_link_review', $result->flags);
    }
}
