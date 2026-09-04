@php
  use App\Models\QuizAttempt;
  use App\Models\UserDailyShield;
  use App\Services\EntitlementService;
  use App\Support\SubscriptionFeatureKeys;

  $user         = auth()->user();
  $hasUnlimitedShields = app(EntitlementService::class)->canAccessFeature($user, SubscriptionFeatureKeys::UNLIMITED_SHIELDS);
  $shieldsLeft  = $hasUnlimitedShields ? null : UserDailyShield::getShields($user);
  $total        = $lessonQuiz->questions->count();
  $questionMeta = $lessonQuiz->questions
      ->map(fn($q) => ['id' => $q->id, 'type' => $q->question_type])
      ->values();
  $timeLimitMinutes = $lessonQuiz->time_limit ? (int) ceil(((int) $lessonQuiz->time_limit) / 60) : null;

  // Result state — populated from session after submit redirect
  $showResult    = session()->has('quiz_result');
  $resultAttempt = null;
  if ($showResult && session('quiz_attempt_id')) {
      $resultAttempt = QuizAttempt::with('quiz.questions.options')
          ->find(session('quiz_attempt_id'));
  }
  $shieldDelta = session('shield_delta');
  $xpEarned    = session('xp_earned');

  // Counts for result state
  $resultCorrect = 0;
  $resultTotal   = 0;
  if ($resultAttempt) {
      $resultTotal   = $resultAttempt->answers ? count($resultAttempt->answers) : 0;
      $resultCorrect = $resultAttempt->answers
          ? collect($resultAttempt->answers)->where('is_correct', true)->count()
          : 0;
  }
  $showResultCompletionModal = $showResult
      && $resultAttempt
      && $resultAttempt->passed
      && !$nextLesson;
@endphp

