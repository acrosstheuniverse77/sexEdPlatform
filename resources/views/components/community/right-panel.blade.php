@props(['connector', 'upcomingSeminars' => collect()])

<aside {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-extrabold text-gray-950">Upcoming seminars</h3>
        <div class="mt-3 space-y-3">
            @foreach($upcomingSeminars as $seminar)
                @php $starts = $seminar->localStartsAt(); @endphp
                <a href="{{ route('connector.seminars.show', [$connector, $seminar]) }}" class="block rounded-xl border border-gray-100 bg-gray-50 p-3 transition hover:border-brand-200 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    <p class="line-clamp-2 text-sm font-bold text-gray-950">{{ $seminar->title }}</p>
                    <p class="mt-1 text-xs font-medium text-gray-500">{{ $starts?->format('M d, h:i A') ?? 'Schedule to be announced' }}</p>
                </a>
            @endforeach
        </div>
        <a href="{{ route('connector.seminars.index', $connector) }}" class="mt-4 inline-flex min-h-10 items-center text-sm font-bold text-brand-700 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">Browse all seminars</a>
    </section>
</aside>
