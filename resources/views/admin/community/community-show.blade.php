@extends('layouts.admin')

@section('title', $community->name.' · Community Details')
@section('page-title', 'Community Details')

@section('content')
<div class="space-y-8">
    @include('admin.community.partials.navigation')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.community.communities') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-brand-200 bg-white px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">← Communities</a>
        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ str($community->status)->headline() }}</span>
    </div>

    <section class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-7">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-brand-700">Community Details</p>
            <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><h1 class="text-3xl font-bold tracking-tight text-gray-950">{{ $community->name }}</h1><p class="mt-1 text-sm text-gray-600">{{ $community->connector?->name ?? 'Community workspace' }}</p></div>
                <div class="flex flex-wrap gap-2"><a href="{{ route('admin.community.communities.edit', $community) }}" class="inline-flex min-h-10 items-center rounded-2xl bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800">Edit Community</a><a href="{{ route('admin.community.communities.members', $community) }}" class="inline-flex min-h-10 items-center rounded-2xl border border-brand-200 bg-white px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">Manage Members</a></div>
            </div>
        </div>
        <div class="space-y-6 p-6">
            @include('admin.community.partials.community-tabs')
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['label' => 'Members', 'value' => $community->members_count],
                    ['label' => 'Posts', 'value' => $community->posts_count],
                    ['label' => 'Status', 'value' => str($community->status)->headline()],
                    ['label' => 'Created', 'value' => $community->created_at?->format('M d, Y')],
                ] as $metric)
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/50 p-4"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-700">{{ $metric['label'] }}</p><p class="mt-2 text-xl font-bold text-gray-950">{{ $metric['value'] }}</p></div>
                @endforeach
            </div>
            @if($community->status === 'active')
                <form method="POST" action="{{ route('admin.community.communities.deactivate', $community) }}" data-confirm-submit data-confirm-title="Deactivate this community?" data-confirm-text="Members will no longer be able to use this community. Existing posts and history will be preserved." data-confirm-icon="warning" data-confirm-button="Deactivate">
                    @csrf
                    <button class="inline-flex min-h-10 items-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100">Deactivate</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
