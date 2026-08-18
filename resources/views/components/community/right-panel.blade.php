@props(['connector', 'upcomingSeminars' => collect(), 'pendingCount' => 0, 'reportedCount' => 0, 'escalatedCount' => 0, 'canModerate' => false])

@php
    $memberCount = $connector->active_members_count ?? $connector->memberships()->where('status', 'active')->count();
    $safetyTotal = max(1, $pendingCount + $reportedCount + $escalatedCount);
@endphp

<aside {{ $attributes->merge(['class' => 'space-y-5']) }}>
    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[13px] font-bold uppercase tracking-[0.18em] text-emerald-700">Upcoming Events</p>
                <h3 class="text-[22px] font-extrabold text-gray-950">Seminars</h3>
                <span class="sr-only">Upcoming seminars</span>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($upcomingSeminars as $seminar)
                @php $starts = $seminar->localStartsAt(); @endphp
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="flex gap-3">
                        <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-2xl bg-white text-emerald-700 shadow-sm">
                            <span class="text-[11px] font-bold uppercase">{{ $starts?->format('M') ?? 'TBA' }}</span>
                            <span class="text-lg font-extrabold">{{ $starts?->format('d') ?? '--' }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-sm font-extrabold text-gray-950">{{ $seminar->title }}</p>
                            <p class="mt-1 text-[13px] text-gray-600">{{ $starts?->format('h:i A') ?? 'Time to be announced' }}</p>
                            <a href="{{ route('connector.seminars.show', [$connector, $seminar]) }}" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-2xl bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Join</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-200 p-5 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3.75v2.5M17 3.75v2.5M4.75 8.75h14.5M6.25 5.25h11.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H6.25a2 2 0 0 1-2-2V7.25a2 2 0 0 1 2-2Z"/></svg>
                    </div>
                    <p class="mt-3 text-sm font-bold text-gray-900">No upcoming seminars.</p>
                    <a href="{{ route('connector.community.index', [$connector, 'type' => 'resource']) }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-2xl border border-sky-200 bg-sky-50 px-4 text-sm font-bold text-sky-700 hover:bg-sky-100">Browse Resources</a>
                </div>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-[13px] font-bold uppercase tracking-[0.18em] text-gray-500">Connector Information</p>
        <h3 class="text-[22px] font-extrabold text-gray-950">Workspace</h3>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-500">Verification</dt>
                <dd class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[13px] font-extrabold text-emerald-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 12 2 2 4-4M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/></svg>
                    {{ str($connector->status)->headline() }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-500">Category</dt>
                <dd class="font-bold text-gray-900">{{ str($connector->category)->headline() }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-500">Members</dt>
                <dd class="font-bold text-gray-900">{{ number_format($memberCount) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-500">Active users</dt>
                <dd class="font-bold text-gray-900">{{ number_format($memberCount) }}</dd>
            </div>
        </dl>
    </section>

    @if($canModerate)
        <section class="rounded-3xl border border-rose-200 bg-white p-5 shadow-sm" x-data="{ safetyOpen: false }">
            <button type="button" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-2xl text-left" @click="safetyOpen = !safetyOpen" :aria-expanded="safetyOpen.toString()">
                <span>
                    <span class="block text-[13px] font-bold uppercase tracking-[0.18em] text-rose-700">Safety Center</span>
                    <span class="block text-[22px] font-extrabold text-gray-950">Moderation health</span>
                    <span class="sr-only">Safety center</span>
                </span>
                <svg class="h-5 w-5 text-rose-700 transition" :class="safetyOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
            </button>

            <div x-show="safetyOpen" x-cloak class="mt-4 space-y-4">
                @foreach([
                    ['label' => 'Pending', 'value' => $pendingCount, 'class' => 'bg-amber-500'],
                    ['label' => 'Reported', 'value' => $reportedCount, 'class' => 'bg-rose-500'],
                    ['label' => 'Escalated', 'value' => $escalatedCount, 'class' => 'bg-red-600'],
                ] as $item)
                    @php $width = max(8, min(100, (int) round(($item['value'] / $safetyTotal) * 100))); @endphp
                    <div>
                        <div class="flex items-center justify-between text-[13px] font-bold">
                            <span class="text-gray-600">{{ $item['label'] }}</span>
                            <span class="text-gray-950">{{ $item['value'] }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $item['class'] }}" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</aside>
