const defaultState = {
    currentUserId: null,
    currentUserName: null,
    currentUserRole: null,
    messageMutationWindowMinutes: 15,
    reportReasons: [],
    conversations: [],
    messagesByConversation: {},
    messageWindowsByConversation: {},
    activeConversationId: null,
    unreadByConversation: {},
    requests: [],
    discovery: {
        role: null,
        supportAdmin: null,
        supportAvailability: {
            available: false,
            title: 'Support Currently Offline',
            message: 'Your message will be reviewed when support becomes available.',
        },
        contacts: {
            learners: [],
            instructors: [],
        },
    },
    discoveryQuery: '',
    adminDiscoveryTab: 'learners',
    pendingOptimistic: {},
    typingByConversation: {},
    typingEmitTimestamps: {},
    onlineUsers: {},
    mobileSidebarOpen: true,
    composerError: null,
    requestActionError: null,
    resolvingRequestIds: {},
    conversationPagination: {
        currentPage: 0,
        lastPage: 0,
        hasMore: true,
        perPage: 20,
        loadingMore: false,
    },
    loading: {
        conversations: false,
        discovery: false,
        messages: false,
        send: false,
        requests: false,
    },
    subscribedConversationIds: {},
    conversationChannels: {},
    requestChannelSubscribed: false,
    presenceChannelSubscribed: false,
    bootstrapped: false,
    bootstrapping: false,
};

function normalizeAvatarUrl(value, defaultDirectory = 'avatars') {
    const raw = String(value || '').trim();

    if (!raw) {
        return null;
    }

    if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('data:') || raw.startsWith('blob:')) {
        return raw;
    }

    if (raw.startsWith('/storage/')) {
        return raw;
    }

    if (raw.startsWith('storage/')) {
        return `/${raw}`;
    }

    if (raw.startsWith('/')) {
        return raw;
    }

    if (!raw.includes('/')) {
        return `/storage/${defaultDirectory}/${raw}`;
    }

    return `/storage/${raw}`;
}

function normalizeParticipant(participant) {
    if (!participant) {
        return null;
    }

    return {
        ...participant,
        avatar_url: normalizeAvatarUrl(participant.avatar_url),
    };
}

function normalizeConversation(conversation) {
    return {
        id: Number(conversation.id),
        participant_one_id: Number(conversation.participant_one_id),
        participant_two_id: Number(conversation.participant_two_id),
        conversation_type: conversation.conversation_type,
        status: conversation.status,
        context_key: conversation.context_key,
        context_label: conversation.context_label || 'Direct Conversation',
        last_message_at: conversation.last_message_at,
        latest_message_preview: conversation.latest_message_preview || '',
        participantOne: normalizeParticipant(conversation.participant_one ?? null),
        participantTwo: normalizeParticipant(conversation.participant_two ?? null),
        other_participant: normalizeParticipant(conversation.other_participant ?? null),
        pending_request: conversation.pending_request
            ? {
                ...conversation.pending_request,
                requester_avatar_url: normalizeAvatarUrl(conversation.pending_request.requester_avatar_url),
            }
            : null,
        can_send: conversation.can_send !== false,
        unread_count: Number(conversation.unread_count || 0),
        support_availability: conversation.support_availability || null,
    };
}

function insertOrReplaceById(collection, item) {
    const index = collection.findIndex((entry) => entry.id === item.id);

    if (index === -1) {
        collection.push(item);
        return;
    }

    collection[index] = {
        ...collection[index],
        ...item,
    };
}

function buildStartPayload(input = {}) {
    const payload = {
        target_user_id: Number(input.target_user_id || 0),
        conversation_type: input.conversation_type,
    };

    if (!payload.target_user_id || !payload.conversation_type) {
        return null;
    }

    if (input.module_id) {
        payload.module_id = Number(input.module_id);
    }

    if (input.lesson_id) {
        payload.lesson_id = Number(input.lesson_id);
    }

    if (input.lesson_topic_id) {
        payload.lesson_topic_id = Number(input.lesson_topic_id);
    }

    if (input.quiz_id) {
        payload.quiz_id = Number(input.quiz_id);
    }

    if (input.initial_message) {
        payload.initial_message = String(input.initial_message);
    }

    return payload;
}

function dedupeMessages(messages = []) {
    const seen = new Set();
    const result = [];

    messages.forEach((message) => {
        const key = String(message.id);

        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        result.push(message);
    });

    return result;
}

