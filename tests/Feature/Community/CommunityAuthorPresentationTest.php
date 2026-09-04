<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Models\CommunityComment;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityAuthorPresentationTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_post_author_shows_avatar_name_and_connector_owner_role_without_a_profile_link(): void
    {
        $owner = User::factory()->create([
            'name' => 'Olivia Owner',
            'role' => 'learner',
            'birthdate' => now()->subYears(31)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $owner->learnerProfile()->create([
            'username' => 'olivia_owner',
            'birthdate' => now()->subYears(31)->toDateString(),
            'avatar_path' => 'avatars/olivia-owner.webp',
        ]);
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Author identity presentation for adult members.',
        ]);

        $html = $this->actingAs($owner)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('data-testid="community-author"', false)
            ->assertSee('Olivia Owner')
            ->assertSee('Connector Owner')
            ->assertSee(asset('storage/avatars/olivia-owner.webp'), false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/<a[^>]*>\s*Olivia Owner\s*<\/a>/i', $html);
        $this->assertStringContainsString('ago', $html);
        $this->assertStringNotContainsString($post->created_at->toDateTimeString(), $html);
    }

    public function test_comment_authors_show_custom_role_general_profile_avatar_and_member_fallback(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(31)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'discussion_prompt',
            'topic_choice' => 'Healthy relationships',
            'body' => 'Comment author presentation for adult members.',
        ]);

        $facilitator = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $facilitator->forceFill(['name' => 'Faith Facilitator'])->save();
        $facilitator->learnerProfile()->update(['avatar_path' => 'avatars/faith.png']);
        $facilitator->connectorMemberships()
            ->where('connector_id', $connector->id)
            ->firstOrFail()
            ->role
            ->update(['name' => 'Health Facilitator']);

        $formerMember = User::factory()->create([
            'name' => 'Morgan Former Member',
            'role' => 'learner',
            'birthdate' => now()->subYears(29)->toDateString(),
        ]);
        $formerMember->assignRole('learner');
        UserProfile::query()->create([
            'user_id' => $formerMember->id,
            'avatar' => 'avatars/morgan.jpg',
        ]);

        foreach ([
            [$facilitator, 'Visible facilitator comment.'],
            [$formerMember, 'Visible former member comment.'],
        ] as [$author, $body]) {
            CommunityComment::query()->create([
                'community_post_id' => $post->id,
                'author_id' => $author->id,
                'body' => $body,
                'status' => CommunityCommentStatus::Visible->value,
                'prescreen_decision' => 'allow',
            ]);
        }

        $this->actingAs($owner)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('Faith Facilitator')
            ->assertSee('Health Facilitator')
            ->assertSee(asset('storage/avatars/faith.png'), false)
            ->assertSee('Morgan Former Member')
            ->assertSee('Member')
            ->assertSee(asset('storage/avatars/morgan.jpg'), false);
    }
}
