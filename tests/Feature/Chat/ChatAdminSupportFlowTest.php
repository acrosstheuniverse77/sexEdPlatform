<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatAdminSupportFlowTest extends TestCase
{
    public function test_learner_support_messages_are_routed_to_configured_support_admin_and_visible_in_all_admin_inboxes(): void
    {
        Event::fake([MessageSent::class]);

        $learner = $this->makeUserWithRole('learner');
        $nonSupportAdmin = $this->makeUserWithRole('admin');
        $supportAdmin = $this->makeUserWithRole('admin');

        config()->set('chat.support_admin_user_id', $supportAdmin->id);
        config()->set('chat.support_admin_requires_active', true);

        $startResponse = $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $nonSupportAdmin->id,
                'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
            ])
            ->assertCreated()
            ->assertJsonPath('requires_request', false);

        $conversationId = (int) $startResponse->json('conversation.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'pair_key' => Conversation::makePairKey($learner->id, $supportAdmin->id),
            'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
            'status' => Conversation::STATUS_ACTIVE,
        ]);

        $learnerMessageResponse = $this->actingAs($learner)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Need platform support from learner.',
            ])
            ->assertCreated();

        $firstMessageId = (int) $learnerMessageResponse->json('message.id');

        $this->actingAs($supportAdmin)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Support admin reply to learner.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $learner->id,
            'message_body' => 'Need platform support from learner.',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $supportAdmin->id,
            'message_body' => 'Support admin reply to learner.',
        ]);

        $sinceResponse = $this->actingAs($learner)
            ->getJson(route('chat.messages.since', ['conversation' => $conversationId, 'lastMessageId' => $firstMessageId]))
            ->assertOk();

        $this->assertTrue(
            collect($sinceResponse->json('messages'))->contains(
                fn (array $message) => (int) $message['sender_id'] === $supportAdmin->id
                    && (string) $message['message_body'] === 'Support admin reply to learner.'
            )
        );

        $supportAdminConversations = $this->actingAs($supportAdmin)
            ->getJson(route('chat.conversations.index'))
            ->assertOk()
            ->json('conversations');

        $this->assertTrue(collect($supportAdminConversations)->contains(fn (array $conversation) => (int) $conversation['id'] === $conversationId));

        $otherAdminConversations = $this->actingAs($nonSupportAdmin)
            ->getJson(route('chat.conversations.index'))
            ->assertOk()
            ->json('conversations');

        $this->assertTrue(collect($otherAdminConversations)->contains(fn (array $conversation) => (int) $conversation['id'] === $conversationId));

        $this->actingAs($nonSupportAdmin)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Escalation from another admin context.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $nonSupportAdmin->id,
            'message_body' => 'Escalation from another admin context.',
        ]);

        Event::assertDispatched(MessageSent::class, 3);
    }

    public function test_instructor_support_flow_uses_active_admin_fallback_and_admin_reply_is_available_via_since_endpoint(): void
    {
        Event::fake([MessageSent::class]);

        $instructor = $this->makeUserWithRole('instructor');
        $inactiveAdmin = $this->makeUserWithRole('admin', ['status' => User::STATUS_INACTIVE]);
        $activeAdmin = $this->makeUserWithRole('admin', ['status' => User::STATUS_ACTIVE]);

        config()->set('chat.support_admin_user_id', null);
        config()->set('chat.support_admin_requires_active', true);

        $discovery = $this->actingAs($instructor)
            ->getJson(route('chat.discovery'))
            ->assertOk();

        $this->assertSame($activeAdmin->id, (int) $discovery->json('support_admin.id'));

        $startResponse = $this->actingAs($instructor)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $inactiveAdmin->id,
                'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
            ])
            ->assertCreated();

        $conversationId = (int) $startResponse->json('conversation.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'pair_key' => Conversation::makePairKey($instructor->id, $activeAdmin->id),
            'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
        ]);

        $learnerMessage = $this->actingAs($instructor)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Instructor asks for support.',
            ])
            ->assertCreated();

        $firstMessageId = (int) $learnerMessage->json('message.id');

        $this->actingAs($activeAdmin)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Support admin reply to instructor.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $activeAdmin->id,
            'message_body' => 'Support admin reply to instructor.',
        ]);

        Event::assertDispatched(MessageSent::class, 2);

        $sinceResponse = $this->actingAs($instructor)
            ->getJson(route('chat.messages.since', ['conversation' => $conversationId, 'lastMessageId' => $firstMessageId]))
            ->assertOk();

        $this->assertTrue(
            collect($sinceResponse->json('messages'))->contains(
                fn (array $message) => (int) $message['sender_id'] === $activeAdmin->id
                    && (string) $message['message_body'] === 'Support admin reply to instructor.'
            )
        );
    }

    public function test_support_start_returns_conflict_when_no_support_admin_is_available(): void
    {
        $learner = $this->makeUserWithRole('learner');

        config()->set('chat.support_admin_user_id', null);
        config()->set('chat.support_admin_requires_active', true);

        User::query()->where('role', 'admin')->delete();

        $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $learner->id,
                'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Platform support is currently unavailable. Please try again later.');
    }

    public function test_support_flow_allows_configured_moderator_without_admin_role(): void
    {
        Event::fake([MessageSent::class]);

        $learner = $this->makeUserWithRole('learner');
        $moderator = $this->makeUserWithRole('instructor');

        Permission::findOrCreate('moderate chat', 'web');
        $moderator->givePermissionTo('moderate chat');

        config()->set('chat.support_admin_user_id', $moderator->id);
        config()->set('chat.support_admin_requires_active', true);

        $startResponse = $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $learner->id,
                'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
            ])
            ->assertCreated();

        $conversationId = (int) $startResponse->json('conversation.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'pair_key' => Conversation::makePairKey($learner->id, $moderator->id),
            'conversation_type' => Conversation::TYPE_ADMIN_SUPPORT,
            'status' => Conversation::STATUS_ACTIVE,
        ]);

        $this->actingAs($learner)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Learner ping to support moderator.',
            ])
            ->assertCreated();

        $this->actingAs($moderator)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Support moderator response.',
            ])
            ->assertCreated();

        Event::assertDispatched(MessageSent::class, 2);
    }

    public function test_support_discovery_exposes_configurable_availability_state(): void
    {
        $learner = $this->makeUserWithRole('learner');
        $supportAdmin = $this->makeUserWithRole('admin', ['chat_status' => 'offline']);

        config()->set('chat.support_admin_user_id', $supportAdmin->id);
        config()->set('chat.support_availability.default_online', true);
        config()->set('chat.support_availability.online_title', 'Support Available');
        config()->set('chat.support_availability.offline_title', 'Support Currently Offline');

        $this->actingAs($learner)
            ->getJson(route('chat.discovery'))
            ->assertOk()
            ->assertJsonPath('support_availability.available', false)
            ->assertJsonPath('support_availability.title', 'Support Currently Offline')
            ->assertJsonPath('support_availability.message', 'Your message will be reviewed when support becomes available.');

        $supportAdmin->forceFill(['chat_status' => 'active'])->save();

        $this->actingAs($learner)
            ->getJson(route('chat.discovery'))
            ->assertOk()
            ->assertJsonPath('support_availability.available', true)
            ->assertJsonPath('support_availability.title', 'Support Available')
            ->assertJsonPath('support_availability.message', 'You can chat with platform support.');
    }

    private function makeUserWithRole(string $role, array $attributes = []): User
    {
        $defaults = [
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ];

        $user = User::factory()->create(array_merge($defaults, $attributes));
        $user->assignRole($role);

        return $user;
    }
}
