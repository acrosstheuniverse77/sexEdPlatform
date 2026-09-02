@extends('layouts.instructor-app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6 p-6">
    <div>
        <p class="text-sm text-gray-500">{{ $lesson->title }}</p>
        <h1 class="text-2xl font-bold text-gray-900">Edit interactive activity</h1>
    </div>

    <form action="{{ $formAction }}" method="POST" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <input type="hidden" name="type" value="interactive">
        <input type="hidden" name="activity_type" value="{{ $activity->activity_type->value }}">
        <input type="hidden" name="placement" value="{{ $activity->placement }}">
        @if($activity->placement === 'inside_topic')
            <input type="hidden" name="parent_topic_id" value="{{ $activity->lesson_topic_id }}">
        @endif
        <label class="block text-sm font-medium text-gray-700">
            Title
            <input name="title" value="{{ old('title', $activity->title) }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300">
        </label>
        <label class="block text-sm font-medium text-gray-700">
            Instructions
            <textarea name="instructions" maxlength="10000" rows="4" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('instructions', $activity->instructions) }}</textarea>
        </label>
        <label class="block text-sm font-medium text-gray-700">
            Explanation
            <textarea name="explanation" maxlength="10000" rows="4" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('explanation', $activity->explanation) }}</textarea>
        </label>
        <input type="hidden" name="configuration" value="{{ json_encode($activity->configuration) }}">
        <div class="flex justify-end gap-3">
            <a href="{{ route(app(\App\Support\ContentPanelContext::class)->name('lessons.show'), $lesson) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</a>
            <button type="submit" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Save activity</button>
        </div>
    </form>
</div>
@endsection
