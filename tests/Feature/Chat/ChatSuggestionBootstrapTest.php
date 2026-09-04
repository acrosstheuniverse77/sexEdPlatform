<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\MessageRequest;
use App\Models\User;
use Tests\TestCase;

class ChatSuggestionBootstrapTest extends TestCase
{
    public function test_kid_chat_bootstrap_contains_shared_suggestions_but_not_adult_only_entries(): void
    {
        $learner = $this->createUserWithRole('learner', [
            'age_bracket_cached' => 'kids',
        ]);

        $response = $this->actingAs($learner)->get(route('chat.page'));

        $response->assertOk()
            ->assertSee('suggestions', false)
            ->assertSee('lesson.explain.topic', false)
            ->assertDontSee('module.important.idea', false);
    }

    public function test_adult_chat_bootstrap_contains_adult_suggestions(): void
    {
        $learner = $this->createUserWithRole('learner', [
            'age_bracket_cached' => 'adults',
        ]);

        $this->actingAs($learner)
            ->get(route('chat.page'))
            ->assertOk()
            ->assertSee('module.important.idea', false);
    }

    public function test_request_gated_start_without_initial_message_does_not_mutate_chat_state(): void
    {
        $learner = $this->createUserWithRole('learner');
        $instructor = $this->createUserWithRole('instructor');

        $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $instructor->id,
                'conversation_type' => Conversation::TYPE_DIRECT,
            ])
            ->assertStatus(428)
            ->assertJsonPath('requires_initial_message', true);

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('message_requests', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_submitted_request_still_uses_existing_request_flow(): void
    {
        $learner = $this->createUserWithRole('learner');
        $instructor = $this->createUserWithRole('instructor');

        $this->actingAs($learner)
            ->postJson(route('chat.conversations.start'), [
                'target_user_id' => $instructor->id,
                'conversation_type' => Conversation::TYPE_DIRECT,
                'initial_message' => 'Can you explain this topic?',
            ])
            ->assertStatus(202)
            ->assertJsonPath('message_request.status', MessageRequest::STATUS_PENDING);

        $this->assertDatabaseCount('message_requests', 1);
        $this->assertDatabaseCount('messages', 1);
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create([
            ...$attributes,
            'role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
