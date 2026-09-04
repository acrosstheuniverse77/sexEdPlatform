@extends('layouts.admin')

@section('title', $community->name.' · Posts')
@section('page-title', 'Community Posts')

@section('content')
<div class="space-y-8">
    @include('admin.community.partials.navigation')
    <section class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
        <div class="bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6"><a href="{{ route('admin.community.communities.show', $community) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">← {{ $community->name }}</a><h1 class="mt-2 text-2xl font-bold text-gray-950">Community Posts</h1><p class="mt-1 text-sm text-gray-600">Review activity published in this community.</p></div>
        <div class="space-y-5 p-6">
            @include('admin.community.partials.community-tabs')
            @include('admin.community.partials.activity-table', ['posts' => $posts])
        </div>
    </section>
    <div>{{ $posts->links() }}</div>
</div>
@endsection
