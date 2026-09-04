@php
    $actionFor = static function ($post): array {
        $status = $post->status?->value ?? (string) $post->status;

        if ($status === 'pending_review') {
            return ['label' => 'Review', 'tone' => 'amber'];
        }

        if ((int) ($post->open_reports_count ?? 0) > 0) {
            return ['label' => 'Investigate', 'tone' => 'rose'];
        }

        if (in_array($status, ['hidden', 'removed', 'archived'], true)) {
            return ['label' => 'View Decision', 'tone' => 'gray'];
        }

        return ['label' => 'View', 'tone' => 'purple'];
    };
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>@if($showSelect ?? false)<th class="px-4 py-3"><span class="sr-only">Select</span></th>@endif<th class="px-4 py-3">Post</th><th class="px-4 py-3">Author</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Engagement</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($posts as $post)
                @php($action = $actionFor($post))
                <tr class="transition hover:bg-brand-50/40">
                    @if($showSelect ?? false)<td class="px-4 py-3"><input type="checkbox" name="selected_posts[]" value="{{ $post->id }}" aria-label="Select {{ $post->title ?: 'Untitled post' }}" class="community-post-selection rounded border-gray-300 text-purple-700 focus:ring-purple-500"></td>@endif
                    <td class="max-w-[20rem] px-4 py-3"><a href="{{ route('admin.community.show', $post) }}" class="font-semibold text-purple-700 hover:text-purple-900">{{ $post->title ?: 'Untitled post' }}</a><p class="mt-1 truncate text-xs text-gray-500">{{ $post->author?->name ?? 'Unknown author' }} · {{ $post->created_at?->format('M d, Y g:i A') }}</p></td>
                    <td class="px-4 py-3">{{ $post->author?->name ?? 'Unknown author' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $post->created_at?->format('M d, Y g:i A') }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ number_format((int) ($post->comments_count ?? 0) + (int) ($post->upvotes_count ?? 0)) }}</td>
                    <td class="px-4 py-3"><x-community.status-badge :status="$post->status" context="admin" /></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.community.show', $post) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full border {{ $action['tone'] === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : ($action['tone'] === 'rose' ? 'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100' : ($action['tone'] === 'gray' ? 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100' : 'border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100')) }}" title="{{ $action['label'] }}" aria-label="{{ $action['label'] }} {{ $post->title ?: 'post' }}"><span class="sr-only">{{ $action['label'] }}</span><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="2.25"/></svg></a></td>
                </tr>
            @empty
                <tr><td colspan="{{ ($showSelect ?? false) ? 7 : 6 }}" class="px-4 py-12 text-center text-gray-500">No Community Hub activity matches these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
