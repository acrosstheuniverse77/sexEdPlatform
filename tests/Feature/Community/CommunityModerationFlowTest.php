<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Models\CommunityModerationAction;
use App\Models\User;
use App\Services\Community\CommunityModerationService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityModerationFlowTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_moderator_can_approve_hide_lock_and_escalate_own_connector_post(): void
    {
        [$connector, $author, $post] = $this->pendingPostFixture();
        $moderator = $this->createAdultConnectorMember($connector, [
            'community.view_space',
            'community.manage_posts',
            'community.approve_posts',
            'community.lock_threads',
            'community.escalate_to_platform',
        ]);

        $service = app(CommunityModerationService::class);

        $approved = $service->approvePost($moderator, $post, 'Reviewed by connector moderator.');
        $this->assertSame(CommunityPostStatus::Published, $approved->status);

        $locked = $service->lockPost($moderator, $approved, 'Conversation complete.');
        $this->assertSame(CommunityPostStatus::Locked, $locked->status);

        $hidden = $service->hidePost($moderator, $locked, 'Safety concern.');
        $this->assertSame(CommunityPostStatus::Hidden, $hidden->status);

        $escalated = $service->escalatePost($moderator, $hidden, 'Needs platform decision.');
        $this->assertSame(CommunityPostStatus::Escalated, $escalated->status);

        $this->assertGreaterThanOrEqual(4, CommunityModerationAction::query()->where('target_id', $post->id)->count());
    }

    public function test_connector_moderator_cannot_moderate_other_connector_post(): void
    {
        [$connector, $author, $post] = $this->pendingPostFixture();
        $otherOwner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(35)->toDateString()]);
        $otherOwner->assignRole('learner');
        $otherConnector = $this->createVerifiedConnector($otherOwner);
        $otherModerator = $this->createAdultConnectorMember($otherConnector, ['community.manage_posts', 'community.approve_posts']);

        $this->expectException(HttpException::class);

        app(CommunityModerationService::class)->approvePost($otherModerator, $post, 'Cross-connector attempt.');
    }

    public function test_platform_admin_can_moderate_any_connector_post(): void
    {
        [$connector, $author, $post] = $this->pendingPostFixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $approved = app(CommunityModerationService::class)->approvePost($admin, $post, 'Platform reviewed.');

        $this->assertSame(CommunityPostStatus::Published, $approved->status);
    }

    private function pendingPostFixture(): array
    {
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question',
            'body' => 'How should adults discuss consent education?',
            'resource_url' => null,
        ]);

        return [$connector, $author, $post];
    }
}