function normalizeMessage(message = {}) {
    return {
        ...message,
        id: message.id,
        conversation_id: Number(message.conversation_id || 0),
        sender_id: Number(message.sender_id || 0),
        sender_avatar_url: normalizeAvatarUrl(message.sender_avatar_url),
        attachments: Array.isArray(message.attachments) ? message.attachments : [],
        is_deleted: Boolean(message.is_deleted),
    };
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('chat', {
        ...defaultState,

        async bootstrap(payload = {}) {
            await this.ensureBootstrapped(payload, {
                loadDiscovery: true,
                loadRequests: true,
            });

            const requestedConversationId = Number(payload.initialConversationId || 0);

            if (requestedConversationId > 0) {
                await this.ensureConversationLoaded(requestedConversationId);

                if (this.findConversationById(requestedConversationId)) {
                    await this.selectConversation(requestedConversationId);
                    return;
                }
            }

            const startPayload = buildStartPayload(payload.startContext || {});

            if (startPayload) {
                await this.startConversation(startPayload, true);
                return;
            }

            if (this.activeConversationId) {
                await this.selectConversation(this.activeConversationId);
                return;
            }

            if (this.conversations.length > 0) {
                await this.selectConversation(this.conversations[0].id);
            }
        },

        async bootstrapGlobal(payload = {}) {
            await this.ensureBootstrapped(payload, {
                loadDiscovery: false,
                loadRequests: true,
            });
        },

        applyBootstrapPayload(payload = {}) {
            if (payload.currentUserId) {
                this.currentUserId = Number(payload.currentUserId || this.currentUserId || 0);
            }

            if (payload.currentUserName) {
                this.currentUserName = payload.currentUserName;
            }

            if (payload.currentUserRole) {
                this.currentUserRole = payload.currentUserRole;
            }

            if (payload.messageMutationWindowMinutes) {
                this.messageMutationWindowMinutes = Number(payload.messageMutationWindowMinutes || this.messageMutationWindowMinutes || 15);
            }

            if (Array.isArray(payload.reportReasons)) {
                this.reportReasons = payload.reportReasons;
            }

            this.adminDiscoveryTab = this.adminDiscoveryTab || 'learners';
            this.mobileSidebarOpen = window.innerWidth >= 1024;
        },

        async ensureBootstrapped(payload = {}, options = {}) {
            const config = {
                loadDiscovery: options.loadDiscovery !== false,
                loadRequests: options.loadRequests !== false,
            };

            this.applyBootstrapPayload(payload);

            if (this.bootstrapping && this._bootstrapPromise) {
                await this._bootstrapPromise;
            }

            if (!this.bootstrapped) {
                this.bootstrapping = true;
                this.startTypingCleanupLoop();
                this.registerViewportListener();

                this._bootstrapPromise = (async () => {
                    await this.fetchConversations(true);

                    if (config.loadDiscovery) {
                        await this.fetchDiscovery();
                    }

                    if (config.loadRequests) {
                        await this.fetchRequests();
                    }

                    this.subscribeRequestChannel();
                    this.subscribePresenceChannel();
                    this.subscribeConversationChannels();

                    this.bootstrapped = true;
                    this.bootstrapping = false;
                })();

                await this._bootstrapPromise;
            } else {
                if (config.loadDiscovery && !this.discovery.role) {
                    await this.fetchDiscovery();
                }

                if (config.loadRequests) {
                    await this.fetchRequests();
                }

                this.subscribeRequestChannel();
                this.subscribePresenceChannel();
                this.subscribeConversationChannels();
            }
        },

        registerViewportListener() {
            if (this._viewportListenerRegistered) {
                return;
            }

            this._viewportListenerRegistered = true;
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    this.mobileSidebarOpen = true;
                }
            });
        },

        shouldShowSidebar() {
            return window.innerWidth >= 1024 || this.mobileSidebarOpen || !this.activeConversationId;
        },

        shouldShowConversationPanel() {
            return window.innerWidth >= 1024 || !this.shouldShowSidebar();
        },

        openSidebar() {
            this.mobileSidebarOpen = true;
        },

        closeSidebar() {
            if (window.innerWidth < 1024) {
                this.mobileSidebarOpen = false;
            }
        },

        async fetchConversations(reset = true) {
            if (reset) {
                this.loading.conversations = true;
                this.conversationPagination.currentPage = 0;
                this.conversationPagination.lastPage = 0;
                this.conversationPagination.hasMore = true;
            } else {
                if (!this.conversationPagination.hasMore || this.conversationPagination.loadingMore) {
                    return;
                }

                this.conversationPagination.loadingMore = true;
            }

            try {
                const targetPage = reset
                    ? 1
                    : this.conversationPagination.currentPage + 1;

                const response = await window.axios.get('/chat/conversations', {
                    params: {
                        page: targetPage,
                        per_page: this.conversationPagination.perPage,
                    },
                });

                const conversations = (response.data.conversations || []).map(normalizeConversation);

                if (reset) {
                    this.setConversations(conversations);
                } else {
                    this.appendConversations(conversations);
                }

                const pagination = response.data.pagination || {};
                this.conversationPagination.currentPage = Number(pagination.current_page || targetPage);
                this.conversationPagination.lastPage = Number(pagination.last_page || this.conversationPagination.currentPage);
                this.conversationPagination.hasMore = Boolean(pagination.has_more);

                this.subscribeConversationChannels();
            } finally {
                this.loading.conversations = false;
                this.conversationPagination.loadingMore = false;
            }
        },

        async loadMoreConversations() {
            await this.fetchConversations(false);
        },

        async ensureConversationLoaded(conversationId) {
            const id = Number(conversationId || 0);

            if (!id) {
                return false;
            }

            if (this.findConversationById(id)) {
                return true;
            }

            while (this.conversationPagination.hasMore) {
                await this.loadMoreConversations();

                if (this.findConversationById(id)) {
                    return true;
                }
            }

            return false;
        },

        isConversationUnavailableError(error) {
            const status = Number(error?.response?.status || 0);
            return status === 403 || status === 404;
        },

        purgeConversation(conversationId) {
            const id = Number(conversationId || 0);

            if (!id) {
                return;
            }

            this.conversations = this.conversations.filter((entry) => Number(entry.id) !== id);

            delete this.messagesByConversation[id];
            delete this.messageWindowsByConversation[id];
            delete this.unreadByConversation[id];
            delete this.subscribedConversationIds[id];
            delete this.conversationChannels[id];
            delete this.typingByConversation[id];
            delete this.typingEmitTimestamps[id];

            if (Number(this.activeConversationId) === id) {
                this.activeConversationId = null;
            }

            this.syncUnreadBadges();
        },

        async fetchDiscovery() {
            this.loading.discovery = true;

            try {
                const response = await window.axios.get('/chat/discovery', {
                    params: {
                        q: this.discoveryQuery || undefined,
                    },
                });

                this.discovery.role = response.data.role || this.currentUserRole;
                this.discovery.supportAdmin = normalizeParticipant(response.data.support_admin || null);
                this.discovery.supportAvailability = response.data.support_availability || this.discovery.supportAvailability;
                this.discovery.contacts = {
                    learners: (response.data.contacts?.learners || []).map((contact) => normalizeParticipant(contact)),
                    instructors: (response.data.contacts?.instructors || []).map((contact) => normalizeParticipant(contact)),
                };
            } finally {
                this.loading.discovery = false;
            }
        },

        async fetchRequests() {
            if (String(this.currentUserRole || '') !== 'instructor') {
                this.requests = [];
                return;
            }

            this.loading.requests = true;

            try {
                const response = await window.axios.get('/chat/requests');
                this.requests = (response.data.requests || []).map((request) => ({
                    ...request,
                    requester_avatar_url: normalizeAvatarUrl(request.requester_avatar_url),
                }));
            } finally {
                this.loading.requests = false;
            }
        },

        setConversations(conversations) {
            this.conversations = [];
            this.unreadByConversation = {};
            this.mergeConversationBatch(conversations);
        },

        appendConversations(conversations) {
            this.mergeConversationBatch(conversations);
        },

        mergeConversationBatch(conversations) {
            conversations.forEach((conversation) => {
                const normalized = normalizeConversation(conversation);
                const existingIndex = this.conversations.findIndex((entry) => entry.id === normalized.id);

                if (existingIndex === -1) {
                    this.conversations.push(normalized);
                } else {
                    this.conversations[existingIndex] = {
                        ...this.conversations[existingIndex],
                        ...normalized,
                    };
                }

                this.unreadByConversation[normalized.id] = Number(normalized.unread_count || this.unreadByConversation[normalized.id] || 0);
            });

            if (this.activeConversationId && !this.findConversationById(this.activeConversationId)) {
                this.activeConversationId = null;
            }

            this.sortConversationsByLatest();
            this.syncUnreadBadges();
        },

        sortConversationsByLatest() {
            this.conversations.sort((a, b) => {
                const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
                const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

                if (aTime === bTime) {
                    return Number(b.id) - Number(a.id);
                }

                return bTime - aTime;
            });
        },

        findConversationById(conversationId) {
            return this.conversations.find((entry) => Number(entry.id) === Number(conversationId)) || null;
        },

        activeConversation() {
            return this.findConversationById(this.activeConversationId);
        },

        activeConversationPendingRequest() {
            const active = this.activeConversation();

            if (!active || active.status !== 'pending_request') {
                return null;
            }

            return active.pending_request || null;
        },

        canSendToConversation(conversationId) {
            const conversation = this.findConversationById(conversationId);

            if (!conversation) {
                return false;
            }

            return conversation.can_send !== false;
        },

        shouldShowPendingRequestActions() {
            return this.currentUserRole === 'instructor'
                && this.activeConversation()?.status === 'pending_request'
                && !!this.activeConversationPendingRequest()?.id;
        },

        activeConversationStateLabel() {
            const status = this.activeConversation()?.status;

            if (status === 'pending_request') {
                return 'Waiting for instructor approval.';
            }

            if (status === 'declined') {
                return 'This conversation request was declined.';
            }

            if (status === 'accepted') {
                return 'Request Accepted';
            }

            return '';
        },

        activeConversationIsDeclined() {
            return this.activeConversation()?.status === 'declined';
        },

        activeConversationIsPending() {
            return this.activeConversation()?.status === 'pending_request';
        },

        activeConversationIsAccepted() {
            return this.activeConversation()?.status === 'accepted';
        },

        activeConversationIsSupport() {
            return this.activeConversation()?.conversation_type === 'admin_support_chat'
                && this.currentUserRole !== 'admin';
        },

        activeSupportAvailability() {
            return this.activeConversation()?.support_availability
                || this.discovery.supportAvailability
                || defaultState.discovery.supportAvailability;
        },

        activeMessages() {
            if (!this.activeConversationId) {
                return [];
            }

            return this.messagesByConversation[this.activeConversationId] || [];
        },

        activeMessageWindow() {
            if (!this.activeConversationId) {
                return null;
            }

            return this.messageWindow(this.activeConversationId);
        },

        filteredConversations() {
            const query = String(this.discoveryQuery || '').toLowerCase().trim();

            if (!query) {
                return this.conversations;
            }

            return this.conversations.filter((conversation) => {
                const participant = this.conversationParticipantName(conversation).toLowerCase();
                const label = String(conversation.context_label || '').toLowerCase();
                const preview = String(conversation.latest_message_preview || '').toLowerCase();

                return participant.includes(query) || label.includes(query) || preview.includes(query);
            });
        },

        conversationGroups() {
            const conversations = this.filteredConversations();

            if (this.currentUserRole !== 'admin') {
                return [
                    {
                        key: 'all',
                        label: 'Recent Conversations',
                        items: conversations,
                    },
                ];
            }

            const groups = {
                support: [],
                contextual: [],
                direct: [],
                other: [],
            };

            conversations.forEach((conversation) => {
                if (conversation.conversation_type === 'admin_support_chat') {
                    groups.support.push(conversation);
                    return;
                }

                if (['module_chat', 'lesson_chat', 'lesson_topic_chat', 'quiz_help'].includes(conversation.conversation_type)) {
                    groups.contextual.push(conversation);
                    return;
                }

                if (conversation.conversation_type === 'direct') {
                    groups.direct.push(conversation);
                    return;
                }

                groups.other.push(conversation);
            });

            return [
                { key: 'support', label: 'Support', items: groups.support },
                { key: 'contextual', label: 'Learning Context', items: groups.contextual },
                { key: 'direct', label: 'Direct Messages', items: groups.direct },
                { key: 'other', label: 'Other', items: groups.other },
            ].filter((group) => group.items.length > 0);
        },

        filteredDiscoveryContacts() {
            if (this.currentUserRole === 'admin') {
                if (this.adminDiscoveryTab === 'instructors') {
                    return this.discovery.contacts.instructors || [];
                }

                return this.discovery.contacts.learners || [];
            }

            if (this.currentUserRole === 'instructor') {
                return this.discovery.contacts.learners || [];
            }

            return this.discovery.contacts.instructors || [];
        },

        conversationParticipantName(conversation) {
            if (conversation.conversation_type === 'admin_support_chat' && this.currentUserRole !== 'admin') {
                return 'Platform Support';
            }

            return conversation.other_participant?.name || 'Conversation';
        },

        conversationParticipantAvatar(conversation) {
            return conversation.other_participant?.avatar_url || null;
        },

        conversationParticipantStatus(conversation) {
            const participant = conversation?.other_participant;

            if (!participant?.id) {
                return 'offline';
            }

            return this.normalizeUserStatus(participant.status);
        },

        contactStatus(contact) {
            if (!contact?.id) {
                return 'offline';
            }

            return this.normalizeUserStatus(contact.status);
        },

        normalizeUserStatus(status) {
            const normalized = String(status || '').toLowerCase().trim();

            if (normalized === 'active' || normalized === 'online') {
                return 'online';
            }

            if (normalized === 'inactive' || normalized === 'do_not_disturb' || normalized === 'dnd') {
                return 'do_not_disturb';
            }

            if (['busy', 'offline'].includes(normalized)) {
                return normalized;
            }

            return 'offline';
        },

        statusLabel(status) {
            return {
                online: 'Online',
                busy: 'Busy',
                do_not_disturb: 'Do Not Disturb',
                offline: 'Offline',
            }[status] || 'Offline';
        },

        statusToneClass(status) {
            if (status === 'online') {
                return 'bg-emerald-500';
            }

            if (status === 'busy') {
                return 'bg-amber-500';
            }

            if (status === 'do_not_disturb') {
                return 'bg-slate-500';
            }

            return 'bg-gray-400';
        },

        formatConversationTime(timestamp) {
            if (!timestamp) {
                return 'No activity';
            }

            const date = new Date(timestamp);

            if (Number.isNaN(date.getTime())) {
                return 'No activity';
            }

            const now = new Date();
            const ageMs = now.getTime() - date.getTime();
            const oneWeekMs = 7 * 24 * 60 * 60 * 1000;

            if (ageMs < oneWeekMs) {
                const relative = this.relativeTime(date);

                if (relative) {
                    return relative;
                }
            }

            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        },

        relativeTime(date) {
            const seconds = Math.round((date.getTime() - Date.now()) / 1000);
            const absSeconds = Math.abs(seconds);

            if (absSeconds < 45) {
                return 'just now';
            }

            const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

            if (absSeconds < 3600) {
                return rtf.format(Math.round(seconds / 60), 'minute');
            }

            if (absSeconds < 86400) {
                return rtf.format(Math.round(seconds / 3600), 'hour');
            }

            return rtf.format(Math.round(seconds / 86400), 'day');
        },

        formatMessageTime(timestamp) {
            if (!timestamp) {
                return '';
            }

            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        },

        isOwnMessage(message) {
            return Number(message.sender_id) === Number(this.currentUserId);
        },

        async selectConversation(conversationId) {
            this.activeConversationId = Number(conversationId);
            this.composerError = null;

            if (this.unreadByConversation[this.activeConversationId] !== undefined) {
                this.unreadByConversation[this.activeConversationId] = 0;
            }

            this.syncUnreadBadges();
            this.subscribeConversationChannel(this.activeConversationId);
            this.clearTypingState(this.activeConversationId);

            if (window.innerWidth < 1024) {
                this.closeSidebar();
            }

            await Promise.all([
                this.loadMessages(this.activeConversationId, true),
                this.markConversationRead(this.activeConversationId),
            ]);

            this.scrollMessagesToBottom(true);
        },

        openConversationInPopup(conversationId, closeCurrent = true) {
            const conversation = this.findConversationById(conversationId);

            if (!conversation) {
                return;
            }

            const detail = {
                conversation_id: Number(conversation.id),
                target_user_id: Number(conversation.other_participant?.id || 0) || null,
                name: this.conversationParticipantName(conversation),
                avatar: this.conversationParticipantAvatar(conversation),
                context_label: conversation.context_label,
                conversation_type: conversation.conversation_type,
            };

            window.dispatchEvent(new CustomEvent('open-global-chat', { detail }));

            if (closeCurrent && Number(this.activeConversationId) === Number(conversation.id)) {
                this.activeConversationId = null;
                this.openSidebar();
            }
        },

        messageWindow(conversationId) {
            const id = Number(conversationId);

            if (!this.messageWindowsByConversation[id]) {
                this.messageWindowsByConversation[id] = {
                    hasMoreBefore: true,
                    oldestMessageId: null,
                    loadingOlder: false,
                    initialized: false,
                };
            }

            return this.messageWindowsByConversation[id];
        },

        async loadMessages(conversationId, reset = false) {
            const id = Number(conversationId);
            const windowState = this.messageWindow(id);

            if (!reset && (!windowState.hasMoreBefore || windowState.loadingOlder)) {
                return;
            }

            if (reset) {
                this.loading.messages = true;
            } else {
                windowState.loadingOlder = true;
            }

            try {
                const params = {
                    limit: 30,
                };

                if (!reset && windowState.oldestMessageId) {
                    params.before_message_id = windowState.oldestMessageId;
                }

                const response = await window.axios.get(`/chat/conversations/${id}/messages`, { params });
                const incoming = (response.data.messages || []).map((message) => normalizeMessage(message));

                if (reset) {
                    this.messagesByConversation[id] = incoming;
                } else {
                    const existing = this.messagesByConversation[id] || [];
                    this.messagesByConversation[id] = dedupeMessages([...incoming, ...existing]);
                }

                const meta = response.data.meta || {};
                const oldestLoadedId = this.messagesByConversation[id]?.[0]?.id || null;

                this.messageWindowsByConversation[id] = {
                    ...windowState,
                    hasMoreBefore: Boolean(meta.has_more_before),
                    oldestMessageId: Number(meta.oldest_message_id || oldestLoadedId || 0) || null,
                    loadingOlder: false,
                    initialized: true,
                };
                return true;
            } catch (error) {
                if (this.isConversationUnavailableError(error)) {
                    this.purgeConversation(id);
                    return false;
                }

                throw error;
            } finally {
                this.loading.messages = false;
                if (this.messageWindowsByConversation[id]) {
                    this.messageWindowsByConversation[id].loadingOlder = false;
                }
            }
        },

        async loadOlderActiveMessages() {
            if (!this.activeConversationId) {
                return;
            }

            await this.loadMessages(this.activeConversationId, false);
        },

        handleMessageStreamScroll(event) {
            if (!this.activeConversationId) {
                return;
            }

            const target = event?.target;

            if (!target) {
                return;
            }

            if (target.scrollTop <= 72) {
                this.loadOlderActiveMessages();
            }
        },

        messageStreamElement() {
            return document.querySelector('[data-chat-message-stream]');
        },

        isNearBottom(element) {
            if (!element) {
                return true;
            }

            const remaining = element.scrollHeight - element.scrollTop - element.clientHeight;
            return remaining <= 140;
        },

        scrollMessagesToBottom(force = false) {
            const stream = this.messageStreamElement();

            if (!stream) {
                return;
            }

            if (!force && !this.isNearBottom(stream)) {
                return;
            }

            requestAnimationFrame(() => {
                stream.scrollTop = stream.scrollHeight;
            });
        },

        async markConversationRead(conversationId) {
            try {
                await window.axios.post(`/chat/conversations/${conversationId}/read`);
            } catch (error) {
                // Read-state sync failures should not block UX actions.
            }
        },

        async startConversation(payload, autoSelect = true) {
            const normalizedPayload = buildStartPayload(payload);
            if (!normalizedPayload) {
                return null;
            }

            const response = await window.axios.post('/chat/conversations/start', normalizedPayload);

            if (response.status === 202) {
                const pendingConversation = response.data.conversation || null;

                await this.fetchConversations(true);

                if (this.currentUserRole === 'instructor') {
                    await this.fetchRequests();
                }

                if (autoSelect && pendingConversation?.id) {
                    await this.selectConversation(pendingConversation.id);
                }

                return {
                    requires_request: true,
                    conversation: pendingConversation,
                    message_request: response.data.message_request,
                };
            }

            await this.fetchConversations(true);

            const conversation = response.data.conversation || null;
            if (autoSelect && conversation?.id) {
                await this.selectConversation(conversation.id);
            }

            return { requires_request: false, conversation };
        },

        async startContactConversation(contact) {
            if (!contact?.id) {
                return;
            }

            const isSupport = Number(contact.id) === Number(this.discovery.supportAdmin?.id);
            const conversationType = isSupport ? 'admin_support_chat' : 'direct';

            await this.startConversation({
                target_user_id: Number(contact.id),
                conversation_type: conversationType,
            }, true);
        },

        appendMessage(message) {
            const normalizedMessage = normalizeMessage(message);
            const conversationId = Number(normalizedMessage.conversation_id);
            if (!this.messagesByConversation[conversationId]) {
                this.messagesByConversation[conversationId] = [];
            }

            const existingIndex = this.messagesByConversation[conversationId]
                .findIndex((entry) => String(entry.id) === String(normalizedMessage.id));

            if (existingIndex !== -1) {
                this.messagesByConversation[conversationId][existingIndex] = {
                    ...this.messagesByConversation[conversationId][existingIndex],
                    ...normalizedMessage,
                };
                return;
            }

            this.messagesByConversation[conversationId].push(normalizedMessage);
            this.messagesByConversation[conversationId] = dedupeMessages(this.messagesByConversation[conversationId]);

            if (conversationId === Number(this.activeConversationId)) {
                this.scrollMessagesToBottom(false);
            }
        },

        mergeBackfillMessages(conversationId, messages = []) {
            if (!this.messagesByConversation[conversationId]) {
                this.messagesByConversation[conversationId] = [];
            }

            this.messagesByConversation[conversationId] = dedupeMessages([
                ...this.messagesByConversation[conversationId],
                ...(messages || []).map((message) => normalizeMessage(message)),
            ]);
        },

        updateConversationFromMessage(payload) {
            const conversation = this.findConversationById(payload.conversation_id);

            if (!conversation) {
                this.fetchConversations(true);
                return;
            }

            conversation.last_message_at = payload.created_at || new Date().toISOString();
            conversation.latest_message_preview = this.messagePreviewText(payload);

            this.sortConversationsByLatest();
        },

        messagePreviewText(payload = {}) {
            const body = String(payload?.message_body || '').trim();

            if (body) {
                return body;
            }

            const attachments = Array.isArray(payload?.attachments) ? payload.attachments : [];

            if (attachments.length < 1) {
                return '';
            }

            if (attachments.some((attachment) => attachment?.is_voice_note)) {
                return attachments.length > 1 ? 'Voice notes shared' : 'Voice note shared';
            }

            if (attachments.some((attachment) => attachment?.is_video)) {
                return attachments.length > 1 ? 'Videos shared' : 'Video shared';
            }

            if (attachments.some((attachment) => attachment?.is_image)) {
                return attachments.length > 1 ? 'Images shared' : 'Image shared';
            }

            return attachments.length > 1 ? 'Files shared' : 'File shared';
        },

        applyMessageSentEvent(payload) {
            this.appendMessage(payload);
            this.updateConversationFromMessage(payload);

            if (this.activeConversationId !== payload.conversation_id) {
                this.unreadByConversation[payload.conversation_id] =
                    (this.unreadByConversation[payload.conversation_id] || 0) + 1;
            } else {
                this.unreadByConversation[payload.conversation_id] = 0;
                this.markConversationRead(payload.conversation_id);
            }

            this.syncUnreadBadges();
        },

        applyMessageUpdatedEvent(payload) {
            const messagePayload = payload?.message || null;

            if (!messagePayload) {
                return;
            }

            this.appendMessage(messagePayload);
            this.updateConversationFromMessage(messagePayload);
        },

        async sendMessageToConversation(conversationId, messageBody, attachments = []) {
            const body = String(messageBody || '').trim();
            const files = Array.isArray(attachments) ? attachments.filter(Boolean) : [];
            const id = Number(conversationId || 0);

            if ((!body && files.length < 1) || !id) {
                return null;
            }

            if (!this.canSendToConversation(id)) {
                this.composerError = 'Messaging is disabled in this conversation.';
                return null;
            }

            this.loading.send = true;
            this.composerError = null;

            const shouldUseOptimistic = files.length < 1;
            const optimisticId = shouldUseOptimistic
                ? this.optimisticSend(id, body)
                : null;

            this.notifyTyping(id, false);

            try {
                let payload = { message_body: body };
                let headers = {};

                if (files.length > 0) {
                    const formData = new FormData();

                    if (body) {
                        formData.append('message_body', body);
                    }

                    files.forEach((file) => {
                        formData.append('attachments[]', file);
                    });

                    payload = formData;
                    headers = { 'Content-Type': 'multipart/form-data' };
                }

                const response = await window.axios.post(
                    `/chat/conversations/${id}/messages`,
                    payload,
                    { headers }
                );

                if (optimisticId) {
                    this.reconcileOptimistic(optimisticId, response.data.message);
                } else {
                    this.appendMessage(response.data.message);
                }

                this.updateConversationFromMessage(response.data.message);

                if (Number(this.activeConversationId) === id) {
                    await this.markConversationRead(id);
                    this.scrollMessagesToBottom(true);
                }

                return response.data.message;
            } catch (error) {
                if (optimisticId) {
                    this.markOptimisticFailed(optimisticId);
                }

                if (error?.response?.status === 429) {
                    this.composerError = error?.response?.data?.message || 'You are sending too fast. Try again shortly.';
                } else if (error?.response?.data?.message) {
                    this.composerError = error.response.data.message;
                }

                throw error;
            } finally {
                this.loading.send = false;
            }
        },

        async sendActiveMessage(messageBody, attachments = []) {
            if (!this.activeConversationId) {
                return null;
            }

            return this.sendMessageToConversation(this.activeConversationId, messageBody, attachments);
        },

        async updateMessage(message, nextBody) {
            if (!message?.id) {
                return null;
            }

            const body = String(nextBody || '').trim();

            if (!body) {
                return null;
            }

            const response = await window.axios.patch(`/chat/messages/${message.id}`, {
                message_body: body,
            });

            this.appendMessage(response.data.message);
            this.updateConversationFromMessage(response.data.message);

            return response.data.message;
        },

        async deleteMessage(message) {
            if (!message?.id) {
                return null;
            }

            const response = await window.axios.delete(`/chat/messages/${message.id}`);
            this.appendMessage(response.data.message);
            this.updateConversationFromMessage(response.data.message);

            return response.data.message;
        },

        async reportMessage(message, payload = {}) {
            if (!message?.id) {
                return null;
            }

            const response = await window.axios.post(`/chat/messages/${message.id}/report`, {
                reason_code: payload.reason_code,
                custom_reason: String(payload.custom_reason || '').trim() || undefined,
            });

            return response.data;
        },

        canMutateMessage(message) {
            if (!message || message.is_deleted) {
                return false;
            }

            if (this.currentUserRole === 'admin') {
                return true;
            }

            if (Number(message.sender_id) !== Number(this.currentUserId)) {
                return false;
            }

            if (!message.created_at) {
                return false;
            }

            const createdAtMs = new Date(message.created_at).getTime();

            if (Number.isNaN(createdAtMs)) {
                return false;
            }

            const expiryMs = createdAtMs + (this.messageMutationWindowMinutes * 60 * 1000);

            return Date.now() <= expiryMs;
        },

        upsertRequest(requestPayload) {
            insertOrReplaceById(this.requests, {
                ...requestPayload,
                requester_avatar_url: normalizeAvatarUrl(requestPayload?.requester_avatar_url),
            });
        },

        resolveRequest(payload) {
            if (!payload?.id) {
                return;
            }

            if (payload.status === 'pending' && this.currentUserRole === 'instructor') {
                this.upsertRequest(payload);
            } else {
                this.requests = this.requests.filter((entry) => Number(entry.id) !== Number(payload.id));
            }

            this.applyConversationStatusUpdate(
                payload.conversation_id || payload.accepted_conversation_id,
                payload.conversation_status
                    || (payload.status === 'accepted' ? 'accepted' : null)
                    || (payload.status === 'declined' ? 'declined' : null)
            );
        },

        applyConversationStatusUpdate(conversationId, status) {
            const id = Number(conversationId || 0);

            if (!id || !status) {
                return;
            }

            const conversation = this.findConversationById(id);

            if (!conversation) {
                return;
            }

            conversation.status = String(status);
            conversation.pending_request = null;
            conversation.can_send = ['accepted', 'active'].includes(conversation.status);
        },

        isResolvingRequest(requestId) {
            return Boolean(this.resolvingRequestIds[String(requestId)]);
        },

        async acceptRequest(requestId) {
            const key = String(requestId);

            if (!requestId || this.isResolvingRequest(requestId)) {
                return;
            }

            this.resolvingRequestIds[key] = true;
            this.requestActionError = null;

            try {
                const response = await window.axios.post(`/chat/requests/${requestId}/accept`);
                this.resolveRequest(response.data.message_request);
                this.applyConversationStatusUpdate(response.data.conversation?.id, response.data.conversation?.status || 'accepted');
                await this.fetchConversations(true);

                const acceptedConversationId = response.data.conversation?.id;
                if (acceptedConversationId) {
                    await this.selectConversation(acceptedConversationId);
                }
            } catch (error) {
                if (error?.response?.status === 409) {
                    this.requestActionError = error.response.data?.message || 'This request has already been accepted.';
                    await this.fetchRequests();
                    await this.fetchConversations(true);
                }
            } finally {
                delete this.resolvingRequestIds[key];
            }
        },

        async declineRequest(requestId) {
            const key = String(requestId);

            if (!requestId || this.isResolvingRequest(requestId)) {
                return;
            }

            this.resolvingRequestIds[key] = true;
            this.requestActionError = null;

            try {
                const response = await window.axios.post(`/chat/requests/${requestId}/decline`);
                this.resolveRequest(response.data.message_request);
                await this.fetchRequests();
                await this.fetchConversations(true);
            } catch (error) {
                if (error?.response?.status === 409) {
                    this.requestActionError = error.response.data?.message || 'This request has already been declined.';
                    await this.fetchRequests();
                    await this.fetchConversations(true);
                }
            } finally {
                delete this.resolvingRequestIds[key];
            }
        },

        optimisticSend(conversationId, messageBody) {
            const optimisticId = `optimistic-${Date.now()}`;
            const optimisticMessage = {
                id: optimisticId,
                conversation_id: Number(conversationId),
                message_body: messageBody,
                sender_id: this.currentUserId,
                sender_name: 'You',
                sender_status: 'online',
                sender_avatar_url: null,
                created_at: new Date().toISOString(),
                optimistic: true,
                failed: false,
            };

            this.pendingOptimistic[optimisticId] = optimisticMessage;
            this.appendMessage(optimisticMessage);
            this.scrollMessagesToBottom(true);

            return optimisticId;
        },

        reconcileOptimistic(optimisticId, authoritativeMessage) {
            const pending = this.pendingOptimistic[optimisticId];
            if (!pending) {
                return;
            }

            const list = this.messagesByConversation[pending.conversation_id] || [];
            const index = list.findIndex((entry) => String(entry.id) === String(optimisticId));

            if (index !== -1) {
                list[index] = authoritativeMessage;
            } else {
                this.appendMessage(authoritativeMessage);
            }

            delete this.pendingOptimistic[optimisticId];
        },

        markOptimisticFailed(optimisticId) {
            const pending = this.pendingOptimistic[optimisticId];
            if (!pending) {
                return;
            }

            pending.failed = true;
        },

        async retryFailedMessage(conversationId, optimisticId) {
            const pending = this.pendingOptimistic[optimisticId];

            if (!pending) {
                return null;
            }

            this.composerError = null;

            const response = await window.axios.post(`/chat/conversations/${conversationId}/messages`, {
                message_body: pending.message_body,
                retry_of: optimisticId,
            });

            this.reconcileOptimistic(optimisticId, response.data.message);
            this.updateConversationFromMessage(response.data.message);

            return response.data.message;
        },

        subscribeConversationChannels() {
            this.conversations.forEach((conversation) => {
                this.subscribeConversationChannel(conversation.id);
            });
        },

        subscribeConversationChannel(conversationId) {
            const id = Number(conversationId);
            if (!id || this.subscribedConversationIds[id]) {
                return;
            }

            const channel = window.chatEcho?.subscribeConversation(id, {
                onMessageSent: (payload) => this.applyMessageSentEvent(payload),
                onMessageUpdated: (payload) => this.applyMessageUpdatedEvent(payload),
                onTyping: (payload) => this.applyTypingEvent(payload),
            });

            if (channel) {
                this.subscribedConversationIds[id] = true;
                this.conversationChannels[id] = channel;
            }
        },

        subscribeRequestChannel() {
            if (this.requestChannelSubscribed || !this.currentUserId) {
                return;
            }

            const channel = window.chatEcho?.subscribeRequests(this.currentUserId, {
                onRequestCreated: (payload) => {
                    if (this.currentUserRole === 'instructor') {
                        this.upsertRequest(payload);
                        this.fetchRequests();
                    }
                },
                onRequestResolved: (payload) => {
                    this.resolveRequest(payload);

                    if (payload.accepted_conversation_id || payload.conversation_id) {
                        this.fetchConversations(true);
                    }
                },
            });

            if (channel) {
                this.requestChannelSubscribed = true;
            }
        },

        subscribePresenceChannel() {
            if (this.presenceChannelSubscribed) {
                return;
            }

            const channel = window.chatEcho?.subscribePresence({
                onHere: (users) => {
                    const next = {};
                    (users || []).forEach((user) => {
                        if (user?.id) {
                            next[user.id] = true;
                        }
                    });
                    this.onlineUsers = next;
                },
                onJoining: (user) => {
                    if (!user?.id) {
                        return;
                    }

                    this.onlineUsers = {
                        ...this.onlineUsers,
                        [user.id]: true,
                    };
                },
                onLeaving: (user) => {
                    if (!user?.id) {
                        return;
                    }

                    const next = { ...this.onlineUsers };
                    delete next[user.id];
                    this.onlineUsers = next;
                },
            });

            if (channel) {
                this.presenceChannelSubscribed = true;
            }
        },

        notifyTyping(conversationId, isTyping = true) {
            const id = Number(conversationId);

            if (!id || !this.conversationChannels[id]) {
                return;
            }

            const now = Date.now();
            const last = Number(this.typingEmitTimestamps[id] || 0);

            if (isTyping && now - last < 1200) {
                return;
            }

            window.chatEcho?.whisperTyping(id, {
                conversation_id: id,
                user_id: this.currentUserId,
                user_name: this.currentUserName || 'Someone',
                typing: Boolean(isTyping),
                sent_at: new Date().toISOString(),
            });

            this.typingEmitTimestamps[id] = now;

            if (this._typingStopTimers?.[id]) {
                clearTimeout(this._typingStopTimers[id]);
            }

            if (!this._typingStopTimers) {
                this._typingStopTimers = {};
            }

            if (isTyping) {
                this._typingStopTimers[id] = setTimeout(() => {
                    this.notifyTyping(id, false);
                }, 1800);
            }
        },

        applyTypingEvent(payload = {}) {
            const conversationId = Number(payload.conversation_id || 0);
            const userId = Number(payload.user_id || 0);

            if (!conversationId || !userId || userId === Number(this.currentUserId)) {
                return;
            }

            if (!this.typingByConversation[conversationId]) {
                this.typingByConversation[conversationId] = {};
            }

            if (payload.typing === false) {
                delete this.typingByConversation[conversationId][userId];
                return;
            }

            this.typingByConversation[conversationId][userId] = {
                user_id: userId,
                user_name: payload.user_name || 'Someone',
                expires_at: Date.now() + 3500,
            };
        },

        startTypingCleanupLoop() {
            if (this._typingCleanupLoopStarted) {
                return;
            }

            this._typingCleanupLoopStarted = true;

            setInterval(() => {
                const now = Date.now();

                Object.keys(this.typingByConversation).forEach((conversationId) => {
                    const entries = this.typingByConversation[conversationId] || {};

                    Object.keys(entries).forEach((userId) => {
                        if ((entries[userId]?.expires_at || 0) < now) {
                            delete entries[userId];
                        }
                    });

                    if (Object.keys(entries).length < 1) {
                        delete this.typingByConversation[conversationId];
                    }
                });
            }, 1000);
        },

        clearTypingState(conversationId) {
            const id = Number(conversationId);

            if (!id) {
                return;
            }

            delete this.typingByConversation[id];
        },

        typingUsersForConversation(conversationId) {
            const id = Number(conversationId || 0);

            if (!id) {
                return [];
            }

            const entries = this.typingByConversation[id] || {};
            const now = Date.now();

            return Object.values(entries)
                .filter((entry) => (entry?.expires_at || 0) >= now)
                .map((entry) => entry.user_name || 'Someone');
        },

        activeTypingUsers() {
            return this.typingUsersForConversation(this.activeConversationId);
        },

        typingLabelForConversation(conversationId) {
            const users = this.typingUsersForConversation(conversationId);

            if (users.length < 1) {
                return '';
            }

            if (users.length === 1) {
                return `${users[0]} is typing...`;
            }

            if (users.length === 2) {
                return `${users[0]} and ${users[1]} are typing...`;
            }

            return `${users[0]} and ${users.length - 1} others are typing...`;
        },

        activeTypingLabel() {
            return this.typingLabelForConversation(this.activeConversationId);
        },

        totalUnreadCount() {
            return Object.values(this.unreadByConversation).reduce((sum, value) => {
                const count = Number(value) || 0;
                return sum + Math.max(0, count);
            }, 0);
        },

        syncUnreadBadges() {
            const totalUnread = this.totalUnreadCount();
            const hasUnread = totalUnread > 0;

            document.querySelectorAll('[data-chat-unread-badge]').forEach((badge) => {
                badge.textContent = hasUnread ? (totalUnread > 99 ? '99+' : String(totalUnread)) : '';
                badge.hidden = !hasUnread;
            });

            window.dispatchEvent(
                new CustomEvent('chat:unread-updated', {
                    detail: { totalUnread },
                })
            );
        },

        async debouncedDiscoveryRefresh() {
            clearTimeout(this._discoveryTimer);
            this._discoveryTimer = setTimeout(() => {
                this.fetchDiscovery();
            }, 250);
        },
    });
});
