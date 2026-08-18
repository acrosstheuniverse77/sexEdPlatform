@props(['connector', 'post', 'canModerate' => false, 'isPinned' => false])

@php
    $authorName = $post->author?->name ?? 'Unknown author';
    $initial = str($authorName)->substr(0, 1)->upper();
    $reactionCounts = $post->reactions->groupBy(fn ($reaction) => $reaction->reaction_type?->value ?? $reaction->reaction_type)->map->count();
    $activeReactions = $post->reactions
        ->where('user_id', auth()->id())
        ->map(fn ($reaction) => $reaction->reaction_type?->value ?? $reaction->reaction_type)
        ->all();
    $typeValue = $post->post_type?->value ?? (string) $post->post_type;
    $typePanel = [
        'announcement' => ['class' => 'from-purple-50 to-white text-purple-700 border-purple-100', 'path' => 'M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z'],
        'event' => ['class' => 'from-emerald-50 to-white text-emerald-700 border-emerald-100', 'path' => 'M7 3.75v2.5M17 3.75v2.5M4.75 8.75h14.5M6.25 5.25h11.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H6.25a2 2 0 0 1-2-2V7.25a2 2 0 0 1 2-2Z'],
        'resource' => ['class' => 'from-sky-50 to-white text-sky-700 border-sky-100', 'path' => 'M6.75 4.5h7.5L18 8.25v11.25H6.75V4.5Zm7.5 0v3.75H18M9 12h6M9 15h6'],
        'moderated_question' => ['class' => 'from-orange-50 to-white text-orange-700 border-orange-100', 'path' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.33-.671.455-.747.387-1.45.963-1.45 1.814v.75M12 18h.01'],
        'discussion_prompt' => ['class' => 'from-gray-50 to-white text-gray-700 border-gray-100', 'path' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.142-4.03 7.5-9 7.5a10.6 10.6 0 0 1-3.29-.514L3 20.25l1.46-4.052A6.872 6.872 0 0 1 3 12c0-4.142 4.03-7.5 9-7.5s9 3.358 9 7.5Z'],
    ][$typeValue] ?? ['class' => 'from-gray-50 to-white text-gray-700 border-gray-100', 'path' => 'M7.5 8.25h9m-9 3.75h6'];

    $reactionButtons = [
        'support' => ['label' => 'Support', 'count' => $reactionCounts->get('support', 0), 'path' => 'M6.633 10.25c.806 0 1.533-.446 1.901-1.151L10.5 5.25A2.25 2.25 0 0 1 14.75 6.75v2h3.333a2 2 0 0 1 1.96 2.392l-1.2 6A2 2 0 0 1 16.883 18.75H8.25a2 2 0 0 1-2-2v-6.5h.383ZM3.75 10.25h2.5v8.5h-2.5v-8.5Z'],
        'helpful' => ['label' => 'Helpful', 'count' => $reactionCounts->get('helpful', 0), 'path' => 'm9 12 2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z'],
        'learned' => ['label' => 'Learned', 'count' => $reactionCounts->get('learned', 0), 'path' => 'M12 3.75 4.5 7.5 12 11.25l7.5-3.75L12 3.75Zm-6 6.75v4.75c0 1.55 2.7 3.5 6 3.5s6-1.95 6-3.5V10.5'],
        'question' => ['label' => 'Question', 'count' => $reactionCounts->get('question', 0), 'path' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.33-.671.455-.747.387-1.45.963-1.45 1.814v.75M12 18h.01'],
        'bookmark' => ['label' => 'Bookmark', 'count' => $reactionCounts->get('bookmark', 0), 'path' => 'M6.75 4.5h10.5v15L12 16.5l-5.25 3V4.5Z'],
    ];
    $resourceUrl = $post->resource_url ? trim((string) $post->resource_url) : null;
    $imageUrl = $resourceUrl && preg_match('/\.(?:avif|gif|jpe?g|png|svg|webp)(?:\?.*)?$/i', $resourceUrl) ? $resourceUrl : null;
@endphp

<article {{ $attributes->merge(['class' => 'group overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-medium']) }}>
    @if($isPinned)
        <div class="flex items-center gap-2 border-b border-brand-100 bg-brand-50 px-5 py-3 text-[13px] font-extrabold uppercase tracking-wide text-brand-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3 2.1 5.6 5.9.3-4.6 3.7 1.5 5.7L12 15.2l-4.9 3.1 1.5-5.7L4 8.9l5.9-.3L12 3Z"/></svg>
            <span>Featured for members</span>
        </div>
    @endif

    <div class="p-6">
        <header class="flex items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-base font-extrabold text-brand-700">
                    {{ $initial }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-extrabold text-gray-950">{{ $connector->name }}</p>
                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-extrabold text-emerald-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 12 2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/></svg>
                            Verified
                        </span>
                    </div>
                    <p class="mt-1 text-[13px] font-medium text-gray-500">{{ $authorName }} · Connector member · {{ $post->created_at?->diffForHumans() }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-community.post-type-badge :type="$post->post_type" />
                        <x-community.status-badge :status="$post->status" />
                    </div>
                </div>
            </div>

        </header>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_170px]">
            <div class="min-w-0">
                <h3 class="text-[20px] font-extrabold leading-snug text-gray-950">{{ $post->title }}</h3>
                <p class="mt-3 text-base leading-7 text-gray-700">{{ str($post->body)->limit(300) }}</p>
                <a href="{{ route('connector.community.show', [$connector, $post]) }}" class="mt-3 inline-flex text-sm font-extrabold text-brand-700 hover:text-brand-900">Read More</a>
            </div>

            @if($imageUrl)
                <a href="{{ route('connector.community.show', [$connector, $post]) }}" class="hidden min-h-36 overflow-hidden rounded-3xl border border-gray-200 bg-gray-100 transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 lg:block" title="Review post" aria-label="Review post">
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="h-full min-h-36 w-full object-cover">
                </a>
            @else
                <div class="hidden min-h-36 items-center justify-center rounded-3xl border bg-gradient-to-br p-5 lg:flex {{ $typePanel['class'] }}">
                    <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $typePanel['path'] }}"/></svg>
                </div>
            @endif
        </div>

        <footer class="mt-6 flex flex-col gap-4 border-t border-gray-100 pt-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                @foreach($reactionButtons as $type => $button)
                    @php $isActiveReaction = in_array($type, $activeReactions, true); @endphp
                    <form method="POST" action="{{ route('connector.community.reactions.store', [$connector, $post]) }}" data-community-reaction-form>
                        @csrf
                        <input type="hidden" name="reaction_type" value="{{ $type }}">
                        <button class="inline-flex min-h-11 items-center gap-2 rounded-2xl border px-3 text-[13px] font-extrabold transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 {{ $isActiveReaction ? 'border-brand-300 bg-brand-100 text-brand-800 shadow-sm ring-1 ring-brand-200' : 'border-gray-200 bg-white text-gray-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' }}" title="{{ $button['label'] }}" aria-label="{{ $button['label'] }}" aria-pressed="{{ $isActiveReaction ? 'true' : 'false' }}" data-community-reaction-button data-active="{{ $isActiveReaction ? 'true' : 'false' }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $button['path'] }}"/></svg>
                            <span data-community-reaction-count>{{ $button['count'] }}</span>
                        </button>
                    </form>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3 text-[13px] font-bold text-gray-500">
                <a href="{{ route('connector.community.show', [$connector, $post]) }}#comments" class="inline-flex items-center gap-1 rounded-lg px-1.5 py-1 text-gray-500 transition hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2" title="Open comments" aria-label="Open comments">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z"/></svg>
                    {{ $post->comments_count ?? $post->comments->count() }}
                </a>
                <span class="inline-flex items-center gap-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S3.732 16.057 2.458 12Z"/></svg>
                    0
                </span>
                <x-community.report-form
                    :connector="$connector"
                    :post="$post"
                    button-label=""
                    button-title="Report safety concern"
                />
                @if($canModerate)
                    <a href="{{ route('connector.community.moderation.index', [$connector, 'focus' => $post->id]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 text-amber-800 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2" title="Open moderation" aria-label="Open moderation">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3.75 5.25 6.5v5.25c0 4.25 2.85 7.9 6.75 8.95 3.9-1.05 6.75-4.7 6.75-8.95V6.5L12 3.75Zm-2.25 8.5 1.75 1.75 3.25-4"/></svg>
                    </a>
                @endif
            </div>
        </footer>
    </div>
</article>
