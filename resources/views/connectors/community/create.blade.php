@extends('layouts.connector-app')

@section('title', 'New Community Post')
@section('page-title', 'New Community Post')

@section('content')
<div class="mx-auto max-w-4xl">
    <form method="POST" action="{{ route('connector.community.store', $connector) }}" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @include('connectors.community.partials.form', ['post' => null])
        <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50">Save Draft</button>
            <a href="{{ route('connector.community.index', $connector) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">Submit for Review</button>
            <button class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-bold text-white hover:bg-purple-800">Publish</button>
        </div>
    </form>
</div>
@endsection
