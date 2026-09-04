@php
    $user = auth()->user();

    $startContext = array_filter([
        'target_user_id' => request()->query('target_user_id'),
        'conversation_type' => request()->query('conversation_type'),
        'module_id' => request()->query('module_id'),
        'lesson_id' => request()->query('lesson_id'),
        'lesson_topic_id' => request()->query('lesson_topic_id'),
        'quiz_id' => request()->query('quiz_id'),
        'initial_message' => request()->query('initial_message'),
    ], static fn ($value) => $value !== null && $value !== '');

    $chatBootstrap = [
        'currentUserId' => $user?->id,
        'currentUserName' => $user?->name,
        'currentUserRole' => $user?->role,
        'messageMutationWindowMinutes' => (int) config('chat.message_mutation_window_minutes', 15),
        'suggestions' => app(\App\Services\Chat\ChatSuggestionCatalog::class)->forUser($user),
        'reportReasons' => collect(\App\Enums\MessageReportReason::cases())
            ->map(fn ($reason) => ['value' => $reason->value, 'label' => $reason->label()])
            ->values()
            ->all(),
        'initialConversationId' => request()->query('conversation_id'),
        'startContext' => $startContext,
    ];
@endphp

<div
    class="flex flex-col overflow-hidden bg-white border border-gray-200 shadow-lg rounded-2xl"
    data-chat-root
    x-data
    x-init="$store.chat.bootstrap({{ \Illuminate\Support\Js::from($chatBootstrap) }})"
>
    <!-- Header Area -->
    <div class="flex items-center justify-between gap-3 px-6 py-5 text-white bg-gradient-to-r from-purple-700 to-pink-500">
        <div>
            <h1 class="text-xl font-bold tracking-tight">Connections</h1>
            <p class="mt-1 text-sm text-purple-100">Connect with instructors, learners, and platform support.</p>
        </div>
    </div>

    <!-- Chat Container -->
    <div class="flex flex-col lg:flex-row min-h-[640px] bg-white">
        <div class="w-full lg:w-auto lg:border-r lg:border-gray-200">
            @include('chat.partials.request-list')
            @include('chat.partials.conversation-list')
        </div>

        @include('chat.partials.conversation-panel')
    </div>
</div>
