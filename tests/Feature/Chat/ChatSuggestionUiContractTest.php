<?php

namespace Tests\Feature\Chat;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChatSuggestionUiContractTest extends TestCase
{
    public function test_full_chat_contains_empty_and_compact_suggestion_contracts(): void
    {
        $contents = File::get(resource_path('views/chat/partials/conversation-panel.blade.php'));

        $this->assertStringContainsString('data-chat-suggestion-empty-state', $contents);
        $this->assertStringContainsString('data-chat-suggestion-compact', $contents);
        $this->assertStringContainsString('data-chat-suggestion-key', $contents);
        $this->assertStringContainsString('Suggested questions', $contents);
        $this->assertStringContainsString('Start a conversation', $contents);
        $this->assertStringContainsString('applySuggestion', $contents);
        $this->assertStringContainsString('sendConversationDraft', $contents);
    }

    public function test_suggestion_ui_is_learner_instructor_scoped_and_not_a_message_type(): void
    {
        $panel = File::get(resource_path('views/chat/partials/conversation-panel.blade.php'));
        $store = File::get(resource_path('js/chat/store.js'));

        $this->assertStringContainsString('isSuggestionEligible', $panel.$store);
        $this->assertStringNotContainsString("message_type: 'suggestion'", $panel.$store);
        $this->assertStringNotContainsString('/chat/suggestions/', $panel.$store);
    }

    public function test_popup_contains_suggestion_bootstrap_and_controls(): void
    {
        $popup = File::get(resource_path('views/chat/partials/global-popup.blade.php'));
        $popupScript = File::get(resource_path('js/chat/global-popup.js'));

        $this->assertStringContainsString('suggestions:', $popup);
        $this->assertStringContainsString('data-chat-suggestion-key', $popup);
        $this->assertStringContainsString('draftKey', $popupScript);
        $this->assertStringContainsString('sendConversationDraft', $popupScript);
        $this->assertStringContainsString("filter((windowItem) => !this.isDraft(windowItem))", $popupScript);
    }
}
