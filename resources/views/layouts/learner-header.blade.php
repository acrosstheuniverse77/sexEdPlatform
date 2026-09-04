@php
    /** @var \App\Models\User $headerUser */
    $headerUser    = Auth::user();
    $headerProfile = $headerUser->learnerProfile;
    $headerName    = $headerProfile?->username ?? $headerUser->name;
    $headerAvatar  = $headerProfile?->avatar_path
        ? asset('storage/' . $headerProfile->avatar_path)
        : null;
    $unreadCount   = $headerUser->unreadNotifications()->count();
    $recentNotifs  = $headerUser->notifications()->latest()->limit(5)->get();
    $payloadNormalizer = app(\App\Support\NotificationPayloadNormalizer::class);
@endphp

<header class="sticky top-0 z-[9998] w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
    <span class="hidden" data-chat-unread-badge-role="learner"></span>
    <div class="flex items-center justify-between h-16 px-4 md:px-6">

        {{-- ─── Left: Sidebar toggle + Search ─── --}}
        <div class="flex items-center gap-3">

            {{-- Desktop sidebar toggle --}}
            <button
                class="hidden xl:flex items-center justify-center w-10 h-10 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                @click="$store.sidebar.toggleExpanded()"
                aria-label="Toggle sidebar"
            >
                <svg width="16" height="12" viewBox="0 0 16 12" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.583 1C0.583 .586.919.25 1.333.25H14.667C15.081.25 15.417.586 15.417 1c0 .414-.336.75-.75.75H1.333A.75.75 0 0 1 .583 1Zm0 10c0-.414.336-.75.75-.75H14.667a.75.75 0 0 1 0 1.5H1.333A.75.75 0 0 1 .583 11ZM1.333 5.25A.75.75 0 0 0 .583 6c0 .414.336.75.75.75H8A.75.75 0 0 0 8 5.25H1.333Z" fill="currentColor"/>
                </svg>
            </button>

            {{-- Mobile sidebar toggle --}}
            <button
                class="flex xl:hidden items-center justify-center w-10 h-10 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                @click="$store.sidebar.toggleMobileOpen()"
                aria-label="Open menu"
            >
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
            </button>

            {{-- ── Live Search (hidden on My Modules page — it has its own search) ── --}}
            @unless(request()->routeIs(['learner.modules.index', 'subscription.*', 'payment.*']))
            <div class="relative hidden sm:block" x-data="learnerSearch()" @click.outside="open = false">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none z-10">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0Z"/>
                    </svg>
                </span>
                <input
                    type="text"
                    x-model="query"
                    @input.debounce.300ms="search()"
                    @focus="if(results.modules.length) open = true"
                    placeholder="Search modules..."
                    autocomplete="off"
                    class="h-10 w-64 xl:w-80 pl-9 pr-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-200 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-400 dark:focus:ring-purple-600 focus:border-transparent transition-all"
                >

                {{-- Results dropdown --}}
                <div
                    x-show="open && results.modules.length"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute top-full mt-1 left-0 w-80 bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-gray-100 dark:border-gray-800 z-50 overflow-hidden"
                >
                    <div class="p-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 px-2 mb-1">Modules</p>
                        <template x-for="item in results.modules" :key="item.id">
                            <a :href="item.url"
                               class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors text-sm text-gray-700 dark:text-gray-300"
                            >
                                <svg class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
                                </svg>
                                <span x-text="item.title" class="truncate flex-1"></span>
                                <span x-show="item.is_premium" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 flex-shrink-0">PREMIUM</span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
            @endunless
        </div>

        {{-- ─── Right: Dark mode + Notifications + User ─── --}}
        <div class="flex items-center gap-2 sm:gap-3">

            @if(auth()->user()->isLearner() && !auth()->user()->isInstructor())
                @if(auth()->user()->instructorApplications()->where('status', 'pending')->exists())
                    <a href="{{ route('learner.instructor.submitted') }}" class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-orange-100 text-orange-700 px-3 py-2 text-xs font-semibold hover:bg-orange-200 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Application Pending
                    </a>
                @else
                     <a href="{{ route('learner.instructor.apply') }}" class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-2 text-xs font-semibold text-white hover:shadow-md transition-all">
                        Apply as Instructor
                    </a>
                @endif
            @endif

            {{-- Dark mode toggle --}}
            <button
                class="flex items-center justify-center w-10 h-10 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                @click="$store.theme.toggle()"
                aria-label="Toggle dark mode"
            >
                <svg x-show="$store.theme.mode === 'dark'" width="18" height="18" fill="none" viewBox="0 0 24 24" x-cloak>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/>
                </svg>
                <svg x-show="$store.theme.mode === 'light'" width="18" height="18" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
                </svg>
            </button>

            {{-- ── Notifications ── --}}
            <div
                class="relative"
                x-data="{
                    open: false,
                    syncReadState() {
                        window.axios.post('{{ route('learner.notifications.dropdown-open') }}').catch(() => {});
                    }
                }"
                @click.outside="open = false"
            >
                <button
                    @click="open = !open; if (open) { syncReadState(); }"
                    class="relative flex items-center justify-center w-10 h-10 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    aria-label="Notifications"
                >
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 0 0-5-5.917V4a1 1 0 1 0-2 0v1.083A6 6 0 0 0 6 11v3.159c0 .538-.214 1.055-.595 1.437L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
                    </svg>
                    @if($unreadCount > 0)
                        <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-0.5 flex items-center justify-center rounded-full bg-red-500 text-white text-[9px] font-bold">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </button>

                {{-- Notification dropdown --}}
                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 top-full mt-2 w-80 bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-800 z-50 overflow-hidden"
                >
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</h3>
                        @if($unreadCount > 0)
                            <form method="POST" action="{{ route('learner.notifications.mark-all-read') }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-purple-600 hover:text-purple-800 dark:text-purple-400 transition-colors">
                                    Mark all read
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($recentNotifs->isEmpty())
                        <div class="px-4 py-8 text-center">
                            <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 0 0-5-5.917V4a1 1 0 1 0-2 0v1.083A6 6 0 0 0 6 11v3.159c0 .538-.214 1.055-.595 1.437L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
                            </svg>
                            <p class="text-sm text-gray-400 dark:text-gray-500">No notifications yet</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-50 dark:divide-gray-800 max-h-80 overflow-y-auto">
                            @foreach($recentNotifs as $notif)
                                @php
                                    $isUnread = is_null($notif->read_at);
                                    $normalized = $payloadNormalizer->normalize((array) $notif->data);
                                    $notifType = $normalized['type'];
                                    $severity = $normalized['severity'];
                                    $isChatMessage = $notifType === 'chat_message_received';
                                    $senderName = $normalized['sender_name'] ?? 'User';
                                    $senderAvatarUrl = $normalized['sender_avatar_url'] ?? null;
                                    $messagePreview = $normalized['message_preview'] ?: $normalized['message'];

                                    $iconColor = match($severity) {
                                        'success' => 'bg-emerald-100 text-emerald-700',
                                        'error' => 'bg-rose-100 text-rose-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <a
                                    href="{{ route('learner.notifications.read', $notif->id) }}"
                                    class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ $isUnread ? 'bg-rose-50/50 dark:bg-rose-900/10' : '' }}"
                                >
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden {{ $isChatMessage ? 'bg-blue-100 text-blue-700' : $iconColor }}">
                                        @if($isChatMessage)
                                            @if($senderAvatarUrl)
                                                <img src="{{ $senderAvatarUrl }}" alt="{{ $senderName }}" class="h-8 w-8 rounded-full object-cover" />
                                            @else
                                                <span class="text-[11px] font-bold">{{ $normalized['sender_initial'] }}</span>
                                            @endif
                                        @elseif($notifType === 'enrollment_approved')
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7"/></svg>
                                        @elseif($notifType === 'instructor_application_approved')
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        @elseif($notifType === 'instructor_application_rejected')
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 0 0-5-5.917V4a1 1 0 1 0-2 0v1.083A6 6 0 0 0 6 11v3.159c0 .538-.214 1.055-.595 1.437L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ $normalized['title'] }}</p>
                                        @if($isChatMessage)
                                            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mt-0.5">{{ $senderName }}</p>
                                        @endif
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug mt-0.5">
                                            {{ $isChatMessage ? '"' . $messagePreview . '"' : $normalized['message'] }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                                    </div>
                                    @if($isUnread)
                                        <span class="w-2 h-2 rounded-full bg-red-500 mt-1 flex-shrink-0"></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- User dropdown --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2 px-2 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    aria-label="User menu"
                >
                    @if($headerAvatar)
                        <img src="{{ $headerAvatar }}" alt="{{ $headerName }}" class="w-8 h-8 rounded-full object-cover">
                    @else
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                            style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);"
                        >
                            {{ strtoupper(mb_substr($headerName, 0, 1)) }}
                        </div>
                    @endif
                    <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300 max-w-[100px] truncate">
                        {{ $headerName }}
                    </span>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" class="text-gray-400"
                        :class="{ 'rotate-180': open }" style="transition: transform .2s">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/>
                    </svg>
                </button>

                {{-- Dropdown --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
                    x-cloak
                >
                    @if($headerUser->can('access instructor panel'))
                        <a
                            href="{{ route('instructor.dashboard') }}"
                            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-purple-700 bg-purple-50/50 hover:bg-purple-100/80 transition-colors border-l-4 border-purple-500"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                            Instructor Dashboard
                        </a>
                        <div class="h-px bg-gray-100 dark:bg-gray-700 my-1"></div>
                    @endif
                    <a
                        href="{{ route('profile.learner.edit') }}"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4Z"/>
                        </svg>
                        Edit Profile
                    </a>
                    <a
                        href="{{ route('subscription.index') }}"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3H6a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3Z"/>
                        </svg>
                        Subscription
                    </a>
                    @include('partials.chat-status-selector')
                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                        >
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/>
                            </svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

@push('scripts')
<script>
function learnerSearch() {
    return {
        query: '',
        open: false,
        results: { modules: [] },
        async search() {
            if (this.query.length < 2) {
                this.results = { modules: [] };
                this.open = false;
                return;
            }
            try {
                const res = await fetch(`/learn/search?q=${encodeURIComponent(this.query)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                });
                if (res.ok) {
                    this.results = await res.json();
                    this.open = this.results.modules.length > 0;
                }
            } catch (e) {
                console.error('Search failed', e);
            }
        }
    };
}
</script>
@endpush
