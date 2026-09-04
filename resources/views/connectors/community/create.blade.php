@extends('layouts.connector-app')

@section('title', 'New Community Post')
@section('page-title', 'New Community Post')

@section('content')
<div class="mx-auto max-w-4xl">
    <form method="POST" action="{{ route('connector.community.store', $connector) }}" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @include('connectors.community.partials.form', ['post' => null, 'topics' => $topics, 'postTypes' => $postTypes])
        <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('connector.community.index', $connector) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-200 px-4 text-sm font-bold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-700 px-4 text-sm font-bold text-white hover:bg-brand-800">Publish</button>
        </div>
    </form>
</div>
@endsection
