@php
    $progress = ($checkpointProgress ?? collect())->get($question->id);
    $blankCount = substr_count($question->question_text, '_____');
    $parts = $blankCount > 0 ? explode('_____', $question->question_text) : [];
@endphp

<section
    x-data="interactiveCheckpoint(@js([
        'type' => $question->question_type,
        'questionId' => $question->id,
        'blankCount' => max(1, $blankCount),
        'wordBank' => $question->question_type === 'fill_blank_select' ? $question->word_bank : null,
        'submitUrl' => route('learner.checkpoints.submit', $question),
        'skipUrl' => route('learner.checkpoints.skip', $question),
        'csrf' => csrf_token(),
        'initialStatus' => $progress?->status,
        'initialExplanation' => $progress?->status === 'correct' ? $question->explanation : null,
    ]))"
    class="my-6 rounded-2xl border border-purple-200 bg-purple-50/50 dark:border-purple-800 dark:bg-purple-900/10 p-5">
    <p class="text-xs font-bold uppercase tracking-widest text-purple-700 dark:text-purple-300">Quick Check</p>
    <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-white">
        @if(in_array($question->question_type, ['fill_blank_text', 'fill_blank_select']) && $blankCount > 0)
            @foreach($parts as $index => $part)
                {!! $part !!}
                @if($index < $blankCount)
                    <button type="button" x-show="wordBank" @click="wordBank.removeWord({{ $index }})" class="inline-flex min-w-28 border-b-2 border-purple-300 align-baseline" x-text="wordBank?.answers()[{{ $index }}] || '_____'"></button>
                @endif
            @endforeach
        @else
            {!! $question->question_text !!}
        @endif
    </h3>

    @if($question->image_url)
        <img src="{{ $question->image_url }}" alt="Question image" class="mt-4 max-h-56 rounded-xl border object-contain">
    @endif

    <div class="mt-4 space-y-3">
        @if(in_array($question->question_type, ['multiple_choice', 'true_false']))
            @foreach($question->options as $option)
                <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <input type="radio" name="checkpoint_{{ $question->id }}" value="{{ $option->id }}" x-model="answer">
                    <span>{{ $option->option_text }}</span>
                </label>
            @endforeach
        @elseif($question->question_type === 'multiple_select')
            @foreach($question->options as $option)
                <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <input type="checkbox" value="{{ $option->id }}" x-model="answer">
                    <span>{{ $option->option_text }}</span>
                </label>
            @endforeach
        @elseif($question->question_type === 'fill_blank_text')
            @for($i = 0; $i < max(1, $blankCount); $i++)
                <input type="text" x-model="answer[{{ $i }}]" class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900" placeholder="Blank {{ $i + 1 }}">
            @endfor
        @elseif($question->question_type === 'identification')
            <input type="text" x-model="answer" class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900" placeholder="Your answer">
        @elseif($question->question_type === 'fill_blank_select')
            <div class="flex flex-wrap gap-2">
                <template x-for="(word, wordIndex) in wordBank?.words || []" :key="wordIndex">
                    <button type="button" @click="wordBank.selectWord(wordIndex)" :disabled="wordBank.isUsed(wordIndex)" x-text="word" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium hover:bg-purple-50 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900"></button>
                </template>
            </div>
        @endif
    </div>

    <div x-show="state !== 'ready' && state !== 'submitting'" class="mt-4 rounded-xl p-4" :class="state === 'correct' ? 'bg-green-50 text-green-800' : state === 'incorrect' ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-700'">
        <p class="font-semibold" x-text="state === 'correct' ? 'Correct' : state === 'incorrect' ? 'Not quite; try again.' : state === 'skipped' ? 'Skipped for now.' : error"></p>
        <p x-show="state === 'correct' && explanation" class="mt-2 text-sm" x-text="explanation"></p>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button type="button" x-show="state === 'ready'" :disabled="state === 'submitting'" @click="submit()" class="rounded-xl px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
            Check Answer
        </button>
        <button type="button" x-show="state === 'incorrect' || state === 'error'" @click="retry()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold dark:border-gray-700">
            Retry
        </button>
        <button type="button" x-show="showSkip()" @click="skip()" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            Skip for now
        </button>
        <button type="button" x-show="showContinue()" @click="continueLearning()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold dark:border-gray-700">
            Continue
        </button>
    </div>

    @if($progress?->status)
        <p class="mt-3 text-xs text-gray-500">Last status: {{ str_replace('_', ' ', $progress->status) }}</p>
    @endif
</section>
