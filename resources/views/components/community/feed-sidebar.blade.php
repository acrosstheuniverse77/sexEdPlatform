@props(['connector', 'active' => 'featured', 'canModerate' => false, 'pendingCount' => 0, 'reportedCount' => 0])

@php
    $items = [
        ['key' => 'featured', 'label' => 'Featured', 'href' => route('connector.community.index', [$connector, 'tab' => 'featured']), 'badge' => 0, 'path' => 'm12 3 2.1 5.6 5.9.3-4.6 3.7 1.5 5.7L12 15.2l-4.9 3.1 1.5-5.7L4 8.9l5.9-.3L12 3Z'],
        ['key' => 'announcement', 'label' => 'Announcements', 'href' => route('connector.community.index', [$connector, 'type' => 'announcement']), 'badge' => 0, 'path' => 'M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z'],
        ['key' => 'event', 'label' => 'Events', 'href' => route('connector.community.index', [$connector, 'type' => 'event']), 'badge' => 0, 'path' => 'M7 3.75v2.5M17 3.75v2.5M4.75 8.75h14.5M6.25 5.25h11.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H6.25a2 2 0 0 1-2-2V7.25a2 2 0 0 1 2-2Z'],
        ['key' => 'resources', 'label' => 'Resources', 'href' => route('connector.community.index', [$connector, 'type' => 'resource']), 'badge' => 0, 'path' => 'M6.75 4.5h7.5L18 8.25v11.25H6.75V4.5Zm7.5 0v3.75H18M9 12h6M9 15h6'],
        ['key' => 'moderated_question', 'label' => 'Q&A', 'href' => route('connector.community.index', [$connector, 'type' => 'moderated_question']), 'badge' => 0, 'path' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.33-.671.455-.747.387-1.45.963-1.45 1.814v.75M12 18h.01'],
        ['key' => 'discussion_prompt', 'label' => 'Discussions', 'href' => route('connector.community.index', [$connector, 'type' => 'discussion_prompt']), 'badge' => 0, 'path' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.142-4.03 7.5-9 7.5a10.6 10.6 0 0 1-3.29-.514L3 20.25l1.46-4.052A6.872 6.872 0 0 1 3 12c0-4.142 4.03-7.5 9-7.5s9 3.358 9 7.5Z'],
    ];
@endphp

<aside {{ $attributes->merge(['class' => 'space-y-5']) }}>

    <nav class="rounded-3xl border border-gray-200 bg-white p-3 shadow-sm">
        <a href="{{ route('connector.community.index', $connector) }}" class="flex min-h-11 items-center gap-3 rounded-2xl px-3 text-sm font-bold {{ $active === 'featured' ? 'bg-brand-700 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 12h16.5M12 3.75v16.5M5.25 5.25h13.5v13.5H5.25z"/></svg>
            <span>Overview</span>
        </a>

        @if($canModerate)
            <a href="{{ route('connector.community.moderation.index', $connector) }}" class="mt-2 flex min-h-11 items-center justify-between gap-3 rounded-2xl px-3 text-sm font-bold text-gray-700 hover:bg-gray-50">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3.75 5.25 6.5v5.25c0 4.25 2.85 7.9 6.75 8.95 3.9-1.05 6.75-4.7 6.75-8.95V6.5L12 3.75Zm0 5.25v4m0 3.25h.01"/></svg>
                    <span>Moderation</span>
                </span>
                @if(($pendingCount + $reportedCount) > 0)
                    <span class="inline-flex min-w-6 justify-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-extrabold text-rose-700">{{ $pendingCount + $reportedCount }}</span>
                @endif
            </a>
        @endif
    </nav>

    <section class="rounded-3xl border border-gray-200 bg-white p-3 shadow-sm" x-data="{ browseOpen: true }">
        <button type="button" class="flex min-h-11 w-full items-center justify-between rounded-2xl px-3 text-sm font-extrabold text-gray-900 hover:bg-gray-50" @click="browseOpen = !browseOpen" :aria-expanded="browseOpen.toString()">
            <span>Browse</span>
            <svg class="h-4 w-4 transition" :class="browseOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
        </button>

        <div x-show="browseOpen" x-cloak class="mt-1 space-y-1">
            @foreach($items as $item)
                <a href="{{ $item['href'] }}" class="flex min-h-11 items-center justify-between gap-3 rounded-2xl px-3 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 {{ $active === $item['key'] ? 'bg-brand-50 text-brand-800 ring-1 ring-brand-100' : 'text-gray-700 hover:bg-gray-50' }}">
                    <span class="flex min-w-0 items-center gap-3">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['path'] }}"/></svg>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </span>
                    @if($item['badge'] > 0)
                        <span class="inline-flex min-w-6 justify-center rounded-full bg-brand-100 px-2 py-0.5 text-xs font-extrabold text-brand-700">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
</aside>
