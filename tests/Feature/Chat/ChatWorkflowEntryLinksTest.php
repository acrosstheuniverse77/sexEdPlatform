<?php

namespace Tests\Feature\Chat;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChatWorkflowEntryLinksTest extends TestCase
{
    public function test_module_lesson_quiz_pages_include_contextual_chat_entry_links(): void
    {
        $moduleShow = File::get(resource_path('views/learner/modules/show.blade.php'));
        $lessonShow = File::get(resource_path('views/learner/lessons/show.blade.php'));
        $quizPage = File::get(resource_path('views/learner/lessons/partials/quiz-page.blade.php'));
        $topicPage = File::get(resource_path('views/learner/lessons/partials/topic-page.blade.php'));

        $this->assertStringContainsString("conversation_type: 'module_chat'", $moduleShow);
        $this->assertStringContainsString("conversation_type: 'lesson_chat'", $lessonShow);
        $this->assertStringContainsString("conversation_type: 'lesson_topic_chat'", $topicPage);
        $this->assertStringContainsString("conversation_type: 'quiz_help'", $quizPage);
        $this->assertStringContainsString("target_user_id: {{ \$module->created_by }}", $lessonShow);
        $this->assertStringContainsString("target_user_id: {{ \$module->created_by }}", $topicPage);
        $this->assertStringContainsString("target_user_id: {{ \$module->created_by }}", $quizPage);
    }

    public function test_instructor_learner_management_page_contains_direct_chat_action(): void
    {
        $instructorUsersIndex = File::get(resource_path('views/instructor/users/index.blade.php'));

        $this->assertStringContainsString("conversation_type: 'direct'", $instructorUsersIndex);
        $this->assertStringContainsString('Message learner', $instructorUsersIndex);
    }

    public function test_chat_sidebar_contains_support_discovery_action_for_non_admin_roles(): void
    {
        $conversationList = File::get(resource_path('views/chat/partials/conversation-list.blade.php'));

        $this->assertStringContainsString('Contact Platform Support', $conversationList);
        $this->assertStringContainsString("conversation_type: 'admin_support_chat'", $conversationList);
    }
}

