@extends(app(\App\Support\ContentPanelContext::class)->layout())

@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-6">
    <div>
        <p class="text-sm text-gray-500">{{ $lesson->title }}</p>
        <h1 class="text-2xl font-bold text-gray-900">Edit interactive activity</h1>
    </div>

    <form action="{{ $formAction }}" method="POST" class="space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @if($errors->any())
            <div id="activity-form-errors" role="alert" aria-labelledby="activity-form-errors-title" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <h2 id="activity-form-errors-title" class="font-semibold">Please fix the activity configuration.</h2>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <input type="hidden" name="lesson_id" value="{{ $lesson->id }}">
        <input type="hidden" name="type" value="interactive">

        <label for="activity_title" class="block text-sm font-semibold text-gray-900">Title
            <input id="activity_title" name="title" value="{{ old('title', $activity->title) }}" required maxlength="255" class="mt-1 block w-full rounded-xl border-gray-300">
        </label>
        @include('instructor.topics.partials.interactive-activity-fields')
        <label for="activity_instructions" class="block text-sm font-semibold text-gray-900">Instructions
            <textarea id="activity_instructions" name="instructions" maxlength="10000" rows="4" class="mt-1 block w-full rounded-xl border-gray-300">{{ old('instructions', $activity->instructions) }}</textarea>
        </label>
        <label for="activity_explanation" class="block text-sm font-semibold text-gray-900">Explanation
            <textarea id="activity_explanation" name="explanation" maxlength="10000" rows="4" class="mt-1 block w-full rounded-xl border-gray-300">{{ old('explanation', $activity->explanation) }}</textarea>
        </label>
        <div class="flex justify-end gap-3">
            <a href="{{ route(app(\App\Support\ContentPanelContext::class)->name('lessons.show'), $lesson) }}" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</a>
            <button type="submit" class="rounded-xl bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Save activity</button>
        </div>
    </form>
</div>
@endsection
