@extends($contentPanelLayout ?? 'layouts.instructor-app')

@php
    $typeLabels = [
        'multiple_choice' => 'Multiple Choice',
        'true_false' => 'True or False',
        'identification' => 'Identification',
        'fill_blank_text' => 'Fill in the Blanks — Text',
        'fill_blank_select' => 'Fill in the Blanks — Word Bank',
        'multiple_select' => 'Multiple Select',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route($contentRoutePrefix . '.quizzes.index') }}" class="hover:text-purple-600">Quizzes</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route($contentRoutePrefix . '.quizzes.show', ['quiz' => $quiz, 'open_modal' => 1]) }}" class="hover:text-purple-600">{{ $quiz->title }}</a>
        <span aria-hidden="true">/</span><span class="font-medium text-gray-700">Add Question</span>
    </nav>

    @if(!$selectedType)
        <section class="rounded-2xl border border-gray-100 bg-white p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-700">No question type selected</p>
            <p class="mt-1 text-xs text-gray-500">Select a question type before authoring.</p>
            <a href="{{ route($contentRoutePrefix . '.quizzes.show', ['quiz' => $quiz, 'open_modal' => 1]) }}" class="mt-4 inline-flex rounded-xl bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Select Question Type</a>
        </section>
    @else
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-700">Question type: {{ $typeLabels[$selectedType] }}</p>
            <a href="{{ route($contentRoutePrefix . '.quizzes.show', ['quiz' => $quiz, 'open_modal' => 1]) }}" class="text-xs text-purple-700 underline">Change type</a>
        </div>

        @if($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4" role="alert">
                <p class="text-sm font-semibold text-red-800">Please fix the following errors:</p>
                <ul class="mt-1 list-inside list-disc text-xs text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route($contentRoutePrefix . '.quizzes.store-question', $quiz) }}" id="questionForm" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <input type="hidden" name="after_save" id="afterSaveInput" value="return">

            @include('instructor.quizzes.partials.question-fields', [
                'selectedType' => $selectedType,
                'allowTypeSwitch' => false,
                'showPoints' => true,
                'showExplanation' => false,
                'editorUploadUrl' => route($contentRoutePrefix . '.upload.image'),
            ])

            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="submit" class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Save &amp; Return to Question Bank</button>
                <button type="button" onclick="document.getElementById('afterSaveInput').value='another'; document.getElementById('questionForm').requestSubmit();" class="flex-1 rounded-xl border-2 border-purple-200 bg-white px-5 py-3 text-sm font-semibold text-purple-700">Save &amp; Add Another</button>
                <a href="{{ route($contentRoutePrefix . '.quizzes.show', $quiz) }}" class="rounded-xl px-4 py-3 text-center text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancel</a>
            </div>
        </form>
    @endif
</div>
@endsection
