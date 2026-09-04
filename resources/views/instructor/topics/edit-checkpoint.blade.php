@extends($contentPanelLayout ?? 'layouts.instructor-app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route($contentRoutePrefix . '.lessons.show', $topic->lesson) }}" class="hover:text-purple-600">{{ $topic->lesson->title }}</a>
        <span aria-hidden="true">/</span><span class="font-medium text-gray-700">Edit Interactive Checkpoint</span>
    </nav>

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4" role="alert">
            <p class="text-sm font-semibold text-red-800">Please fix the following errors:</p>
            <ul class="mt-1 list-inside list-disc text-xs text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Placement</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $placement === 'inside_topic' ? 'Inside Topic' : 'Between Topics' }}</p>
            <p class="mt-1 text-xs text-gray-500">Placement is fixed after creation.</p>
        </section>

        @if($placement === 'between_topics')
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700">Checkpoint Title</label>
                    <input id="title" name="title" value="{{ old('title', $topic->title) }}" required maxlength="255" class="mt-2 w-full rounded-xl border-gray-200 focus:border-purple-400 focus:ring-purple-300">
                </div>
            </section>
        @endif

        @include('instructor.quizzes.partials.question-fields', [
            'question' => $question,
            'selectedType' => old('question_type', $question->question_type),
            'allowTypeSwitch' => true,
            'showPoints' => false,
            'showExplanation' => true,
            'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
            'useQuestionTextForEditor' => true,
        ])

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route($contentRoutePrefix . '.lessons.show', $topic->lesson) }}" class="rounded-xl px-5 py-3 text-center text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-xl px-6 py-3 text-sm font-semibold text-white hover:opacity-90" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Save Checkpoint</button>
        </div>
    </form>
</div>
@endsection
