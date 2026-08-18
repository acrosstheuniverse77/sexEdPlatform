<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class ConnectorCommunityPostFlowTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_low_risk_announcement_publishes_immediately(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
            'account_type' => User::ACCOUNT_TYPE_LEARNER_ADULT,
            'age_bracket_cached' => 'adults',
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Health session schedule',
            'body' => 'Adults can attend the connector webinar this Friday.',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame($owner->id, $post->published_by);
        $this->assertDatabaseHas('community_post_versions', [
            'community_post_id' => $post->id,
            'version_number' => 1,
        ]);
    }

    public function test_connector_post_store_route_creates_post(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(34)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $response = $this->actingAs($member)->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'title' => 'Route announcement',
            'body' => 'Adults are invited to a public health education session.',
            'resource_url' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('community_posts', [
            'connector_id' => $connector->id,
            'author_id' => $member->id,
            'title' => 'Route announcement',
        ]);
    }

    public function test_moderated_question_enters_pending_review(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question for moderators',
            'body' => 'How should adults discuss consent education with families?',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPostStatus::PendingReview, $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_contact_information_blocks_post_with_validation_error(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $this->expectException(ValidationException::class);

        app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Contact me',
            'body' => 'Email adult@example.com for private details.',
            'resource_url' => null,
        ]);
    }

    public function test_editing_published_post_reruns_prescreening_and_versions_content(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'First title',
            'body' => 'Safe announcement.',
            'resource_url' => null,
        ]);

        $updated = app(CommunityPostService::class)->update($owner, $post, [
            'post_type' => 'moderated_question',
            'title' => 'Question title',
            'body' => 'Could moderators review this question?',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPostStatus::PendingReview, $updated->status);
        $this->assertSame(2, $updated->versions()->count());
    }

    public function test_minor_cannot_create_post(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $minor = $this->createMinorLearner(15);
        $role = $this->createCustomRole($connector, ['community.view_space', 'community.create_post']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        app(CommunityPostService::class)->create($minor, $connector, [
            'post_type' => 'announcement',
            'title' => 'Minor post',
            'body' => 'This must not publish.',
            'resource_url' => null,
        ]);
    }
}
