<?php

namespace Tests\Feature\Chat;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChatSuggestionWorkflowTest extends TestCase
{
    public function test_chat_store_exposes_local_suggestion_and_draft_contract(): void
    {
        $contents = File::get(resource_path('js/chat/store.js'));

        $this->assertStringContainsString('suggestions:', $contents);
        $this->assertStringContainsString('suggestionsForContext', $contents);
        $this->assertStringContainsString('isSuggestionEligible', $contents);
        $this->assertStringContainsString('prepareConversationDraft', $contents);
        $this->assertStringContainsString('sendConversationDraft', $contents);
        $this->assertStringContainsString('clearConversationDraft', $contents);
    }

    public function test_suggestion_selection_has_no_dedicated_send_endpoint_or_message_type(): void
    {
        $store = File::get(resource_path('js/chat/store.js'));
        $panel = File::get(resource_path('views/chat/partials/conversation-panel.blade.php'));

        $this->assertStringNotContainsString('/chat/suggestions/', $store.$panel);
        $this->assertStringNotContainsString("message_type: 'suggestion'", $store.$panel);
    }
}
