@props(['connector', 'canCreatePost' => false])

<section {{ $attributes->merge(['class' => 'rounded-3xl border border-gray-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-100 text-sm font-extrabold text-brand-700">
            {{ str(auth()->user()?->name ?? 'U')->substr(0, 1)->upper() }}
        </div>
        @if($canCreatePost)
            <a href="{{ route('connector.community.create', $connector) }}" class="flex min-h-11 flex-1 items-center rounded-2xl border border-brand-100 bg-brand-50/70 px-4 text-left text-sm font-extrabold text-brand-700 transition hover:-translate-y-0.5 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                Create structured post
            </a>
        @else
            <div class="flex min-h-11 flex-1 items-center rounded-2xl border border-gray-200 bg-gray-50 px-4 text-sm font-semibold text-gray-500">
                You can read published connector updates. Posting is limited to roles with community posting access.
            </div>
        @endif
    </div>
</section>
