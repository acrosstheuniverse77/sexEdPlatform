@extends('layouts.admin')

@section('title', 'Community Hub')
@section('page-title', 'Community Hub')

@section('content')
@php
    $cards = [
        ['label' => 'All connector spaces', 'value' => $stats['spaces'] ?? 0, 'tone' => 'purple'],
        ['label' => 'Reported', 'value' => $stats['reported'] ?? 0, 'tone' => 'rose'],
        ['label' => 'Escalated', 'value' => $stats['escalated'] ?? 0, 'tone' => 'rose'],
        ['label' => 'Pending', 'value' => $stats['pending'] ?? 0, 'tone' => 'amber'],
        ['label' => 'Featured', 'value' => $stats['featured'] ?? 0, 'tone' => 'purple'],
    ];
@endphp

<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-brand-200/80 bg-white shadow-soft ring-1 ring-brand-200/40">
        <div class="bg-gradient-to-br from-brand-50 via-white to-brand-100/70 p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-purple-700">Platform moderation</p>
                <h2 class="mt-1 text-2xl font-bold text-gray-950">Community Hub</h2>
                <p class="mt-1 text-sm text-gray-600">All connector spaces, reported and escalated posts, featured content, and global safety controls.</p>
            </div>
            <a href="{{ route('admin.community.settings') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Global safety controls" aria-label="Global safety controls">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3.75 5.25 6.5v5.25c0 4.25 2.85 7.9 6.75 8.95 3.9-1.05 6.75-4.7 6.75-8.95V6.5L12 3.75Zm0 5.25v4m0 3.25h.01"/></svg>
            </a>
        </div>

        @if($isGloballyFrozen)
            <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">Emergency freeze is active. Connector posting and comments are paused globally.</div>
        @endif
        </div>
    </section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach($cards as $card)
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold {{ $card['tone'] === 'rose' ? 'text-rose-700' : ($card['tone'] === 'amber' ? 'text-amber-700' : 'text-purple-700') }}">{{ number_format($card['value']) }}</p>
            </section>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="font-bold text-gray-950">Community moderation stream</h3>
            <p class="mt-1 text-sm text-gray-500">Newest posts across connector spaces, with report and escalation context.</p>
        </div>
        <form method="GET" action="{{ route('admin.community.index') }}" class="grid gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4 md:grid-cols-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search posts" class="rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-400">
            <select name="type" class="rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-400">
                <option value="">All types</option>
                @foreach(\App\Enums\CommunityPostType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-400">
                <option value="">All statuses</option>
                @foreach(\App\Enums\CommunityPostStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-purple-700 text-white hover:bg-purple-800" title="Filter" aria-label="Filter">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M6.75 12h10.5M10.5 17.25h3"/></svg>
            </button>
        </form>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Post</th>
                    <th class="px-4 py-3">Connector</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Reports</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($posts as $post)
                    <tr class="transition hover:bg-brand-50/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.community.show', $post) }}" class="font-semibold text-purple-700 hover:text-purple-900">{{ $post->title }}</a>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <x-community.post-type-badge :type="$post->post_type" />
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $post->connector?->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3"><x-community.post-type-badge :type="$post->post_type" /></td>
                        <td class="px-4 py-3"><x-community.status-badge :status="$post->status" /></td>
                        <td class="px-4 py-3">{{ $post->reports->count() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.community.show', $post) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100" title="Review post" aria-label="Review post">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S3.732 16.057 2.458 12Z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500">No community posts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>{{ $posts->links() }}</div>
</div>
@endsection
