@php
    $items = [
        ['label' => 'Overview', 'route' => 'admin.community.index', 'active' => request()->routeIs('admin.community.index')],
        ['label' => 'Communities', 'route' => 'admin.community.communities', 'active' => request()->routeIs('admin.community.communities*')],
        ['label' => 'Content', 'route' => 'admin.community.content.index', 'active' => request()->routeIs('admin.community.content.*')],
        ['label' => 'Moderation', 'route' => 'admin.community.moderation.index', 'active' => request()->routeIs('admin.community.moderation.*')],
        ['label' => 'Safety', 'route' => 'admin.community.settings', 'active' => request()->routeIs('admin.community.settings')],
    ];
@endphp

<nav aria-label="Community Hub sections" class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
    @foreach($items as $item)
        <a href="{{ route($item['route']) }}" class="rounded-2xl px-4 py-2 text-sm font-semibold transition {{ $item['active'] ? 'bg-brand-700 text-white shadow-sm' : 'text-gray-600 hover:bg-brand-50 hover:text-brand-700' }}">{{ $item['label'] }}</a>
    @endforeach
</nav>
