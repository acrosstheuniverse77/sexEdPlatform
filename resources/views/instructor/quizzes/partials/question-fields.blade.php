@php
    $allowTypeSwitch = $allowTypeSwitch ?? true;
    $showPoints = $showPoints ?? true;
    $showExplanation = $showExplanation ?? false;
    $selectedType = old('question_type', $question->question_type ?? ($selectedType ?? 'multiple_choice'));
    $questionText = old('question_text', $question->question_text ?? '');
    $blankCount = substr_count(strip_tags((string) $questionText), '_____');
    $existingOptions = isset($question) ? $question->options->values() : collect();
    $submittedOptions = old('options');
    $submittedCorrect = array_map('intval', (array) old('correct_options', []));
    $options = is_array($submittedOptions)
        ? collect($submittedOptions)->values()->map(fn ($text, $index) => ['text' => $text, 'isCorrect' => in_array($index, $submittedCorrect, true), 'readonly' => $selectedType === 'true_false'])->all()
        : $existingOptions->map(fn ($option) => ['text' => $option->option_text, 'isCorrect' => (bool) $option->is_correct, 'readonly' => $selectedType === 'true_false'])->all();
    $storedAnswers = (string) ($question->acceptable_answers ?? '');

    if (old('acceptable_answers') !== null) {
        $answers = array_values((array) old('acceptable_answers'));
    } elseif ($selectedType === 'identification') {
        $answers = $storedAnswers === '' ? [''] : explode('|', $storedAnswers);
    } elseif ($selectedType === 'fill_blank_select') {
        $answers = $storedAnswers === '' ? [''] : preg_split('/[;|]/', $storedAnswers);
    } elseif ($selectedType === 'fill_blank_text' && str_contains($storedAnswers, ';')) {
        $answers = explode(';', $storedAnswers);
    } elseif ($selectedType === 'fill_blank_text' && $blankCount > 1) {
        $tokens = $storedAnswers === '' ? [] : explode('|', $storedAnswers);
        $answers = count($tokens) === $blankCount ? $tokens : [$storedAnswers];
    } else {
        $answers = [$storedAnswers];
    }

    $typeMeta = [
        'multiple_choice' => ['label' => 'Multiple Choice', 'description' => 'Learners choose one answer.', 'badge' => 'bg-brand-50 text-brand-700 border-brand-200'],
        'true_false' => ['label' => 'True or False', 'description' => 'Learners decide whether the statement is true or false.', 'badge' => 'bg-green-50 text-green-700 border-green-200'],
        'identification' => ['label' => 'Identification', 'description' => 'Learners type a short accepted answer.', 'badge' => 'bg-pink-50 text-pink-700 border-pink-200'],
        'fill_blank_text' => ['label' => 'Fill in the Blanks — Text', 'description' => 'Learners type an answer for every blank.', 'badge' => 'bg-yellow-50 text-yellow-700 border-yellow-200'],
        'fill_blank_select' => ['label' => 'Fill in the Blanks — Word Bank', 'description' => 'Learners choose ordered answers from a Word Bank.', 'badge' => 'bg-orange-50 text-orange-700 border-orange-200'],
        'multiple_select' => ['label' => 'Multiple Select', 'description' => 'Learners select every correct answer.', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200'],
    ];
    $initialState = [
        'type' => $selectedType,
        'questionText' => $questionText,
        'points' => old('points', $question->points ?? 1),
        'explanation' => old('explanation', $question->explanation ?? ''),
        'options' => $options,
        'answers' => $answers,
        'wordBank' => old('word_bank', isset($question) && is_array($question->word_bank) ? implode(', ', $question->word_bank) : ''),
        'caseSensitive' => (bool) old('case_sensitive', $question->case_sensitive ?? false),
        'currentImageUrl' => $question->image_url ?? null,
        'editorUploadUrl' => $editorUploadUrl,
        'typeMeta' => $typeMeta,
    ];
@endphp

@once
    @push('head')
        <script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
    @endpush
@endonce

<div x-data="questionAuthoring(@js($initialState))" class="space-y-6" data-question-authoring>
    <input type="hidden" name="question_type" :value="questionType">

    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <span class="inline-flex rounded-xl border px-3 py-1 text-sm font-semibold" :class="typeMeta[questionType].badge" x-text="typeMeta[questionType].label"></span>
                <p class="mt-1 text-xs text-gray-500" x-text="typeMeta[questionType].description"></p>
                <p class="sr-only" aria-live="polite" x-text="`${typeMeta[questionType].label} configuration selected`"></p>
            </div>
            @if($allowTypeSwitch)
                <div class="w-full sm:w-72">
                    <label for="question_type_selector" class="mb-1 block text-xs font-semibold text-gray-700">Change Question Type</label>
                    <select id="question_type_selector" :value="questionType" @change="switchType($event.target.value)" class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
                        @foreach($typeMeta as $value => $meta)<option value="{{ $value }}">{{ $meta['label'] }}</option>@endforeach
                    </select>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-2 flex items-center justify-between gap-3">
            <label for="question_text" class="text-sm font-semibold text-gray-700">Question Text <span class="text-red-500">*</span></label>
            <button x-show="isBlankType()" type="button" @click="insertBlank()" class="min-h-10 rounded-xl border border-purple-200 bg-purple-50 px-3 text-xs font-semibold text-purple-700 hover:bg-purple-100">Insert Blank (_____)</button>
        </div>
        <template x-if="isRichType()">
            <textarea id="question_text" name="question_text" x-model="questionText" rows="5" aria-describedby="question_text_error question_text_client_error" :aria-invalid="Boolean(errors.question_text || @js($errors->has('question_text')))" class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300"></textarea>
        </template>
        <template x-if="isBlankType()">
            <textarea id="question_text" name="question_text" x-ref="plainQuestion" x-model="questionText" @input="syncAnswersToBlanks()" rows="5" aria-describedby="question_text_error question_text_client_error" :aria-invalid="Boolean(errors.question_text || @js($errors->has('question_text')))" class="w-full rounded-xl border-gray-200 font-mono text-sm focus:border-purple-400 focus:ring-purple-300" placeholder="Use _____ (five underscores) for every blank."></textarea>
        </template>
        <p x-show="isBlankType()" class="mt-2 text-xs text-purple-700"><span x-text="blankCount()"></span> blank(s) detected.</p>
        @error('question_text') <p id="question_text_error" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        <p id="question_text_client_error" x-show="errors.question_text" x-text="errors.question_text" class="mt-1 text-xs text-red-600" role="alert"></p>
    </section>

    @if($showPoints)
        <div>
            <label for="points" class="mb-2 block text-sm font-semibold text-gray-700">Points <span class="text-red-500">*</span></label>
            <input id="points" name="points" type="number" min="1" x-model.number="points" required class="w-32 rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
            @error('points') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @else
        <input type="hidden" name="points" value="1">
    @endif

    <template x-if="isChoiceType()">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm" role="group" aria-labelledby="answer_options_heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="border-l-4 border-purple-700 pl-3">
                    <p id="answer_options_heading" class="text-sm font-semibold text-gray-900">Answer Options</p>
                    <p x-show="questionType === 'multiple_choice'" class="text-xs text-gray-500">Select exactly one correct answer.</p>
                    <p x-show="questionType === 'true_false'" class="text-xs text-gray-500">Choose whether True or False is correct.</p>
                    <p x-show="questionType === 'multiple_select'" class="text-xs text-gray-500">Select every correct answer.</p>
                </div>
                <button x-show="canAddOptions()" type="button" @click="addOption()" class="min-h-10 rounded-xl border border-purple-200 bg-purple-50 px-3 text-xs font-semibold text-purple-700 hover:bg-purple-100">Add Option</button>
            </div>
            <div class="mt-4 space-y-3">
                <template x-for="(option, index) in options" :key="option.key">
                    <div class="flex flex-col gap-3 rounded-xl border p-3 sm:flex-row sm:items-center" :class="option.isCorrect ? 'border-green-300 bg-green-50' : 'border-gray-200'">
                        <template x-if="questionType !== 'multiple_select'">
                            <input type="radio" name="correct_options[]" :value="index" :checked="option.isCorrect" @change="setOnlyCorrect(index)" :aria-label="`Mark option ${index + 1} correct`" :aria-invalid="Boolean(errors.correct_options || @js($errors->has('correct_options')))" aria-describedby="correct_options_server_error correct_options_error" class="h-6 w-6 text-green-600 focus:ring-green-500">
                        </template>
                        <template x-if="questionType === 'multiple_select'">
                            <input type="checkbox" name="correct_options[]" :value="index" :checked="option.isCorrect" @change="option.isCorrect = $event.target.checked" :aria-label="`Mark option ${index + 1} correct`" :aria-invalid="Boolean(errors.correct_options || @js($errors->has('correct_options')))" aria-describedby="correct_options_server_error correct_options_error" class="h-6 w-6 rounded text-green-600 focus:ring-green-500">
                        </template>
                        <input type="text" name="options[]" x-model="option.text" :readonly="option.readonly" required :aria-label="`Answer option ${index + 1}`" :aria-invalid="Boolean(errors.options || @js($errors->has('options') || $errors->has('options.*')))" aria-describedby="answer_options_server_error answer_options_error" class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
                        <span x-show="option.isCorrect" class="text-xs font-semibold text-green-700">Correct</span>
                        <button x-show="canRemoveOptions()" type="button" @click="removeOption(index)" :aria-label="`Remove option ${index + 1}`" class="min-h-10 min-w-10 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600">×</button>
                    </div>
                </template>
            </div>
            @if($errors->has('options') || $errors->has('options.*'))<p id="answer_options_server_error" class="mt-2 text-xs text-red-600" role="alert">{{ $errors->first('options') ?: $errors->first('options.*') }}</p>@endif
            @error('correct_options') <p id="correct_options_server_error" class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            <p id="answer_options_error" x-show="errors.options" x-text="errors.options" class="mt-2 text-xs text-red-600" role="alert"></p>
            <p id="correct_options_error" x-show="errors.correct_options" x-text="errors.correct_options" class="mt-2 text-xs text-red-600" role="alert"></p>
        </section>
    </template>

    <template x-if="['fill_blank_text', 'fill_blank_select', 'identification'].includes(questionType)">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="border-l-4 border-purple-700 pl-3">
                    <p class="text-sm font-semibold text-gray-900" x-text="questionType === 'identification' ? 'Acceptable Answers' : 'Correct Answers (in order)'"></p>
                    <p x-show="questionType === 'fill_blank_text'" class="text-xs text-gray-500">Add one row per blank. Alternatives within one blank use |, for example color|colour.</p>
                    <p x-show="questionType === 'fill_blank_select'" class="text-xs text-gray-500">Add one Word Bank answer per blank in question order.</p>
                    <p x-show="questionType === 'identification'" class="text-xs text-gray-500">Add every short answer that should be accepted.</p>
                </div>
                <button x-show="questionType === 'identification'" type="button" @click="addAnswer()" class="min-h-10 rounded-xl border border-purple-200 bg-purple-50 px-3 text-xs font-semibold text-purple-700">Add Answer</button>
            </div>
            <div class="mt-4 space-y-2">
                <template x-for="(answer, index) in answers" :key="answerKeys[index]">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                        <span class="w-16 text-xs text-gray-500" x-text="questionType === 'identification' ? `${index + 1}.` : `Blank ${index + 1}`"></span>
                        <input type="text" name="acceptable_answers[]" x-model="answers[index]" required :aria-label="`Accepted answer ${index + 1}`" :aria-invalid="Boolean(errors.acceptable_answers || @js($errors->has('acceptable_answers') || $errors->has('acceptable_answers.*')))" aria-describedby="acceptable_answers_server_error acceptable_answers_error" class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
                        <button x-show="questionType === 'identification' && answers.length > 1" type="button" @click="removeAnswer(index)" :aria-label="`Remove acceptable answer ${index + 1}`" class="min-h-10 min-w-10 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600">×</button>
                    </div>
                </template>
            </div>
            <label x-show="questionType !== 'fill_blank_select'" class="mt-4 flex items-start gap-3 rounded-xl border border-yellow-200 bg-yellow-50 p-3">
                <input type="checkbox" name="case_sensitive" value="1" x-model="caseSensitive" class="mt-0.5 h-6 w-6 rounded text-purple-600">
                <span><span class="block text-sm font-medium text-gray-700">Case Sensitive</span><span class="block text-xs text-gray-500">Require capitalization to match exactly.</span></span>
            </label>
            @if($errors->has('acceptable_answers') || $errors->has('acceptable_answers.*'))<p id="acceptable_answers_server_error" class="mt-2 text-xs text-red-600" role="alert">{{ $errors->first('acceptable_answers') ?: $errors->first('acceptable_answers.*') }}</p>@endif
            <p id="acceptable_answers_error" x-show="errors.acceptable_answers" x-text="errors.acceptable_answers" class="mt-2 text-xs text-red-600" role="alert"></p>
        </section>
    </template>

    <template x-if="questionType === 'fill_blank_select'">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <label for="word_bank" class="block text-sm font-semibold text-gray-900">Word Bank</label>
            <p class="mb-3 text-xs text-gray-500">Enter comma-separated words learners can choose from. Max 10 words.</p>
            <input id="word_bank" name="word_bank" type="text" x-model="wordBank" required aria-describedby="word_bank_server_error word_bank_error" :aria-invalid="Boolean(errors.word_bank || @js($errors->has('word_bank')))" class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300">
            @error('word_bank') <p id="word_bank_server_error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            <p id="word_bank_error" x-show="errors.word_bank" x-text="errors.word_bank" class="mt-1 text-xs text-red-600" role="alert"></p>
        </section>
    </template>

    <template x-if="questionType === 'identification'">
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <label for="image" class="block text-sm font-semibold text-gray-900">Question Image <span class="font-normal text-gray-400">(optional)</span></label>
            <p class="mb-3 text-xs text-gray-500">JPG or PNG, max 2 MB.</p>
            <img x-show="currentImageUrl" :src="currentImageUrl" alt="Current question image" class="mb-3 max-h-48 rounded-xl border border-gray-200 object-contain">
            <input id="image" name="image" type="file" x-ref="imageInput" accept=".jpg,.jpeg,.png" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:font-semibold file:text-purple-700">
            @error('image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </section>
    </template>

    @if($showExplanation)
        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <label for="explanation" class="block text-sm font-semibold text-gray-900">Explanation <span class="font-normal text-gray-400">(Optional)</span></label>
            <p class="mb-3 text-xs text-gray-500">Shown after the learner answers. It is not shown when the learner skips.</p>
            <textarea id="explanation" name="explanation" rows="4" maxlength="5000" x-model="explanation" class="w-full rounded-xl border-gray-200 text-sm focus:border-purple-400 focus:ring-purple-300"></textarea>
            @error('explanation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </section>
    @endif
</div>
