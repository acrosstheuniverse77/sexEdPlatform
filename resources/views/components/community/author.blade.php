@props([
    'user',
    'connector',
    'timestamp' => null,
    'status' => null,
    'compact' => false,
])

@php
    $name = trim((string) ($user?->name ?? '')) ?: 'Unknown member';
    $words = str($name)->squish()->explode(' ')->filter()->take(2);
    $initials = $words->map(fn ($word) => str($word)->substr(0, 1)->upper())->implode('') ?: 'M';
    $avatarPath = $user?->learnerProfile?->avatar_path
        ?? $user?->instructorProfile?->profile_photo_path
        ?? $user?->profile?->avatar;
    $avatarUrl = null;

    if ($avatarPath) {
        $avatarPath = trim((string) $avatarPath);
        $avatarUrl = str_starts_with($avatarPath, 'http://')
            || str_starts_with($avatarPath, 'https://')
            || str_starts_with($avatarPath, '//')
                ? $avatarPath
                : asset('storage/' . ltrim(preg_replace('#^storage/#', '', $avatarPath), '/'));
    }

    $memberships = $user?->relationLoaded('connectorMemberships')
        ? $user->connectorMemberships
        : collect();
    $membership = $memberships->first(fn ($item) => (int) $item->connector_id === (int) $connector->id && $item->status === 'active');
    $roleLabel = $membership?->role?->is_owner
        ? 'Connector Owner'
        : (trim((string) ($membership?->role?->name ?? '')) ?: 'Member');
    $timestampLabel = $timestamp instanceof \Carbon\CarbonInterface
        ? $timestamp->diffForHumans()
        : trim((string) $timestamp);
    $avatarSize = $compact ? 'h-8 w-8 text-[11px]' : 'h-10 w-10 text-xs';
@endphp

<div data-testid="community-author" {{ $attributes->class(['flex min-w-0 items-center gap-3']) }}>
    @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" aria-hidden="true" class="{{ $avatarSize }} shrink-0 rounded-full border border-gray-200 bg-white object-cover">
    @else
        <span aria-hidden="true" class="{{ $avatarSize }} inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-extrabold text-brand-700">{{ $initials }}</span>
    @endif

    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="truncate text-sm font-bold text-gray-900">{{ $name }}</span>
            <span class="inline-flex rounded-full border border-brand-100 bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700">{{ $roleLabel }}</span>
        </div>
        @if($timestampLabel !== '' || $status)
            <p class="mt-0.5 text-xs text-gray-500">
                @if($timestampLabel !== '')
                    <span>{{ $timestampLabel }}</span>
                @endif
                @if($timestampLabel !== '' && $status)
                    <span aria-hidden="true"> · </span>
                @endif
                @if($status)
                    <span>{{ $status }}</span>
                @endif
            </p>
        @endif
    </div>
</div>
