<div class="flex flex-wrap gap-2" aria-label="Community details sections">
    @foreach([
        ['label' => 'Overview', 'route' => 'admin.community.communities.show', 'active' => request()->routeIs('admin.community.communities.show')],
        ['label' => 'Posts', 'route' => 'admin.community.communities.posts', 'active' => request()->routeIs('admin.community.communities.posts')],
        ['label' => 'Members', 'route' => 'admin.community.communities.members', 'active' => request()->routeIs('admin.community.communities.members')],
        ['label' => 'Settings', 'route' => 'admin.community.communities.edit', 'active' => request()->routeIs('admin.community.communities.edit')],
    ] as $tab)
        <a href="{{ route($tab['route'], $community) }}" class="rounded-2xl border px-4 py-2 text-sm font-semibold transition {{ $tab['active'] ? 'border-brand-700 bg-brand-700 text-white shadow-sm' : 'border-gray-200 text-gray-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' }}">{{ $tab['label'] }}</a>
    @endforeach
</div>
