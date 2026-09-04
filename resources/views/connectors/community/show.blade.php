@extends('layouts.connector-app')

@section('title', $post->title)
@section('page-title', 'Community Post')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <x-community.post-card :connector="$connector" :post="$post" :can-moderate="$canModerate" />

    <section id="comments" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-bold text-gray-900">Comments {{ $post->visible_comments_count ?? 0 }}</h3>
            @if($post->isLocked())
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Thread locked</span>
            @endif
        </div>

        @if($canComment)
            <form method="POST" action="{{ route('connector.community.comments.store', [$connector, $post]) }}" class="mt-5 space-y-2 border-b border-gray-100 pb-5">
                @csrf
                <label class="sr-only" for="comment-body">Comment</label>
                <textarea id="comment-body" name="body" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Add a comment..." required>{{ old('body') }}</textarea>
                @error('body')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                <button class="inline-flex min-h-11 items-center rounded-xl bg-brand-700 px-4 text-sm font-bold text-white hover:bg-brand-800">Comment</button>
            </form>
        @endif

        <div class="mt-5 space-y-4">
            @forelse($post->topLevelComments as $comment)
                <div>
                    <x-community.comment
                        :connector="$connector"
                        :post="$post"
                        :comment="$comment"
                        :can-comment="$canComment"
                        :can-audit="$canAuditComments"
                    />

                    @if($comment->replies->isNotEmpty())
                        <div class="ml-5 mt-3 space-y-3 border-l-2 border-brand-100 pl-4 sm:ml-8">
                            @foreach($comment->replies as $reply)
                                <x-community.comment
                                    :connector="$connector"
                                    :post="$post"
                                    :comment="$reply"
                                    :can-comment="$canComment"
                                    :can-audit="$canAuditComments"
                                    is-reply
                                />
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 p-6 text-center">
                    <p class="text-sm font-semibold text-gray-700">No comments yet.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
