@extends('layouts.learner-app')

@section('title', $quiz->title)

@section('content')

@php
  $questions    = $quiz->questions;
  $total        = $questions->count();
  $user         = auth()->user();
  $shieldsLeft  = $user->isPremium() ? null : \App\Models\UserDailyShield::getShields($user);
  $questionMeta = $questions->map(fn($q) => ['id' => $q->id, 'type' => $q->question_type])->values();
  $timeLimitMinutes = $quiz->time_limit ? (int) ceil(((int) $quiz->time_limit) / 60) : null;
@endphp

{{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     QUIZ WIZARD ROOT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
<div
  x-data="quizWizard()"
  x-init="init({{ $total }}, {{ $questionMeta->toJson() }}, {{ $quiz->time_limit ?? 'null' }})"
  @quiz-blank-change.window="updateBlankAnswer($event.detail)"
  @keydown.enter.prevent="handleEnterKey($event)"
  class="max-w-2xl mx-auto space-y-4">

  {{-- ── PROGRESS ZONE ──────────────────────────────────────────── --}}
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">

    {{-- Title + chips row --}}
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="min-w-0">
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
          {{ $quiz->lesson?->title ?? ($quiz->module?->title ?? 'Quiz') }}
        </p>
        <h1 class="text-sm font-bold text-gray-900 dark:text-white leading-snug mt-0.5 truncate">
          {{ $quiz->title }}
        </h1>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        @if($quiz->time_limit)
        <div
          class="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold transition-colors duration-300"
          :class="{
            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': timeLeft > 60,
            'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400': timeLeft <= 60 && timeLeft > 30,
            'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 animate-pulse': timeLeft <= 30
          }">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span x-text="formatTime(timeLeft)"></span>
        </div>
        @endif
        @if(!$user->isPremium())
        <div class="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            {{ $shieldsLeft }}/3
          </div>
        @endif
      </div>
    </div>

    {{-- Quiz settings --}}
    <div class="mb-3 flex flex-wrap gap-2 text-[11px]">
      @if($quiz->attempt_limit !== null)
        <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800/40">
          Attempt Limit: {{ $quiz->attempt_limit }}
        </span>
      @endif
      @if($timeLimitMinutes)
        <span class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800/40">
          Time Limit: {{ $timeLimitMinutes }} {{ \Illuminate\Support\Str::plural('minute', $timeLimitMinutes) }}
        </span>
      @endif
      <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800/40">
        Passing Score: {{ $quiz->passing_score }}%
      </span>
    </div>

    @if($quiz->time_limit)
      <div x-show="timeLeft <= 10 && timeLeft > 0"
           x-cloak
           class="mb-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
        <span x-text="timeLeft + ' seconds remaining'"></span>
      </div>
    @endif

    {{-- Question dot tracker --}}
    <div class="flex items-center gap-1.5 flex-wrap">
      @foreach($questions as $i => $q)
      <button
        type="button"
        @click="jumpTo({{ $i }})"
        class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold transition-all duration-200 focus:outline-none"
        :class="{
          'ring-2 ring-offset-1 ring-purple-400 scale-110': current === {{ $i }},
          'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-purple-100 dark:hover:bg-purple-900/30': current !== {{ $i }} && !answeredMap[{{ $q->id }}],
          'text-white': answeredMap[{{ $q->id }}] || current === {{ $i }}
        }"
        :style="(answeredMap[{{ $q->id }}] || current === {{ $i }}) ? 'background:linear-gradient(135deg,#A30EB2,#3B0CB1)' : ''">
        {{ $i + 1 }}
      </button>
      @endforeach
    </div>

    {{-- Counter + progress bar --}}
    <div class="mt-2.5 flex items-center justify-between text-xs">
      <span class="text-gray-500 dark:text-gray-400">
        Question <strong class="text-gray-700 dark:text-gray-200" x-text="current + 1"></strong> of {{ $total }}
      </span>
      <span class="text-gray-400 dark:text-gray-500">
        <span x-text="Object.values(answeredMap).filter(Boolean).length"></span> / {{ $total }} answered
      </span>
    </div>
    <div class="mt-1.5 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
      <div class="h-full rounded-full transition-all duration-500 ease-out"
           style="background:linear-gradient(90deg,#A30EB2,#3B0CB1)"
           :style="`width:${Math.round((Object.values(answeredMap).filter(Boolean).length / {{ $total }}) * 100)}%`"></div>
    </div>
  </div>

  {{-- ── QUESTION CARDS + FORM ───────────────────────────────────── --}}
  <form method="POST" action="{{ route('quizzes.submit', $quiz) }}" id="quizForm">
    @csrf
    <input type="hidden" name="started_at" value="{{ now()->timestamp }}">
    <input type="hidden" name="auto_submit" id="auto_submit" value="0">

    @foreach($questions as $index => $question)
    {{-- Each question panel: shown when current === index and not in review mode --}}
    <div
      x-show="current === {{ $index }} && !showReview"
      x-cloak
      data-question-index="{{ $index }}"
      data-question-id="{{ $question->id }}"
      class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">

      {{-- Question header --}}
      <div class="p-5 sm:p-6">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-white font-bold text-sm"
               style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
            {{ $index + 1 }}
          </div>
          <div class="flex-1 min-w-0 pt-1">
            <div class="flex flex-wrap gap-1.5 mb-2.5">
              @if($question->question_type === 'multiple_choice')
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Multiple Choice</span>
              @elseif($question->question_type === 'true_false')
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">True / False</span>
              @elseif($question->question_type === 'multiple_select')
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Multiple Select</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300 border border-amber-200 dark:border-amber-800">Select all that apply</span>
              @elseif($question->question_type === 'fill_blank_text')
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Fill in the Blank</span>
              @elseif($question->question_type === 'fill_blank_select')
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">Word Selection</span>
              @elseif($question->question_type === 'identification')
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300">Identification</span>
              @endif
              @if($question->case_sensitive)
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 border border-red-200 dark:border-red-800">⚠ Case Sensitive</span>
              @endif
            </div>

            @if(!in_array($question->question_type, ['fill_blank_text', 'fill_blank_select']))
              <h3 class="text-base font-semibold text-gray-900 dark:text-white leading-snug">
                {!! $question->question_text !!}
              </h3>
            @endif
          </div>
        </div>
      </div>

      {{-- Answer input area --}}
      <div class="px-5 sm:px-6 pb-5 space-y-2.5">

        {{-- ── Multiple Select ── --}}
        @if($question->question_type === 'multiple_select')
          @foreach($question->options as $option)
          <label
            id="lbl_ms_{{ $question->id }}_{{ $option->id }}"
            class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all duration-150 select-none border-gray-200 dark:border-gray-600 hover:border-purple-300 dark:hover:border-purple-700 hover:bg-purple-50/50 dark:hover:bg-purple-900/10">
            <input
              type="checkbox"
              id="cb_{{ $question->id }}_{{ $option->id }}"
              name="answers[{{ $question->id }}][]"
              value="{{ $option->id }}"
              @change="
                updateMultiSelect({{ $question->id }});
                let lbl = document.getElementById('lbl_ms_{{ $question->id }}_{{ $option->id }}');
                if ($event.target.checked) {
                  lbl.classList.add('border-purple-400','bg-purple-50','dark:bg-purple-900/20','dark:border-purple-500');
                  lbl.classList.remove('border-gray-200','dark:border-gray-600');
                } else {
                  lbl.classList.remove('border-purple-400','bg-purple-50','dark:bg-purple-900/20','dark:border-purple-500');
                  lbl.classList.add('border-gray-200','dark:border-gray-600');
                }
              "
              class="w-5 h-5 rounded text-purple-600 border-gray-300 focus:ring-purple-500 flex-shrink-0">
            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $option->option_text }}</span>
          </label>
          @endforeach

        {{-- ── Fill in Blank (text) ── --}}
        @elseif($question->question_type === 'fill_blank_text')
          @php
            $parts      = explode('_____', $question->question_text);
            $blankCount = count($parts) - 1;
          @endphp
          <div class="p-4 bg-gray-50 dark:bg-gray-700/40 rounded-xl border border-gray-200 dark:border-gray-600">
            @if($blankCount > 0)
              <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed font-medium">
                @foreach($parts as $pIdx => $part)
                  {!! e($part) !!}
                  @if($pIdx < $blankCount)
                    <input
                      type="text"
                      name="answers[{{ $question->id }}][]"
                      @input="
                        let all = [...document.querySelectorAll('[name=\'answers[{{ $question->id }}][]\']')];
                        all.every(i => i.value.trim()) ? markAnswered({{ $question->id }}) : markUnanswered({{ $question->id }});
                      "
                      class="inline-block mx-1 px-3 py-1.5 border-b-2 border-purple-400 dark:border-purple-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-none focus:outline-none focus:border-purple-600 min-w-[120px] text-sm"
                      placeholder="blank {{ $pIdx + 1 }}"
                      autocomplete="off">
                  @endif
                @endforeach
              </p>
            @else
              <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ $question->question_text }}</p>
              <input
                type="text"
                name="answers[{{ $question->id }}]"
                @input="$event.target.value.trim() ? markAnswered({{ $question->id }}) : markUnanswered({{ $question->id }})"
                class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200 dark:focus:ring-purple-800"
                placeholder="Your answer…"
                autocomplete="off">
            @endif
          </div>

        {{-- ── Fill in Blank (word selection) ── --}}
        @elseif($question->question_type === 'fill_blank_select')
          @php
            $parts           = explode('_____', $question->question_text);
            $blankCount      = max(1, count($parts) - 1);
            $shuffledWords   = collect($question->word_bank)->shuffle()->values()->all();
          @endphp
          <div x-data="wordBankQuestion(@js($shuffledWords), {{ $blankCount }}, (answers) => $dispatch('quiz-blank-change', { questionId: {{ $question->id }}, filled: answers.every(Boolean) }))" class="space-y-3">

            <div class="p-4 bg-gray-50 dark:bg-gray-700/40 rounded-xl border border-gray-200 dark:border-gray-600">
              <p class="text-sm font-medium text-gray-800 dark:text-gray-200 leading-relaxed flex flex-wrap items-center gap-1.5">
                @foreach($parts as $pIdx => $part)
                  <span>{{ $part }}</span>
                  @if($pIdx < $blankCount)
                    <span
                      @click="removeWord({{ $pIdx }})"
                      class="inline-flex items-center justify-center px-3 py-1 min-w-[90px] rounded-lg border-2 border-dashed font-semibold text-sm cursor-pointer transition-all duration-150"
                      :class="selectedWords[{{ $pIdx }}] === null
                        ? 'border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500'
                        : 'border-purple-400 dark:border-purple-500 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 hover:bg-purple-100'">
                      <span x-show="selectedWords[{{ $pIdx }}] === null" class="text-xs text-gray-400">tap ↓</span>
                      <span x-show="selectedWords[{{ $pIdx }}] !== null" x-text="wordBank[selectedWords[{{ $pIdx }}]]"></span>
                    </span>
                    <input type="hidden"
                           :name="`answers[{{ $question->id }}][{{ $pIdx }}]`"
                           :value="selectedWords[{{ $pIdx }}] !== null ? wordBank[selectedWords[{{ $pIdx }}]] : ''">
                  @endif
                @endforeach
              </p>
            </div>

            <div>
              <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Word Bank</p>
              <div class="flex flex-wrap gap-2">
                <template x-for="(word, wordIndex) in wordBank" :key="wordIndex">
                  <button
                    type="button"
                    @click="selectWord(wordIndex)"
                    x-show="!isUsed(wordIndex)"
                    x-text="word"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-2 border-gray-200 dark:border-gray-600 hover:border-purple-400 dark:hover:border-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-all duration-150 active:scale-95">
                  </button>
                </template>
              </div>
            </div>
          </div>

        {{-- ── Identification ── --}}
        @elseif($question->question_type === 'identification')
          @if($question->image_url)
            <div class="flex justify-center mb-3">
              <img src="{{ $question->image_url }}"
                   alt="Question image"
                   class="max-w-full max-h-56 rounded-xl border-2 border-gray-200 dark:border-gray-600 object-contain shadow-sm">
            </div>
          @endif
          <input
            type="text"
            name="answers[{{ $question->id }}]"
            @input="$event.target.value.trim() ? markAnswered({{ $question->id }}) : markUnanswered({{ $question->id }})"
            class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-200 dark:focus:ring-purple-800"
            placeholder="Your answer…"
            autocomplete="off">

        {{-- ── Multiple Choice / True-False (radio) ── --}}
        @else
          @foreach($question->options as $option)
          <label
            id="lbl_opt_{{ $question->id }}_{{ $option->id }}"
            class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all duration-150 select-none border-gray-200 dark:border-gray-600 hover:border-purple-300 dark:hover:border-purple-700 hover:bg-purple-50/50 dark:hover:bg-purple-900/10">
            <input
              type="radio"
              id="radio_{{ $question->id }}_{{ $option->id }}"
              name="answers[{{ $question->id }}]"
              value="{{ $option->id }}"
              @change="
                markAnswered({{ $question->id }});
                document.querySelectorAll('[name=\'answers[{{ $question->id }}]\']').forEach(r => {
                  let l = document.getElementById('lbl_opt_{{ $question->id }}_' + r.value);
                  if (!l) return;
                  if (r.checked) {
                    l.classList.add('border-purple-400','bg-purple-50');
                    l.classList.remove('border-gray-200');
                  } else {
                    l.classList.remove('border-purple-400','bg-purple-50');
                    l.classList.add('border-gray-200');
                  }
                });
              "
              class="w-5 h-5 text-purple-600 border-gray-300 focus:ring-purple-500 flex-shrink-0">
            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $option->option_text }}</span>
          </label>
          @endforeach
        @endif

      </div>

      {{-- Navigation footer --}}
      <div class="flex items-center gap-3 px-5 sm:px-6 py-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700">

        {{-- Back button --}}
        <button
          type="button"
          x-show="current > 0"
          @click="goBack()"
          class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-150 active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Back
        </button>

        <div class="flex-1"></div>

        {{-- Skip --}}
        <button
          type="button"
          @click="goNext()"
          x-show="!isAnswered({{ $question->id }})"
          class="px-4 py-2.5 text-sm font-semibold text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-150">
          Skip
        </button>

        {{-- Next / Review --}}
        <button
          type="button"
          @click="goNext()"
          :disabled="!isAnswered({{ $question->id }})"
          class="flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-sm transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed active:scale-95"
          :class="isAnswered({{ $question->id }}) ? 'hover:opacity-90 hover:scale-[1.02]' : ''"
          style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
          <span>{{ $index === $total - 1 ? 'Review Answers' : 'Next' }}</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

      </div>
    </div>{{-- end question panel --}}
    @endforeach


    {{-- ── REVIEW SCREEN ──────────────────────────────────────────── --}}
    <div
      x-show="showReview"
      x-cloak
      class="space-y-4">

      {{-- Review card --}}
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center gap-3 p-5 border-b border-gray-100 dark:border-gray-700">
          <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-white"
               style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Review Your Answers</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tap any question to make changes.</p>
          </div>
        </div>

        <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-2">
          @foreach($questions as $i => $q)
          <button
            type="button"
            @click="jumpTo({{ $i }}); showReview = false"
            class="flex items-center gap-2 p-3 rounded-xl border-2 text-left transition-all duration-150 hover:scale-[1.02] active:scale-95"
            :class="answeredMap[{{ $q->id }}]
              ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20'
              : 'border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20'">
            <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center text-[10px] font-bold text-white"
                 :style="answeredMap[{{ $q->id }}] ? 'background:#16a34a' : 'background:#ea580c'">
              {{ $i + 1 }}
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold truncate leading-tight"
                 :class="answeredMap[{{ $q->id }}] ? 'text-green-700 dark:text-green-300' : 'text-orange-600 dark:text-orange-400'">
                {{ Str::limit($q->question_text, 30) }}
              </p>
              <p class="text-[10px] mt-0.5"
                 :class="answeredMap[{{ $q->id }}] ? 'text-green-500' : 'text-orange-400'">
                <span x-text="answeredMap[{{ $q->id }}] ? 'Answered' : 'Skipped'"></span>
              </p>
            </div>
          </button>
          @endforeach
        </div>

        {{-- Summary --}}
        <div class="flex items-center gap-4 px-5 py-3 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700 text-xs font-semibold">
          <span class="flex items-center gap-1.5 text-green-600 dark:text-green-400">
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            <span x-text="Object.values(answeredMap).filter(Boolean).length + ' answered'"></span>
          </span>
          <span class="flex items-center gap-1.5 text-orange-500 dark:text-orange-400">
            <span class="w-2 h-2 rounded-full bg-orange-400"></span>
            <span x-text="({{ $total }} - Object.values(answeredMap).filter(Boolean).length) + ' skipped'"></span>
          </span>
        </div>
      </div>

      {{-- Shield notice (free users) --}}
      @if(!$user->isPremium())
      <div class="flex items-start gap-3 px-4 py-3 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <div>
          <p class="text-sm font-bold text-purple-800 dark:text-purple-200">1 shield will be used on submit</p>
          <p class="text-xs text-purple-600 dark:text-purple-400 mt-0.5">
            Score ≥ {{ $quiz->passing_score }}% and your shield is <strong>refunded</strong> (net zero cost to pass).
            You have <strong>{{ $shieldsLeft }}/3</strong> shields today.
          </p>
        </div>
      </div>
      @endif

      {{-- Actions --}}
      <div class="flex items-stretch gap-3">
        <button
          type="button"
          @click="showReview = false"
          class="flex items-center gap-1.5 px-4 py-3 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-150">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Edit Answers
        </button>

        <button
          type="submit"
          form="quizForm"
          class="flex-1 flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white shadow-sm transition-all duration-150 hover:opacity-90 hover:scale-[1.01] active:scale-[0.98]"
          style="background:linear-gradient(135deg,#A30EB2,#730DB1,#3B0CB1)">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Submit Quiz
        </button>
      </div>

    </div>{{-- end review screen --}}

  </form>

