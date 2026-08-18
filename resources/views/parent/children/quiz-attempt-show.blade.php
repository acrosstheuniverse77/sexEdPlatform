@extends('layouts.learner-app')

@section('title', 'Quiz Attempt Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('parent.children.show', $child) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Dependent Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Quiz Attempt Details</h1>
            <p class="text-sm text-gray-500 mt-1">Review {{ $child->full_name }}'s attempt results and answers.</p>
        </div>

        <button type="button"
                onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $child->id }}, conversation_type: "direct", name: @json($child->full_name ?: $child->name) } }))'
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
            Message Dependent
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Quiz</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $attempt->quiz?->title ?? 'Quiz' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Module</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $attempt->quiz?->module?->title ?? 'N/A' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Score</p>
            <p class="mt-1 text-sm font-semibold {{ $attempt->passed ? 'text-emerald-700' : 'text-rose-700' }}">{{ $attempt->score }}%</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Completed</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $attempt->completed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        @if($questionResults->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-gray-500">
                No detailed answer payload is available for this attempt.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-xs text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">Question</th>
                            <th class="px-5 py-3 text-left">Selected Answer</th>
                            <th class="px-5 py-3 text-left">Correct Answer</th>
                            <th class="px-5 py-3 text-center">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($questionResults as $result)
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $result['question_text'] ?: 'Question' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Type: {{ str_replace('_', ' ', (string) $result['question_type']) }}</p>
                                </td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $result['selected_answer'] }}</td>
                                <td class="px-5 py-4 align-top text-gray-700 dark:text-gray-300">{{ $result['correct_answer'] }}</td>
                                <td class="px-5 py-4 align-top text-center">
                                    @if($result['is_correct'])
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Correct</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Incorrect</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
