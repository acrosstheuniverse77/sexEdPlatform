<?php

return [
    'message_mutation_window_minutes' => env('CHAT_MESSAGE_MUTATION_WINDOW_MINUTES', 15),
    'max_attachments_per_message' => env('CHAT_MAX_ATTACHMENTS_PER_MESSAGE', 5),
    'max_attachment_kb' => env('CHAT_MAX_ATTACHMENT_KB', 10240),
    'support_admin_user_id' => env('CHAT_SUPPORT_ADMIN_USER_ID'),
    'support_admin_requires_active' => env('CHAT_SUPPORT_ADMIN_REQUIRES_ACTIVE', true),
    'support_availability' => [
        'default_online' => env('CHAT_SUPPORT_DEFAULT_ONLINE', false),
        'online_title' => env('CHAT_SUPPORT_ONLINE_TITLE', 'Support Available'),
        'offline_title' => env('CHAT_SUPPORT_OFFLINE_TITLE', 'Support Currently Offline'),
        'online_message' => env('CHAT_SUPPORT_ONLINE_MESSAGE', 'You can chat with platform support.'),
        'offline_message' => env('CHAT_SUPPORT_OFFLINE_MESSAGE', 'Your message will be reviewed when support becomes available.'),
    ],
];
