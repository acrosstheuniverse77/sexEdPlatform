<?php

namespace Tests\Feature\Community;

use App\Notifications\Community\CommunityPostDecisionNotification;
use App\Notifications\Community\CommunityPostEscalatedNotification;
use App\Notifications\Community\CommunityPostPendingReviewNotification;
use App\Notifications\Community\CommunitySafetyEventNotification;
use App\Models\User;
use App\Services\Community\CommunityFeedSettingsService;
use App\Services\Community\CommunityModerationService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityNotificationTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_pending_review_post_notifies_connector_moderators_only(): void
    {
        Notification::fake();

        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $moderator = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.approve_posts']);
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question for review',
            'body' => 'How can adult facilitators discuss consent with learners?',
            'resource_url' => null,
        ]);

        Notification::assertSentTo($moderator, CommunityPostPendingReviewNotification::class);
        Notification::assertNotSentTo($viewer, CommunityPostPendingReviewNotification::class);
    }

    public function test_moderation_decision_notifies_author_and_escalation_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $moderator = $this->createAdultConnectorMember($connector, [
            'community.view_space',
            'community.approve_posts',
            'community.escalate_to_platform',
        ]);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question for platform review',
            'body' => 'How should adult educators handle sensitive anonymous questions?',
            'resource_url' => null,
        ]);

        app(CommunityModerationService::class)->approvePost($moderator, $post, 'Approved.');
        app(CommunityModerationService::class)->escalatePost($moderator, $post->fresh(), 'Needs platform review.');

        Notification::assertSentTo($author, CommunityPostDecisionNotification::class);
        Notification::assertSentTo($admin, CommunityPostEscalatedNotification::class);
    }

    public function test_global_freeze_notifies_admins_without_guardian_or_minor_alerts(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $minor = $this->createMinorLearner();
        $guardian = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(36)->toDateString()]);
        $guardian->assignRole('learner');

        app(CommunityFeedSettingsService::class)->freezeGlobal($admin, 'Safety incident review.');

        Notification::assertSentTo($admin, CommunitySafetyEventNotification::class);
        Notification::assertNotSentTo($minor, CommunitySafetyEventNotification::class);
        Notification::assertNotSentTo($guardian, CommunitySafetyEventNotification::class);
    }

    public function test_adult_hub_activity_does_not_send_guardian_or_child_feed_notifications(): void
    {
        Notification::fake();

        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $minor = $this->createMinorLearner(12);

        app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'announcement',
            'title' => 'Adult-only hub post',
            'body' => 'No child workflow notification should be created.',
            'resource_url' => null,
        ]);

        Notification::assertNothingSentTo($minor);
    }
}
