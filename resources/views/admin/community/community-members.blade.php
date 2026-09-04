@extends('layouts.admin')

@section('title', $community->name.' · Members')
@section('page-title', 'Community Members')

@section('content')
<div class="space-y-8">
    @include('admin.community.partials.navigation')
    <section class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6"><a href="{{ route('admin.community.communities.show', $community) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">← {{ $community->name }}</a><h1 class="mt-2 text-2xl font-bold text-gray-950">Community Members</h1><p class="mt-1 text-sm text-gray-600">Manage active connector members participating in this community.</p></div>
        <div class="space-y-5 p-6">
            @include('admin.community.partials.community-tabs')
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Member</th><th class="px-4 py-3">Joined</th><th class="px-4 py-3">Posts</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead><tbody class="divide-y divide-gray-100">
                @forelse($memberships as $membership)
                    <tr class="hover:bg-brand-50/40"><td class="px-4 py-4"><p class="font-semibold text-gray-950">{{ $membership->user?->name ?? 'Unknown member' }}</p><p class="mt-1 text-xs text-gray-500">{{ $membership->user?->email ?? 'No email' }}</p></td><td class="px-4 py-4 text-gray-600">{{ $membership->accepted_at?->format('M d, Y') ?? 'Pending' }}</td><td class="px-4 py-4 text-gray-600">{{ number_format($membership->posts_count) }}</td><td class="px-4 py-4"><span class="font-semibold text-emerald-700">{{ str($membership->status)->headline() }}</span></td><td class="px-4 py-4 text-right"><a href="{{ route('admin.community.communities.members', $community) }}#member-{{ $membership->id }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-200 text-brand-700 hover:bg-brand-50" title="View member" aria-label="View {{ $membership->user?->name ?? 'member' }}"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="2.25"/></svg></a></td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">No active members are available.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </section>
</div>
@endsection
