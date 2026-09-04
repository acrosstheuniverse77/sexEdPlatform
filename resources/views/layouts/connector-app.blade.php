<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Connector') | {{ config('app.name', 'Conscious Connections') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('media/Logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-gray-50 font-sans text-gray-900 antialiased"
    x-data
    x-init="
        $store.sidebar.isExpanded = window.innerWidth >= 1280;
        window.addEventListener('resize', () => {
            if (window.innerWidth < 1280) {
                $store.sidebar.setMobileOpen(false);
                $store.sidebar.isExpanded = false;
            } else {
                $store.sidebar.isMobileOpen = false;
                $store.sidebar.isExpanded = true;
            }
        });
    ">
    @php
        $connectorAccess = app(\App\Services\Connectors\ConnectorAccessService::class);
        $connectorNotificationQuery = auth()->user()
            ->notifications()
            ->where(function ($query) use ($connector) {
                $query->where('data->connector_id', $connector->id)
                    ->orWhere('data->action_url', 'like', '%/connector/'.$connector->id.'/%');
            });
        $connectorRecentNotifications = (clone $connectorNotificationQuery)->latest()->limit(8)->get();
        $connectorNotificationUnreadCount = (clone $connectorNotificationQuery)->whereNull('read_at')->count();
        $connectorNotificationNormalizer = app(\App\Support\NotificationPayloadNormalizer::class);
        $connectorNavItems = [
            ['Dashboard', 'connector.dashboard', null, 'M3.75 5.75A2 2 0 0 1 5.75 3.75h3.5a2 2 0 0 1 2 2v3.5a2 2 0 0 1-2 2h-3.5a2 2 0 0 1-2-2v-3.5Zm9 0a2 2 0 0 1 2-2h3.5a2 2 0 0 1 2 2v3.5a2 2 0 0 1-2 2h-3.5a2 2 0 0 1-2-2v-3.5Zm-9 9a2 2 0 0 1 2-2h3.5a2 2 0 0 1 2 2v3.5a2 2 0 0 1-2 2h-3.5a2 2 0 0 1-2-2v-3.5Zm9 0a2 2 0 0 1 2-2h3.5a2 2 0 0 1 2 2v3.5a2 2 0 0 1-2 2h-3.5a2 2 0 0 1-2-2v-3.5Z'],
            ['Members', 'connector.members.index', null, 'M16 11a4 4 0 1 0-8 0m8 0a4 4 0 1 1-8 0m8 0v1a4 4 0 0 0 4 4m-12-5v1a4 4 0 0 1-4 4m4-4h8m-8 0a4 4 0 0 0-4 4v1m12-5a4 4 0 0 1 4 4v1'],
            ['Seminars', 'connector.seminars.index', null, 'M7 3.75v2.5M17 3.75v2.5M4.75 8.75h14.5M6.25 5.25h11.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H6.25a2 2 0 0 1-2-2V7.25a2 2 0 0 1 2-2Z'],
            ['Community Hub', 'connector.community.index', 'community.view_space', 'M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z'],
            ['Notifications', 'connector.notifications.index', null, 'M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9'],
            ['Roles & Permissions', 'connector.roles.index', 'connector.manage_roles', 'M12 3.75 5.25 6.5v5.25c0 4.25 2.85 7.9 6.75 8.95 3.9-1.05 6.75-4.7 6.75-8.95V6.5L12 3.75Zm-2.25 8.5 1.75 1.75 3.25-4'],
            ['Modules', 'connector.modules', 'connector.manage_modules', 'M5 4.75h14v14H5zM8 8.75h8M8 12h8M8 15.25h5'],
            ['Educators', 'connector.educators', 'connector.manage_educators', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0'],
            ['Subscription', 'connector.subscription', 'connector.view_subscription', 'M4.75 6.5h14.5M6 4.75h12A1.25 1.25 0 0 1 19.25 6v12A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V6A1.25 1.25 0 0 1 6 4.75Zm2 9.25h1m3 0h1'],
        ];
    @endphp

    <div class="min-h-screen xl:flex">
        <div x-show="$store.sidebar.isMobileOpen" x-cloak @click="$store.sidebar.setMobileOpen(false)" class="fixed inset-0 z-[9998] bg-gray-900/50 xl:hidden"></div>

        <aside class="fixed left-0 top-0 z-[9999] flex h-screen flex-col overflow-hidden border-r border-gray-200 bg-white transition-all duration-300 ease-in-out"
            :class="{
                'w-[270px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
                'w-[80px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen,
                'translate-x-0': $store.sidebar.isMobileOpen,
                '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
            }"
            @mouseenter="$store.sidebar.setHovered(true)"
            @mouseleave="$store.sidebar.setHovered(false)">
            <div class="flex items-center border-b border-gray-100 px-5 py-5" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : 'justify-start'">
                <a href="{{ route('connector.dashboard', $connector) }}" class="flex min-w-0 items-center gap-3">
                    <img src="/media/Logo.png" alt="Conscious Connections" class="h-10 w-10 object-contain">
                    <div x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-cloak class="min-w-0 leading-tight">
                        <span class="block text-lg font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-purple-700 to-pink-500">Connector</span>
                        <span class="mt-1 block truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-600">{{ $connector->name ?? 'Workspace' }}</span>
                    </div>
                </a>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-4">
                @foreach($connectorNavItems as [$label, $route, $permission, $path])
                    @continue($permission && ! $connectorAccess->hasPermission(auth()->user(), $connector, $permission))
                    @php $active = request()->routeIs($route); @endphp
                    <a href="{{ route($route, $connector) }}"
                        class="group flex items-center gap-3 overflow-hidden whitespace-nowrap rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 {{ $active ? 'text-white shadow-sm' : 'text-gray-600 hover:bg-purple-50 hover:text-purple-700' }}"
                        @if($active) style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);" @endif
                        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : ''">
                        <svg class="h-5 w-5 flex-shrink-0 {{ $active ? 'text-white' : 'text-gray-500 group-hover:text-purple-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $path }}"/>
                        </svg>
                        <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-cloak class="truncate">{{ $label }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="space-y-1 border-t border-gray-100 p-3">
                <a href="{{ route('learner.dashboard') }}" class="flex items-center gap-3 overflow-hidden whitespace-nowrap rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : ''">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12 12 4l9 8M5.5 10.5v8h13v-8"/></svg>
                    <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-cloak>Learner Dashboard</span>
                </a>
            </div>
        </aside>

        <div class="flex min-h-screen flex-1 flex-col transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[270px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[80px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered
            }">
            <header class="sticky top-0 z-[9997] border-b border-gray-200 bg-white px-4 py-3 md:px-6">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" class="hidden h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-100 xl:flex" @click="$store.sidebar.toggleExpanded()" aria-label="Toggle sidebar">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h10M4 17h16"/></svg>
                        </button>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 xl:hidden" @click="$store.sidebar.toggleMobileOpen()" aria-label="Open menu">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>
                        <div class="min-w-0">
                            <h1 class="truncate text-xl font-bold">@yield('page-title', $connector->name ?? 'Connector')</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative" x-data="{ open: false, syncReadState() { window.axios.post('{{ route('connector.notifications.dropdown-open', $connector) }}').catch(() => {}); } }">
                            <button
                                type="button"
                                @click="open = !open; if (open) { syncReadState(); }"
                                class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-100"
                                aria-label="Open notifications"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
                                </svg>
                                @if($connectorNotificationUnreadCount > 0)
                                    <span data-testid="connector-notification-badge" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
                                        {{ $connectorNotificationUnreadCount > 9 ? '9+' : $connectorNotificationUnreadCount }}
                                    </span>
                                @endif
                            </button>

                            <div
                                x-show="open"
                                @click.away="open = false"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl"
                            >
                                <div class="flex items-center justify-between border-b border-gray-100 bg-gradient-to-r from-purple-50 via-white to-purple-100/70 px-4 py-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                                        <p class="text-xs text-gray-500">Connector updates and actions.</p>
                                    </div>
                                    @if($connectorNotificationUnreadCount > 0)
                                        <form method="POST" action="{{ route('connector.notifications.mark-all-read', $connector) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-purple-700 transition hover:text-purple-900">Mark all read</button>
                                        </form>
                                    @endif
                                </div>

                                @if($connectorRecentNotifications->isNotEmpty())
                                    <div class="max-h-64 divide-y divide-gray-50 overflow-y-auto">
                                        @foreach($connectorRecentNotifications as $notification)
                                            @php
                                                $isUnread = is_null($notification->read_at);
                                                $normalized = $connectorNotificationNormalizer->normalize((array) $notification->data);
                                                $severityClass = match($normalized['severity']) {
                                                    'success' => 'border-l-4 border-emerald-500',
                                                    'error' => 'border-l-4 border-rose-500',
                                                    default => 'border-l-4 border-slate-300',
                                                };
                                            @endphp
                                            <a href="{{ route('connector.notifications.read', [$connector, $notification->id]) }}" class="flex items-start gap-3 px-4 py-3 transition hover:bg-gray-50 {{ $severityClass }} {{ $isUnread ? 'bg-rose-50/40' : '' }}">
                                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-purple-100 text-purple-700">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-semibold text-gray-800">{{ $normalized['title'] }}</p>
                                                    <p class="mt-0.5 line-clamp-2 text-[11px] text-gray-600">{{ $normalized['message'] }}</p>
                                                    <p class="mt-1 text-[10px] text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="border-t border-gray-100 px-4 py-2">
                                        <a href="{{ route('connector.notifications.index', $connector) }}" class="block py-1 text-center text-xs font-medium text-purple-700 transition hover:text-purple-900">
                                            View all notifications
                                        </a>
                                    </div>
                                @else
                                    <div class="px-4 py-6 text-center">
                                        <p class="text-sm text-gray-400">No notifications yet</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('connector.status', $connector) }}" class="hidden rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-purple-700 sm:inline-flex">Status</a>
                        <a href="{{ route('learner.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-purple-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-purple-800">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12 12 4l9 8M5.5 10.5v8h13v-8"/></svg>
                            <span class="hidden sm:inline">Learner</span>
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')

    @if(session('success') || session('error') || session('info') || session('warning') || session('status') || $errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function fireToasts() {
                if (typeof window.toast === 'undefined') {
                    return setTimeout(fireToasts, 80);
                }
                @if(session('success'))
                    window.toast.success("{{ addslashes(session('success')) }}");
                @endif
                @if(session('error'))
                    window.toast.error("{{ addslashes(session('error')) }}");
                @endif
                @if(session('info'))
                    window.toast.info("{{ addslashes(session('info')) }}");
                @endif
                @if(session('warning'))
                    window.toast.warning("{{ addslashes(session('warning')) }}");
                @endif
                @if(session('status'))
                    window.toast.info("{{ addslashes(session('status')) }}");
                @endif
                @if($errors->any())
                    @foreach($errors->all() as $error)
                        window.toast.error("{{ addslashes($error) }}");
                    @endforeach
                @endif
            }
            fireToasts();
        });
    </script>
    @endif

    @include('chat.partials.global-popup')
</body>
</html>
