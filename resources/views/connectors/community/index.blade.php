@extends('layouts.connector-app')

@section('title', 'Community Hub')
@section('page-title', 'Community Hub')

@section('content')
@php
    $activeNav = match($activeType) {
        'announcement' => 'announcement',
        'event' => 'event',
        'resource' => 'resources',
        'moderated_question' => 'moderated_question',
        'discussion_prompt' => 'discussion_prompt',
        default => $activeTab === 'featured' ? 'featured' : 'featured',
    };

@endphp

<div class="mx-auto max-w-[1500px]">
    <div class="grid gap-7 xl:grid-cols-[250px_minmax(0,1fr)_320px]">
        <x-community.feed-sidebar
            :connector="$connector"
            :active="$activeNav"
            :can-moderate="$canModerate"
            :pending-count="$pendingCount"
            :reported-count="$reportedCount"
            class="hidden xl:block"
        />

        <main class="min-w-0 space-y-7">

            <section class="sticky top-[76px] z-20 rounded-3xl border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur">
                <form method="GET" action="{{ route('connector.community.index', $connector) }}" class="space-y-4" x-data="communityHubFilters()">
                    <input type="hidden" name="sort" value="newest">
                    <div>
                        <label class="relative block">
                            <span class="sr-only">Search posts</span>
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                            <input type="search" name="search" value="{{ $activeSearch }}" placeholder="Search posts" class="min-h-11 w-full rounded-2xl border-gray-200 bg-gray-50 pl-11 text-base text-gray-900 transition focus:border-brand-500 focus:bg-white focus:ring-brand-500" @input.debounce.450ms="submit">
                        </label>
                    </div>

                </form>
            </section>

            @if($space->frozen_at)
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">This connector community space is frozen while moderators review safety concerns.</div>
            @endif

            <x-community.post-composer :connector="$connector" :can-create-post="$canCreatePost" />

            <section class="space-y-5">
                <div>
                    <p class="text-[13px] font-bold uppercase tracking-[0.18em] text-gray-500">Community Feed</p>
                    <h3 class="text-[22px] font-extrabold text-gray-950">Latest discussions and updates</h3>
                </div>

                @forelse($posts as $post)
                    <x-community.post-card :connector="$connector" :post="$post" :can-moderate="$canModerate" />
                @empty
                    <section class="rounded-3xl border border-dashed border-gray-200 bg-white p-10 text-center shadow-sm">
                        <div class="mx-auto flex max-w-md flex-col items-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-brand-50 text-brand-700">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z"/></svg>
                            </div>
                            <p class="mt-4 text-lg font-bold text-gray-950">No posts match this view.</p>
                            <p class="mt-2 text-base text-gray-600">Try another category, clear filters, or browse connector resources.</p>
                            <a href="{{ route('connector.community.index', $connector) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-2xl border border-brand-200 bg-brand-50 px-5 text-sm font-bold text-brand-700 hover:bg-brand-100">Browse Resources</a>
                        </div>
                    </section>
                @endforelse
            </section>

            <div>{{ $posts->links() }}</div>
        </main>

        <x-community.right-panel
            :connector="$connector"
            :upcoming-seminars="$upcomingSeminars"
            :pending-count="$pendingCount"
            :reported-count="$reportedCount"
            :escalated-count="$escalatedCount"
            :can-moderate="$canModerate"
            class="hidden xl:block"
        />

        <div class="space-y-7 xl:hidden">
            <x-community.feed-sidebar
                :connector="$connector"
                :active="$activeNav"
                :can-moderate="$canModerate"
                :pending-count="$pendingCount"
                :reported-count="$reportedCount"
            />
            <x-community.right-panel
                :connector="$connector"
                :upcoming-seminars="$upcomingSeminars"
                :pending-count="$pendingCount"
                :reported-count="$reportedCount"
                :escalated-count="$escalatedCount"
                :can-moderate="$canModerate"
            />
        </div>
    </div>
</div>
<script>
    function communityHubFilters() {
        return {
            submit(event) {
                event.target.form.requestSubmit();
            },
        };
    }
</script>
@endsection
