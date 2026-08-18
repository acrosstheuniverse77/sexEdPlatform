@props(['connector', 'post'])

@php
    $counts = $post->reactions->groupBy(fn ($item) => $item->reaction_type?->value ?? $item->reaction_type)->map->count();
    $activeReactions = $post->reactions
        ->where('user_id', auth()->id())
        ->map(fn ($item) => $item->reaction_type?->value ?? $item->reaction_type)
        ->all();
    $icons = [
        'support' => 'M6.633 10.25c.806 0 1.533-.446 1.901-1.151L10.5 5.25A2.25 2.25 0 0 1 14.75 6.75v2h3.333a2 2 0 0 1 1.96 2.392l-1.2 6A2 2 0 0 1 16.883 18.75H8.25a2 2 0 0 1-2-2v-6.5h.383ZM3.75 10.25h2.5v8.5h-2.5v-8.5Z',
        'helpful' => 'm9 12 2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
        'learned' => 'M12 3.75 4.5 7.5 12 11.25l7.5-3.75L12 3.75Zm-6 6.75v4.75c0 1.55 2.7 3.5 6 3.5s6-1.95 6-3.5V10.5',
        'question' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.33-.671.455-.747.387-1.45.963-1.45 1.814v.75M12 18h.01',
        'bookmark' => 'M6.75 4.5h10.5v15L12 16.5l-5.25 3V4.5Z',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    @foreach(\App\Enums\CommunityReactionType::cases() as $reaction)
        @php $isActiveReaction = in_array($reaction->value, $activeReactions, true); @endphp
        <form method="POST" action="{{ route('connector.community.reactions.store', [$connector, $post]) }}" data-community-reaction-form>
            @csrf
            <input type="hidden" name="reaction_type" value="{{ $reaction->value }}">
            <button class="inline-flex min-h-11 items-center gap-2 rounded-2xl border px-3 text-[13px] font-extrabold transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 {{ $isActiveReaction ? 'border-brand-300 bg-brand-100 text-brand-800 shadow-sm ring-1 ring-brand-200' : 'border-gray-200 bg-white text-gray-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' }}" title="{{ $reaction->label() }}" aria-label="{{ $reaction->label() }}" aria-pressed="{{ $isActiveReaction ? 'true' : 'false' }}" data-community-reaction-button data-active="{{ $isActiveReaction ? 'true' : 'false' }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$reaction->value] ?? $icons['support'] }}"/></svg>
                <span>{{ $reaction->label() }}</span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs" data-community-reaction-count>{{ $counts->get($reaction->value, 0) }}</span>
            </button>
        </form>
    @endforeach
</div>
