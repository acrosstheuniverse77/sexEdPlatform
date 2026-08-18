<?php

namespace Tests\Feature\Chat;

use App\Models\ParentChildInvitation;
use App\Models\User;
use Tests\TestCase;

class ChatPageRenderTest extends TestCase
{
    public function test_admin_instructor_and_learner_can_open_shared_chat_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $instructor = User::factory()->create(['role' => 'instructor']);
        $instructor->assignRole('instructor');

        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        $this->actingAs($admin)
            ->get(route('chat.page'))
            ->assertOk()
            ->assertSee('data-chat-root', false)
            ->assertSee('data-chat-conversation-list', false)
            ->assertSee('data-chat-conversation-panel', false)
            ->assertSee('data-chat-report-modal', false)
            ->assertSee('@click.stop.prevent="openReportModal(message)"', false);

        $this->actingAs($instructor)
            ->get(route('chat.page'))
            ->assertOk()
            ->assertSee('data-chat-root', false)
            ->assertSee('data-chat-request-list', false);

        $this->actingAs($learner)
            ->get(route('chat.page'))
            ->assertOk()
            ->assertSee('data-chat-root', false)
            ->assertSee('data-chat-request-list', false);
    }

    public function test_admin_messages_page_uses_shared_data_driven_chat_container(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('data-chat-root', false)
            ->assertDontSee('Real-time messaging backend coming soon', false);
    }

    public function test_learner_with_pending_guardian_invitation_can_open_chat_page(): void
    {
        $parent = User::factory()->create(['role' => 'learner']);
        $parent->assignRole('learner');
        $learner = User::factory()->create(['role' => 'learner']);
        $learner->assignRole('learner');

        ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $learner->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'relationship_type' => 'legal_guardian',
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($learner)
            ->get(route('chat.page'))
            ->assertOk();
    }
}
