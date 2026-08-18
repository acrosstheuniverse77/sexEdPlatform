@extends('layouts.connector-app')

@section('title', $post->title)
@section('page-title', 'Community Post')

@section('content')
@php
    $moderationActions = [
        'approve' => ['label' => 'Approve', 'reason' => 'Connector moderation action.', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100', 'path' => 'M5 13l4 4L19 7'],
        'hide' => ['label' => 'Hide', 'reason' => 'Connector moderation action.', 'class' => 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50', 'path' => 'M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c1.618 0 3.15-.365 4.519-1.017M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 12.544 12.544M18.772 18.772 21 21'],
        'lock' => ['label' => 'Lock comments', 'reason' => 'Connector moderation action.', 'class' => 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100', 'path' => 'M16.5 10.5V7.5a4.5 4.5 0 0 0-9 0v3m-.75 0h10.5A1.75 1.75 0 0 1 19 12.25v5.5a1.75 1.75 0 0 1-1.75 1.75H6.75A1.75 1.75 0 0 1 5 17.75v-5.5a1.75 1.75 0 0 1 1.75-1.75Z'],
        'unlock' => ['label' => 'Unlock comments', 'reason' => 'Connector moderation action.', 'class' => 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100', 'path' => 'M8.25 10.5V7.75a3.75 3.75 0 0 1 7.08-1.72M6.75 10.5h10.5A1.75 1.75 0 0 1 19 12.25v5.5a1.75 1.75 0 0 1-1.75 1.75H6.75A1.75 1.75 0 0 1 5 17.75v-5.5a1.75 1.75 0 0 1 1.75-1.75Z'],
        'restore' => ['label' => 'Restore', 'reason' => 'Connector moderation action.', 'class' => 'border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100', 'path' => 'M9 15 4.5 10.5 9 6m-4.5 4.5h10.75a4.25 4.25 0 0 1 0 8.5H12'],
        'escalate' => ['label' => 'Escalate', 'reason' => 'Connector moderation action.', 'class' => 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100', 'path' => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'],
    ];
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <x-community.post-card :connector="$connector" :post="$post" :can-moderate="$canModerate" />

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <section id="comments" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Comments</h3>
                    <p class="mt-1 text-sm text-gray-500">Flat comments only. No private messaging or nested replies in V1.</p>
                </div>
                @if($post->isLocked())
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Thread locked</span>
                @endif
            </div>

            <div class="mt-5 space-y-4">
                @forelse($post->comments as $comment)
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ $comment->author?->name ?? 'Unknown' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $comment->created_at?->diffForHumans() }} · {{ $comment->status?->label() ?? str($comment->status)->headline() }}</p>
                            </div>
                            <x-community.report-form
                                :connector="$connector"
                                :post="$post"
                                :comment="$comment"
                                button-label="Report comment"
                                button-class="text-xs font-bold text-rose-700 hover:text-rose-900"
                                button-title="Report comment"
                            />
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-700">{{ $comment->body }}</p>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 p-6 text-center">
                        <p class="text-sm font-semibold text-gray-700">No comments yet.</p>
                        <p class="mt-1 text-xs text-gray-500">Comments should stay educational, public, and free of contact solicitation.</p>
                    </div>
                @endforelse
            </div>

            @if($canComment)
                <form method="POST" action="{{ route('connector.community.comments.store', [$connector, $post]) }}" class="mt-5 space-y-3 border-t border-gray-100 pt-5">
                    @csrf
                    <x-community.safety-reminder class="text-xs" />
                    <textarea name="body" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Add a public educational comment..." required></textarea>
                    <button class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-bold text-white hover:bg-purple-800">Comment</button>
                </form>
            @endif
        </section>

        <aside class="space-y-4">
            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <h3 class="font-bold text-gray-900">Post safety</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Status</dt>
                        <dd><x-community.status-badge :status="$post->status" /></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Reports</dt>
                        <dd class="font-bold text-gray-900">{{ $post->reports->count() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Comments</dt>
                        <dd class="font-bold text-gray-900">{{ $post->comments->count() }}</dd>
                    </div>
                </dl>
            </section>

            @if($canModerate)
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                    <h3 class="font-bold text-amber-950">Moderation controls</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($moderationActions as $action => $meta)
                            <form method="POST" action="{{ route('connector.community.moderation.'.$action, [$connector, $post]) }}" data-confirm-submit data-confirm-title="{{ $meta['label'] }}?" data-confirm-text="Record this connector moderation action for this post." data-confirm-icon="warning" data-confirm-button="{{ $meta['label'] }}">
                                @csrf
                                <input type="hidden" name="reason" value="{{ $meta['reason'] }}">
                                <button class="inline-flex h-11 w-11 items-center justify-center rounded-xl border {{ $meta['class'] }} focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2" title="{{ $meta['label'] }}" aria-label="{{ $meta['label'] }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['path'] }}"/></svg>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
