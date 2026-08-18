@extends('layouts.connector-app')

@section('title', 'Edit Community Post')
@section('page-title', 'Edit Community Post')

@section('content')
<div class="mx-auto max-w-4xl">
    <form method="POST" action="{{ route('connector.community.update', [$connector, $post]) }}" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6">
        @csrf
        @method('PUT')
        @include('connectors.community.partials.form', ['post' => $post])
        <div class="flex justify-end gap-3">
            <a href="{{ route('connector.community.show', [$connector, $post]) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">Submit for Review</button>
            <button class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-bold text-white hover:bg-purple-800">Publish</button>
        </div>
    </form>
</div>
@endsection
