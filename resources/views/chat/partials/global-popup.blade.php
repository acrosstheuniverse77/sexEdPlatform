@php
    $user = auth()->user();
@endphp
@if($user)
<div
    x-data="globalPopupChat({
        currentUserId: {{ (int) $user->id }},
        currentUserName: @js($user->name),
        currentUserRole: @js($user->role),
        messageMutationWindowMinutes: {{ (int) config('chat.message_mutation_window_minutes', 15) }},
        suggestions: @js(app(\App\Services\Chat\ChatSuggestionCatalog::class)->forUser($user))
    })"
    x-init="init()"
    x-cloak
    class="pointer-events-none fixed bottom-0 right-4 z-50 mb-4 flex items-end gap-4"
>
    <template x-for="windowItem in openWindows" :key="`popup-${windowItem.id}`">
        <div
            class="pointer-events-auto w-[22rem] overflow-hidden rounded-t-2xl border border-gray-200 bg-white shadow-[0_-4px_24px_-8px_rgba(0,0,0,0.2)] transition-all duration-300"
            :class="windowItem.isMinimized ? 'h-16 cursor-pointer' : 'h-[30rem]'"
            @click="if (windowItem.isMinimized) { toggleMinimize(windowItem) }"
        >
            <div class="flex h-16 items-center justify-between border-b border-fuchsia-200 bg-gradient-to-r from-fuchsia-600 to-indigo-600 px-3 text-white">
                <div class="flex min-w-0 items-center gap-2">
                    <div class="relative h-9 w-9 shrink-0">
                        <template x-if="windowAvatar(windowItem)">
                            <img :src="windowAvatar(windowItem)" alt="Avatar" class="h-9 w-9 rounded-full border border-white/40 object-cover">
                        </template>
                        <template x-if="!windowAvatar(windowItem)">
                            <div class="h-9 w-9 rounded-full bg-white/20 text-white flex items-center justify-center text-sm font-semibold">
                                <span x-text="windowTitle(windowItem).slice(0, 1).toUpperCase()"></span>
                            </div>
                        </template>
                        <span class="absolute -bottom-0.5 -right-0.5 inline-flex h-3 w-3 rounded-full border-2 border-white" :class="$store.chat.statusToneClass(windowStatus(windowItem))"></span>
                    </div>

                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" x-text="windowTitle(windowItem)"></p>
                        <p
                            class="truncate text-[11px] text-white/80"
                            x-text="`${$store.chat.statusLabel(windowStatus(windowItem))} • ${windowContext(windowItem) || 'Direct Conversation'}`"
                        ></p>
                        <p class="truncate text-[11px] text-white/80" x-show="typingLabel(windowItem)" x-text="typingLabel(windowItem)"></p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <span
                        class="inline-flex min-w-5 items-center justify-center rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-semibold"
                        x-show="windowItem.isMinimized && unreadCount(windowItem) > 0"
                        x-text="unreadCount(windowItem) > 99 ? '99+' : unreadCount(windowItem)"
                    ></span>

                    <button type="button" @click.stop="openFullChat(windowItem)" class="rounded-full p-1.5 text-white/85 hover:bg-white/15 hover:text-white" title="View Full Chat">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H8M17 7V16" />
                        </svg>
                    </button>
                    <button type="button" @click.stop="toggleMinimize(windowItem)" class="rounded-full p-1.5 text-white/85 hover:bg-white/15 hover:text-white" title="Minimize">
                        <svg x-show="!windowItem.isMinimized" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" />
                        </svg>
                        <svg x-show="windowItem.isMinimized" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                    <button type="button" @click.stop="closeWindow(windowItem.id)" class="rounded-full p-1.5 text-white/85 hover:bg-white/15 hover:text-white" title="Close">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div x-show="!windowItem.isMinimized" class="flex h-[calc(30rem-4rem)] flex-col bg-gray-50">
                <div class="space-y-2 border-b border-gray-100 bg-white px-3 py-2" x-show="isPending(windowItem)">
                    <p class="text-xs font-semibold text-amber-800">Message Request</p>
                    <p class="text-xs text-amber-700">This learner wants to start a conversation with you.</p>
                    <div class="flex items-center gap-2" x-show="shouldShowPendingRequestActions(windowItem)">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="$store.chat.isResolvingRequest(pendingRequest(windowItem)?.id)"
                            @click="acceptRequest(windowItem)"
                        >
                            Accept Request
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="$store.chat.isResolvingRequest(pendingRequest(windowItem)?.id)"
                            @click="declineRequest(windowItem)"
                        >
                            Decline Request
                        </button>
                    </div>
                </div>

                <div class="border-b border-gray-100 bg-rose-50 px-3 py-2" x-show="isDeclined(windowItem)">
                    <p class="text-xs font-semibold text-rose-700">This conversation request was declined.</p>
                </div>

                <div class="border-b border-gray-100 bg-white px-3 py-1.5" x-show="messageWindowState(windowItem)?.hasMoreBefore">
                    <button
                        type="button"
                        class="w-full rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                        @click="loadOlderMessages(windowItem)"
                    >
                        Load older messages
                    </button>
                </div>

                <div
                    class="flex-1 space-y-2 overflow-y-auto px-3 py-3"
                    :data-popup-messages="windowItem.id"
                    @scroll.passive="handleMessageScroll(windowItem, $event)"
                >
                    <template x-if="messagesFor(windowItem).length < 1 && suggestionsFor(windowItem, 7).length > 0">
                        <div data-chat-suggestion-empty-state class="rounded-xl border border-indigo-100 bg-indigo-50/70 px-3 py-3">
                            <p class="text-sm font-semibold text-gray-900" x-text="isDraft(windowItem) ? 'Start a conversation' : 'Suggested questions'"></p>
                            <p class="mt-1 text-xs leading-5 text-gray-600" x-text="isDraft(windowItem) ? 'Choose a prompt or type your own message. You can edit it before sending.' : 'Choose a prompt to help guide your question.'"></p>
                            <div class="mt-2 flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">
                                <template x-for="suggestion in suggestionsFor(windowItem, windowItem.showMoreSuggestions ? 7 : 4)" :key="`popup-suggestion-empty-${windowItem.id}-${suggestion.key}`">
                                    <button
                                        type="button"
                                        data-chat-suggestion-key
                                        :data-chat-suggestion-key="suggestion.key"
                                        class="rounded-full border border-indigo-200 bg-white px-2.5 py-1.5 text-left text-[11px] font-medium text-indigo-700 transition hover:border-indigo-400 hover:bg-indigo-100"
                                        @click="applySuggestion(windowItem, suggestion)"
                                        :aria-label="`Use suggestion: ${suggestion.text}`"
                                        x-text="suggestion.text"
                                    ></button>
                                </template>
                                <button
                                    type="button"
                                    class="rounded-full border border-gray-200 bg-white px-2.5 py-1.5 text-left text-[11px] font-semibold text-gray-600 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700"
                                    x-show="suggestionsFor(windowItem, 7).length > 4"
                                    @click="windowItem.showMoreSuggestions = !windowItem.showMoreSuggestions"
                                    x-text="windowItem.showMoreSuggestions ? 'Show fewer' : 'More suggestions'"
                                ></button>
                            </div>
                        </div>
                    </template>

                    <template x-if="messagesFor(windowItem).length < 1">
                        <div x-show="suggestionsFor(windowItem, 7).length < 1" class="rounded-lg border border-dashed border-gray-300 bg-white px-3 py-2 text-xs text-gray-500">
                            No messages yet.
                        </div>
                    </template>

                    <template x-for="message in messagesFor(windowItem)" :key="`popup-message-${windowItem.id}-${message.id}`">
                        <div class="flex" :class="$store.chat.isOwnMessage(message) ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[84%] rounded-2xl px-3 py-2 text-xs shadow-sm" :class="$store.chat.isOwnMessage(message) ? 'rounded-br-sm bg-indigo-600 text-white' : 'rounded-bl-sm border border-gray-200 bg-white text-gray-900'">
                                <p x-show="message.message_body" :class="message.is_deleted ? 'italic opacity-80' : ''" x-text="message.message_body"></p>

                                <div class="mt-2 grid gap-2" x-show="(message.attachments || []).length > 0 && !message.is_deleted">
                                    <template x-for="attachment in (message.attachments || [])" :key="`popup-attachment-${message.id}-${attachment.id}`">
                                        <div class="rounded-lg border border-gray-200/80 bg-white/80 p-2 text-[11px] text-gray-700">
                                            <template x-if="attachment.is_image">
                                                <a :href="attachment.url" target="_blank" rel="noopener">
                                                    <img :src="attachment.preview_url || attachment.url" alt="Attachment" class="max-h-36 w-full rounded-md object-cover">
                                                </a>
                                            </template>

                                            <template x-if="attachment.is_video">
                                                <video :src="attachment.url" controls preload="metadata" class="max-h-40 w-full rounded-md bg-black"></video>
                                            </template>

                                            <template x-if="attachment.is_audio && !attachment.is_video && !attachment.is_image">
                                                <audio :src="attachment.url" controls class="w-full"></audio>
                                            </template>

                                            <template x-if="!attachment.is_image && !attachment.is_video && !attachment.is_audio">
                                                <a :href="attachment.url" target="_blank" rel="noopener" class="font-medium text-indigo-700 hover:underline" x-text="attachment.file_name"></a>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <p class="mt-1 text-[10px]" :class="$store.chat.isOwnMessage(message) ? 'text-indigo-100' : 'text-gray-400'" x-text="$store.chat.formatMessageTime(message.created_at)"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="border-t border-gray-100 bg-white px-3 py-2">
                    <div
                        data-chat-suggestion-compact
                        class="mb-2 flex items-center gap-1.5 overflow-x-auto pb-0.5"
                        x-show="messagesFor(windowItem).length > 0 && suggestionsFor(windowItem, 3).length > 0"
                    >
                        <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Suggested</span>
                        <template x-for="suggestion in suggestionsFor(windowItem, 3)" :key="`popup-suggestion-compact-${windowItem.id}-${suggestion.key}`">
                            <button
                                type="button"
                                data-chat-suggestion-key
                                :data-chat-suggestion-key="suggestion.key"
                                class="shrink-0 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-medium text-gray-700 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                                @click="applySuggestion(windowItem, suggestion)"
                                :aria-label="`Use suggestion: ${suggestion.text}`"
                                x-text="suggestion.text"
                            ></button>
                        </template>
                    </div>

                    <div class="grid gap-1.5" x-show="(windowItem.queuedAttachments || []).length > 0">
                        <template x-for="item in (windowItem.queuedAttachments || [])" :key="item.id">
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] text-gray-600">
                                <p class="truncate" x-text="`${item.name} • ${formatSize(item.size)}`"></p>
                                <button type="button" class="text-gray-500 hover:text-gray-700" @click="removeAttachment(windowItem, item.id)">Remove</button>
                            </div>
                        </template>
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <input
                            type="file"
                            multiple
                            class="hidden"
                            :id="`popup-attachment-${windowItem.id}`"
                            @change="queueAttachments(windowItem, $event)"
                        >

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-600"
                            :disabled="!canSend(windowItem)"
                            @click="openAttachmentPicker(windowItem)"
                            title="Attach files"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.656-5.656L5.757 10.757a6 6 0 108.486 8.486L20 13" />
                            </svg>
                        </button>

                        <input
                            type="text"
                            x-model="windowItem.composer"
                            :data-popup-composer="windowItem.id"
                            :disabled="!canSend(windowItem) || windowItem.sending"
                            @input="$store.chat.notifyTyping(windowItem.id, windowItem.composer.trim().length > 0)"
                            @blur="$store.chat.notifyTyping(windowItem.id, false)"
                            @keydown.enter.prevent="sendMessage(windowItem)"
                            :placeholder="canSend(windowItem) ? 'Type your message...' : (isPending(windowItem) ? 'Waiting for instructor approval.' : 'Messaging is disabled in this conversation')"
                            class="flex-1 rounded-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-r from-fuchsia-600 to-indigo-600 text-white hover:from-fuchsia-700 hover:to-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="windowItem.sending || (!windowItem.composer.trim() && (windowItem.queuedAttachments || []).length < 1) || !canSend(windowItem)"
                            @click="sendMessage(windowItem)"
                            title="Send message"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
