@extends('layouts.admin')

@section('title', 'Community Hub')
@section('page-title', 'Community Hub')

@section('content')
    <div class="space-y-8">
        <div class="flex justify-end">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.community.settings') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-brand-200 bg-white px-4 py-2 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50">Safety Controls</a>
                <a href="{{ route('admin.community.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50" title="Refresh overview">Refresh</a>
            </div>
        </div>

        @include('admin.community.partials.navigation')

        @if($isGloballyFrozen)
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">Emergency freeze is active. Community posting and comments are paused globally.</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['label' => 'Active Communities', 'value' => $stats['spaces'] ?? 0, 'tone' => 'from-brand-500 via-brand-700 to-brand-900', 'shadow' => 'shadow-brand-200', 'icon' => 'M4.5 6.75h15m-13.5 0v10.5A2.25 2.25 0 0 0 8.25 19.5h7.5A2.25 2.25 0 0 0 18 17.25V6.75M9 10.5h6m-6 3h4.5'],
                ['label' => 'Pending Review', 'value' => $stats['pending'] ?? 0, 'tone' => 'from-amber-400 via-amber-600 to-orange-700', 'shadow' => 'shadow-amber-200', 'icon' => 'M12 6v6l3 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ['label' => 'Reports', 'value' => $stats['reported'] ?? 0, 'tone' => 'from-rose-500 via-rose-700 to-red-900', 'shadow' => 'shadow-rose-200', 'icon' => 'M12 9v3.75m0 3h.008v.008H12V15.75ZM4.5 19.5h15a1.5 1.5 0 0 0 1.3-2.25l-7.5-13a1.5 1.5 0 0 0-2.6 0l-7.5 13a1.5 1.5 0 0 0 1.3 2.25Z'],
                ['label' => 'Published Posts', 'value' => $stats['published'] ?? 0, 'tone' => 'from-purple-500 via-purple-700 to-indigo-900', 'shadow' => 'shadow-purple-200', 'icon' => 'M5.25 12.75 9 16.5l9.75-9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ] as $metric)
                <div class="min-h-[116px] rounded-[28px] border border-brand-200 bg-gradient-to-br from-white via-brand-50/70 to-brand-100/70 p-5 shadow-theme-xs">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-700">{{ $metric['label'] }}</p>
                            <p class="mt-2 text-4xl font-bold leading-none text-gray-900">{{ number_format($metric['value']) }}</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg {{ $metric['tone'] }} {{ $metric['shadow'] }}">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $metric['icon'] }}" /></svg>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <section aria-labelledby="communities" class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6">
                <div class="flex items-end justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-700">Community activity</p><h2 id="communities" class="mt-2 text-xl font-bold text-gray-900">Communities</h2><p class="mt-1 text-sm text-gray-500">Activity at a glance across active communities.</p></div><a href="{{ route('admin.community.communities') }}" class="text-sm font-bold text-brand-700 hover:text-brand-900">View all →</a></div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-white text-left text-xs font-semibold uppercase tracking-[0.2em] text-gray-500"><tr><th class="px-6 py-4">Community</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Members</th><th class="px-6 py-4">Posts</th><th class="px-6 py-4 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($communities as $community)
                            <tr class="transition hover:bg-brand-50/40"><td class="px-6 py-4"><p class="font-semibold text-gray-950">{{ $community->name }}</p><p class="mt-1 text-xs text-gray-500">{{ $community->connector?->name ?? 'Community workspace' }}</p></td><td class="px-6 py-4"><span class="font-semibold text-emerald-700">{{ str($community->status)->headline() }}</span></td><td class="px-6 py-4 text-gray-600">{{ number_format($community->connector?->memberships->count() ?? 0) }}</td><td class="px-6 py-4 text-gray-600">{{ number_format($community->posts_count) }}</td><td class="px-6 py-4 text-right"><a href="{{ route('admin.community.communities.show', $community) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-200 text-brand-700 transition hover:bg-brand-50" title="View community" aria-label="View {{ $community->name }}"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="2.25"/></svg></a></td></tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No active communities are available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="recent-activity" class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6"><p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-700">Moderation stream</p><h2 id="recent-activity" class="mt-2 text-xl font-bold text-gray-900">Recent Activity</h2><p class="mt-1 text-sm text-gray-500">Published posts and moderation activity in one searchable view.</p></div>
            <form method="GET" action="{{ route('admin.community.index') }}" class="grid gap-3 border-b border-brand-100 bg-white px-6 py-5 md:grid-cols-5">
                <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search posts..." class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Type</span><select name="type" aria-label="Filter by type" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"><option value="">All types</option>@foreach(\App\Enums\CommunityPostType::cases() as $type)<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span><select name="status" aria-label="Filter by status" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"><option value="">All statuses</option>@foreach(collect(\App\Enums\CommunityPostStatus::cases())->reject(fn ($status) => $status->value === 'escalated') as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Community</span><select name="connector_id" aria-label="Filter by community" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"><option value="">All communities</option>@foreach($communities as $community)<option value="{{ $community->connector_id }}" @selected((string) request('connector_id') === (string) $community->connector_id)>{{ $community->name }}</option>@endforeach</select></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Date</span><select name="date" aria-label="Filter by date" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"><option value="">Any date</option><option value="1" @selected(request('date') === '1')>Today</option><option value="7" @selected(request('date') === '7')>Last 7 days</option><option value="30" @selected(request('date') === '30')>Last 30 days</option><option value="365" @selected(request('date') === '365')>Last year</option></select></label>
                <div class="flex items-end gap-2 md:col-span-5"><button class="inline-flex min-h-10 items-center rounded-2xl bg-brand-700 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800">Apply filters</button><a href="{{ route('admin.community.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-brand-200 px-5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">Reset Filters</a></div>
            </form>
            @include('admin.community.partials.activity-table', ['posts' => $posts])
        </section>

        <div>{{ $posts->links() }}</div>
    </div>
@endsection
