@extends('layouts.admin')

@section('title', 'Community Post Review')
@section('page-title', 'Community Post Review')

@section('content')
<div class="grid gap-6 lg:grid-cols-[1fr_340px]">
    <article class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-purple-700">{{ $post->connector?->name ?? 'Connector' }}</p>
                <h2 class="mt-1 text-2xl font-bold text-gray-950">{{ $post->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">By {{ $post->author?->name ?? 'Unknown' }} · {{ $post->created_at?->format('M d, Y g:i A') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-community.post-type-badge :type="$post->post_type" />
                <x-community.status-badge :status="$post->status" />
            </div>
        </div>

        <div class="prose mt-5 max-w-none text-gray-800">{!! nl2br(e($post->body)) !!}</div>
        @if($post->resource_url)
            <a href="{{ $post->resource_url }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-purple-700 hover:bg-gray-100" title="Open resource" aria-label="Open resource">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H18v4.5M18 6l-7.5 7.5M6.75 4.5h5.25M6 19.5h12A1.5 1.5 0 0 0 19.5 18v-5.25M4.5 6v12A1.5 1.5 0 0 0 6 19.5"/></svg>
            </a>
        @endif

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Reports</p>
                <p class="mt-1 text-2xl font-bold text-rose-700">{{ $post->reports->count() }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Comments</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $post->comments->count() }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Moderation actions</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $post->moderationActions->count() }}</p>
            </div>
        </div>

        <div class="mt-8">
            <h3 class="font-bold text-gray-900">Comments</h3>
            <div class="mt-3 space-y-3">
                @forelse($post->comments as $comment)
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-bold text-gray-800">{{ $comment->author?->name ?? 'Unknown' }}</p>
                            <span class="text-xs text-gray-500">{{ $comment->status?->label() ?? str($comment->status)->headline() }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-700">{{ $comment->body }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No comments.</p>
                @endforelse
            </div>
        </div>
    </article>

    <aside class="space-y-4">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <h3 class="font-bold text-rose-950">Platform Actions</h3>
            <p class="mt-1 text-sm text-rose-800">Admin decisions override connector-level moderation.</p>
            <div class="mt-4 flex flex-wrap gap-2 rounded-xl bg-white p-3">
                @foreach([
                    'hide' => ['label' => 'Hide', 'path' => 'M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c1.618 0 3.15-.365 4.519-1.017M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 12.544 12.544M18.772 18.772 21 21'],
                    'restore' => ['label' => 'Restore', 'path' => 'M4 4v6h6M20 20v-6h-6M5 15a7 7 0 0 0 12 3M19 9A7 7 0 0 0 7 6'],
                ] as $action => $meta)
                    <form method="POST" action="{{ route('admin.community.moderation.'.$action, $post) }}" data-confirm-submit data-confirm-title="{{ $meta['label'] }} this post?" data-confirm-text="Record this platform moderation action. Admin decisions override connector moderation." data-confirm-icon="warning" data-confirm-button="{{ $meta['label'] }}">
                        @csrf
                        <input type="hidden" name="reason" value="Platform moderation action.">
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50" title="{{ $meta['label'] }}" aria-label="{{ $meta['label'] }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['path'] }}"/></svg>
                        </button>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('admin.community.moderation.remove', $post) }}" data-confirm-submit data-confirm-title="Remove this post?" data-confirm-text="Remove this post from the Community Hub and record a platform moderation action." data-confirm-icon="warning" data-confirm-button="Remove">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="reason" value="Platform removal.">
                    <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Remove" aria-label="Remove">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M10 11v6m4-6v6M9 7V5h6v2m-8 0 1 13h8l1-13"/></svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-gray-900">Enforcement shortcuts</h3>
            <p class="mt-1 text-sm text-gray-500">Use only when content review shows account or connector risk.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @if($post->connector)
                    <form method="POST" action="{{ route('admin.connectors.suspend', $post->connector) }}" data-confirm-submit data-confirm-title="Suspend connector?" data-confirm-text="Pause this connector because of a Community Hub safety incident." data-confirm-icon="warning" data-confirm-button="Suspend Connector">
                        @csrf
                        <input type="hidden" name="reason" value="Community Feed safety incident.">
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Suspend connector space" aria-label="Suspend connector space">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3.75 5.25 6.5v5.25c0 4.25 2.85 7.9 6.75 8.95 3.9-1.05 6.75-4.7 6.75-8.95V6.5L12 3.75ZM9 12h6"/></svg>
                        </button>
                    </form>
                @endif
                @if($post->author)
                    <form method="POST" action="{{ route('admin.users.status.update', $post->author) }}" data-confirm-submit data-confirm-title="Suspend this user?" data-confirm-text="Suspend this user account because of a Community Hub safety incident." data-confirm-icon="warning" data-confirm-button="Suspend User">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="suspended">
                        <input type="hidden" name="reason" value="Community Feed safety incident.">
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-700 hover:bg-rose-50" title="Suspend user" aria-label="Suspend user">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9A3.75 3.75 0 1 1 8.25 9a3.75 3.75 0 0 1 7.5 0ZM4.5 19.5a7.5 7.5 0 0 1 11.25-6.5M18 15l3 3m0-3-3 3"/></svg>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-gray-900">Reports</h3>
            <div class="mt-3 space-y-3">
                @forelse($post->reports as $report)
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm">
                        <p class="font-bold text-gray-800">{{ $report->reason_label }}</p>
                        <div class="prose prose-sm mt-1 max-w-none text-gray-600">{!! $report->details_html !!}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No reports.</p>
                @endforelse
            </div>
        </div>
    </aside>
</div>
@endsection
