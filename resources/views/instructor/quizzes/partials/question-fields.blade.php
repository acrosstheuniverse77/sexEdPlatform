@php
    $selectedType = old('question_type', $question->question_type ?? ($selectedType ?? 'multiple_choice'));
    $existingOptions = isset($question) ? $question->options->values() : collect();
    $existingCorrect = $existingOptions->filter->is_correct->keys()->all();
@endphp

<div class="space-y-5" id="questionFields">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Question Type</label>
        <select name="question_type" id="question_type" class="w-full rounded-xl border-gray-200">
            @foreach([
                'multiple_choice' => 'Multiple Choice',
                'true_false' => 'True or False',
                'identification' => 'Identification',
                'fill_blank_text' => 'Fill in the Blanks - Text',
                'fill_blank_select' => 'Fill in the Blanks - Word Bank',
                'multiple_select' => 'Multiple Select',
            ] as $value => $label)
                <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Question</label>
        <textarea name="question_text" rows="4" class="w-full rounded-xl border-gray-200">{{ old('question_text', $question->question_text ?? '') }}</textarea>
    </div>

    <input type="hidden" name="points" value="{{ old('points', $question->points ?? 1) }}">

    <div data-question-section="options" class="space-y-3">
        <label class="block text-sm font-medium text-gray-700">Answer Options</label>
        @for($i = 0; $i < 4; $i++)
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    type="text"
                    name="options[]"
                    value="{{ old('options.' . $i, $existingOptions[$i]->option_text ?? '') }}"
                    class="flex-1 rounded-xl border-gray-200"
                    placeholder="Option {{ $i + 1 }}"
                >
                <label class="inline-flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="correct_options[]"
                        value="{{ $i }}"
                        class="rounded border-gray-300 text-purple-600"
                        @checked(in_array($i, old('correct_options', $existingCorrect), false))
                    >
                    Correct
                </label>
            </div>
        @endfor
    </div>

    <div data-question-section="text-answer" class="space-y-3 hidden">
        <label class="block text-sm font-medium text-gray-700">Acceptable Answers</label>
        <input
            type="text"
            name="acceptable_answers[]"
            value="{{ old('acceptable_answers.0', isset($question) ? $question->acceptable_answers : '') }}"
            class="w-full rounded-xl border-gray-200"
            placeholder="Use pipes for alternatives, semicolons for blanks"
        >
        <label class="inline-flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                name="case_sensitive"
                value="1"
                class="rounded border-gray-300 text-purple-600"
                @checked(old('case_sensitive', $question->case_sensitive ?? false))
            >
            Case sensitive
        </label>
    </div>

    <div data-question-section="word-bank" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Word Bank</label>
        <input
            type="text"
            name="word_bank"
            value="{{ old('word_bank', isset($question) && is_array($question->word_bank) ? implode(', ', $question->word_bank) : '') }}"
            class="w-full rounded-xl border-gray-200"
            placeholder="word one, word two, word three"
        >
    </div>

    <div data-question-section="identification-image" class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Identification Image</label>
        <input type="file" name="image" accept="image/*" class="w-full rounded-xl border-gray-200">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Explanation</label>
        <textarea
            name="explanation"
            rows="3"
            class="w-full rounded-xl border-gray-200"
            placeholder="Optional feedback shown after learners answer."
        >{{ old('explanation', $question->explanation ?? '') }}</textarea>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('question_type');
    if (!select) return;

    const toggleQuestionSections = () => {
        const type = select.value;
        document.querySelectorAll('[data-question-section]').forEach((el) => el.classList.add('hidden'));

        if (['multiple_choice', 'true_false', 'multiple_select'].includes(type)) {
            document.querySelector('[data-question-section="options"]')?.classList.remove('hidden');
        }
        if (['fill_blank_text', 'fill_blank_select', 'identification'].includes(type)) {
            document.querySelector('[data-question-section="text-answer"]')?.classList.remove('hidden');
        }
        if (type === 'fill_blank_select') {
            document.querySelector('[data-question-section="word-bank"]')?.classList.remove('hidden');
        }
        if (type === 'identification') {
            document.querySelector('[data-question-section="identification-image"]')?.classList.remove('hidden');
        }
    };

    select.addEventListener('change', toggleQuestionSections);
    toggleQuestionSections();
});
</script>
@endpush
