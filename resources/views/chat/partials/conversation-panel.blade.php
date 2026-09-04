<section
    class="flex-1 flex flex-col bg-white"
    data-chat-conversation-panel
    x-data="{
        composer: '',
        queuedAttachments: [],
        editingMessageId: null,
        editingBody: '',
        activeMenuMessageId: null,
        reportingMessage: null,
        reportReasonCode: '',
        reportCustomReason: '',
        reportSubmitting: false,
        reportError: '',
        isRecording: false,
        mediaRecorder: null,
        recordingStream: null,
        recordingChunks: [],
        discardRecording: false,
        recordingSeconds: 0,
        recordingTimer: null,
        showMoreSuggestions: false,
        makeQueuedAttachment(file, source = 'file') {
            const mime = String(file?.type || '').toLowerCase();
            const isImage = mime.startsWith('image/');
            const isVideo = mime.startsWith('video/');
            const isAudio = mime.startsWith('audio/');

            return {
                id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                file,
                name: file?.name || 'Attachment',
                size: Number(file?.size || 0),
                mime,
                source,
                isImage,
                isVideo,
                isAudio,
                previewUrl: (isImage || isVideo || isAudio) ? URL.createObjectURL(file) : null,
            };
        },
        queueAttachments(event) {
            const files = Array.from(event?.target?.files || []);
            files.forEach((file) => {
                this.queuedAttachments.push(this.makeQueuedAttachment(file, 'file'));
            });

            if (this.$refs.attachmentInput) {
                this.$refs.attachmentInput.value = null;
            }
        },
        removeQueuedAttachment(index) {
            const removed = this.queuedAttachments.splice(index, 1)[0] || null;

            if (removed?.previewUrl) {
                URL.revokeObjectURL(removed.previewUrl);
            }
        },
        clearQueuedAttachments() {
            this.queuedAttachments.forEach((item) => {
                if (item?.previewUrl) {
                    URL.revokeObjectURL(item.previewUrl);
                }
            });

            this.queuedAttachments = [];
        },
        clearComposerState() {
            this.composer = '';
            this.clearQueuedAttachments();

            if (this.$refs.attachmentInput) {
                this.$refs.attachmentInput.value = null;
            }
        },
        composerCanSend() {
            return Boolean($store.chat.activeConversation()?.can_send)
                || Boolean($store.chat.pendingConversationDraft);
        },
        applySuggestion(suggestion) {
            if (!suggestion?.text) {
                return;
            }

            if (this.composer.trim() && !window.confirm('Replace your current draft with this suggestion?')) {
                return;
            }

            this.composer = suggestion.text;
            this.$nextTick(() => this.$refs.composerInput?.focus());
        },
        async submitComposer() {
            if (!this.composer.trim() && this.queuedAttachments.length < 1) {
                return;
            }

            if ($store.chat.pendingConversationDraft && !$store.chat.activeConversationId) {
                await $store.chat.sendConversationDraft($store.chat.pendingConversationDraft, this.composer);
            } else {
                await $store.chat.sendActiveMessage(this.composer, this.queuedAttachments.map((entry) => entry.file));
            }

            this.clearComposerState();
        },
        startEditing(message) {
            this.editingMessageId = message.id;
            this.editingBody = message.message_body || '';
            this.activeMenuMessageId = null;
        },
        cancelEditing() {
            this.editingMessageId = null;
            this.editingBody = '';
        },
        async saveEditing(message) {
            if (!this.editingBody.trim()) {
                return;
            }

            await $store.chat.updateMessage(message, this.editingBody);
            this.cancelEditing();
        },
        async removeMessage(message) {
            if (!confirm('Remove this message for everyone in this conversation?')) {
                return;
            }

            await $store.chat.deleteMessage(message);
            this.activeMenuMessageId = null;
        },
        toggleMessageMenu(messageId) {
            this.activeMenuMessageId = this.activeMenuMessageId === messageId ? null : messageId;
        },
        closeMessageMenu() {
            this.activeMenuMessageId = null;
        },
        openReportModal(message) {
            this.reportingMessage = message;
            this.reportReasonCode = '';
            this.reportCustomReason = '';
            this.reportError = '';
            this.activeMenuMessageId = null;
        },
        closeReportModal() {
            if (this.reportSubmitting) {
                return;
            }

            this.reportingMessage = null;
            this.reportReasonCode = '';
            this.reportCustomReason = '';
            this.reportError = '';
        },
        async submitReport() {
            if (!this.reportingMessage || !this.reportReasonCode) {
                this.reportError = 'Choose a reporting reason.';
                return;
            }

            if (this.reportReasonCode === 'other' && !this.reportCustomReason.trim()) {
                this.reportError = 'Add a short explanation for Other.';
                return;
            }

            this.reportSubmitting = true;
            this.reportError = '';

            try {
                const response = await $store.chat.reportMessage(this.reportingMessage, {
                    reason_code: this.reportReasonCode,
                    custom_reason: this.reportReasonCode === 'other' ? this.reportCustomReason : '',
                });

                $store.chat.composerError = response?.message || 'Message reported for review.';
                this.reportingMessage = null;
                this.reportReasonCode = '';
                this.reportCustomReason = '';
            } catch (error) {
                this.reportError = error?.response?.data?.message || 'Unable to submit this report right now.';
            } finally {
                this.reportSubmitting = false;
            }
        },
        messageMenuAvailable(message) {
            return !message?.is_deleted;
        },
        canEditOrDelete(message) {
            return $store.chat.canMutateMessage(message) && !message?.is_deleted;
        },
        formatQueuedSize(sizeBytes) {
            const bytes = Number(sizeBytes || 0);

            if (bytes < 1024) {
                return `${bytes} B`;
            }

            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }

            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },
        recordingDurationLabel() {
            const totalSeconds = Number(this.recordingSeconds || 0);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        },
        startRecordingTimer() {
            if (this.recordingTimer) {
                clearInterval(this.recordingTimer);
            }

            this.recordingSeconds = 0;
            this.recordingTimer = setInterval(() => {
                this.recordingSeconds += 1;
            }, 1000);
        },
        stopRecordingTimer() {
            if (this.recordingTimer) {
                clearInterval(this.recordingTimer);
                this.recordingTimer = null;
            }

            this.recordingSeconds = 0;
        },
        cleanupRecordingStream() {
            if (!this.recordingStream) {
                return;
            }

            this.recordingStream.getTracks().forEach((track) => track.stop());
            this.recordingStream = null;
        },
        async startVoiceRecording() {
            if (this.isRecording) {
                return;
            }

            if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
                $store.chat.composerError = 'Voice recording is not supported in this browser.';
                return;
            }

            try {
                this.discardRecording = false;
                this.recordingChunks = [];

                this.recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });

                const preferredMimeTypes = [
                    'audio/webm;codecs=opus',
                    'audio/webm',
                    'audio/ogg;codecs=opus',
                ];

                const supportedMimeType = preferredMimeTypes.find((mimeType) => MediaRecorder.isTypeSupported(mimeType));
                const recorderOptions = supportedMimeType ? { mimeType: supportedMimeType } : undefined;

                this.mediaRecorder = new MediaRecorder(this.recordingStream, recorderOptions);

                this.mediaRecorder.addEventListener('dataavailable', (event) => {
                    if (event.data?.size > 0) {
                        this.recordingChunks.push(event.data);
                    }
                });

                this.mediaRecorder.addEventListener('stop', () => {
                    const mimeType = this.mediaRecorder?.mimeType || 'audio/webm';

                    if (!this.discardRecording && this.recordingChunks.length > 0) {
                        const blob = new Blob(this.recordingChunks, { type: mimeType });
                        const extension = mimeType.includes('ogg') ? 'ogg' : 'webm';
                        const file = new File(
                            [blob],
                            `voice-note-${Date.now()}.${extension}`,
                            { type: mimeType, lastModified: Date.now() }
                        );

                        this.queuedAttachments.push(this.makeQueuedAttachment(file, 'voice-note'));
                    }

                    this.recordingChunks = [];
                    this.mediaRecorder = null;
                    this.discardRecording = false;
                    this.isRecording = false;
                    this.stopRecordingTimer();
                    this.cleanupRecordingStream();
                });

                this.mediaRecorder.start(150);
                this.isRecording = true;
                this.startRecordingTimer();
                $store.chat.composerError = null;
            } catch (error) {
                this.isRecording = false;
                this.stopRecordingTimer();
                this.cleanupRecordingStream();
                $store.chat.composerError = 'Microphone access is required to record a voice note.';
            }
        },
        stopVoiceRecording() {
            if (!this.mediaRecorder || !this.isRecording) {
                return;
            }

            this.mediaRecorder.stop();
        },
        cancelVoiceRecording() {
            if (!this.mediaRecorder || !this.isRecording) {
                return;
            }

            this.discardRecording = true;
            this.mediaRecorder.stop();
        },
        activePendingRequestId() {
            return Number($store.chat.activeConversationPendingRequest()?.id || 0) || null;
        },
        isResolvingActivePendingRequest() {
            const requestId = this.activePendingRequestId();

            if (!requestId) {
                return false;
            }

            return $store.chat.isResolvingRequest(requestId);
        },
        async acceptActivePendingRequest() {
            const requestId = this.activePendingRequestId();

            if (!requestId) {
                return;
            }

            await $store.chat.acceptRequest(requestId);
        },
        async declineActivePendingRequest() {
            const requestId = this.activePendingRequestId();

            if (!requestId) {
                return;
            }

            await $store.chat.declineRequest(requestId);
        },
    }"
    x-show="$store.chat.shouldShowConversationPanel()"
