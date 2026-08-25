@php
    $progress = ($checkpointProgress ?? collect())->get($question->id);
    $blankCount = substr_count($question->question_text, '_____');
    $parts = $blankCount > 0 ? explode('_____', $question->question_text) : [];
@endphp

<section
    x-data="interactiveCheckpoint({
        type: '{{ $question->question_type }}',
        questionId: {{ $question->id }},
        blankCount: {{ max(1, $blankCount) }},
        submitUrl: '{{ route('learner.checkpoints.submit', $question) }}',
        skipUrl: '{{ route('learner.checkpoints.skip', $question) }}',
        csrf: '{{ csrf_token() }}'
    })"
    class="my-6 rounded-2xl border border-purple-200 bg-purple-50/50 dark:border-purple-800 dark:bg-purple-900/10 p-5">
    <p class="text-xs font-bold uppercase tracking-widest text-purple-700 dark:text-purple-300">Quick Check</p>
    <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-white">
        @if(in_array($question->question_type, ['fill_blank_text', 'fill_blank_select']) && $blankCount > 0)
            @foreach($parts as $index => $part)
                {!! $part !!}
                @if($index < $blankCount)
                    <span class="inline-flex min-w-28 border-b-2 border-purple-300 align-baseline"></span>
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
            <div class="grid gap-2 sm:grid-cols-2">
                @for($i = 0; $i < max(1, $blankCount); $i++)
                    <div class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <span class="text-gray-500">Blank {{ $i + 1 }}:</span>
                        <span class="font-semibold" x-text="answer[{{ $i }}] || '...'"></span>
                    </div>
                @endfor
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($question->word_bank ?? [] as $word)
                    <button type="button" @click="chooseWord(@js($word))" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium hover:bg-purple-50 dark:border-gray-700 dark:bg-gray-900">
                        {{ $word }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <template x-if="feedback">
        <div class="mt-4 rounded-xl p-4" :class="isCorrect ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
            <p class="font-semibold" x-text="isCorrect ? 'Correct' : 'Not quite'"></p>
            <p x-show="explanation" class="mt-2 text-sm" x-text="explanation"></p>
        </div>
    </template>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button type="button" @click="submit()" class="rounded-xl px-4 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
            Check Answer
        </button>
        <button type="button" x-show="feedback && !isCorrect" @click="reset()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold dark:border-gray-700">
            Retry
        </button>
        <button type="button" @click="skip()" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            Skip for now
        </button>
        <button type="button" x-show="feedback" @click="continueLearning()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold dark:border-gray-700">
            Continue
        </button>
    </div>

    @if($progress?->status)
        <p class="mt-3 text-xs text-gray-500">Last status: {{ str_replace('_', ' ', $progress->status) }}</p>
    @endif
</section>

@once
@push('scripts')
<script>
function emptyCheckpointAnswer(type, blankCount) {
    if (type === 'multiple_select') return [];
    if (['fill_blank_text', 'fill_blank_select'].includes(type)) return Array(blankCount).fill('');
    return '';
}

function interactiveCheckpoint(config) {
    return {
        answer: emptyCheckpointAnswer(config.type, config.blankCount),
        feedback: false,
        isCorrect: null,
        explanation: null,
        chooseWord(word) {
            const index = this.answer.findIndex((value) => !value);
            this.answer[index === -1 ? this.answer.length - 1 : index] = word;
        },
        async submit() {
            const response = await fetch(config.submitUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json'},
                body: JSON.stringify({answer: this.answer}),
            });
            const data = await response.json();
            this.feedback = true;
            this.isCorrect = data.is_correct;
            this.explanation = data.explanation;
        },
        async skip() {
            await fetch(config.skipUrl, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json'},
            });
            this.feedback = false;
            this.explanation = null;
        },
        reset() {
            this.answer = emptyCheckpointAnswer(config.type, config.blankCount);
            this.feedback = false;
            this.isCorrect = null;
            this.explanation = null;
        },
        continueLearning() {
            this.feedback = false;
            this.$dispatch('checkpoint-continued', { questionId: config.questionId });
        },
    };
}
</script>
@endpush
@endonce
