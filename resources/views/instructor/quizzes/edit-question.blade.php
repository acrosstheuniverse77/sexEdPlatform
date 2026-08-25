@extends($contentPanelLayout ?? 'layouts.instructor-app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route($contentRoutePrefix . '.quizzes.show', $quiz) }}" class="hover:text-purple-600">{{ $quiz->title }}</a>
        <span aria-hidden="true">/</span><span class="font-medium text-gray-700">Edit Question</span>
    </nav>

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4" role="alert">
            <p class="text-sm font-semibold text-red-800">Please fix the following errors:</p>
            <ul class="mt-1 list-inside list-disc text-xs text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route($contentRoutePrefix . '.quizzes.update-question', ['quiz' => $quiz, 'question' => $question]) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @method('PUT')

        @include('instructor.quizzes.partials.question-fields', [
            'question' => $question,
            'selectedType' => $question->question_type,
            'allowTypeSwitch' => true,
            'showPoints' => true,
            'showExplanation' => false,
            'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
        ])

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route($contentRoutePrefix . '.quizzes.show', $quiz) }}" class="rounded-xl px-5 py-3 text-center text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-xl px-6 py-3 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Update Question</button>
        </div>
    </form>

    <form method="POST" action="{{ route($contentRoutePrefix . '.quizzes.delete-question', ['quiz' => $quiz, 'question' => $question]) }}" onsubmit="return confirm('Delete this question?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="min-h-10 rounded-xl px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Delete Question</button>
    </form>
</div>
@endsection