>
    <header class="border-b border-gray-100 bg-gradient-to-r from-fuchsia-50 via-white to-indigo-50 px-5 py-4">
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-500 lg:hidden"
                x-show="$store.chat.activeConversationId"
                @click="$store.chat.openSidebar()"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <template x-if="$store.chat.activeConversation()">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="relative h-10 w-10 flex-shrink-0">
                        <template x-if="$store.chat.conversationParticipantAvatar($store.chat.activeConversation())">
                            <img
                                :src="$store.chat.conversationParticipantAvatar($store.chat.activeConversation())"
                                alt="Avatar"
                                class="h-10 w-10 rounded-full border border-gray-200 object-cover"
                            >
                        </template>
                        <template x-if="!$store.chat.conversationParticipantAvatar($store.chat.activeConversation())">
                            <div class="h-10 w-10 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center text-sm font-semibold">
                                <span x-text="$store.chat.conversationParticipantName($store.chat.activeConversation()).slice(0, 1).toUpperCase()"></span>
                            </div>
                        </template>
                        <span
                            class="absolute -bottom-0.5 -right-0.5 inline-flex h-3 w-3 rounded-full border-2 border-white"
                            :class="$store.chat.statusToneClass($store.chat.conversationParticipantStatus($store.chat.activeConversation()))"
                        ></span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-semibold text-gray-900" x-text="$store.chat.conversationParticipantName($store.chat.activeConversation())"></h2>
                        <div class="mt-0.5 flex items-center gap-2 text-[11px] text-gray-500">
                            <span x-text="($store.chat.activeConversation().other_participant?.role || 'member').replace(/^./, (c) => c.toUpperCase())"></span>
                            <span class="text-gray-300">•</span>
                            <span x-text="$store.chat.statusLabel($store.chat.conversationParticipantStatus($store.chat.activeConversation()))"></span>
                        </div>
                        <p class="mt-0.5 truncate text-[11px] text-gray-500" x-text="$store.chat.activeConversation().context_label"></p>
                        <p
                            class="text-[11px] text-blue-600"
                            x-show="$store.chat.activeTypingLabel()"
                            x-text="$store.chat.activeTypingLabel()"
                        ></p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-fuchsia-50 hover:text-fuchsia-600 transition-colors"
                        title="Pop-Out Chat"
                        @click="$store.chat.openConversationInPopup($store.chat.activeConversation().id, true)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H8M17 7V16" />
                        </svg>
                    </button>
                </div>
            </template>

            <template x-if="!$store.chat.activeConversation()">
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-gray-900">Connections</h2>
                    <p class="text-xs text-gray-500">Select a conversation to begin.</p>
                </div>
            </template>

            <template x-if="$store.chat.pendingConversationDraft && !$store.chat.activeConversationId">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-100 text-sm font-semibold text-gray-700">
                        <template x-if="$store.chat.pendingConversationDraft.fallback_avatar">
                            <img :src="$store.chat.pendingConversationDraft.fallback_avatar" alt="Instructor avatar" class="h-full w-full object-cover">
                        </template>
                        <template x-if="!$store.chat.pendingConversationDraft.fallback_avatar">
                            <span class="flex h-full w-full items-center justify-center" x-text="($store.chat.pendingConversationDraft.fallback_name || 'Instructor').slice(0, 1).toUpperCase()"></span>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-semibold text-gray-900" x-text="$store.chat.pendingConversationDraft.fallback_name || 'Instructor'"></h2>
                        <p class="mt-0.5 truncate text-[11px] text-gray-500" x-text="$store.chat.pendingConversationDraft.context_label || 'Direct Conversation'"></p>
                    </div>
                </div>
            </template>
        </div>
    </header>

    <div
        class="flex-1 overflow-y-auto px-5 py-4 space-y-4 bg-gray-50/30"
        data-chat-message-stream
        @scroll.passive="$store.chat.handleMessageStreamScroll($event)"
        @click="closeMessageMenu()"
    >
        <template x-if="!$store.chat.activeConversationId">
            <div class="h-full min-h-[280px] flex items-center justify-center text-center px-6">
                <div>
                    <p class="text-sm font-semibold text-gray-700">No active conversation</p>
                    <p class="mt-1 text-xs text-gray-500">Select one from the left panel or start a new conversation.</p>
                </div>
            </div>
        </template>

        <template x-if="$store.chat.activeConversationId">
            <div class="space-y-3">
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2"
                    x-show="$store.chat.activeConversationIsPending()"
                >
                    <p class="text-xs font-semibold text-amber-800" x-text="$store.chat.activeConversationStateLabel()"></p>

                    <div class="mt-2 flex items-center gap-2" x-show="$store.chat.shouldShowPendingRequestActions()">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isResolvingActivePendingRequest()"
                            @click="acceptActivePendingRequest()"
                        >
                            Accept Request
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-gradient-to-r from-fuchsia-600 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:from-fuchsia-700 hover:to-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                            :disabled="isResolvingActivePendingRequest()"
                            @click="declineActivePendingRequest()"
                        >
                            Decline Request
                        </button>
                    </div>
                </div>

                <div
                    class="rounded-xl border px-3 py-2"
                    x-show="$store.chat.activeConversationIsSupport()"
                    :class="$store.chat.activeSupportAvailability().available ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'"
                    data-chat-support-availability
                >
                    <p
                        class="text-xs font-semibold"
                        :class="$store.chat.activeSupportAvailability().available ? 'text-emerald-800' : 'text-amber-800'"
                        x-text="$store.chat.activeSupportAvailability().title"
                    ></p>
                    <p
                        class="mt-0.5 text-xs"
                        :class="$store.chat.activeSupportAvailability().available ? 'text-emerald-700' : 'text-amber-700'"
                        x-text="$store.chat.activeSupportAvailability().message"
                    ></p>
                </div>

                <div
                    class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2"
                    x-show="$store.chat.activeConversationIsDeclined()"
                >
                    <p class="text-xs font-semibold text-rose-700">This conversation request was declined.</p>
                </div>

                <div
                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2"
                    x-show="$store.chat.activeConversationIsAccepted()"
                >
                    <p class="text-xs font-semibold text-emerald-700">Request Accepted</p>
                </div>

                <p
                    class="text-xs font-semibold text-rose-700"
                    x-show="$store.chat.requestActionError"
                    x-text="$store.chat.requestActionError"
                ></p>

                <div class="flex justify-center" x-show="$store.chat.activeMessageWindow()?.hasMoreBefore">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="$store.chat.activeMessageWindow()?.loadingOlder"
                        @click="$store.chat.loadOlderActiveMessages()"
                    >
                        <span x-show="!$store.chat.activeMessageWindow()?.loadingOlder">Load older messages</span>
                        <span x-show="$store.chat.activeMessageWindow()?.loadingOlder">Loading...</span>
                    </button>
                </div>

                <template x-if="$store.chat.activeMessages().length < 1">
                    <div>
                        <div
                            class="rounded-2xl border border-brand-100 bg-white px-4 py-4 shadow-sm"
                            data-chat-suggestion-empty-state
                            x-show="$store.chat.activeSuggestions(7).length > 0"
                        >
                            <p class="text-sm font-semibold text-gray-800">Start a conversation</p>
                            <p class="mt-1 text-xs leading-relaxed text-gray-500">Have a question about your lessons or learning progress? Choose a suggestion below or type your own message.</p>
                            <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="list" aria-label="Suggested questions">
                                <template x-for="suggestion in $store.chat.activeSuggestions(showMoreSuggestions ? 7 : 4)" :key="`empty-suggestion-${suggestion.key}`">
                                    <button
                                        type="button"
                                        class="min-h-11 flex-shrink-0 rounded-full border border-brand-200 bg-brand-50 px-3 py-2 text-left text-xs font-semibold text-brand-800 transition hover:border-brand-300 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-300"
                                        data-chat-suggestion-key
                                        :data-chat-suggestion-key="suggestion.key"
                                        :aria-label="`Use suggestion: ${suggestion.text}`"
                                        @click="applySuggestion(suggestion)"
                                        x-text="suggestion.text"
                                    ></button>
                                </template>
                                <button
                                    type="button"
                                    class="min-h-11 flex-shrink-0 rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-300"
                                    x-show="$store.chat.activeSuggestions(7).length > 4"
                                    @click="showMoreSuggestions = !showMoreSuggestions"
                                    x-text="showMoreSuggestions ? 'Show fewer' : 'More suggestions'"
                                ></button>
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-3 text-xs text-gray-500"
                            x-show="$store.chat.activeSuggestions(4).length < 1"
                        >
                            No messages yet. Start the conversation.
                        </div>
                    </div>
                </template>

                <template x-for="message in $store.chat.activeMessages()" :key="message.id">
                    <div class="group/message flex items-end gap-2 transition-all duration-300" :class="$store.chat.isOwnMessage(message) ? 'justify-end' : 'justify-start'">
                        <template x-if="!$store.chat.isOwnMessage(message)">
                            <div class="h-8 w-8 flex-shrink-0 mb-1">
                                <template x-if="message.sender_avatar_url">
                                    <img :src="message.sender_avatar_url" alt="Avatar" class="h-full w-full rounded-full border-2 border-white shadow-sm object-cover">
                                </template>
                                <template x-if="!message.sender_avatar_url">
                                    <div class="h-full w-full rounded-full bg-gradient-to-br from-purple-100 to-pink-100 text-purple-700 flex items-center justify-center text-xs font-bold border border-purple-200">
                                        <span x-text="(message.sender_name || 'U').slice(0, 1).toUpperCase()"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="relative max-w-[85%]" @click.stop>
                            <button
                                type="button"
                                class="absolute -top-3 right-1 inline-flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-md transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 active:scale-95"
                                :class="activeMenuMessageId === message.id ? 'opacity-100' : 'opacity-0 scale-95 group-hover/message:opacity-100 group-hover/message:scale-100 focus:opacity-100 focus:scale-100'"
                                x-show="messageMenuAvailable(message)"
                                @click.stop="toggleMessageMenu(message.id)"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6h.01M12 12h.01M12 18h.01" />
                                </svg>
                            </button>

                            <div
                                class="absolute right-1 top-6 z-20 w-48 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl origin-top-right transform transition-all duration-200"
                                x-show="activeMenuMessageId === message.id"
                                x-transition:enter="ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.away="closeMessageMenu()"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                    x-show="canEditOrDelete(message)"
                                    @click="startEditing(message)"
                                >
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Edit Message
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                                    x-show="canEditOrDelete(message)"
                                    @click="removeMessage(message)"
                                >
                                    <svg class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Delete Message
                                </button>
                                <div class="h-px bg-gray-100 my-1" x-show="canEditOrDelete(message)"></div>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                    @click.stop.prevent="openReportModal(message)"
                                >
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                    </svg>
                                    Report Message
                                </button>
                            </div>

                            <div
                                class="rounded-2xl px-4 py-3 shadow-md"
                                :class="$store.chat.isOwnMessage(message)
                                    ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-br-sm'
                                    : 'border border-gray-100 bg-white text-gray-800 rounded-bl-sm'"
                            >
                                <template x-if="editingMessageId !== message.id">
                                    <div class="space-y-2">
                                        <p
                                            class="text-[13px] leading-relaxed"
                                            :class="message.is_deleted ? 'italic opacity-70 text-xs' : ''"
                                            x-text="message.message_body"
                                        ></p>

                                        <div class="grid gap-2" x-show="(message.attachments || []).length > 0 && !message.is_deleted">
                                            <template x-for="attachment in message.attachments" :key="`attachment-${message.id}-${attachment.id}`">
                                                <div class="rounded-lg border border-gray-200/80 bg-white/70 p-2 text-[11px]">
                                                    <template x-if="attachment.is_image">
                                                        <a :href="attachment.url" target="_blank" rel="noopener" class="block">
                                                            <img :src="attachment.preview_url || attachment.url" alt="Attachment" class="max-h-52 w-full rounded-md object-cover">
                                                        </a>
                                                    </template>

                                                    <template x-if="attachment.is_video">
                                                        <video
                                                            :src="attachment.url"
                                                            controls
                                                            preload="metadata"
                                                            class="max-h-56 w-full rounded-md bg-black"
                                                        ></video>
                                                    </template>

                                                    <template x-if="attachment.is_audio && !attachment.is_video && !attachment.is_image">
                                                        <div class="space-y-1.5">
                                                            <audio :src="attachment.url" controls class="w-full"></audio>
                                                            <p class="text-[10px] font-medium text-gray-600" x-text="attachment.is_voice_note ? 'Voice note' : attachment.file_name"></p>
                                                        </div>
                                                    </template>

                                                    <template x-if="!attachment.is_image && !attachment.is_video && !attachment.is_audio">
                                                        <a
                                                            :href="attachment.url"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="inline-flex items-center gap-2 font-medium text-blue-700 hover:underline"
                                                        >
                                                            <span>Attachment:</span>
                                                            <span x-text="attachment.file_name"></span>
                                                        </a>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="editingMessageId === message.id">
                                    <div class="space-y-2">
                                        <textarea
                                            x-model="editingBody"
                                            rows="2"
                                            class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-900"
                                        ></textarea>
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 px-2 py-1 text-[10px] font-semibold text-gray-700"
                                                @click="cancelEditing()"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md bg-blue-600 px-2 py-1 text-[10px] font-semibold text-white"
                                                @click="saveEditing(message)"
                                            >
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <div class="mt-1 flex items-center justify-end gap-2">
                                    <p
                                        class="text-[10px]"
                                        :class="$store.chat.isOwnMessage(message) ? 'text-white/80' : 'text-gray-400'"
                                        x-text="$store.chat.formatMessageTime(message.created_at)"
                                    ></p>
                                    <span
                                        class="text-[10px]"
                                        :class="$store.chat.isOwnMessage(message) ? 'text-white/80' : 'text-gray-400'"
                                        x-show="message.edited_at && !message.is_deleted"
                                    >
                                        edited
                                    </span>
                                    <button
                                        type="button"
                                        class="text-[10px] font-semibold underline"
                                        x-show="message.failed"
                                        @click="$store.chat.retryFailedMessage($store.chat.activeConversationId, message.id)"
                                    >
                                        Retry
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

        </template>
    </div>

    <div
        class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-900/40 px-4"
        x-show="reportingMessage"
        x-cloak
        data-chat-report-modal
        @click.self="closeReportModal()"
        @keydown.escape.window="closeReportModal()"
    >
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl" @click.stop>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Report Message</h3>
                    <p class="mt-1 text-sm text-gray-500">Choose the reason that best describes the issue.</p>
                </div>
                <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="closeReportModal()">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                <p class="line-clamp-3 text-sm text-gray-700" x-text="reportingMessage?.message_body || 'Message unavailable.'"></p>
            </div>

            <div class="mt-4 space-y-2">
                <template x-for="reason in $store.chat.reportReasons" :key="reason.value">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50" :class="reportReasonCode === reason.value ? 'border-brand-300 bg-brand-50 text-brand-800' : 'text-gray-700'">
                        <input type="radio" name="chat_report_reason" class="text-brand-600 focus:ring-brand-500" :value="reason.value" x-model="reportReasonCode">
                        <span x-text="reason.label"></span>
                    </label>
                </template>
            </div>

            <label class="mt-4 block" x-show="reportReasonCode === 'other'">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Custom explanation</span>
                <textarea x-model="reportCustomReason" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100" placeholder="Briefly explain the issue."></textarea>
            </label>

            <p class="mt-3 text-sm font-semibold text-rose-600" x-show="reportError" x-text="reportError"></p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="closeReportModal()">
                    Cancel
                </button>
                <button type="button" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="reportSubmitting" @click="submitReport()">
                    <span x-show="!reportSubmitting">Submit Report</span>
                    <span x-show="reportSubmitting">Submitting...</span>
                </button>
            </div>
        </div>
    </div>

    <footer class="border-t border-gray-100 px-5 py-4 space-y-3">
        <div
            class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2"
            data-chat-suggestion-compact
            x-show="$store.chat.activeConversationId && $store.chat.activeMessages().length > 0 && $store.chat.activeSuggestions(3).length > 0"
        >
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Suggested questions</p>
            <div class="flex gap-2 overflow-x-auto pb-1" role="list" aria-label="Suggested questions">
                <template x-for="suggestion in $store.chat.activeSuggestions(3)" :key="`compact-suggestion-${suggestion.key}`">
                    <button
                        type="button"
                        class="min-h-11 flex-shrink-0 rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-300"
                        data-chat-suggestion-key
                        :data-chat-suggestion-key="suggestion.key"
                        :aria-label="`Use suggestion: ${suggestion.text}`"
                        @click="applySuggestion(suggestion)"
                        x-text="suggestion.text"
                    ></button>
                </template>
                <button
                    type="button"
                    class="min-h-11 flex-shrink-0 rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-300"
                    x-show="$store.chat.draftSuggestions(7).length > 4"
                    @click="showMoreSuggestions = !showMoreSuggestions"
                    x-text="showMoreSuggestions ? 'Show fewer' : 'More suggestions'"
                ></button>
            </div>
        </div>

        <div
            class="rounded-2xl border border-brand-100 bg-white px-4 py-4 shadow-sm"
            data-chat-suggestion-empty-state
                            x-show="$store.chat.pendingConversationDraft && !$store.chat.activeConversationId && $store.chat.draftSuggestions(7).length > 0"
        >
            <p class="text-sm font-semibold text-gray-800">Start a conversation</p>
            <p class="mt-1 text-xs leading-relaxed text-gray-500">Have a question? Choose a suggestion below or type your own message.</p>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="list" aria-label="Suggested questions">
                                <template x-for="suggestion in $store.chat.draftSuggestions(showMoreSuggestions ? 7 : 4)" :key="`draft-suggestion-${suggestion.key}`">
                    <button
                        type="button"
                        class="min-h-11 flex-shrink-0 rounded-full border border-brand-200 bg-brand-50 px-3 py-2 text-left text-xs font-semibold text-brand-800 transition hover:border-brand-300 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-300"
                        data-chat-suggestion-key
                        :data-chat-suggestion-key="suggestion.key"
                        :aria-label="`Use suggestion: ${suggestion.text}`"
                        @click="applySuggestion(suggestion)"
                        x-text="suggestion.text"
                    ></button>
                </template>
            </div>
        </div>

        <template x-if="$store.chat.activeConversationId || $store.chat.pendingConversationDraft">
            <form
                class="space-y-3"
                data-chat-composer
                @submit.prevent="submitComposer()"
            >
                <div
                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
                    x-show="isRecording"
                >
                    Recording voice note... <span class="font-semibold" x-text="recordingDurationLabel()"></span>
                </div>

                <div class="grid gap-2 sm:grid-cols-2" x-show="queuedAttachments.length > 0">
                    <template x-for="(attachment, index) in queuedAttachments" :key="attachment.id">
                        <div class="relative rounded-xl border border-gray-200 bg-gray-50 p-2">
                            <button
                                type="button"
                                class="absolute right-1 top-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-white text-gray-500 shadow"
                                @click="removeQueuedAttachment(index)"
                            >
                                <span class="text-xs">×</span>
                            </button>

                            <template x-if="attachment.isImage">
                                <img :src="attachment.previewUrl" alt="Image preview" class="h-28 w-full rounded-lg object-cover">
                            </template>

                            <template x-if="attachment.isVideo">
                                <video :src="attachment.previewUrl" controls class="h-28 w-full rounded-lg bg-black object-cover"></video>
                            </template>

                            <template x-if="attachment.isAudio && !attachment.isVideo">
                                <audio :src="attachment.previewUrl" controls class="w-full"></audio>
                            </template>

                            <div class="mt-1.5 min-w-0">
                                <p class="truncate text-[11px] font-medium text-gray-700" x-text="attachment.name"></p>
                                <p class="text-[10px] text-gray-500" x-text="`${formatQueuedSize(attachment.size)}${attachment.source === 'voice-note' ? ' • Voice note' : ''}`"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-3">
                    <input
                        type="file"
                        x-ref="attachmentInput"
                        class="hidden"
                        multiple
                        @change="queueAttachments($event)"
                    >

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="$store.chat.loading.send || !$store.chat.activeConversationId || !$store.chat.activeConversation()?.can_send || isRecording"
                        @click="$refs.attachmentInput.click()"
                        title="Attach files"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.656-5.656L5.757 10.757a6 6 0 108.486 8.486L20 13" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-gray-600 transition disabled:cursor-not-allowed disabled:opacity-60"
                        :class="isRecording ? 'border-red-300 bg-red-50 text-red-600' : 'border-gray-200 bg-white hover:bg-gray-50'"
                        :disabled="$store.chat.loading.send || !$store.chat.activeConversationId || !$store.chat.activeConversation()?.can_send"
                        @click="isRecording ? stopVoiceRecording() : startVoiceRecording()"
                        :title="isRecording ? 'Stop recording' : 'Record voice note'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.5a4.5 4.5 0 004.5-4.5V8a4.5 4.5 0 10-9 0v6a4.5 4.5 0 004.5 4.5zm0 0V21m-4-2h8" />
                        </svg>
                    </button>

                        <input
                            type="text"
                            x-ref="composerInput"
                            x-model="composer"
                            @input="$store.chat.notifyTyping($store.chat.activeConversationId, composer.trim().length > 0)"
                            @blur="$store.chat.notifyTyping($store.chat.activeConversationId, false)"
                            :disabled="$store.chat.loading.send || !composerCanSend()"
                            :placeholder="composerCanSend()
                                ? 'Type a message...'
                                : ($store.chat.activeConversationIsPending()
                                    ? 'Waiting for instructor approval.'
                                    : ($store.chat.activeConversationIsDeclined()
                                        ? 'This instructor declined the conversation request.'
                                        : 'Messaging is disabled in this conversation'))"
                            class="flex-1 rounded-full border border-gray-200 bg-white px-5 py-3 text-[13px] text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-200 shadow-inner disabled:cursor-not-allowed disabled:bg-gray-100 transition-all font-medium"
                        >

                    <button
                        type="submit"
                        :disabled="$store.chat.loading.send || !composerCanSend() || isRecording"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-purple-700 to-pink-500 text-white shadow-md transition-transform hover:scale-105 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                        title="Send message"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>

                <div class="flex items-center justify-end" x-show="isRecording">
                    <button
                        type="button"
                        class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                        @click="cancelVoiceRecording()"
                    >
                        Discard recording
                    </button>
                </div>
            </form>
        </template>

        <p class="text-xs text-red-600" x-show="$store.chat.composerError" x-text="$store.chat.composerError"></p>

        <template x-if="!$store.chat.activeConversationId">
            <div class="text-xs text-gray-500">Start a conversation to enable the message composer.</div>
        </template>
    </footer>
</section>