{{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     QUIZ PAGE — single Alpine component, three states
     pageState: 'landing' | 'taking' | 'result'
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
<div
  x-data="{
    pageState: '{{ $showResult && $resultAttempt ? 'result' : 'landing' }}',

    /* ── Quiz wizard state ── */
    current: 0,
    total: {{ $total }},
    answeredMap: {},
    showReview: false,
    showCompletionModal: {{ $showResultCompletionModal ? 'true' : 'false' }},
    timeLeft: 0,
    timerInterval: null,
    quizStartedAt: null,

    startQuiz() {
      this.quizStartedAt = Math.floor(Date.now() / 1000);
      this.pageState = 'taking';
      this.$nextTick(() => this.initQuiz(
        {{ $total }},
        {{ $questionMeta->toJson() }},
        {{ $lessonQuiz->time_limit ?? 'null' }}
      ));
    },

    initQuiz(total, questionMeta, timeLimitSeconds) {
      this.total      = total;
      this.timeLeft   = timeLimitSeconds ?? 0;
      this.current    = 0;
      this.answeredMap = {};
      this.showReview = false;
      questionMeta.forEach(q => { this.answeredMap[q.id] = false; });
      if (timeLimitSeconds && timeLimitSeconds > 0) {
        this.timerInterval = setInterval(() => {
          if (this.timeLeft <= 0) {
            clearInterval(this.timerInterval);
            const autoSubmitInput = document.getElementById('lesson_auto_submit');
            if (autoSubmitInput) {
              autoSubmitInput.value = '1';
            }
            document.getElementById('lessonQuizForm').submit();
            return;
          }
          this.timeLeft--;
        }, 1000);
      }
    },

    formatTime(s) {
      if (!s && s !== 0) return '';
      return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    },

    markAnswered(qId)   { this.answeredMap[qId] = true;  },
    markUnanswered(qId) { this.answeredMap[qId] = false; },
    updateMultiSelect(qId) {
      this.answeredMap[qId] =
        document.querySelectorAll(`input[name='answers[${qId}][]']:checked`).length > 0;
    },
    updateBlankAnswer(detail) {
      this.answeredMap[detail.questionId] = detail.filled;
    },
    isAnswered(qId) { return !!this.answeredMap[qId]; },

    goNext() {
      if (this.current >= this.total - 1) { this.showReview = true; }
      else                                { this.current++;          }
    },
    goBack()        { if (this.current > 0) this.current--;    },
    jumpTo(index)   { this.current = index; this.showReview = false; },

    currentQuestionId() {
      const panel = document.querySelector(`[data-lesson-question-index='${this.current}']`);
      if (!panel) {
        return null;
      }

      const raw = panel.getAttribute('data-lesson-question-id');
      const parsed = Number(raw);

      return Number.isNaN(parsed) ? null : parsed;
    },

    handleEnterKey(event) {
      if (this.pageState !== 'taking' || this.showReview) {
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
  }"
  @quiz-blank-change.window="updateBlankAnswer($event.detail)"
  @keydown.enter.prevent="handleEnterKey($event)"
  class="flex flex-col h-full">

  {{-- ══════════════════════════════════════════════════════════════
       STATE: LANDING
  ══════════════════════════════════════════════════════════════ --}}
  <div x-show="pageState === 'landing'" x-cloak class="flex-1 overflow-y-auto">
    <div class="max-w-3xl p-6 mx-auto space-y-5">

      {{-- Header --}}
      <div class="flex items-start gap-4">
        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-2xl"
             style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div>
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $lessonQuiz->title }}</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Test your knowledge from this lesson</p>

            @if(($module->created_by ?? null) && ($module->created_by ?? null) !== auth()->id())
            <button
              type="button"
              @click="$dispatch('open-global-chat', {
                  target_user_id: {{ $module->created_by }},
                  name: '{{ addslashes($module->creator?->name ?? 'Instructor') }}',
                  avatar: 'https://ui-avatars.com/api/?name={{ urlencode($module->creator?->name ?? 'Instructor') }}&color=1D4ED8&background=EFF6FF',
                  target_role: 'instructor',
                  conversation_type: 'quiz_help',
                  quiz_id: {{ $lessonQuiz->id }}
              })"
              class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-semibold text-blue-800 hover:bg-blue-100"
            >
              Need Help With This Quiz?
            </button>
            @endif
        </div>
      </div>

      @if($lessonQuiz->description)
      <div class="px-4 py-3 text-sm text-purple-900 border-l-4 border-purple-400 rounded-xl bg-purple-50 dark:bg-purple-900/20 dark:text-purple-200">
        {!! nl2br(e($lessonQuiz->description)) !!}
      </div>
      @endif

      {{-- Stats grid --}}
      <div class="grid grid-cols-3 gap-3">
        <div class="p-3 text-center bg-gray-50 dark:bg-gray-700/50 rounded-xl">
          <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-blue-100 rounded-lg dark:bg-blue-900/40">
            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $total }}</p>
          <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Questions</p>
        </div>

        <div class="p-3 text-center bg-gray-50 dark:bg-gray-700/50 rounded-xl">
          <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-green-100 rounded-lg dark:bg-green-900/40">
            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-xl font-bold text-gray-900 dark:text-white">
            {{ $timeLimitMinutes ?? '—' }}
          </p>
          <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
            {{ $timeLimitMinutes ? \Illuminate\Support\Str::plural('Minute', $timeLimitMinutes) : 'No Limit' }}
          </p>
        </div>

        <div class="p-3 text-center bg-gray-50 dark:bg-gray-700/50 rounded-xl">
          <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 rounded-lg bg-amber-100 dark:bg-amber-900/40">
            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
          </div>
          <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $lessonQuiz->passing_score }}%</p>
          <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Pass Score</p>
        </div>
      </div>
      {{-- Question type breakdown chips --}}
      @if($questionTypeCounts->count() > 0)
      @php
          $__typeLabels = [
              'multiple_choice'   => 'Multiple Choice',
              'true_false'        => 'True / False',
              'multiple_select'   => 'Multiple Select',
              'identification'    => 'Identification',
              'fill_blank_text'   => 'Fill in the Blank',
              'fill_blank_select' => 'Word Bank',
          ];
      @endphp
      <div class="flex flex-wrap gap-2">
          @foreach($questionTypeCounts as $__qType => $__qCount)
          <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-700/60 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-300">
              <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);"></span>
              {{ $__qCount }} {{ $__typeLabels[$__qType] ?? ucfirst(str_replace('_', ' ', $__qType)) }}
          </span>
          @endforeach
      </div>
      @endif

      {{-- Previous attempt card --}}
      @if($quizAttempt)
      <div class="rounded-2xl border-2 overflow-hidden
                  {{ $quizAttempt->passed
                     ? 'border-green-200 dark:border-green-800'
                     : 'border-orange-200 dark:border-orange-800' }}">
        <div class="flex items-center gap-4 p-4
                    {{ $quizAttempt->passed
                       ? 'bg-green-50 dark:bg-green-900/20'
                       : 'bg-orange-50 dark:bg-orange-900/20' }}">
          <div class="w-12 h-12 rounded-full flex-shrink-0 flex items-center justify-center
                      {{ $quizAttempt->passed ? 'bg-green-500' : 'bg-orange-400' }}">
            @if($quizAttempt->passed)
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            @else
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
            @endif
          </div>
          <div>
            <p class="text-sm font-bold
                      {{ $quizAttempt->passed ? 'text-green-800 dark:text-green-200' : 'text-orange-800 dark:text-orange-200' }}">
              {{ $quizAttempt->passed ? 'Previously Passed' : 'Previous Attempt' }}
            </p>
            <p class="text-2xl font-bold
                      {{ $quizAttempt->passed ? 'text-green-700 dark:text-green-300' : 'text-orange-700 dark:text-orange-300' }}">
              {{ number_format($quizAttempt->score, 0) }}%
            </p>
          </div>
          <div class="ml-auto text-right">
            <p class="text-xs text-gray-500 dark:text-gray-400">Best score</p>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
              {{ $quizAttempt->completed_at?->diffForHumans() ?? '—' }}
            </p>
          </div>
        </div>
      </div>

      @else
      {{-- First-time motivational card --}}
      <div class="p-5 text-center border rounded-2xl bg-purple-50/40 dark:bg-purple-900/10 border-purple-100/60 dark:border-purple-800/40">
        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 rounded-2xl"
             style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
          </svg>
        </div>
        <p class="text-base font-bold text-gray-900 dark:text-white">Ready to test your knowledge?</p>
        <p class="max-w-xs mx-auto mt-1 text-sm text-gray-500 dark:text-gray-400">
          Answer {{ $total }} question{{ $total !== 1 ? 's' : '' }} at your own pace.
          @if($lessonQuiz->time_limit) You have {{ $lessonQuiz->time_limit }} minutes. @endif
          You can retake if needed.
        </p>
        <div class="flex items-center justify-center gap-3 mt-4">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-1 text-xs text-gray-600 dark:text-gray-300">
            <svg class="w-3 h-3 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            One question at a time
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-1 text-xs text-gray-600 dark:text-gray-300">
            <svg class="w-3 h-3 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            Review before submitting
          </span>
        </div>
      </div>
      @endif

      {{-- Attempt history (shown when more than 1 attempt exists) --}}
      @if($quizAttempts->count() > 1)
      <div>
        <div class="border-l-4 pl-3 mb-2.5" style="border-color: #730DB1;">
          <p class="text-xs font-semibold tracking-widest text-gray-500 uppercase dark:text-gray-400">Your Attempts</p>
        </div>
        <div class="space-y-2">
          @foreach($quizAttempts as $__att)
          <div class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40">
            <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $__att->passed ? 'bg-green-500' : 'bg-red-400' }}"></div>
            <div class="flex-1 min-w-0">
              <span class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ number_format($__att->score, 0) }}%</span>
              <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">{{ $__att->created_at->diffForHumans() }}</span>
            </div>
            <span class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold
                         {{ $__att->passed
                            ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'
                            : 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-300' }}">
              {{ $__att->passed ? 'Passed' : 'Failed' }}
            </span>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Shield cost notice --}}
      @if(!$hasUnlimitedShields)
      <div class="p-4 border rounded-xl border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-900/20">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-xl"
               style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-amber-900 dark:text-amber-200">
              1 shield required &mdash; {{ $shieldsLeft }}/3 remaining today
            </p>
            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
              Pass ({{ $lessonQuiz->passing_score }}%) and your shield is refunded. Fail and it costs 1 shield.
            </p>
          </div>
        </div>
      </div>
      @if($shieldsLeft <= 0)
      <div class="flex items-start gap-3 p-4 border border-red-200 rounded-xl dark:border-red-800 bg-red-50 dark:bg-red-900/20">
        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
          <p class="text-sm font-bold text-red-700 dark:text-red-300">No shields remaining today</p>
          <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">
            Shields reset tomorrow, or <a href="{{ route('subscription.upgrade') }}" class="font-bold underline">upgrade to Premium</a> for unlimited attempts.
          </p>
        </div>
      </div>
      @endif
      @else
      <div class="p-4 border border-green-200 rounded-xl dark:border-green-800/60 bg-green-50 dark:bg-green-900/20">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center flex-shrink-0 bg-green-600 w-9 h-9 rounded-xl">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-green-900 dark:text-green-200">Unli Shields Active</p>
            <p class="text-xs text-green-700 dark:text-green-400 mt-0.5">You can retry quizzes without consuming daily shields.</p>
          </div>
        </div>
      </div>
      @endif

      {{-- Start button --}}
      <button
        @click="startQuiz()"
        @if(!$hasUnlimitedShields && $shieldsLeft <= 0) disabled @endif
        class="w-full flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-sm font-bold text-white transition-all duration-150 hover:opacity-90 hover:scale-[1.01] active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed"
        style="background:linear-gradient(135deg,#A30EB2,#730DB1,#3B0CB1)">
        @if($quizAttempt)
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          Retake Quiz
        @else
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
          Start Quiz
        @endif
      </button>

    </div>
  </div>


  {{-- ══════════════════════════════════════════════════════════════
       STATE: TAKING (quiz wizard)
  ══════════════════════════════════════════════════════════════ --}}
  <div x-show="pageState === 'taking'" x-cloak class="flex flex-col flex-1 min-h-0">

    {{-- Progress zone --}}
    <div class="flex-shrink-0 px-5 py-4 bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">

      <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-sm font-bold text-gray-900 truncate dark:text-white">
          {{ $lessonQuiz->title }}
        </p>
        <div class="flex items-center flex-shrink-0 gap-2">
          @if($lessonQuiz->time_limit)
          <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold transition-colors duration-300"
               :class="{
                 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': timeLeft > 60,
                 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400': timeLeft <= 60 && timeLeft > 30,
                 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 animate-pulse': timeLeft <= 30
               }">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-text="formatTime(timeLeft)"></span>
          </div>
          @endif
        </div>
      </div>

      {{-- Dot tracker --}}
      <div class="flex items-center gap-1.5 flex-wrap">
        @foreach($lessonQuiz->questions as $i => $q)
        <button type="button" @click="jumpTo({{ $i }})"
                class="flex items-center justify-center w-8 h-8 text-xs font-bold transition-all duration-200 rounded-full focus:outline-none"
                :class="{
                  'ring-2 ring-offset-1 ring-purple-400 scale-110': current === {{ $i }},
                  'bg-gray-100 dark:bg-gray-700 text-gray-400': current !== {{ $i }} && !answeredMap[{{ $q->id }}],
                  'text-white': answeredMap[{{ $q->id }}] || current === {{ $i }}
                }"
                :style="(answeredMap[{{ $q->id }}] || current === {{ $i }}) ? 'background:linear-gradient(135deg,#A30EB2,#3B0CB1)' : ''">
          {{ $i + 1 }}
        </button>
        @endforeach
      </div>

      <div class="mt-1.5 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
        <span>Q<strong class="text-gray-700 dark:text-gray-200 ml-0.5" x-text="current + 1"></strong> / {{ $total }}</span>
        <span><span x-text="Object.values(answeredMap).filter(Boolean).length"></span> answered</span>
      </div>
      @if($lessonQuiz->time_limit)
      <div x-show="timeLeft <= 10 && timeLeft > 0"
           x-cloak
           class="px-3 py-2 mt-2 text-xs font-semibold text-red-700 border border-red-200 rounded-xl bg-red-50 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
        <span x-text="timeLeft + ' seconds remaining'"></span>
      </div>
      @endif
      <div class="h-1 mt-1 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
        <div class="h-full transition-all duration-500 rounded-full"
             style="background:linear-gradient(90deg,#A30EB2,#3B0CB1)"
             :style="`width:${Math.round((Object.values(answeredMap).filter(Boolean).length/{{ $total }})*100)}%`"></div>
      </div>
    </div>

    {{-- Scrollable question area --}}
    <div class="flex-1 overflow-y-auto">
      <form method="POST" action="{{ route('quizzes.submit', $lessonQuiz) }}" id="lessonQuizForm">
        @csrf
        <input type="hidden" name="started_at" :value="quizStartedAt ?? ''">
        <input type="hidden" name="auto_submit" id="lesson_auto_submit" value="0">

        @foreach($lessonQuiz->questions as $index => $question)
           <div x-show="current === {{ $index }} && !showReview" x-cloak
             data-lesson-question-index="{{ $index }}"
             data-lesson-question-id="{{ $question->id }}"
             class="max-w-3xl p-5 mx-auto sm:p-6">

          {{-- Question card --}}
          <div class="overflow-hidden bg-white border border-gray-100 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">

            {{-- Question header --}}
            <div class="p-5">
              <div class="flex items-start gap-3">
                <div class="flex items-center justify-center flex-shrink-0 text-sm font-bold text-white rounded-full w-11 h-11"
                     style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
                  {{ $index + 1 }}
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                  <div class="flex flex-wrap gap-1.5 mb-2">
                    @if($question->question_type === 'multiple_choice')
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Multiple Choice</span>
                    @elseif($question->question_type === 'true_false')
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">True / False</span>
                    @elseif($question->question_type === 'multiple_select')
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Multiple Select</span>
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300 border border-amber-200 dark:border-amber-800">Select all that apply</span>
                    @elseif($question->question_type === 'fill_blank_text')
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Fill in the Blank</span>
                    @elseif($question->question_type === 'fill_blank_select')
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">Word Selection</span>
                    @elseif($question->question_type === 'identification')
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300">Identification</span>
                    @endif
                    @if($question->case_sensitive)
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 border border-red-200 dark:border-red-800">Case Sensitive</span>
                    @endif
                  </div>
                  @if(!in_array($question->question_type, ['fill_blank_text', 'fill_blank_select']))
                    <h3 class="text-sm font-semibold leading-snug text-gray-900 dark:text-white">
                      {!! $question->question_text !!}
                    </h3>
                  @endif
                </div>
              </div>
            </div>

            {{-- Answer area --}}
            <div class="px-5 pb-5 space-y-2">

              {{-- Multiple Select --}}
              @if($question->question_type === 'multiple_select')
                @foreach($question->options as $option)
                <label id="lbl_ms_{{ $question->id }}_{{ $option->id }}"
                       class="flex items-center gap-3 p-4 transition-all duration-150 border-2 border-gray-200 cursor-pointer select-none rounded-xl dark:border-gray-600 hover:border-purple-300 dark:hover:border-purple-700 hover:bg-purple-50/50 dark:hover:bg-purple-900/10">
                  <input type="checkbox"
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
                         class="flex-shrink-0 w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                  <span class="text-sm text-gray-800 dark:text-gray-200">{{ $option->option_text }}</span>
                </label>
                @endforeach

              {{-- Fill Blank (text) --}}
              @elseif($question->question_type === 'fill_blank_text')
                @php
                  $parts      = explode('_____', $question->question_text);
                  $blankCount = count($parts) - 1;
                @endphp
                <div class="p-4 border border-gray-200 bg-gray-50 dark:bg-gray-700/40 rounded-xl dark:border-gray-600">
                  @if($blankCount > 0)
                    <p class="text-sm font-medium leading-loose text-gray-800 dark:text-gray-200">
                      @foreach($parts as $pIdx => $part)
                        {!! e($part) !!}
                        @if($pIdx < $blankCount)
                          <input type="text" name="answers[{{ $question->id }}][]"
                                 @input="
                                   let all = [...document.querySelectorAll('[name=\'answers[{{ $question->id }}][]\']')];
                                   all.every(i => i.value.trim()) ? markAnswered({{ $question->id }}) : markUnanswered({{ $question->id }});
                                 "
                                 class="inline-block mx-1 px-2.5 py-1 border-b-2 border-purple-400 dark:border-purple-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-none focus:outline-none focus:border-purple-600 min-w-[100px] text-sm"
                                 placeholder="blank {{ $pIdx + 1 }}" autocomplete="off">
                        @endif
                      @endforeach
                    </p>
                  @else
                    <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $question->question_text }}</p>
                    <input type="text" name="answers[{{ $question->id }}]"
                           @input="$event.target.value.trim() ? markAnswered({{ $question->id }}) : markUnanswered({{ $question->id }})"
                           class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200 dark:focus:ring-purple-800"
                           placeholder="Your answer…" autocomplete="off">
                  @endif
                </div>

              {{-- Fill Blank (word selection) --}}
              @elseif($question->question_type === 'fill_blank_select')
                @php
                  $parts         = explode('_____', $question->question_text);
                  $blankCount    = max(1, count($parts) - 1);
                  $shuffledWords = collect($question->word_bank)->shuffle()->values()->all();
                @endphp
                <div x-data="wordBankQuestion(@js($shuffledWords), {{ $blankCount }}, (answers) => $dispatch('quiz-blank-change', { questionId: {{ $question->id }}, filled: answers.every(Boolean) }))" class="space-y-3">
                  <div class="p-4 border border-gray-200 bg-gray-50 dark:bg-gray-700/40 rounded-xl dark:border-gray-600">
                    <p class="flex flex-wrap items-center gap-1 text-sm font-medium leading-relaxed text-gray-800 dark:text-gray-200">
                      @foreach($parts as $pIdx => $part)
                        <span>{{ $part }}</span>
                        @if($pIdx < $blankCount)
                          <span @click="removeWord({{ $pIdx }})"
                                class="inline-flex items-center justify-center px-3 py-1 min-w-[80px] rounded-lg border-2 border-dashed font-semibold text-sm cursor-pointer transition-all duration-150"
                                :class="selectedWords[{{ $pIdx }}] === null
                                  ? 'border-gray-300 dark:border-gray-600 text-gray-400'
                                  : 'border-purple-400 dark:border-purple-500 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 hover:bg-purple-100'">
                            <span x-show="selectedWords[{{ $pIdx }}] === null" class="text-xs text-gray-400">tap ↓</span>
                            <span x-show="selectedWords[{{ $pIdx }}] !== null" x-text="wordBank[selectedWords[{{ $pIdx }}]]"></span>
                          </span>
                          <input type="hidden" :name="`answers[{{ $question->id }}][{{ $pIdx }}]`"
                                 :value="selectedWords[{{ $pIdx }}] !== null ? wordBank[selectedWords[{{ $pIdx }}]] : ''">
                        @endif
                      @endforeach
                    </p>
                  </div>
                  <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Word Bank</p>
                    <div class="flex flex-wrap gap-2">
                      <template x-for="(word, wi) in wordBank" :key="wi">
                        <button type="button" @click="selectWord(wi)"
                                x-show="!isUsed(wi)" x-text="word"
                                class="px-3 py-1.5 rounded-xl text-sm font-semibold bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-2 border-gray-200 dark:border-gray-600 hover:border-purple-400 dark:hover:border-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-all duration-150 active:scale-95">
                        </button>
                      </template>
                    </div>
                  </div>
                </div>

              {{-- Identification --}}
              @elseif($question->question_type === 'identification')
                @if($question->image_path)
                  <div class="flex justify-center mb-3">
                    <img src="{{ asset('storage/' . $question->image_path) }}" alt="Question image"
                         class="object-contain max-w-full border-2 border-gray-200 shadow-sm max-h-48 rounded-xl dark:border-gray-600">
                  </div>
                @endif
                <input type="text" name="answers[{{ $question->id }}]"
                       @input="$event.target.value.trim() ? markAnswered({{ $question->id }}) : markUnanswered({{ $question->id }})"
                       class="w-full px-4 py-2.5 border-2 border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200 dark:focus:ring-purple-800"
                       placeholder="Your answer…" autocomplete="off">

              {{-- Radio (multiple choice / true-false) --}}
              @else
                @foreach($question->options as $option)
                <label id="lbl_r_{{ $question->id }}_{{ $option->id }}"
                       class="flex items-center gap-3 p-4 transition-all duration-150 border-2 border-gray-200 cursor-pointer select-none rounded-xl dark:border-gray-600 hover:border-purple-300 dark:hover:border-purple-700 hover:bg-purple-50/50 dark:hover:bg-purple-900/10">
                  <input type="radio"
                         name="answers[{{ $question->id }}]"
                         value="{{ $option->id }}"
                         @change="
                           markAnswered({{ $question->id }});
                           document.querySelectorAll('[name=\'answers[{{ $question->id }}]\']').forEach(r => {
                             let l = document.getElementById('lbl_r_{{ $question->id }}_' + r.value);
                             if (!l) return;
                             if (r.checked) { l.classList.add('border-purple-400','bg-purple-50','dark:bg-purple-900/20','dark:border-purple-500'); l.classList.remove('border-gray-200','dark:border-gray-600'); }
                             else           { l.classList.remove('border-purple-400','bg-purple-50','dark:bg-purple-900/20','dark:border-purple-500'); l.classList.add('border-gray-200','dark:border-gray-600'); }
                           });
                         "
                         class="flex-shrink-0 w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                  <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $option->option_text }}</span>
                </label>
                @endforeach
              @endif

            </div>

            {{-- Navigation footer --}}
            <div class="flex items-center gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50 dark:bg-gray-700/30 dark:border-gray-700">
              <button type="button" x-show="current > 0" @click="goBack()"
                      class="flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-150 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
              </button>
              <div class="flex-1"></div>
              <button type="button" @click="goNext()" x-show="!isAnswered({{ $question->id }})"
                      class="px-5 py-2.5 text-sm font-semibold text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-150">
                Skip
              </button>
              <button type="button" @click="goNext()"
                      :disabled="!isAnswered({{ $question->id }})"
                      class="flex items-center gap-1.5 px-6 py-3 rounded-xl text-sm font-bold text-white transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed active:scale-95"
                      :class="isAnswered({{ $question->id }}) ? 'hover:opacity-90 hover:scale-[1.02]' : ''"
                      style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
                <span>{{ $index === $total - 1 ? 'Review Answers' : 'Next' }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>

        </div>
        @endforeach

        {{-- Review screen --}}
        <div x-show="showReview" x-cloak class="max-w-3xl p-5 mx-auto space-y-4 sm:p-6">

          <div class="overflow-hidden bg-white border border-gray-100 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
            <div class="flex items-center gap-3 p-4 border-b border-gray-100 dark:border-gray-700">
              <div class="flex items-center justify-center flex-shrink-0 text-white rounded-full w-11 h-11"
                   style="background:linear-gradient(135deg,#A30EB2,#3B0CB1)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white">Review Your Answers</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tap a question to change your answer.</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2 p-3">
              @foreach($lessonQuiz->questions as $i => $q)
              <button type="button" @click="jumpTo({{ $i }}); showReview = false"
                      class="flex items-center gap-2 p-2.5 rounded-xl border-2 text-left transition-all duration-150 hover:scale-[1.02] active:scale-95"
                      :class="answeredMap[{{ $q->id }}]
                        ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20'
                        : 'border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20'">
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-[9px] font-bold text-white"
                     :style="answeredMap[{{ $q->id }}] ? 'background:#16a34a' : 'background:#ea580c'">
                  {{ $i + 1 }}
                </div>
                <p class="text-[11px] font-semibold truncate"
                   :class="answeredMap[{{ $q->id }}] ? 'text-green-700 dark:text-green-300' : 'text-orange-600 dark:text-orange-400'">
                  {{ Str::limit($q->question_text, 28) }}
                </p>
              </button>
              @endforeach
            </div>

            <div class="flex items-center gap-4 px-4 py-2.5 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700 text-xs font-semibold">
              <span class="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                <span x-text="Object.values(answeredMap).filter(Boolean).length + ' answered'"></span>
              </span>
              <span class="flex items-center gap-1.5 text-orange-500 dark:text-orange-400">
                <span class="w-2 h-2 bg-orange-400 rounded-full"></span>
                <span x-text="({{ $total }} - Object.values(answeredMap).filter(Boolean).length) + ' skipped'"></span>
              </span>
            </div>
          </div>

          @if(!$hasUnlimitedShields)
          <div class="flex items-start gap-3 px-4 py-3 border border-purple-200 rounded-xl dark:border-purple-800 bg-purple-50 dark:bg-purple-900/20">
            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <div>
              <p class="text-sm font-bold text-purple-800 dark:text-purple-200">1 shield on submit</p>
              <p class="text-xs text-purple-600 dark:text-purple-400 mt-0.5">
                Pass ≥{{ $lessonQuiz->passing_score }}% and it's refunded. You have {{ $shieldsLeft }}/3 today.
              </p>
            </div>
          </div>
          @else
          <div class="flex items-start gap-3 px-4 py-3 border border-green-200 rounded-xl dark:border-green-800 bg-green-50 dark:bg-green-900/20">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
            </svg>
            <div>
              <p class="text-sm font-bold text-green-800 dark:text-green-200">Unli Shields Active</p>
              <p class="text-xs text-green-700 dark:text-green-400 mt-0.5">Submit and retry without shield limits.</p>
            </div>
          </div>
          @endif

          <div class="flex items-stretch gap-3">
            <button type="button" @click="showReview = false"
                    class="flex items-center gap-1.5 px-4 py-3 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-150">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              Edit Answers
            </button>
            <button type="submit" form="lessonQuizForm"
                    class="flex-1 flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white transition-all duration-150 hover:opacity-90 hover:scale-[1.01] active:scale-[0.98]"
                    style="background:linear-gradient(135deg,#A30EB2,#730DB1,#3B0CB1)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Submit Quiz
            </button>
          </div>

        </div>
      </form>
    </div>
  </div>


  {{-- ══════════════════════════════════════════════════════════════
       STATE: RESULT
  ══════════════════════════════════════════════════════════════ --}}
  @if($showResult && $resultAttempt)
  <div x-show="pageState === 'result'" x-cloak class="flex-1 overflow-y-auto">
    <div class="max-w-3xl p-5 mx-auto space-y-5">

      {{-- Score ring + headline --}}
      <div class="overflow-hidden bg-white border border-gray-100 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">

        <div class="h-1.5 w-full {{ $resultAttempt->passed ? '' : 'bg-red-500' }}"
             @if($resultAttempt->passed) style="background:linear-gradient(90deg,#A30EB2,#3B0CB1)" @endif></div>

        <div class="flex flex-col items-center p-6 text-center">

          {{-- SVG Score ring --}}
          <div class="relative mb-4 w-28 h-28">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor"
                      class="text-gray-100 dark:text-gray-700" stroke-width="10"/>
              <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round"
                      @if($resultAttempt->passed) stroke="url(#rGrad)" @else stroke="#ef4444" @endif
                      stroke-dasharray="{{ round(2 * 3.14159 * 52 * $resultAttempt->score / 100, 1) }} 327"/>
              @if($resultAttempt->passed)
              <defs>
                <linearGradient id="rGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#A30EB2"/>
                  <stop offset="100%" stop-color="#3B0CB1"/>
                </linearGradient>
              </defs>
              @endif
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-2xl font-bold {{ $resultAttempt->passed ? 'text-gray-900 dark:text-white' : 'text-red-500' }}">
                {{ $resultAttempt->score }}%
              </span>
              <span class="text-[9px] font-bold uppercase tracking-widest {{ $resultAttempt->passed ? 'text-purple-600 dark:text-purple-400' : 'text-red-400' }}">
                {{ $resultAttempt->passed ? 'Passed' : 'Failed' }}
              </span>
            </div>
          </div>

          <h3 class="text-xl font-bold {{ $resultAttempt->passed ? 'text-gray-900 dark:text-white' : 'text-red-500' }}">
            {{ $resultAttempt->passed ? 'You Passed!' : 'Keep Going!' }}
          </h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $resultCorrect }} of {{ $resultTotal }} correct &nbsp;&middot;&nbsp; Passing: {{ $lessonQuiz->passing_score }}%
          </p>

          {{-- Gamification chips --}}
          <div class="flex flex-wrap items-center justify-center gap-2 mt-4">
            @if($xpEarned)
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
              <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
              </svg>
              <span class="text-xs font-bold text-amber-700 dark:text-amber-300">+{{ $xpEarned }} XP</span>
            </div>
            @endif
            @if(!$hasUnlimitedShields)
              @if($shieldDelta === 0)
              <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700">
                <svg class="w-3.5 h-3.5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="text-xs font-bold text-green-700 dark:text-green-300">Shield Protected</span>
              </div>
              @elseif($shieldDelta === -1)
              <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700">
                <svg class="w-3.5 h-3.5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="text-xs font-bold text-red-600 dark:text-red-400">-1 Shield</span>
              </div>
              @endif
            @else
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
              <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/>
              </svg>
              <span class="text-xs font-bold text-amber-700 dark:text-amber-300">Unli Shields</span>
            </div>
            @endif
          </div>

          {{-- Stats row --}}
          <div class="grid w-full grid-cols-3 gap-2 mt-4">
            <div class="text-center p-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
              <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $resultTotal }}</p>
              <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Total</p>
            </div>
            <div class="text-center p-2.5 bg-green-50 dark:bg-green-900/20 rounded-xl">
              <p class="text-lg font-bold text-green-600 dark:text-green-400">{{ $resultCorrect }}</p>
              <p class="text-[10px] text-green-600 dark:text-green-500 mt-0.5">Correct</p>
            </div>
            <div class="text-center p-2.5 bg-red-50 dark:bg-red-900/20 rounded-xl">
              <p class="text-lg font-bold text-red-500 dark:text-red-400">{{ $resultTotal - $resultCorrect }}</p>
              <p class="text-[10px] text-red-500 dark:text-red-400 mt-0.5">Wrong</p>
            </div>
          </div>

          {{-- Action buttons --}}
          <div class="flex items-stretch w-full gap-3 mt-5">
            @php $canRetry = $hasUnlimitedShields || (($shieldsLeft ?? 0) > 0); @endphp

            <button type="button" @click="pageState = 'landing'"
                    class="flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-150 active:scale-95">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              Back
            </button>

            @if(!$resultAttempt->passed)
              @if($canRetry)
              <button type="button" @click="startQuiz()"
                      class="flex-1 flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-all duration-150 hover:opacity-90 hover:scale-[1.02] active:scale-[0.98]"
                      style="background:linear-gradient(135deg,#A30EB2,#730DB1,#3B0CB1)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Try Again
              </button>
              @else
              <div class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                No Shields Left
              </div>
              @endif
            @else
              @if($nextLesson)
                <a href="{{ route('learner.lessons.show', $nextLesson) }}"
                   class="flex-1 flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-all duration-150 hover:opacity-90 hover:scale-[1.02] active:scale-[0.98]"
                   style="background:linear-gradient(135deg,#A30EB2,#730DB1,#3B0CB1)">
                  Proceed to Next Lesson
                </a>
              @else
                <button type="button"
                        @click="showCompletionModal = true"
                        class="flex-1 flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-all duration-150 hover:opacity-90 hover:scale-[1.02] active:scale-[0.98]"
                        style="background:linear-gradient(135deg,#A30EB2,#730DB1,#3B0CB1)">
                  View Completion Options
                </button>
              @endif
            @endif
          </div>

        </div>
      </div>

      {{-- Question scorecard (number only — no answers shown to learners) --}}
      <div class="overflow-hidden bg-white border border-gray-100 shadow-sm dark:bg-gray-800 rounded-2xl dark:border-gray-700">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
          <div class="pl-3 border-l-4 border-purple-400">
            <p class="text-sm font-bold text-gray-900 dark:text-white">Question Scorecard</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Which questions you got right and wrong</p>
          </div>
        </div>
        <div class="p-4">
          <div class="flex flex-wrap gap-2">
            @foreach($resultAttempt->quiz->questions as $rIdx => $rQ)
              @php
                $rAns   = $resultAttempt->answers[$rQ->id] ?? null;
                $rRight = $rAns['is_correct'] ?? false;
              @endphp
              <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0
                          {{ $rRight ? 'bg-green-500' : 'bg-red-500' }}">
                {{ $rIdx + 1 }}
              </div>
            @endforeach
          </div>
          <div class="flex items-center gap-4 mt-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1.5">
              <span class="flex-shrink-0 w-3 h-3 bg-green-500 rounded-full"></span>
              Correct
            </span>
            <span class="flex items-center gap-1.5">
              <span class="flex-shrink-0 w-3 h-3 bg-red-500 rounded-full"></span>
              Wrong
            </span>
          </div>
        </div>
      </div>

      @if($resultAttempt->passed && !$nextLesson)
      <div x-show="showCompletionModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-purple-500">Module Completion</p>
          <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-gray-100">Congratulations!</h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">You passed the final lesson quiz and completed this module.</p>
          <div class="mt-5 flex flex-col sm:flex-row gap-2">
            @if($certificateEligible)
              @if($moduleCertificate)
                <a href="{{ route('learner.certificates.show', $moduleCertificate) }}" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white hover:opacity-90 transition" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                  View Certificate
                </a>
              @else
                <form method="POST" action="{{ route('learner.certificates.check', $module) }}" class="flex-1">
                  @csrf
                  <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white hover:opacity-90 transition" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                    Claim Certificate
                  </button>
                </form>
              @endif
            @else
              <a href="{{ route('learner.modules.show', $module) }}" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white hover:opacity-90 transition" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                Return to Module
              </a>
            @endif
            <button type="button" @click="showCompletionModal = false" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
              Close
            </button>
          </div>
        </div>
      </div>
      @endif

    </div>
  </div>
  @endif

</div>{{-- end quiz page root --}}

@push('scripts')
<script>
if (typeof quizWizard === 'undefined') {
  function quizWizard() { return {}; } // placeholder — wizard is embedded in quiz-page x-data
}
</script>
@endpush
