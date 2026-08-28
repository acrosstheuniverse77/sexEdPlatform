@extends('layouts.learner-fullscreen')

@section('title', $lesson->title)
@section('back-url', route('learner.modules.show', $module))
@section('module-title', $module->title)
@section('lesson-title', $lesson->title)

@section('progress-bar')
    @php
        $requiredTopics = $lessonTopics->where('type', '!=', 'interactive_checkpoint');
        $topicCount  = $requiredTopics->count();
        $doneCount   = $requiredTopics->whereIn('id', $completedTopicIds)->count();
        $progressPct = $topicCount > 0 ? round(($doneCount / $topicCount) * 100) : 0;
    @endphp
    <div class="flex items-center gap-2 w-full">
        <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500"
                 style="width: {{ $progressPct }}%; background: linear-gradient(to right, #A30EB2, #3B0CB1);"></div>
        </div>
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
            {{ $doneCount }}/{{ $topicCount }} topics
        </span>
    </div>
@endsection

@section('content')
<div class="flex h-full min-h-0 overflow-hidden"
     x-data="{ ...checkpointCoordinator(), sidebarOpen: JSON.parse(localStorage.getItem('lessonSidebarOpen') ?? 'true') }"
     @checkpoint-active.window="activate($event.detail.questionId)"
     @checkpoint-continued.window="release($event.detail.questionId)"
     x-effect="localStorage.setItem('lessonSidebarOpen', JSON.stringify(sidebarOpen))">

    {{-- ═══════════════════════════════════════════
         LEFT SIDEBAR — Module Curriculum Overview
    ═══════════════════════════════════════════ --}}
    @php
        $_totalLessons = $allLessons->count();
        $_doneLessons  = count($completedLessonIds);
        $_modPct       = $_totalLessons > 0 ? round($_doneLessons / $_totalLessons * 100) : 0;

        // Pre-compute locked lessons for sequential access enforcement
        $sidebarLockedIds = [];
        $__prevDone = true;
        foreach ($allLessons as $__si => $__sl) {
            if ($__si > 0 && !$__prevDone) {
                $sidebarLockedIds[] = $__sl->id;
            }
            $__prevDone = in_array($__sl->id, $completedLessonIds);
        }
    @endphp

    <aside :class="sidebarOpen ? 'w-[280px] min-w-[280px] border-r border-gray-200 dark:border-gray-800' : 'w-0 min-w-0'"
           class="flex-shrink-0 h-full flex flex-col bg-white dark:bg-gray-900 overflow-hidden transition-all duration-300 ease-in-out"
           x-data="{
               q: '',
               open: {{ $lesson->id }},
               lessonVisible(sc) {
                   const term = this.q.toLowerCase().trim();
                   return !term || sc.includes(term);
               },
               lessonOpen(id, sc) {
                   const term = this.q.toLowerCase().trim();
                   if (term) return sc.includes(term);
                   return this.open === id;
               }
           }">

        {{-- Module Progress Header --}}
        <div class="px-4 pt-4 pb-3 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
            <p class="text-xs font-semibold text-purple-600 dark:text-purple-400 truncate" title="{{ $module->title }}">{{ $module->title }}</p>
            <div class="flex items-center justify-between mt-2 mb-1.5">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $_doneLessons }}/{{ $_totalLessons }} lessons</p>
                <span class="text-sm font-bold" style="color: #A30EB2;">{{ $_modPct }}%</span>
            </div>
            <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500"
                     style="width: {{ $_modPct }}%; background: linear-gradient(to right, #A30EB2, #3B0CB1);"></div>
            </div>
        </div>

        {{-- Search --}}
        <div class="px-3 py-2.5 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="q"
                       placeholder="Search lessons, topics, quizzes…"
                       class="w-full pl-8 pr-7 py-2 text-sm rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                <button x-show="q" @click="q = ''" x-cloak
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Scrollable lesson accordion --}}
        <div class="flex-1 overflow-y-auto">
            @foreach($allLessons as $__l)
                @php
                    $__lIsCompleted = in_array($__l->id, $completedLessonIds);
                    $__lIsCurrent   = $__l->id === $lesson->id;
                    $__lIsLocked    = in_array($__l->id, $sidebarLockedIds);
                    $__lTopics      = $__l->topics;
                    $__lRequiredTopics = $__lTopics->where('type', '!=', 'interactive_checkpoint');
                    $__lTopicCount  = $__lRequiredTopics->count();
                    $__lDoneCount   = $__lRequiredTopics->filter(fn($t) => in_array($t->id, $allCompletedTopicIds))->count();
                    $__lQuiz        = $__l->quiz;
                    $__lTotalItems  = $__lTopicCount + ($__lQuiz ? 1 : 0);
                    $__lDoneItems   = $__lDoneCount + (($__lIsCurrent && ($quizAttempt?->passed ?? false)) ? 1 : 0);
                    // Build searchable content string: lesson + all topic titles + quiz title
                    $__sc = strtolower(
                        $__l->title . ' ' .
                        $__lTopics->pluck('title')->implode(' ') . ' ' .
                        ($__lQuiz?->title ?? '')
                    );
                @endphp

                <div class="border-b border-gray-100 dark:border-gray-800 last:border-b-0"
                     x-show="lessonVisible({{ json_encode($__sc) }})">

                    {{-- ── Lesson Header ── --}}
                    @if($__lIsLocked)
                        <div class="flex items-center gap-3 px-4 py-3.5 opacity-40 cursor-not-allowed select-none">
                            <div class="flex-shrink-0 w-7 h-7 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 leading-snug">{{ $__l->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $__lTotalItems }} items · Locked</p>
                            </div>
                        </div>
                    @else
                        <button type="button"
                                @click="open = open === {{ $__l->id }} ? null : {{ $__l->id }}"
                                class="w-full flex items-center gap-3 px-4 py-3.5 text-left transition-colors
                                       {{ $__lIsCurrent ? 'bg-violet-50 dark:bg-violet-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                            <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center
                                {{ $__lIsCompleted
                                    ? 'bg-emerald-100 dark:bg-emerald-900/40'
                                    : ($__lIsCurrent
                                        ? 'bg-violet-100 dark:bg-violet-900/30 border-2 border-violet-500'
                                        : 'border-2 border-gray-200 dark:border-gray-600') }}">
                                @if($__lIsCompleted)
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                @elseif($__lIsCurrent)
                                    <div class="w-2.5 h-2.5 rounded-full bg-violet-500"></div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold leading-snug
                                          {{ $__lIsCurrent ? 'text-violet-700 dark:text-violet-300' : 'text-gray-800 dark:text-gray-100' }}">
                                    {{ $__l->title }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $__lDoneItems }}/{{ $__lTotalItems }} items</p>
                            </div>
                            <svg class="flex-shrink-0 w-4 h-4 text-gray-400 transition-transform duration-200"
                                 :class="lessonOpen({{ $__l->id }}, {{ json_encode($__sc) }}) ? 'rotate-180' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    @endif

                    {{-- ── Expanded stepper (flex-column — zero absolute positioning, overflow safe) ── --}}
                    @if(!$__lIsLocked)
                    <div x-show="lessonOpen({{ $__l->id }}, {{ json_encode($__sc) }})"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         class="px-4 pt-1 pb-2">

                        @foreach($__lTopics as $__tIdx => $__t)
                            @php
                                $__tIsLast  = $loop->last && !$__lQuiz;
                                $__tDone    = in_array($__t->id, $allCompletedTopicIds);
                                $__tCurrent = $__lIsCurrent && $currentTopicIndex === $__tIdx && !request()->has('quiz');
                                $__tLocked  = $__lIsCurrent && in_array($__t->id, $lockedTopicIds);
                                $__tLabel   = match($__t->type) {
                                    'interactive_checkpoint' => 'QUICK CHECK',
                                    'video'       => 'VIDEO',
                                    'text'        => 'TEXT',
                                    'worksheet'   => 'FILE',
                                    'quiz'        => 'QUIZ',
                                    'interactive' => 'INTERACTIVE',
                                    default       => 'CONTENT',
                                };
                                $__tUrl = route('learner.lessons.show', ['lesson' => $__l->id, 'topic' => $__tIdx]);
                            @endphp

                            {{-- Flex-column stepper row --}}
                            <div class="flex gap-3 {{ $__tLocked ? 'opacity-50' : '' }}">
                                {{-- Left: dot + connector --}}
                                <div class="flex flex-col items-center flex-shrink-0" style="width:16px;">
                                <div class="mt-3 flex-shrink-0 w-3.5 h-3.5 rounded-full flex items-center justify-center
                                            {{ $__tDone
                                                ? 'bg-violet-600'
                                                : ($__tCurrent
                                                    ? 'bg-white border-2 border-violet-500'
                                                    : 'bg-white border-2 border-gray-300 dark:border-gray-600') }}">
                                        @if($__tDone)
                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </div>
                                    @if(!$__tIsLast)
                                        <div class="flex-1 mt-1 min-h-[8px] rounded-full"
                                             style="width:2px; background:{{ $__tDone ? '#c4b5fd' : '#e5e7eb' }};"></div>
                                    @endif
                                </div>
                                {{-- Right: content --}}
                                <div class="flex-1 min-w-0 {{ !$__tIsLast ? 'pb-3' : 'pb-1' }}">
                                    @if($__tLocked)
                                        <div class="py-0.5 cursor-not-allowed">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 leading-snug">{{ $__t->title }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $__t->duration }}m · {{ $__tLabel }}{{ $__t->is_prerequisite ? ' · Required' : '' }}</p>
                                        </div>
                                    @else
                                        <a href="{{ $__tUrl }}"
                                           class="block rounded-lg py-0.5 px-2 -mx-2 transition-colors
                                                  {{ $__tCurrent ? 'bg-violet-50 dark:bg-violet-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                                            <p class="text-sm font-medium leading-snug
                                                       {{ $__tCurrent ? 'text-violet-700 dark:text-violet-300' : 'text-gray-700 dark:text-gray-200' }}">
                                                {{ $__t->title }}
                                            </p>
                                            <p class="text-xs mt-0.5 {{ $__tCurrent ? 'text-violet-500' : 'text-gray-400 dark:text-gray-500' }}">
                                                {{ $__t->duration }}m · {{ $__tLabel }}{{ $__t->is_prerequisite ? ' · Required' : '' }}
                                            </p>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- Quiz step (always shown if quiz exists) --}}
                        @if($__lQuiz)
                            @php
                                $__qActive  = $__lIsCurrent && request()->has('quiz');
                                $__qDone    = $__lIsCurrent && ($quizAttempt?->passed ?? false);
                                $__qLocked  = $__lDoneCount < $__lTopicCount;
                                $__qUrl     = route('learner.lessons.show', ['lesson' => $__l->id, 'quiz' => 1]);
                            @endphp
                            <div class="flex gap-3 {{ $__qLocked ? 'opacity-50' : '' }}">
                                <div class="flex flex-col items-center flex-shrink-0" style="width:16px;">
                                    <div class="mt-3 flex-shrink-0 w-3.5 h-3.5 rounded-full flex items-center justify-center
                                                {{ $__qDone
                                                    ? 'bg-violet-600'
                                                    : ($__qActive
                                                        ? 'bg-white border-2 border-violet-500'
                                                        : 'bg-white border-2 border-gray-300 dark:border-gray-600') }}">
                                        @if($__qDone)
                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </div>
                                    {{-- No connector after last item --}}
                                </div>
                                <div class="flex-1 min-w-0 pb-1">
                                    @if($__qLocked)
                                        <div class="py-0.5 cursor-not-allowed">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 leading-snug">{{ $__lQuiz->title }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $__lQuiz->questions->count() }} questions · QUIZ · Complete topics first</p>
                                        </div>
                                    @else
                                        <a href="{{ $__qUrl }}"
                                           class="block rounded-lg py-0.5 px-2 -mx-2 transition-colors
                                                  {{ $__qActive ? 'bg-violet-50 dark:bg-violet-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                                            <p class="text-sm font-medium leading-snug
                                                       {{ $__qActive ? 'text-violet-700 dark:text-violet-300' : 'text-gray-700 dark:text-gray-200' }}">
                                                {{ $__lQuiz->title }}
                                            </p>
                                            <p class="text-xs mt-0.5 {{ $__qActive ? 'text-violet-500' : 'text-gray-400 dark:text-gray-500' }}">
                                                {{ $__lQuiz->questions->count() }} questions · QUIZ
                                            </p>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                    </div>
                    @endif

                </div>{{-- end lesson item --}}
            @endforeach
        </div>{{-- end scrollable --}}

          <div class="px-3 py-3 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
            <div class="rounded-xl border border-purple-100 dark:border-purple-800/40 bg-purple-50/60 dark:bg-purple-900/20 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Module Certificate</p>

                @if($moduleCertificate && $certificateEligible)
                    <a href="{{ route('learner.certificates.show', $moduleCertificate) }}"
                       class="mt-2 inline-flex w-full items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-white transition hover:opacity-90"
                       style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                        View Certificate
                    </a>
                @elseif($certificateEligible)
                    <form method="POST" action="{{ route('learner.certificates.check', $module) }}" class="mt-2">
                        @csrf
                        <button type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/25 border border-emerald-200 dark:border-emerald-800/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/35 transition-colors">
                            Get Certificate
                        </button>
                    </form>
                @else
                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                        Complete all lessons, lesson topics, and quizzes to unlock your certificate.
                    </p>
                @endif
            </div>
        </div>

        {{-- Footer: Back to Module --}}
        <div class="flex-shrink-0 px-4 py-3 border-t border-gray-100 dark:border-gray-800">
            <a href="{{ route('learner.modules.show', $module) }}"
               class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Module
            </a>
        </div>
    </aside>

    {{-- ═══════════════════════════════════════════
         RIGHT — Main Content
    ═══════════════════════════════════════════ --}}
    <main class="flex-1 min-w-0 flex flex-col overflow-hidden bg-gray-50 dark:bg-gray-950">
        {{-- Sidebar toggle + breadcrumb strip --}}
        <div class="flex-shrink-0 flex items-center gap-2 px-3 pt-3 pb-1">
            <button type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    :title="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'">
                {{-- Chevron-double-left when open, double-right when closed --}}
                <svg x-show="sidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
                <svg x-show="!sidebarOpen" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                </svg>
            </button>
            <span class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $lesson->title }}</span>
            @if($module->created_by && $module->created_by !== auth()->id())
                <button
                    type="button"
                    @click="$dispatch('open-global-chat', {
                        target_user_id: {{ $module->created_by }},
                        name: '{{ addslashes($module->creator?->name ?? 'Instructor') }}',
                        avatar: 'https://ui-avatars.com/api/?name={{ urlencode($module->creator?->name ?? 'Instructor') }}&color=1D4ED8&background=EFF6FF',
                        conversation_type: 'lesson_chat',
                        lesson_id: {{ $lesson->id }}
                    })"
                    class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-800 hover:bg-blue-100"
                >
                    Ask Instructor About This Lesson
                </button>
            @endif
        </div>
        @if($currentTopic && !request()->has('quiz') && $lessonTopics->count() > 1)
            <div class="flex-shrink-0 flex items-center justify-center gap-1.5 px-4 pb-2">
                @foreach($lessonTopics as $__dot)
                    @php
                        $__dotDone = in_array($__dot->id, $completedTopicIds)
                            || in_array($__dot->id, $resolvedCheckpointTopicIds);
                        $__dotCurrent = $__dot->id === $currentTopic->id;
                    @endphp
                    <div class="rounded-full transition-all duration-300 {{ $__dotCurrent ? 'h-3 w-3 ring-2 ring-offset-1' : 'h-2 w-2' }}"
                         style="{{ $__dotDone || $__dotCurrent
                             ? 'background: linear-gradient(135deg, #A30EB2, #3B0CB1);' . ($__dotCurrent ? ' --tw-ring-color: #A30EB2;' : '')
                             : 'background-color: #e5e7eb;' }}"
                         title="{{ $__dot->title }}"></div>
                @endforeach
            </div>
        @endif
        {{-- Scrollable content --}}
        <div class="flex-1 min-h-0 overflow-y-auto">
            <div class="max-w-4xl mx-auto px-4 py-6">

                @if(request()->has('quiz') && $lessonQuiz)
                    @include('learner.lessons.partials.quiz-page')
                @elseif($currentTopic)
                    @include('learner.lessons.partials.topic-page')
                @else
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-12 text-center">
                        <svg class="mx-auto h-14 w-14 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No content available for this lesson.</p>
                    </div>
                @endif

            </div>
        </div>

        {{-- ── Bottom Action Bar — sticky, always visible ── --}}
        @if($currentTopic && !request()->has('quiz'))
            @php
                $__barDone = $currentTopic->type !== 'interactive_checkpoint'
                    && in_array($currentTopic->id, $completedTopicIds);
            @endphp
            <div data-lesson-footer
                 class="flex-shrink-0 border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
                 style="height: calc(4.5rem + env(safe-area-inset-bottom)); padding-bottom: env(safe-area-inset-bottom);">
                <div class="flex h-[4.5rem] items-center justify-between gap-3 px-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        @if($currentTopicIndex > 0)
                            <a href="{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $currentTopicIndex - 1]) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                <span class="hidden sm:inline">Previous</span>
                                <span class="sm:hidden">Back</span>
                            </a>
                        @endif
                        @if($__barDone)
                            <form action="{{ route('learner.topics.uncomplete', $currentTopic) }}" method="POST">
                                @csrf
                                <button type="submit" class="whitespace-nowrap text-xs text-gray-400 underline underline-offset-2 transition-colors hover:text-red-400 dark:text-gray-500">Mark as Incomplete</button>
                            </form>
                        @endif
                    </div>
                    <div x-show="footerForwardVisible()" x-cloak class="flex-shrink-0">
                        @include('learner.lessons.partials.lesson-forward-action')
                    </div>
                </div>
            </div>
        @endif
    </main>
</div>
@endsection
