document.addEventListener('alpine:init', () => {
    const POPUP_STATE_STORAGE_PREFIX = 'chat.popup.windows';

    window.globalPopupChat = (payload = {}) => ({
        openWindows: [],
        popupStateKey: null,

        async init() {
            await this.bootstrapStore();
            this.popupStateKey = this.resolvePopupStateKey();
            await this.restoreWindowsFromStorage();

            window.addEventListener('open-global-chat', async (event) => {
                await this.openChat(event.detail || {});
            });

            window.addEventListener('beforeunload', () => {
                this.persistWindowsState();
            });

            this._readSyncInterval = setInterval(() => {
                this.syncOpenWindowReadState();
            }, 1500);
        },

        resolvePopupStateKey() {
            const userId = Number(payload.currentUserId || 0);

            if (!userId) {
                return null;
            }

            return `${POPUP_STATE_STORAGE_PREFIX}.${userId}`;
        },

        async bootstrapStore() {
            await this.$store.chat.bootstrapGlobal({
                currentUserId: payload.currentUserId,
                currentUserName: payload.currentUserName,
                currentUserRole: payload.currentUserRole,
                messageMutationWindowMinutes: payload.messageMutationWindowMinutes,
                suggestions: payload.suggestions,
                notificationsEnabled: false,
            });
        },

        async openChat(detail = {}) {
            await this.bootstrapStore();

            const resolved = await this.resolveConversation(detail);

            if (!resolved) {
                return;
            }

            if (resolved.draft) {
                this.openDraftWindow(resolved.draft, detail);
                return;
            }

            const conversationId = resolved.conversationId;

            await this.openWindowByConversationId(conversationId, detail, {
                markRead: true,
                persist: true,
            });
        },

        pruneConversationState(conversationId, shouldPersist = true) {
            const id = Number(conversationId || 0);

            if (!id) {
                return;
            }

            this.openWindows = this.openWindows.filter((windowItem) => Number(windowItem.id) !== id);
            this.$store.chat.purgeConversation(id);

            if (shouldPersist) {
                this.persistWindowsState();
            }
        },

        async openWindowByConversationId(conversationId, detail = {}, options = {}) {
            const config = {
                markRead: options.markRead !== false,
                persist: options.persist !== false,
            };

            const loadedConversation = await this.$store.chat.ensureConversationLoaded(conversationId);

            if (!loadedConversation) {
                this.pruneConversationState(conversationId, config.persist);
                return false;
            }

            this.$store.chat.subscribeConversationChannel(conversationId);
            const loadedMessages = await this.$store.chat.loadMessages(conversationId, true);

            if (loadedMessages === false) {
                this.pruneConversationState(conversationId, config.persist);
                return false;
            }

            const existing = this.openWindows.find((windowItem) => Number(windowItem.id) === Number(conversationId));
            if (existing) {
                existing.isMinimized = false;

                if (config.markRead) {
                    await this.markConversationRead(conversationId);
                }

                this.scrollToBottom(conversationId);

                if (config.persist) {
                    this.persistWindowsState();
                }

                return true;
            }

            if (this.openWindows.length >= 3) {
                this.openWindows.shift();
            }

            this.openWindows.push({
                id: Number(conversationId),
                composer: '',
                queuedAttachments: [],
                isMinimized: false,
                contextLabel: detail.context_label || '',
                fallbackName: detail.name || 'Conversation',
                fallbackAvatar: detail.avatar || null,
                fallbackConversationType: detail.conversation_type || 'direct',
                showMoreSuggestions: false,
                sending: false,
            });

            if (config.markRead) {
                await this.markConversationRead(conversationId);
            }

            this.scrollToBottom(conversationId);

            if (config.persist) {
                this.persistWindowsState();
            }

            return true;
        },

        openDraftWindow(draft, detail = {}) {
            if (!draft?.target_user_id || !draft?.conversation_type) {
                return false;
            }

            const draftKey = [
                'draft',
                draft.target_user_id,
                draft.conversation_type,
                draft.module_id || 0,
                draft.lesson_id || 0,
                draft.lesson_topic_id || 0,
                draft.quiz_id || 0,
            ].join('-');

            const existing = this.openWindows.find((windowItem) => windowItem.draftKey === draftKey);
            if (existing) {
                existing.isMinimized = false;
                return true;
            }

            if (this.openWindows.length >= 3) {
                this.openWindows.shift();
            }

            this.openWindows.push({
                id: draftKey,
                draftKey,
                draft,
                composer: draft.composer || '',
                queuedAttachments: [],
                isMinimized: false,
                contextLabel: detail.context_label || draft.context_label || '',
                fallbackName: detail.name || draft.fallback_name || 'Instructor',
                fallbackAvatar: detail.avatar || draft.fallback_avatar || null,
                fallbackConversationType: draft.conversation_type,
                targetRole: draft.target_role || detail.target_role || null,
                showMoreSuggestions: false,
                sending: false,
            });

            return true;
        },

        restoreWindowsPayload() {
            if (!this.popupStateKey) {
                return [];
            }

            try {
                const serialized = window.localStorage.getItem(this.popupStateKey);

                if (!serialized) {
                    return [];
                }

                const payload = JSON.parse(serialized);

                if (!Array.isArray(payload?.windows)) {
                    return [];
                }

                return payload.windows
                    .map((entry, index) => ({
                        conversation_id: Number(entry.conversation_id || 0),
                        is_minimized: Boolean(entry.is_minimized),
                        context_label: entry.context_label || '',
                        name: entry.name || 'Conversation',
                        avatar: entry.avatar || null,
                        conversation_type: entry.conversation_type || 'direct',
                        position: Number(entry.position ?? index),
                    }))
                    .filter((entry) => entry.conversation_id > 0)
                    .sort((a, b) => a.position - b.position)
                    .slice(0, 3);
            } catch (error) {
                return [];
            }
        },

        async restoreWindowsFromStorage() {
            const restoredWindows = this.restoreWindowsPayload();

            if (restoredWindows.length < 1) {
                return;
            }

            for (const windowConfig of restoredWindows) {
                const restoredWindowOpened = await this.openWindowByConversationId(windowConfig.conversation_id, {
                    context_label: windowConfig.context_label,
                    name: windowConfig.name,
                    avatar: windowConfig.avatar,
                    conversation_type: windowConfig.conversation_type,
                }, {
                    markRead: false,
                    persist: false,
                });

                if (!restoredWindowOpened) {
                    continue;
                }

                const restored = this.openWindows.find((entry) => Number(entry.id) === Number(windowConfig.conversation_id));

                if (restored) {
                    restored.isMinimized = Boolean(windowConfig.is_minimized);
                }
            }

            this.persistWindowsState();
        },

        persistWindowsState() {
            if (!this.popupStateKey) {
                return;
            }

            const windows = this.openWindows
                .filter((windowItem) => !this.isDraft(windowItem))
                .slice(0, 3)
                .map((windowItem, index) => ({
                conversation_id: Number(windowItem.id),
                is_minimized: Boolean(windowItem.isMinimized),
                context_label: windowItem.contextLabel || '',
                name: windowItem.fallbackName || '',
                avatar: windowItem.fallbackAvatar || null,
                conversation_type: windowItem.fallbackConversationType || 'direct',
                position: index,
                }));

            try {
                if (windows.length < 1) {
                    window.localStorage.removeItem(this.popupStateKey);
                    return;
                }

                window.localStorage.setItem(this.popupStateKey, JSON.stringify({ windows }));
            } catch (error) {
                // Ignore storage write failures.
            }
        },

        async resolveConversation(detail = {}) {
            const explicitConversationId = Number(detail.conversation_id || 0);

            if (explicitConversationId > 0) {
                return { conversationId: explicitConversationId };
            }

            const startPayload = {
                target_user_id: Number(detail.target_user_id || 0),
                conversation_type: detail.conversation_type || 'direct',
                module_id: detail.module_id,
                lesson_id: detail.lesson_id,
                lesson_topic_id: detail.lesson_topic_id,
                quiz_id: detail.quiz_id,
                initial_message: detail.initial_message,
                target_role: detail.target_role || detail.targetRole,
                context_label: detail.context_label || detail.contextLabel,
                name: detail.name,
                avatar: detail.avatar,
            };

            if (!startPayload.target_user_id) {
                return null;
            }

            let startResult = null;

            try {
                startResult = await this.$store.chat.startConversation(startPayload, false);
            } catch (error) {
                const message = error?.response?.data?.message || 'Unable to open this conversation right now.';

                if (window.toast?.error) {
                    window.toast.error(message);
                }

                return null;
            }

            if (startResult?.requires_initial_message) {
                return { draft: startResult.draft || this.$store.chat.pendingConversationDraft };
            }

            const conversationId = Number(startResult?.conversation?.id || 0) || null;

            return conversationId ? { conversationId } : null;
        },

        isDraft(windowItem) {
            return Boolean(windowItem?.draftKey);
        },

        conversationFor(windowItem) {
            return this.$store.chat.findConversationById(windowItem.id);
        },

        windowTitle(windowItem) {
            const conversation = this.conversationFor(windowItem);

            if (!conversation) {
                return windowItem.fallbackName || 'Conversation';
            }

            return this.$store.chat.conversationParticipantName(conversation);
        },

        windowAvatar(windowItem) {
            const conversation = this.conversationFor(windowItem);

            if (!conversation) {
                return windowItem.fallbackAvatar;
            }

            return this.$store.chat.conversationParticipantAvatar(conversation) || windowItem.fallbackAvatar;
        },

        windowStatus(windowItem) {
            const conversation = this.conversationFor(windowItem);

            if (!conversation) {
                return 'offline';
            }

            return this.$store.chat.conversationParticipantStatus(conversation);
        },

        windowContext(windowItem) {
            const conversation = this.conversationFor(windowItem);

            if (!conversation) {
                return windowItem.contextLabel || '';
            }

            return conversation.context_label || windowItem.contextLabel || '';
        },

        messagesFor(windowItem) {
            return this.$store.chat.messagesByConversation[windowItem.id] || [];
        },

        messageWindowState(windowItem) {
            return this.$store.chat.messageWindow(windowItem.id);
        },

        typingLabel(windowItem) {
            return this.$store.chat.typingLabelForConversation(windowItem.id);
        },

        unreadCount(windowItem) {
            return Number(this.$store.chat.unreadByConversation[windowItem.id] || 0);
        },

        canSend(windowItem) {
            if (this.isDraft(windowItem)) {
                return this.$store.chat.isSuggestionEligible(
                    windowItem.targetRole || windowItem.draft?.target_role,
                    windowItem.draft?.conversation_type,
                );
            }

            return this.$store.chat.canSendToConversation(windowItem.id);
        },

        suggestionsFor(windowItem, limit = 4, excludedKeys = []) {
            if (this.isDraft(windowItem)) {
                if (!this.$store.chat.isSuggestionEligible(
                    windowItem.targetRole || windowItem.draft?.target_role,
                    windowItem.draft?.conversation_type,
                )) {
                    return [];
                }

                return this.$store.chat.suggestionsForContext(windowItem.draft, limit, excludedKeys);
            }

            const conversation = this.conversationFor(windowItem);
            if (!conversation || !this.$store.chat.isSuggestionEligible(
                conversation.other_participant?.role,
                conversation.conversation_type,
            )) {
                return [];
            }

            return this.$store.chat.suggestionsForContext({
                conversation_type: conversation.conversation_type,
                target_role: conversation.other_participant?.role,
            }, limit, excludedKeys);
        },

        applySuggestion(windowItem, suggestion) {
            if (!suggestion?.text) {
                return;
            }

            if (windowItem.composer?.trim()
                && !window.confirm('Replace your current draft with this suggestion?')) {
                return;
            }

            windowItem.composer = suggestion.text;

            this.$nextTick(() => {
                const input = this.$el.querySelector(`[data-popup-composer='${windowItem.id}']`);
                input?.focus();
            });
        },

        isPending(windowItem) {
            const conversation = this.conversationFor(windowItem);
            return conversation?.status === 'pending_request';
        },

        isDeclined(windowItem) {
            const conversation = this.conversationFor(windowItem);
            return conversation?.status === 'declined';
        },

        pendingRequest(windowItem) {
            const conversation = this.conversationFor(windowItem);

            if (!conversation || conversation.status !== 'pending_request') {
                return null;
            }

            return conversation.pending_request || null;
        },

        shouldShowPendingRequestActions(windowItem) {
            const request = this.pendingRequest(windowItem);

            return this.$store.chat.currentUserRole === 'instructor'
                && !!request?.id;
        },

        async acceptRequest(windowItem) {
            const request = this.pendingRequest(windowItem);

            if (!request?.id) {
                return;
            }

            await this.$store.chat.acceptRequest(request.id);
            await this.$store.chat.ensureConversationLoaded(windowItem.id);
            await this.markConversationRead(windowItem.id);
        },

        async declineRequest(windowItem) {
            const request = this.pendingRequest(windowItem);

            if (!request?.id) {
                return;
            }

            await this.$store.chat.declineRequest(request.id);
            await this.$store.chat.ensureConversationLoaded(windowItem.id);
            await this.markConversationRead(windowItem.id);
        },

        queueAttachments(windowItem, event) {
            const files = Array.from(event?.target?.files || []);

            files.forEach((file) => {
                windowItem.queuedAttachments.push({
                    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    file,
                    name: file?.name || 'Attachment',
                    size: Number(file?.size || 0),
                });
            });

            if (event?.target) {
                event.target.value = null;
            }
        },

        removeAttachment(windowItem, attachmentId) {
            windowItem.queuedAttachments = (windowItem.queuedAttachments || []).filter((item) => item.id !== attachmentId);
        },

        openAttachmentPicker(windowItem) {
            const input = document.getElementById(`popup-attachment-${windowItem.id}`);

            if (input) {
                input.click();
            }
        },

        formatSize(bytes) {
            const value = Number(bytes || 0);

            if (value < 1024) {
                return `${value} B`;
            }

            if (value < 1024 * 1024) {
                return `${(value / 1024).toFixed(1)} KB`;
            }

            return `${(value / (1024 * 1024)).toFixed(1)} MB`;
        },

        async sendMessage(windowItem) {
            const body = String(windowItem.composer || '').trim();
            const files = (windowItem.queuedAttachments || []).map((item) => item.file).filter(Boolean);

            if ((!body && files.length < 1) || windowItem.sending) {
                return;
            }

            windowItem.sending = true;

            try {
                if (this.isDraft(windowItem)) {
                    if (files.length > 0) {
                        this.$store.chat.composerError = 'Attachments can be added after the conversation starts.';
                        return;
                    }

                    const result = await this.$store.chat.sendConversationDraft(windowItem.draft, body);
                    const conversationId = Number(result?.conversation?.id || 0);

                    if (conversationId > 0) {
                        this.openWindows = this.openWindows.filter((entry) => entry.draftKey !== windowItem.draftKey);
                        await this.openWindowByConversationId(conversationId, {
                            context_label: windowItem.contextLabel,
                            name: windowItem.fallbackName,
                            avatar: windowItem.fallbackAvatar,
                            conversation_type: windowItem.draft.conversation_type,
                        }, {
                            markRead: true,
                            persist: true,
                        });
                    }

                    return;
                }

                await this.$store.chat.sendMessageToConversation(windowItem.id, body, files);
                windowItem.composer = '';
                windowItem.queuedAttachments = [];
                this.scrollToBottom(windowItem.id);
                await this.markConversationRead(windowItem.id);
            } finally {
                windowItem.sending = false;
            }
        },

        closeWindow(windowId) {
            this.openWindows = this.openWindows.filter((windowItem) => Number(windowItem.id) !== Number(windowId));
            this.persistWindowsState();
        },

        async toggleMinimize(windowItem) {
            windowItem.isMinimized = !windowItem.isMinimized;

            if (!windowItem.isMinimized && !this.isDraft(windowItem)) {
                await this.markConversationRead(windowItem.id);
                this.scrollToBottom(windowItem.id);
            }

            this.persistWindowsState();
        },

        async openFullChat(windowItem) {
            if (this.isDraft(windowItem)) {
                return;
            }

            window.location.href = `/chat/conversation/${windowItem.id}`;
        },

        async loadOlderMessages(windowItem) {
            await this.$store.chat.loadMessages(windowItem.id, false);
        },

        async handleMessageScroll(windowItem, event) {
            const target = event?.target;

            if (!target) {
                return;
            }

            if (target.scrollTop <= 72) {
                await this.loadOlderMessages(windowItem);
            }

            const distanceFromBottom = target.scrollHeight - target.scrollTop - target.clientHeight;
            if (distanceFromBottom <= 120) {
                await this.markConversationRead(windowItem.id);
            }
        },

        async markConversationRead(conversationId) {
            try {
                await this.$store.chat.markConversationRead(conversationId);
            } catch (error) {
                // Ignore read sync errors in popup context.
            }

            if (this.$store.chat.unreadByConversation[conversationId] !== undefined) {
                this.$store.chat.unreadByConversation[conversationId] = 0;
                this.$store.chat.syncUnreadBadges();
            }
        },

        scrollToBottom(conversationId) {
            requestAnimationFrame(() => {
                const element = this.$el.querySelector(`[data-popup-messages='${conversationId}']`);

                if (element) {
                    element.scrollTop = element.scrollHeight;
                }
            });
        },

        syncOpenWindowReadState() {
            this.openWindows.forEach((windowItem) => {
                if (!this.isDraft(windowItem) && !windowItem.isMinimized && this.unreadCount(windowItem) > 0) {
                    this.markConversationRead(windowItem.id);
                }
            });
        },
    });
});