</div>{{-- end quiz wizard root --}}

@push('scripts')
<script>
function quizWizard() {
  return {
    current: 0,
    total: 0,
    answeredMap: {},
    showReview: false,
    timeLeft: 0,
    timerInterval: null,

    init(total, questionMeta, timeLimitSeconds) {
      this.total = total;
      this.timeLeft = timeLimitSeconds ?? 0;
      questionMeta.forEach(q => { this.answeredMap[q.id] = false; });

      if (timeLimitSeconds && timeLimitSeconds > 0) {
        this.timerInterval = setInterval(() => {
          if (this.timeLeft <= 0) {
            clearInterval(this.timerInterval);
            const autoSubmitInput = document.getElementById('auto_submit');
            if (autoSubmitInput) {
              autoSubmitInput.value = '1';
            }
            document.getElementById('quizForm').submit();
            return;
          }
          this.timeLeft--;
        }, 1000);
      }
    },

    formatTime(seconds) {
      if (!seconds && seconds !== 0) return '';
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      return `${m}:${String(s).padStart(2, '0')}`;
    },

    markAnswered(qId)  { this.answeredMap[qId] = true;  },
    markUnanswered(qId){ this.answeredMap[qId] = false; },

    updateMultiSelect(qId) {
      const checked = document.querySelectorAll(`input[name="answers[${qId}][]"]:checked`).length;
      this.answeredMap[qId] = checked > 0;
    },

    updateBlankAnswer(detail) {
      this.answeredMap[detail.questionId] = detail.filled;
    },

    isAnswered(qId) { return !!this.answeredMap[qId]; },

    goNext() {
      if (this.current >= this.total - 1) {
        this.showReview = true;
      } else {
        this.current++;
      }
    },

    goBack() {
      if (this.current > 0) this.current--;
    },

    jumpTo(index) {
      this.current = index;
      this.showReview = false;
    },

    currentQuestionId() {
      const panel = document.querySelector(`[data-question-index='${this.current}']`);
      if (!panel) {
        return null;
      }

      const raw = panel.getAttribute('data-question-id');
      const parsed = Number(raw);

      return Number.isNaN(parsed) ? null : parsed;
    },

    handleEnterKey(event) {
      if (this.showReview) {
        return;
      }

      const activeTag = document.activeElement?.tagName;
      if (activeTag === 'TEXTAREA') {
        return;
      }

      const questionId = this.currentQuestionId();
      if (!questionId) {
        return;
      }

      if (this.isAnswered(questionId)) {
        this.goNext();
      }
    },
  };
}
</script>
@endpush

@endsection
