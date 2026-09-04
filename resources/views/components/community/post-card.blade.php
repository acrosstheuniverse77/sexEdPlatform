@props(['connector', 'post', 'canModerate' => false])

@php
    $typeValue = $post->post_type?->value ?? (string) $post->post_type;
    $typeLabel = $post->post_type?->label() ?? str($typeValue)->headline();
    $statusValue = $post->status?->value ?? (string) $post->status;
    $showStatus = $canModerate && ! in_array($statusValue, ['published'], true);
    $upvoteCount = $post->upvotes_count ?? $post->upvotes?->count() ?? 0;
    $hasUpvoted = $post->upvotes?->contains('user_id', auth()->id()) ?? false;
    $commentCount = $post->visible_comments_count ?? $post->comments_count ?? $post->comments?->count() ?? 0;
    $canPin = $canModerate && in_array($statusValue, ['published', 'locked'], true);
@endphp

<article {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-brand-100 hover:shadow-md']) }}>
    <div class="p-5">
        <div class="min-w-0">
            <header class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-gray-500">
                        @if($post->isFeatured())
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-1 text-brand-700">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 5-4 4-4-4m4 4v10"/></svg>
                                Pinned
                            </span>
                        @endif
                        <span>{{ $typeLabel }}</span>
                        @if($post->topic)
                            <span aria-hidden="true">/</span>
                            <span>{{ $post->topic }}</span>
                        @endif
                        @if($showStatus)
                            <span aria-hidden="true">/</span>
                            <x-community.status-badge :status="$post->status" />
                        @endif
                    </div>
                    <x-community.author class="mt-3" :user="$post->author" :connector="$connector" :timestamp="$post->created_at" />
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if($canPin)
                        <form method="POST" action="{{ route('connector.community.posts.pin', [$connector, $post]) }}" data-confirm-submit data-confirm-title="{{ $post->isFeatured() ? 'Unpin post?' : 'Pin post?' }}" data-confirm-text="Update this post's pinned state for the Community Hub." data-confirm-icon="warning" data-confirm-button="{{ $post->isFeatured() ? 'Unpin' : 'Pin' }}">
                            @csrf
                            @if($post->isFeatured())
                                @method('DELETE')
                            @endif
                            <button class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 text-gray-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2" title="{{ $post->isFeatured() ? 'Unpin post' : 'Pin post' }}" aria-label="{{ $post->isFeatured() ? 'Unpin post' : 'Pin post' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 5-4 4-4-4m4 4v10"/></svg>
                            </button>
                        </form>
                    @endif
                </div>
            </header>

            <a href="{{ route('connector.community.show', [$connector, $post]) }}" class="mt-3 block text-lg font-extrabold leading-snug text-gray-950 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">{{ $post->title }}</a>
            <p class="mt-2 text-sm leading-6 text-gray-700">{{ str($post->body)->limit(300) }}</p>
            <x-community.media-gallery :connector="$connector" :post="$post" />

            <footer class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3 text-sm font-bold text-gray-500">
                <form method="POST" action="{{ route('connector.community.posts.upvote', [$connector, $post]) }}" data-community-upvote-form data-testid="community-post-upvote" class="shrink-0">
                    @csrf
                    <button class="flex min-h-11 w-12 flex-col items-center justify-center rounded-xl border text-xs font-extrabold transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 {{ $hasUpvoted ? 'border-brand-300 bg-brand-100 text-brand-800' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' }}" aria-label="Upvote post" aria-pressed="{{ $hasUpvoted ? 'true' : 'false' }}" data-community-upvote-button data-active="{{ $hasUpvoted ? 'true' : 'false' }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        <span data-community-upvote-count>{{ $upvoteCount }}</span>
                    </button>
                </form>
                <a href="{{ route('connector.community.show', [$connector, $post]) }}#comments" class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-gray-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2" title="Open comments" aria-label="Open comments">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z"/></svg>
                    {{ $commentCount }}
                </a>
                <x-community.report-form :connector="$connector" :post="$post" button-label="" button-title="Report post" />
            </footer>
        </div>
    </div>
</article>

@once
    <script>
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-community-upvote-form]');
            if (!form || !window.fetch) return;
            event.preventDefault();
            const button = form.querySelector('[data-community-upvote-button]');
            const count = form.querySelector('[data-community-upvote-count]');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    },
                });
                if (!response.ok) {
                    form.submit();
                    return;
                }
                const result = await response.json();
                count.textContent = result.count;
                button.dataset.active = result.active ? 'true' : 'false';
                button.setAttribute('aria-pressed', result.active ? 'true' : 'false');
                button.classList.toggle('border-brand-300', result.active);
                button.classList.toggle('bg-brand-100', result.active);
                button.classList.toggle('text-brand-800', result.active);
            } catch (error) {
                form.submit();
            }
        });
    </script>
@endonce
