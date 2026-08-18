{{-- resources/views/parent/children/show.blade.php --}}
@extends('layouts.learner-app')

@section('title', $child->name . ' — Monitoring')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @php
        $childAvatarUrl = $child->learnerProfile?->avatar_path
            ? asset('storage/' . ltrim((string) $child->learnerProfile->avatar_path, '/'))
            : null;
        $relationshipLabel = \App\Support\GuardianRelationshipTypes::label($parentChildLink?->pivot?->relationship_type ?? null, $parentChildLink?->pivot?->relationship_custom ?? null);
        $relationshipStatus = $parentChildLink?->pivot?->relationship_status ?? 'active';
    @endphp

    {{-- Back link + child header --}}
    <div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <a href="{{ route('parent.children.index') }}"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to My Dependents
            </a>
            <button type="button"
                    onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $child->id }}, conversation_type: "direct", name: @json($child->full_name ?: $child->name) } }))'
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                Message Dependent
            </button>
        </div>

        <div class="flex items-center gap-4 mt-3">
            @if($childAvatarUrl)
                <img src="{{ $childAvatarUrl }}"
                     alt="{{ $child->full_name ?: $child->name }} avatar"
                     class="w-14 h-14 rounded-full object-cover border border-gray-200 flex-shrink-0">
            @else
                <div class="w-14 h-14 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
                     style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                    {{ strtoupper(mb_substr($child->name, 0, 2)) }}
                </div>
            @endif
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $child->name }}</h1>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                        {{ $relationshipLabel }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                        {{ ucfirst($relationshipStatus) }}
                    </span>
                </div>
                @if($child->learnerProfile && $child->learnerProfile->birthdate)
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-sm text-gray-500">{{ $child->learnerProfile->getAge() }} years old</span>
                        @php $bracket = $child->learnerProfile->getAgeBracket(); @endphp
                        @if($bracket)
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-700">
                                {{ ucfirst($bracket) }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 text-sm">
            {{ session('info') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div x-data="{ tab: 'progress' }">

        {{-- Tab nav --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700 gap-1 overflow-x-auto">
            <button
                @click="tab = 'progress'"
                :style="tab === 'progress' ? 'border-color: #A30EB2; color: #A30EB2;' : ''"
                class="px-4 py-2.5 text-sm whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors"
                :class="tab === 'progress' ? 'font-semibold' : ''">
                Progress
            </button>
            <button
                @click="tab = 'quiz'"
                :style="tab === 'quiz' ? 'border-color: #A30EB2; color: #A30EB2;' : ''"
                class="px-4 py-2.5 text-sm whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors"
                :class="tab === 'quiz' ? 'font-semibold' : ''">
                Quiz Results
            </button>
            <button
                @click="tab = 'achievements'"
                :style="tab === 'achievements' ? 'border-color: #A30EB2; color: #A30EB2;' : ''"
                class="px-4 py-2.5 text-sm whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors"
                :class="tab === 'achievements' ? 'font-semibold' : ''">
                Achievements
            </button>
            @if($canApproveContent)
                <button
                    @click="tab = 'approval'"
                    :style="tab === 'approval' ? 'border-color: #A30EB2; color: #A30EB2;' : ''"
                    class="px-4 py-2.5 text-sm whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors"
                    :class="tab === 'approval' ? 'font-semibold' : ''">
                    Content Approval
                    @if($pendingEnrollments->isNotEmpty())
                        <span class="ml-1 px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">
                            {{ $pendingEnrollments->count() }}
                        </span>
                    @endif
                </button>
            @endif
        </div>

        {{-- ── Progress Tab ── --}}
        <div x-show="tab === 'progress'" x-cloak class="pt-6">
            @if($progress->isEmpty())
                <div class="text-center py-12 text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5"/>
                    </svg>
                    <p class="text-sm">No modules enrolled yet.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($progress as $enrollment)
                        @php
                            $module = $enrollment->module;
                            $creator = $module?->creator;
                            $thumbnailUrl = $module?->thumbnail_url;
                        @endphp
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                    @if($thumbnailUrl)
                                        <img src="{{ $thumbnailUrl }}"
                                             alt="{{ $module?->title ?? 'Module' }} thumbnail"
                                             class="w-14 h-14 rounded-xl object-cover border border-gray-200 dark:border-gray-600 flex-shrink-0">
                                    @else
                                        <div class="w-14 h-14 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 text-xs font-semibold flex items-center justify-center flex-shrink-0">
                                            MODULE
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $module?->title ?? 'Module' }}
                                        </h3>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Instructor: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $creator?->name ?? 'Unavailable' }}</span>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                        Enrolled {{ $enrollment->enrolled_at?->diffForHumans() ?? 'recently' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="text-lg font-bold flex-shrink-0" style="color: #A30EB2;">
                                    {{ $enrollment->progress_pct }}%
                                </span>
                            </div>
                            <div class="mt-3">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>{{ $enrollment->completed_lessons }} of {{ $enrollment->total_lessons }} lessons complete</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all"
                                         style="width: {{ $enrollment->progress_pct }}%; background: linear-gradient(90deg, #A30EB2, #3B0CB1);">
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs text-gray-500">
                                        {{ ucfirst((string) ($module?->access_type ?? 'free')) }} access
                                        · {{ ucfirst((string) ($module?->enrollment_mode ?? 'auto')) }} enrollment
                                    </p>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <a href="{{ route('parent.children.enrollments.show', [$child, $enrollment]) }}"
                                       class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                                        {{ (int) ($enrollment->total_lessons ?? 0) > 0 ? 'View Lesson Details' : 'View Module Details' }}
                                    </a>
                                    <button type="button"
                                            @click="tab = 'quiz'"
                                            class="inline-flex items-center rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50">
                                        Open Quiz Results
                                    </button>

                                    @if($creator)
                                        <button
                                            type="button"
                                            onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $creator->id }}, conversation_type: "module_chat", module_id: {{ (int) $module->id }}, name: @json($creator->name) } }))'
                                            class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                        >
                                            Message Instructor
                                        </button>
                                    @else
                                        <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-500">
                                            Instructor unavailable
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Quiz Results Tab ── --}}
        <div x-show="tab === 'quiz'" x-cloak class="pt-6">
            @if(! $canViewQuizAnswers)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Quiz answer review is disabled for this child account.
                </div>
            @elseif($quizResults->isEmpty())
                <div class="text-center py-12 text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm">No quizzes taken yet.</p>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3 text-left">Quiz</th>
                                <th class="px-5 py-3 text-left">Module</th>
                                <th class="px-5 py-3 text-center">Score</th>
                                <th class="px-5 py-3 text-center">Result</th>
                                <th class="px-5 py-3 text-right">Date</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($quizResults as $attempt)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $attempt->quiz?->title ?? 'Quiz' }}
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">
                                        {{ $attempt->quiz?->module?->title ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-center font-semibold">{{ $attempt->score }}%</td>
                                    <td class="px-5 py-3 text-center">
                                        @if($attempt->passed)
                                            <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Passed</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Failed</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-400">
                                        {{ $attempt->completed_at?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('parent.children.quiz-attempts.show', [$child, $attempt]) }}"
                                           class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ── Achievements Tab ── --}}
        <div x-show="tab === 'achievements'" x-cloak class="pt-6 space-y-6">
            @php
                $gamification = $achievements['gamification'];
                $rewardLogs   = $achievements['rewardLogs'];
            @endphp

            {{-- Gamification summary --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 text-center">
                    <p class="text-3xl font-bold" style="color: #A30EB2;">{{ $gamification?->level ?? 1 }}</p>
                    <p class="text-xs text-gray-400 mt-1">Level</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 text-center">
                    <p class="text-3xl font-bold" style="color: #730DB1;">{{ $gamification?->score ?? 0 }}</p>
                    <p class="text-xs text-gray-400 mt-1">XP</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 text-center">
                    <p class="text-3xl font-bold" style="color: #3B0CB1;">{{ $gamification?->streak_count ?? 0 }}</p>
                    <p class="text-xs text-gray-400 mt-1">Day Streak</p>
                </div>
            </div>

            {{-- Reward log --}}
            @if($rewardLogs->isEmpty())
                <div class="text-center py-8 text-gray-400">
                    <p class="text-sm">No rewards earned yet.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($rewardLogs as $log)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 px-5 py-4 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xl flex-shrink-0"
                                 style="background: linear-gradient(135deg, #f3e8ff, #ede9fe);">
                                {{ $log->achievement?->icon ?? '🏆' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $log->achievement?->title ?? 'Achievement' }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $log->earned_at?->format('M d, Y') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Content Approval Tab (only if can_approve_content) ── --}}
        @if($canApproveContent)
            <div x-show="tab === 'approval'" x-cloak class="pt-6">
                @if($pendingEnrollments->isEmpty())
                    <div class="text-center py-12 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm">No pending enrollment requests.</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($pendingEnrollments as $enrollment)
                            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ $enrollment->module->title }}
                                        </h3>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            Ages {{ $enrollment->module->min_age }}–{{ $enrollment->module->max_age }}
                                            · Requested {{ $enrollment->created_at->diffForHumans() }}
                                        </p>
                                        <span class="mt-2 inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                                            Pending Guardian Approval
                                        </span>
                                        <a href="{{ route('parent.children.enrollments.show', [$child, $enrollment]) }}"
                                           class="mt-2 inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                                            View Details
                                        </a>
                                    </div>
                                    <div class="flex gap-2 flex-shrink-0">
                                        <a href="{{ route('parent.children.enrollments.show', [$child, $enrollment]) }}"
                                           class="px-4 py-2 text-sm font-semibold text-white rounded-xl hover:opacity-90 transition"
                                           style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                            Review & Decide
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </div>{{-- /x-data tabs --}}

</div>
@endsection
