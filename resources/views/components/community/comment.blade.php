@props([
    'connector',
    'post',
    'comment',
    'canComment' => false,
    'canAudit' => false,
    'isReply' => false,
])

@php
    $upvoteCount = $comment->upvotes_count ?? $comment->upvotes?->count() ?? 0;
    $hasUpvoted = $comment->upvotes?->contains('user_id', auth()->id()) ?? false;
    $statusValue = $comment->status?->value ?? (string) $comment->status;
    $isVisible = $statusValue === \App\Enums\CommunityCommentStatus::Visible->value;
    $canReply = ! $isReply && $canComment && $isVisible;
    $statusLabel = $canAudit ? ($comment->status?->label() ?? str($statusValue)->headline()) : null;
@endphp

<article
    data-testid="{{ $isReply ? 'community-comment-reply' : 'community-comment-root' }}"
    class="rounded-xl border {{ $isReply ? 'border-gray-200 bg-white p-3' : 'border-gray-100 bg-gray-50 p-4' }}"
>
    <div class="flex gap-3">
        @if($isVisible)
            <form method="POST" action="{{ route('connector.community.comments.upvote', [$connector, $post, $comment]) }}" data-community-upvote-form class="shrink-0">
                @csrf
                <button class="flex min-h-11 w-11 flex-col items-center justify-center rounded-xl border text-xs font-extrabold transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 {{ $hasUpvoted ? 'border-brand-300 bg-brand-100 text-brand-800' : 'border-gray-200 bg-white text-gray-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' }}" aria-label="Upvote {{ $isReply ? 'reply' : 'comment' }}" aria-pressed="{{ $hasUpvoted ? 'true' : 'false' }}" data-community-upvote-button data-active="{{ $hasUpvoted ? 'true' : 'false' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <span data-community-upvote-count>{{ $upvoteCount }}</span>
                </button>
            </form>
        @else
            <div class="flex min-h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl border border-gray-200 bg-gray-100 text-xs font-extrabold text-gray-400" aria-label="{{ $upvoteCount }} upvotes">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                <span>{{ $upvoteCount }}</span>
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <x-community.author
                    :user="$comment->author"
                    :connector="$connector"
                    :timestamp="$comment->created_at"
                    :status="$statusLabel"
                    compact
                />
                <x-community.report-form :connector="$connector" :post="$post" :comment="$comment" button-label="" button-class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500" button-title="Report {{ $isReply ? 'reply' : 'comment' }}" />
            </div>

            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $comment->body }}</p>

            @if($canReply)
                <div x-data="{ open: false }" data-reply-for="{{ $comment->id }}" class="mt-3">
                    <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.body.focus())" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-xs font-bold text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500" :aria-expanded="open.toString()" aria-controls="reply-form-{{ $comment->id }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14 4 9m0 0 5-5M4 9h10a6 6 0 0 1 6 6v1"/></svg>
                        Reply
                    </button>
                    <form id="reply-form-{{ $comment->id }}" x-show="open" x-cloak method="POST" action="{{ route('connector.community.comments.store', [$connector, $post]) }}" class="mt-2 space-y-2 rounded-xl border border-brand-100 bg-white p-3">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <label class="sr-only" for="reply-body-{{ $comment->id }}">Reply to {{ $comment->author?->name ?? 'member' }}</label>
                        <textarea x-ref="body" id="reply-body-{{ $comment->id }}" name="body" rows="2" maxlength="2000" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Write a public reply..." required></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="inline-flex min-h-11 items-center rounded-lg px-3 text-xs font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                            <button class="inline-flex min-h-11 items-center rounded-lg bg-brand-700 px-3 text-xs font-bold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">Post reply</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</article>
