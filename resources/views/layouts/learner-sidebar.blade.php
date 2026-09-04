@php
    $authUser        = Auth::user();
    $sidebarProfile  = $authUser->learnerProfile;
    $sidebarUsername = $sidebarProfile?->username ?? $authUser->name;
    $sidebarAvatar   = $sidebarProfile?->avatar_path
        ? asset('storage/' . $sidebarProfile->avatar_path)
        : null;
    $pendingParentApprovalRequestCount = $authUser->unreadNotifications()
        ->where('data->type', 'child_enrollment_approval_requested')
        ->count();
    $hasApprovedParentLinks = $authUser->parentLinks()
        ->where('verification_status', 'approved')
        ->whereNotNull('relationship_verified_at')
        ->exists();
    $navItems = [
        [
            'label'  => 'Dashboard',
            'route'  => 'learner.dashboard',
            'active' => request()->routeIs('learner.dashboard'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M3.25 5.5A2.25 2.25 0 0 1 5.5 3.25H9A2.25 2.25 0 0 1 11.25 5.5V9A2.25 2.25 0 0 1 9 11.25H5.5A2.25 2.25 0 0 1 3.25 9V5.5Zm0 9A2.25 2.25 0 0 1 5.5 12.25H9A2.25 2.25 0 0 1 11.25 14.5V18A2.25 2.25 0 0 1 9 20.25H5.5A2.25 2.25 0 0 1 3.25 18v-3.5Zm9-9A2.25 2.25 0 0 1 14.5 3.25H18A2.25 2.25 0 0 1 20.25 5.5V9A2.25 2.25 0 0 1 18 11.25h-3.5A2.25 2.25 0 0 1 12.25 9V5.5Zm0 9A2.25 2.25 0 0 1 14.5 12.25H18A2.25 2.25 0 0 1 20.25 14.5V18A2.25 2.25 0 0 1 18 20.25h-3.5A2.25 2.25 0 0 1 12.25 18v-3.5Z"/></svg>',
        ],
        [
            'label'  => 'Subscriptions',
            'route'  => 'subscription.index',
            'active' => request()->routeIs('subscription.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M4.75 5.5A.75.75 0 0 1 5.5 4.75h13a.75.75 0 0 1 .75.75v1.75H4.75V5.5Zm-1.5 3v10A2.75 2.75 0 0 0 6 21.25h12A2.75 2.75 0 0 0 20.75 18.5V8.5H3.25Zm1.5 1.5h15v9A1.25 1.25 0 0 1 18.5 20H6A1.25 1.25 0 0 1 4.75 18.75V9.5Zm1 5a.75.75 0 0 1 .75-.75H11a.75.75 0 0 1 0 1.5H6.5a.75.75 0 0 1-.75-.75Zm0 3a.75.75 0 0 1 .75-.75h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z"/></svg>',
        ],
        [
            'label'  => 'Connectors',
            'route'  => 'connectors.index',
            'active' => request()->routeIs('connectors.*') || request()->routeIs('connector.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M7.25 4A3.25 3.25 0 0 0 4 7.25v9.5A3.25 3.25 0 0 0 7.25 20h9.5A3.25 3.25 0 0 0 20 16.75v-9.5A3.25 3.25 0 0 0 16.75 4h-9.5ZM5.5 7.25A1.75 1.75 0 0 1 7.25 5.5h9.5a1.75 1.75 0 0 1 1.75 1.75v9.5a1.75 1.75 0 0 1-1.75 1.75h-9.5a1.75 1.75 0 0 1-1.75-1.75v-9.5ZM8 8.75A.75.75 0 0 1 8.75 8h2.5a.75.75 0 0 1 0 1.5h-2.5A.75.75 0 0 1 8 8.75Zm0 3.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5h-6.5A.75.75 0 0 1 8 12Zm.75 2.5a.75.75 0 0 0 0 1.5h4.5a.75.75 0 0 0 0-1.5h-4.5Z"/></svg>',
        ],
        [
            'label'  => 'Browse Seminars',
            'route'  => 'learner.seminars.index',
            'active' => request()->routeIs('learner.seminars.*') || request()->routeIs('seminars.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M7 2.75a.75.75 0 0 1 .75.75v1h8.5v-1a.75.75 0 0 1 1.5 0v1H18A3.25 3.25 0 0 1 21.25 7.75V18A3.25 3.25 0 0 1 18 21.25H6A3.25 3.25 0 0 1 2.75 18V7.75A3.25 3.25 0 0 1 6 4.5h.25v-1A.75.75 0 0 1 7 2.75Zm11 3.25H6A1.75 1.75 0 0 0 4.25 7.75v.5h15.5v-.5A1.75 1.75 0 0 0 18 6ZM4.25 9.75V18A1.75 1.75 0 0 0 6 19.75h12A1.75 1.75 0 0 0 19.75 18V9.75H4.25Z"/></svg>',
        ],
        [
            'label'  => 'My Modules',
            'route'  => 'learner.modules.index',
            'active' => request()->routeIs('learner.modules.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M3.25 4A.75.75 0 0 1 4 3.25h16a.75.75 0 0 1 .75.75v14a.75.75 0 0 1-.75.75H4a.75.75 0 0 1-.75-.75V4Zm1.5.75v12.5h14V4.75H4.75ZM7 8.25a.75.75 0 0 0 0 1.5h10a.75.75 0 0 0 0-1.5H7Zm0 4a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H7Z"/></svg>',
        ],
        [
            'label'  => 'Gamification',
            'route'  => 'learner.gamification',
            'active' => request()->routeIs('learner.gamification'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2l2.82 5.72L21 8.62l-4.5 4.39 1.06 6.22L12 16.34l-5.56 2.89 1.06-6.22L3 8.62l6.18-.9L12 2Z"/><circle cx="12" cy="12" r="2.25" fill="white"/></svg>',
        ],
        [
            'label'  => 'Notifications',
            'route'  => 'learner.notifications.index',
            'active' => request()->routeIs('learner.notifications.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 0 0-5-5.917V4a1 1 0 1 0-2 0v1.083A6 6 0 0 0 6 11v3.159c0 .538-.214 1.055-.595 1.437L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>',
        ],
        [
            'label'  => 'Chat',
            'route'  => 'chat.page',
            'active' => request()->routeIs('chat.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8m-8 4h5m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        ],
        [
            'label'  => 'Certificates',
            'route'  => 'learner.certificates.index',
            'active' => request()->routeIs('learner.certificates.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M7 2.75A4.25 4.25 0 0 0 2.75 7v10A4.25 4.25 0 0 0 7 21.25h10A4.25 4.25 0 0 0 21.25 17V7A4.25 4.25 0 0 0 17 2.75H7ZM4.25 7A2.75 2.75 0 0 1 7 4.25h10A2.75 2.75 0 0 1 19.25 7v10A2.75 2.75 0 0 1 17 19.25H7A2.75 2.75 0 0 1 4.25 17V7Zm4 2a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5h-7.5Zm-.75 4.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5a.75.75 0 0 1-.75-.75Zm.75 3.25a.75.75 0 0 0 0 1.5h4.5a.75.75 0 0 0 0-1.5h-4.5Z"/></svg>',
        ],
    ];

    if ($hasApprovedParentLinks) {
        $navItems[] = [
            'label'  => 'My Guardian',
            'route'  => 'learner.parent.index',
            'active' => request()->routeIs('learner.parent.*'),
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M12 3.25a4.75 4.75 0 1 0 0 9.5 4.75 4.75 0 0 0 0-9.5ZM8.75 8a3.25 3.25 0 1 1 6.5 0 3.25 3.25 0 0 1-6.5 0Zm-5.5 9.5A3.25 3.25 0 0 1 6.5 14.25h11A3.25 3.25 0 0 1 20.75 17.5v1.25a.75.75 0 0 1-1.5 0V17.5A1.75 1.75 0 0 0 17.5 15.75h-11A1.75 1.75 0 0 0 4.75 17.5v1.25a.75.75 0 0 1-1.5 0V17.5Z"/></svg>',
        ];
    }

    // Add My Dependents nav item for verified guardian accounts.
    if ($authUser->isParent() || ($authUser->isParentRegistration() && $authUser->isParentVerificationApproved())) {
        $navItems[] = [
            'label'  => 'My Dependents',
            'route'  => 'parent.children.index',
            'active' => request()->routeIs('parent.children.*'),
            'badge'  => $pendingParentApprovalRequestCount,
            'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M9 4a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM4.25 7a4.75 4.75 0 1 1 9.5 0 4.75 4.75 0 0 1-9.5 0Zm10.5-1.25a.75.75 0 0 1 .75-.75 4.75 4.75 0 0 1 0 9.5.75.75 0 0 1 0-1.5 3.25 3.25 0 0 0 0-6.5.75.75 0 0 1-.75-.75ZM2.75 17.5A3.25 3.25 0 0 1 6 14.25h6A3.25 3.25 0 0 1 15.25 17.5v.5a.75.75 0 0 1-1.5 0v-.5A1.75 1.75 0 0 0 12 15.75H6A1.75 1.75 0 0 0 4.25 17.5v.5a.75.75 0 0 1-1.5 0v-.5Zm15 0a.75.75 0 0 1 .75-.75 1.75 1.75 0 0 1 1.75 1.75v.5a.75.75 0 0 1-1.5 0v-.5a.25.25 0 0 0-.25-.25.75.75 0 0 1-.75-.75Z"/></svg>',
        ];
    }
@endphp

<aside
    id="learner-sidebar"
    class="fixed top-0 left-0 z-[99999] h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 transition-all duration-300 ease-in-out flex flex-col"
    :class="{
        'w-[270px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
        'w-[80px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="$store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)"
>
    {{-- ─── Logo ─── --}}
    <div
        class="flex items-center px-5 py-5 border-b border-gray-100 dark:border-gray-800 flex-shrink-0 overflow-hidden"
        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : 'justify-start'"
    >
        <a href="{{ route('learner.dashboard') }}" class="flex items-center gap-3">
            {{-- Brand logo + text (expanded) --}}
            <img
                src="/media/Logo.png"
                alt="Conscious Connections"
                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                x-transition:enter="transition-opacity duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="h-10 w-10 object-contain"
                x-cloak
            >
            <div
                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                x-cloak
                class="leading-tight"
            >
                <span class="text-lg font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-purple-700 to-pink-500 whitespace-nowrap block">Conscious <br>
                Connections</span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-600 mt-1 block">Learner Panel</span>
            </div>
            {{-- Icon-only logo (collapsed) --}}
            <img
                src="/media/Logo.png"
                alt="Logo"
                x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
                class="h-9 w-9 object-contain"
            >
        </a>
    </div>

    {{-- ─── Navigation ─── --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 px-3 space-y-1">
        @foreach($navItems as $item)
            <a
                href="{{ route($item['route'], $item['params'] ?? []) }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group overflow-hidden whitespace-nowrap
                    {{ $item['active']
                        ? 'text-white shadow-sm'
                        : 'text-gray-600 dark:text-gray-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-700 dark:hover:text-purple-300' }}"
                @if($item['active'])
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                @endif
                :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : ''"
            >
                <span class="flex-shrink-0 transition-transform duration-200 group-hover:scale-110 {{ $item['active'] ? 'text-white' : 'text-gray-500 dark:text-gray-400 group-hover:text-purple-600 dark:group-hover:text-purple-400' }}">
                    {!! $item['icon'] !!}
                </span>
                <span
                    x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                    x-cloak
                    class="truncate"
                >{{ $item['label'] }}</span>
                @if(($item['badge'] ?? 0) > 0)
                    <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
                          x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                          x-cloak>
                        {{ ($item['badge'] ?? 0) > 9 ? '9+' : (int) $item['badge'] }}
                    </span>
                    <span class="h-2 w-2 rounded-full bg-rose-500"
                          x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"></span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- ─── Bottom: Edit Profile + Logout ─── --}}
    <div class="flex-shrink-0 border-t border-gray-100 dark:border-gray-800 p-3 space-y-1">

        {{-- Edit Profile --}}
        <a
            href="{{ route('profile.learner.edit') }}"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all duration-200 overflow-hidden whitespace-nowrap"
            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : ''"
        >
            <div class="relative w-7 h-7 flex-shrink-0">
                <div
                    class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold"
                    style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);"
                >
                    {{ strtoupper(mb_substr($sidebarUsername, 0, 1)) }}
                </div>
                @if($sidebarAvatar)
                    <img src="{{ $sidebarAvatar }}" alt="{{ $sidebarUsername }}"
                         class="absolute inset-0 w-7 h-7 rounded-full object-cover"
                         onerror="this.remove()">
                @endif
            </div>
            <span
                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                x-cloak
                class="truncate"
            >Edit Profile</span>
        </a>

        {{-- Logout --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-all duration-200 overflow-hidden whitespace-nowrap"
                :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : ''"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" class="flex-shrink-0">
                    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M15.75 12a.75.75 0 0 0-.75-.75H5.81l2.72-2.72a.75.75 0 0 0-1.06-1.06l-4 4a.75.75 0 0 0 0 1.06l4 4a.75.75 0 1 0 1.06-1.06L5.81 12.75H15a.75.75 0 0 0 .75-.75Zm.25-7.25A.75.75 0 0 0 15.25 5H17a2.25 2.25 0 0 1 2.25 2.25v9.5A2.25 2.25 0 0 1 17 19h-1.75a.75.75 0 0 0 0 1.5H17A3.75 3.75 0 0 0 20.75 16.75v-9.5A3.75 3.75 0 0 0 17 3.5h-1.75a.75.75 0 0 0-.25-.75Z"/>
                </svg>
                <span
                    x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                    x-cloak
                >Log Out</span>
            </button>
        </form>
    </div>
</aside>
