@extends('layouts.connector-app')

@section('title', 'Community Review Center')
@section('page-title', 'Community Review Center')

@section('content')
@php
    $tabs = [
        'all' => 'All posts',
        'pending' => 'Pending',
        'reported' => 'Reported',
        'hidden' => 'Hidden',
        'rejected' => 'Rejected',
        'escalated' => 'Escalated',
    ];

@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-amber-50 via-white to-brand-50 px-6 py-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Connector moderation</p>
                    <h2 class="mt-1 text-2xl font-bold text-gray-950">Review center</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-600">Review posts that need connector action. Keep decisions calm, visible, and separate from platform-admin enforcement.</p>
                </div>
                <a href="{{ route('connector.community.index', $connector) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50" title="Back to Community Hub" aria-label="Back to Community Hub">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                </a>
            </div>
        </div>

        <div class="flex gap-2 overflow-x-auto border-t border-amber-100 bg-white px-4 py-3">
            @foreach($tabs as $key => $label)
                <a href="{{ route('connector.community.moderation.index', [$connector, 'tab' => $key]) }}" class="inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-bold {{ $tab === $key ? 'border-amber-300 bg-amber-100 text-amber-900' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                    <span>{{ $label }}</span>
                    <span class="inline-flex min-w-6 justify-center rounded-full bg-white/80 px-2 py-0.5 text-xs">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid gap-4">
        @forelse($items as $post)
            @php
                $postStatus = $post->status?->value ?? $post->status;
            @endphp
            <article class="overflow-hidden rounded-2xl border {{ $postStatus === 'escalated' ? 'border-rose-300 bg-rose-50/60' : 'border-gray-200 bg-white' }} shadow-sm">
                <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-community.post-type-badge :type="$post->post_type" />
                            <x-community.status-badge :status="$post->status" />
                            @if($post->reports_count > 0)
                                <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700">{{ $post->reports_count }} reports</span>
                            @endif
                        </div>
                        <h3 class="mt-3 text-lg font-bold text-gray-950">{{ $post->title }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $post->author?->name ?? 'Unknown author' }} · {{ $post->comments_count }} comments</p>
                        <p class="mt-3 text-sm leading-6 text-gray-700">{{ str($post->body)->limit(320) }}</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl bg-gray-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Reason</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800">{{ $post->reports->first()?->reason_label ?? 'Pre-screen or moderator review' }}</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Risk flags</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800">{{ collect($post->prescreen_flags ?? [])->filter()->keys()->implode(', ') ?: 'No automated flags' }}</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Last action</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800">{{ $post->moderationActions->first()?->action_type?->label() ?? 'Awaiting review' }}</p>
                            </div>
                        </div>
                    </div>

                    @php
                        $actions = match ($postStatus) {
                            'pending_review' => ['approve' => 'Approve', 'reject' => 'Reject', 'escalate' => 'Escalate'],
                            'published' => ['hide' => 'Hide', 'lock' => 'Lock', 'remove' => 'Remove', 'escalate' => 'Escalate'],
                            'locked' => ['unlock' => 'Unlock', 'hide' => 'Hide', 'remove' => 'Remove', 'escalate' => 'Escalate'],
                            'hidden' => ['restore' => 'Restore', 'remove' => 'Remove', 'escalate' => 'Escalate'],
                            'removed' => ['restore' => 'Restore', 'escalate' => 'Escalate'],
                            'escalated' => ['restore' => 'Restore', 'remove' => 'Remove'],
                            default => [],
                        };
                        $actions = array_filter(
                            $actions,
                            fn ($label, $action) => $moderationPermissions[$action] ?? false,
                            ARRAY_FILTER_USE_BOTH,
                        );
                    @endphp
                    <div class="w-full shrink-0 space-y-2 lg:w-56">
                        <a href="{{ route('connector.community.show', [$connector, $post]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-brand-200 bg-brand-50 px-4 text-sm font-bold text-brand-700 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">View post</a>
                        <details class="rounded-xl border border-amber-200 bg-amber-50/70">
                                <summary class="flex min-h-11 cursor-pointer items-center px-4 text-sm font-bold text-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-inset">Manage post</summary>
                                <div class="space-y-2 border-t border-amber-200 p-3">
                                    @if($actions === [])
                                        <p class="text-xs leading-5 text-gray-600">No actions are available for your role.</p>
                                    @else
                                        <p class="text-xs leading-5 text-gray-600">Each action is recorded in the connector moderation history.</p>
                                        @foreach($actions as $action => $label)
                                            <form method="POST" action="{{ route('connector.community.moderation.'.$action, [$connector, $post]) }}" data-confirm-submit data-confirm-title="{{ $label }} post?" data-confirm-text="Record this connector moderation action for this post." data-confirm-icon="warning" data-confirm-button="{{ $label }}">
                                                @csrf
                                                <input type="hidden" name="reason" value="Connector moderation action.">
                                                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border {{ $action === 'escalate' ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : ($action === 'remove' || $action === 'reject' ? 'border-amber-300 bg-amber-100 text-amber-900 hover:bg-amber-200' : 'border-gray-200 bg-white text-gray-800 hover:bg-gray-50') }} px-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </div>
                            </details>
                    </div>
                </div>
            </article>
        @empty
            <section class="rounded-2xl border border-dashed border-gray-200 bg-white p-10 text-center">
                <p class="text-lg font-bold text-gray-900">No items in this queue.</p>
                <p class="mt-2 text-sm text-gray-500">Moderation items will appear here when posts need review or safety action.</p>
            </section>
        @endforelse
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
