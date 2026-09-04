@extends('layouts.connector-app')

@section('title', 'Community Hub')
@section('page-title', 'Community Hub')

@section('content')
@php
    $activeFilters = collect([
        'search' => $activeSearch,
        'type' => $activeType ? collect($postTypes)->firstWhere('value', $activeType)?->label() : null,
        'topic' => $activeTopic,
        'sort' => $activeSort !== 'top' ? str($activeSort)->headline()->toString() : null,
        'status' => $canModerate && $activeStatus ? collect($statusOptions)->firstWhere('value', $activeStatus)?->label() : null,
    ])->filter();
@endphp

<div class="mx-auto max-w-[1180px]">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_290px]">
        <main class="min-w-0 space-y-5">
            <section class="sticky top-[76px] z-20 rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur" x-data="communityHubFilters()">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <form method="GET" action="{{ route('connector.community.index', $connector) }}" class="flex min-w-0 flex-1 gap-2" x-ref="filterForm">
                        <label class="relative min-w-0 flex-1">
                            <span class="sr-only">Search posts</span>
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                            <input type="search" name="search" value="{{ $activeSearch }}" placeholder="Search posts" class="min-h-11 w-full rounded-xl border-gray-200 bg-gray-50 pl-11 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-brand-500" @input.debounce.450ms="$refs.filterForm.requestSubmit()">
                        </label>
                        <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2" @click="open = !open" :aria-expanded="open.toString()">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10m-7 6h4"/></svg>
                            Filters
                        </button>

                        <div x-show="open" x-cloak @click.away="open = false" class="absolute left-4 right-4 top-[calc(100%-0.5rem)] z-30 rounded-2xl border border-gray-200 bg-white p-4 shadow-lg">
                            <div class="{{ $canModerate ? 'grid gap-3 md:grid-cols-4' : 'grid gap-3 md:grid-cols-3' }}">
                                <label class="block text-sm font-bold text-gray-700">Post type
                                    <select name="type" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">All types</option>
                                        @foreach($postTypes as $type)
                                            <option value="{{ $type->value }}" @selected($activeType === $type->value)>{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm font-bold text-gray-700">Topic
                                    <select name="topic" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">All topics</option>
                                        @foreach($topics as $topic)
                                            <option value="{{ $topic }}" @selected($activeTopic === $topic)>{{ $topic }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm font-bold text-gray-700">Sort
                                    <select name="sort" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="top" @selected($activeSort === 'top')>Top</option>
                                        <option value="newest" @selected($activeSort === 'newest')>Newest</option>
                                    </select>
                                </label>
                                @if($canModerate)
                                    <label class="block text-sm font-bold text-gray-700">Status
                                        <select name="status" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="">Published and locked</option>
                                            @foreach($statusOptions as $status)
                                                <option value="{{ $status->value }}" @selected($activeStatus === $status->value)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </div>
                            <div class="mt-4 flex justify-end gap-2">
                                <a href="{{ route('connector.community.index', $connector) }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-bold text-gray-700 hover:bg-gray-50">Clear filters</a>
                                <button class="inline-flex min-h-11 items-center rounded-xl bg-brand-700 px-4 text-sm font-bold text-white hover:bg-brand-800">Apply</button>
                            </div>
                        </div>
                    </form>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if($canCreatePost)
                            <a href="{{ route('connector.community.create', $connector) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-700 px-4 text-sm font-bold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5"/></svg>
                                Create post
                            </a>
                        @endif
                        @if($canModerate)
                            <a href="{{ route('connector.community.moderation.index', $connector) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-bold text-amber-800 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3.75 5.25 6.5v5.25c0 4.25 2.85 7.9 6.75 8.95 3.9-1.05 6.75-4.7 6.75-8.95V6.5L12 3.75Z"/></svg>
                                Open moderation
                            </a>
                        @endif
                    </div>
                </div>

                @if($activeFilters->isNotEmpty())
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach($activeFilters as $label)
                            <span class="inline-flex min-h-8 items-center rounded-full bg-brand-50 px-3 text-xs font-bold text-brand-700">
                                {{ $label }}
                            </span>
                        @endforeach
                        <a href="{{ route('connector.community.index', $connector) }}" class="text-xs font-bold text-gray-500 hover:text-brand-700">Clear filters</a>
                    </div>
                @endif
            </section>

            @if($space->frozen_at)
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">This connector community space is frozen while moderators review safety concerns.</div>
            @endif

            <section class="space-y-4">
                @forelse($posts as $post)
                    <x-community.post-card :connector="$connector" :post="$post" :can-moderate="$canModerate" />
                @empty
                    <section class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center shadow-sm">
                        <p class="text-base font-bold text-gray-900">No posts match this view.</p>
                        <p class="mt-1 text-sm text-gray-500">Try a different search or filter.</p>
                    </section>
                @endforelse
            </section>

            <div>{{ $posts->links() }}</div>
        </main>

        <x-community.right-panel :connector="$connector" :upcoming-seminars="$upcomingSeminars" class="hidden xl:block" />
    </div>
</div>
<script>
    function communityHubFilters() {
        return { open: false };
    }
</script>
@endsection
