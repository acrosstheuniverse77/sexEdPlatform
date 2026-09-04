@extends('layouts.admin')

@section('title', $community->name.' · Edit Community')
@section('page-title', 'Edit Community')

@section('content')
<div class="space-y-8">
    @include('admin.community.partials.navigation')
    <section class="max-w-2xl overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6"><a href="{{ route('admin.community.communities.show', $community) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">← {{ $community->name }}</a><h1 class="mt-2 text-2xl font-bold text-gray-950">Edit Community</h1><p class="mt-1 text-sm text-gray-600">Update the community name or availability without removing its history.</p></div>
        <form method="POST" action="{{ route('admin.community.communities.update', $community) }}" class="space-y-5 p-6">@csrf @method('PUT')
            <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Community name</span><input name="name" value="{{ old('name', $community->name) }}" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm shadow-sm focus:border-brand-400 focus:ring-4 focus:ring-brand-100">@error('name')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span><select name="status" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm shadow-sm focus:border-brand-400 focus:ring-4 focus:ring-brand-100"><option value="active" @selected(old('status', $community->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $community->status) === 'inactive')>Inactive</option></select></label>
            <div class="flex flex-wrap gap-2"><button class="inline-flex min-h-10 items-center rounded-2xl bg-brand-700 px-5 py-2 text-sm font-bold text-white hover:bg-brand-800">Save Changes</button><a href="{{ route('admin.community.communities.show', $community) }}" class="inline-flex min-h-10 items-center rounded-2xl border border-gray-200 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a></div>
        </form>
    </section>
</div>
@endsection
