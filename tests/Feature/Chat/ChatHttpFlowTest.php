<?php

namespace Tests\Feature\Chat;

use App\Models\AdminCreatorProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRequest;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ParentChildAccount;
use App\Models\ParentChildInvitation;
use App\Models\User;
use App\Enums\EnrollmentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatHttpFlowTest extends TestCase
{
    public function test_list_conversations_endpoint_returns_user_scoped_results(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $forLearner = Conversation::create([
            'participant_one_id' => $learner->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($learner->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null).':alt',
        ]);

        $response = $this->actingAs($learner)
            ->getJson(route('chat.conversations.index'))
            ->assertOk();

        $this->assertCount(1, $response->json('conversations'));
        $this->assertSame($forLearner->id, $response->json('conversations.0.id'));
    }

    public function test_conversation_index_uses_admin_affiliation_for_admin_other_participant_name(): void
    {
        $platformOwner = User::factory()->create(['role' => 'instructor']);
        $platformOwner->assignRole('instructor');

        $admin = User::factory()->create(['role' => 'admin', 'name' => 'admin_username']);
        $admin->assignRole('admin');

        AdminCreatorProfile::query()->create([
            'user_id' => $admin->id,
            'public_display_name' => 'Admin Creator',
            'bio' => null,
            'affiliation' => 'Conscious Connections Team',
            'avatar_path' => null,
            'show_individual_attribution' => false,
        ]);

        Conversation::query()->create([
            'participant_one_id' => $platformOwner->id,
            'participant_two_id' => $admin->id,
            'pair_key' => Conversation::makePairKey($platformOwner->id, $admin->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        $response = $this->actingAs($platformOwner)
            ->getJson(route('chat.conversations.index'))
            ->assertOk();

        $this->assertSame('Conscious Connections Team', $response->json('conversations.0.other_participant.name'));
    }

    public function test_start_send_accept_decline_and_forbidden_flows(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $startDirect = $this->actingAs($admin)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $instructor->id,
                'conversation_type' => Conversation::TYPE_DIRECT,
            ])
            ->assertCreated();

        $conversationId = (int) $startDirect->json('conversation.id');

        $this->actingAs($admin)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'Hello from admin.',
            ])
            ->assertCreated()
            ->assertJsonPath('message.conversation_id', $conversationId);

        $requestStart = $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $instructor->id,
                'conversation_type' => Conversation::TYPE_DIRECT,
                'initial_message' => 'Can we discuss your module?',
            ])
            ->assertStatus(202)
            ->assertJsonPath('requires_request', true)
            ->assertJsonPath('conversation.status', Conversation::STATUS_PENDING_REQUEST);

        $messageRequestId = (int) $requestStart->json('message_request.id');
        $pendingConversationId = (int) $requestStart->json('conversation.id');

        $this->assertGreaterThan(0, $pendingConversationId);

        $this->actingAs($learner)
            ->getJson(route('chat.requests.index'))
            ->assertOk()
            ->assertJsonPath('requests', []);

        $this->actingAs($instructor)
            ->getJson(route('chat.requests.index'))
            ->assertOk()
            ->assertJsonCount(1, 'requests');

        $acceptResponse = $this->actingAs($instructor)
            ->postJson(route('chat.requests.accept', ['messageRequest' => $messageRequestId]))
            ->assertOk()
            ->assertJsonPath('message_request.status', MessageRequest::STATUS_ACCEPTED);

        $this->assertSame($pendingConversationId, (int) $acceptResponse->json('conversation.id'));

        $this->assertDatabaseHas('conversations', [
            'id' => $pendingConversationId,
            'status' => Conversation::STATUS_ACCEPTED,
        ]);

        $this->actingAs($instructor)
            ->postJson(route('chat.requests.accept', ['messageRequest' => $messageRequestId]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'This request has already been accepted.');

        $toDecline = MessageRequest::create([
            'requester_id' => $learner->id,
            'instructor_id' => $instructor->id,
            'status' => MessageRequest::STATUS_PENDING,
            'initial_message' => 'Second request',
            'accepted_conversation_id' => $pendingConversationId,
        ]);

        Conversation::query()->whereKey($pendingConversationId)->update([
            'status' => Conversation::STATUS_PENDING_REQUEST,
        ]);

        $this->actingAs($instructor)
            ->postJson(route('chat.requests.decline', ['messageRequest' => $toDecline->id]))
            ->assertOk()
            ->assertJsonPath('message_request.status', MessageRequest::STATUS_DECLINED);

        $this->actingAs($instructor)
            ->postJson(route('chat.requests.decline', ['messageRequest' => $toDecline->id]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'This request has already been declined.');

        $this->actingAs($learner)
            ->postJson(route('chat.messages.store', ['conversation' => $conversationId]), [
                'message_body' => 'I should be forbidden from this conversation.',
            ])
            ->assertForbidden();

        $anotherRequest = MessageRequest::create([
            'requester_id' => $admin->id,
            'instructor_id' => $instructor->id,
            'status' => MessageRequest::STATUS_PENDING,
            'initial_message' => 'Admin request',
        ]);

        $this->actingAs($learner)
            ->postJson(route('chat.requests.accept', ['messageRequest' => $anotherRequest->id]))
            ->assertForbidden();
    }

    public function test_conversation_index_is_paginated_and_returns_lazy_load_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        for ($index = 0; $index < 25; $index++) {
            $peer = User::factory()->create(['role' => 'instructor']);

            Conversation::create([
                'participant_one_id' => $admin->id,
                'participant_two_id' => $peer->id,
                'pair_key' => Conversation::makePairKey($admin->id, $peer->id),
                'conversation_type' => Conversation::TYPE_DIRECT,
                'status' => Conversation::STATUS_ACTIVE,
                'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
            ]);
        }

        $firstPage = $this->actingAs($admin)
            ->getJson(route('chat.conversations.index'))
            ->assertOk();

        $this->assertCount(20, $firstPage->json('conversations'));
        $this->assertTrue((bool) $firstPage->json('pagination.has_more'));

        $secondPage = $this->actingAs($admin)
            ->getJson(route('chat.conversations.index', ['page' => 2]))
            ->assertOk();

        $this->assertCount(5, $secondPage->json('conversations'));
        $this->assertFalse((bool) $secondPage->json('pagination.has_more'));
    }

    public function test_parent_can_start_chat_with_linked_child_and_child_instructor(): void
    {
        $parent = User::factory()->create(['role' => 'learner']);
        $parent->assignRole('learner');

        $child = User::factory()->create(['role' => 'learner']);
        $child->assignRole('learner');

        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        ParentChildAccount::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'verification_status' => 'approved',
            'relationship_verified_at' => now(),
        ]);

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'is_published' => true,
            'content_owner_type' => 'instructor',
        ]);

        ModuleEnrollment::create([
            'user_id' => $child->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($parent)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $child->id,
                'conversation_type' => Conversation::TYPE_DIRECT,
            ])
            ->assertCreated()
            ->assertJsonPath('requires_request', false)
            ->assertJsonPath('conversation.conversation_type', Conversation::TYPE_DIRECT);

        $this->actingAs($parent)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $instructor->id,
                'conversation_type' => Conversation::TYPE_MODULE_CHAT,
                'module_id' => $module->id,
            ])
            ->assertCreated()
            ->assertJsonPath('requires_request', false)
            ->assertJsonPath('conversation.conversation_type', Conversation::TYPE_MODULE_CHAT);
    }

    public function test_enrolled_learner_can_start_instructor_chat_with_pending_guardian_invitation(): void
    {
        $parent = User::factory()->create(['role' => 'learner']);
        $parent->assignRole('learner');
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $learner->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'relationship_type' => 'legal_guardian',
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'is_published' => true,
            'content_owner_type' => 'instructor',
        ]);

        ModuleEnrollment::query()->create([
            'user_id' => $learner->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $instructor->id,
                'conversation_type' => Conversation::TYPE_DIRECT,
                'initial_message' => 'Can you help with this module?',
            ])
            ->assertCreated()
            ->assertJsonPath('requires_request', false);
    }

    public function test_message_index_returns_latest_window_and_supports_loading_older_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        for ($index = 1; $index <= 35; $index++) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $admin->id,
                'message_body' => "message {$index}",
            ]);
        }

        $latest = $this->actingAs($admin)
            ->getJson(route('chat.messages.index', ['conversation' => $conversation->id]))
            ->assertOk();

        $this->assertCount(30, $latest->json('messages'));
        $this->assertTrue((bool) $latest->json('meta.has_more_before'));

        $oldestFromWindow = (int) $latest->json('meta.oldest_message_id');

        $older = $this->actingAs($admin)
            ->getJson(route('chat.messages.index', ['conversation' => $conversation->id]).'?before_message_id='.$oldestFromWindow)
            ->assertOk();

        $this->assertCount(5, $older->json('messages'));
        $this->assertFalse((bool) $older->json('meta.has_more_before'));
    }

    public function test_message_store_is_rate_limited_to_ten_messages_per_ten_seconds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        for ($index = 1; $index <= 10; $index++) {
            $this->actingAs($admin)
                ->postJson(route('chat.messages.store', ['conversation' => $conversation->id]), [
                    'message_body' => "burst {$index}",
                ])
                ->assertCreated();
        }

        $this->actingAs($admin)
            ->postJson(route('chat.messages.store', ['conversation' => $conversation->id]), [
                'message_body' => 'burst overflow',
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'You are sending messages too quickly. Please wait a moment.');
    }

    public function test_message_store_supports_attachments_and_returns_attachment_payload_contract(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('chat.messages.store', ['conversation' => $conversation->id]), [
                'message_body' => 'Here is the worksheet preview.',
                'attachments' => [
                    UploadedFile::fake()->create('preview.png', 24, 'image/png'),
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $this->assertNotEmpty($response->json('message.attachments'));
        $this->assertSame('preview.png', $response->json('message.attachments.0.file_name'));
        $this->assertSame(true, $response->json('message.attachments.0.is_image'));

        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $response->json('message.id'),
            'uploaded_by_id' => $admin->id,
            'file_name' => 'preview.png',
        ]);

        $voiceResponse = $this->actingAs($admin)
            ->post(route('chat.messages.store', ['conversation' => $conversation->id]), [
                'attachments' => [
                    UploadedFile::fake()->create('voice-note.mp3', 64, 'audio/mpeg'),
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $this->assertTrue((bool) $voiceResponse->json('message.attachments.0.is_audio'));
        $this->assertTrue((bool) $voiceResponse->json('message.attachments.0.is_voice_note'));

        $voiceAttachment = MessageAttachment::query()
            ->where('message_id', $voiceResponse->json('message.id'))
            ->firstOrFail();

        $this->assertStringStartsWith('chat/voice_notes/', (string) $voiceAttachment->path);
    }

    public function test_message_report_endpoint_creates_single_structured_report_per_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $instructor->id,
            'message_body' => 'Please report this for moderation checks.',
        ]);

        $this->actingAs($admin)
            ->postJson(route('chat.messages.report', ['message' => $message->id]), [
                'reason_code' => 'offensive_language',
            ])
            ->assertOk()
            ->assertJsonPath('reported', true);

        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_id' => $admin->id,
            'status' => 'open',
            'reason_code' => 'offensive_language',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
        ]);

        $this->assertSame(1, DB::table('message_reports')
            ->where('message_id', $message->id)
            ->where('reporter_id', $admin->id)
            ->count());
    }

    public function test_message_report_endpoint_requires_structured_reason_and_other_custom_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $instructor->id,
            'message_body' => 'Please report this for moderation checks.',
        ]);

        $this->actingAs($admin)
            ->postJson(route('chat.messages.report', ['message' => $message->id]), [
                'reason_code' => 'other',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_reason');

        $this->actingAs($admin)
            ->postJson(route('chat.messages.report', ['message' => $message->id]), [
                'reason_code' => 'child_safety_concern',
                'custom_reason' => '',
            ])
            ->assertOk()
            ->assertJsonPath('reported', true);

        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_id' => $admin->id,
            'status' => 'open',
            'reason_code' => 'child_safety_concern',
            'custom_reason' => null,
        ]);
    }

    public function test_message_report_endpoint_prevents_duplicate_report_spam_during_cooldown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $instructor->id,
            'message_body' => 'Please report this for moderation checks.',
        ]);

        $this->actingAs($admin)
            ->postJson(route('chat.messages.report', ['message' => $message->id]), [
                'reason_code' => 'harassment_abuse',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('chat.messages.report', ['message' => $message->id]), [
                'reason_code' => 'spam_repeated_messages',
            ])
            ->assertStatus(429)
            ->assertJsonPath('reported', false);

        $this->assertSame(1, DB::table('message_reports')
            ->where('message_id', $message->id)
            ->where('reporter_id', $admin->id)
            ->count());
    }

    public function test_chat_status_endpoint_allows_manual_status_changes(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        $this->actingAs($learner)
            ->patchJson(route('chat.status.update'), [
                'status' => 'busy',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'busy');

        $this->assertDatabaseHas('users', [
            'id' => $learner->id,
            'chat_status' => 'busy',
        ]);

        $this->actingAs($learner)
            ->patchJson(route('chat.status.update'), [
                'status' => 'do_not_disturb',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'do_not_disturb');

        $this->assertDatabaseHas('users', [
            'id' => $learner->id,
            'chat_status' => 'inactive',
        ]);
    }

    public function test_message_edit_and_delete_obey_mutation_window_policy_and_use_placeholder_on_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $conversation = Conversation::create([
            'participant_one_id' => $admin->id,
            'participant_two_id' => $instructor->id,
            'pair_key' => Conversation::makePairKey($admin->id, $instructor->id),
            'conversation_type' => Conversation::TYPE_DIRECT,
            'status' => Conversation::STATUS_ACTIVE,
            'context_key' => Conversation::makeContextKey(Conversation::TYPE_DIRECT, null),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'message_body' => 'Original body',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('chat.messages.update', ['message' => $message->id]), [
                'message_body' => 'Edited body',
            ])
            ->assertOk()
            ->assertJsonPath('message.message_body', 'Edited body');

        $this->actingAs($admin)
            ->deleteJson(route('chat.messages.destroy', ['message' => $message->id]))
            ->assertOk()
            ->assertJsonPath('message.message_body', '[message removed]')
            ->assertJsonPath('message.is_deleted', true);

        $expiredMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $instructor->id,
            'message_body' => 'Will expire',
        ]);

        $expiredMessage->forceFill([
            'created_at' => now()->subMinutes(30),
        ])->save();

        $this->actingAs($instructor)
            ->patchJson(route('chat.messages.update', ['message' => $expiredMessage->id]), [
                'message_body' => 'Too late',
            ])
            ->assertForbidden();
    }
}
