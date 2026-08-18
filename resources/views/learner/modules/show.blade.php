@extends('layouts.learner-app')

@section('title', $module->title)

@section('content')
@php
    use App\Services\EntitlementService;
    use App\Support\SubscriptionFeatureKeys;

    /** @var \App\Models\User $authUser */
    $authUser = auth()->user();
    $gami    = $authUser->gamification;
    $hasUnlimitedShields = app(EntitlementService::class)->canAccessFeature($authUser, SubscriptionFeatureKeys::UNLIMITED_SHIELDS);

    $isPaidModule = $isPaidModule ?? $module->isPaidAccess();
    $hasPurchased = $hasPurchased ?? false;
    $approvedEnrollmentsCount = $approvedEnrollmentsCount ?? 0;
    $isAtCapacity = $isAtCapacity ?? false;
    $canPurchase = $canPurchase ?? false;
    $isOwnedByCurrentUser = $isOwnedByCurrentUser ?? $module->isOwnedBy($authUser);
    $needsParentApproval = $needsParentApproval ?? false;
    $isParentApprovedForPurchase = $isParentApprovedForPurchase ?? false;

    $ownershipDisplay = $ownershipDisplay ?? app(\App\Services\Content\AdminOwnershipDisplayService::class)->forModule($module);
    $creator = $module->creator;
    $ownerType = (string) ($ownershipDisplay['owner_type'] ?? 'instructor');
    $instructorProfile = $creator?->instructorProfile;
    $instructorName = $creator?->full_name ?: $creator?->name ?: 'Instructor';
    $displayOwnerName = (string) ($ownershipDisplay['display_owner_name'] ?? ($creator?->full_name ?: $creator?->name ?: 'Instructor'));
    $creatorProfile = $creator?->adminCreatorProfile;
    $instructorPhoto = $ownerType === 'admin'
        ? ($creatorProfile?->avatar_path
            ? asset('storage/' . ltrim((string) $creatorProfile->avatar_path, '/'))
            : asset('media/Logo.png'))
        : ($instructorProfile?->profile_photo_path
            ? asset('storage/' . ltrim($instructorProfile->profile_photo_path, '/'))
            : null);

    $enrollmentCapacityLabel = $module->enrollment_limit !== null
        ? sprintf('%d / %d Enrolled', $approvedEnrollmentsCount, (int) $module->enrollment_limit)
        : sprintf('%d Enrolled', $approvedEnrollmentsCount);
    $isModuleDeactivated = !$module->isLearnerVisible();

    $canSubmitReview = (bool) ($reviewEligibility['eligible'] ?? false);
    $reviewBlocker = $reviewEligibility['reason'] ?? null;
    $reviewReasons = [
        'inappropriate_content' => 'Inappropriate content',
        'misleading_information' => 'Misleading information',
        'plagiarized_content' => 'Plagiarized content',
        'offensive_language' => 'Offensive language',
        'harmful_material' => 'Incorrect or harmful educational material',
        'spam_or_promotional_abuse' => 'Spam or promotional abuse',
    ];
    $initialReportTarget = old('target_type', 'module');
    if ($initialReportTarget === 'instructor' && !$creator) {
        $initialReportTarget = 'module';
    }
@endphp

{{-- Chat contract marker: conversation_type: 'module_chat' --}}

<div class="space-y-5">

    @if($isModuleDeactivated)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
        This module is currently unavailable because it has been deactivated by the instructor.
    </div>
    @endif

    {{-- Back link --}}
    <div>
        <a href="{{ route('learner.modules.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Modules
        </a>
    </div>

    {{-- Gamification strip --}}
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-4 py-3 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
        {{-- Streak --}}
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-orange-50 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-800/40">
            <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 23c-4.97 0-9-3.582-9-8 0-3.5 2-6.5 5-8-.5 1.5 0 3 1 4 .5-2 2-4 4-5-.5 2 1 4 2 5 .5-1 .5-2.5 0-3.5 2 1.5 3 4 3 7.5 1-1 1.5-2.5 1.5-4 1.5 1.5 2.5 3.5 2.5 6 0 4.418-4.03 8-9 8z"/>
            </svg>
            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $gami?->streak_count ?? 0 }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Day Streak</span>
        </div>
        {{-- Shields --}}
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-800/40">
            <svg class="w-4 h-4 text-purple-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
            </svg>
            @if($hasUnlimitedShields)
                <span class="text-sm font-bold text-gray-900 dark:text-white">&infin;</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Unlimited Shields</span>
            @else
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $shieldsRemaining }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Shields</span>
            @endif
        </div>
        {{-- Points --}}
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/40">
            <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($gami?->score ?? 0) }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Points</span>
        </div>
        <a href="{{ route('learner.gamification') }}"
           class="ml-auto text-xs font-medium text-purple-600 dark:text-purple-400 hover:underline whitespace-nowrap">
            How it works
            <svg class="inline w-3 h-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>

    {{-- 2-column layout --}}
    <div
        class="grid grid-cols-1 lg:grid-cols-3 gap-6"
        x-data="{
            expandedLesson: null,
            reviewModalOpen: @js($errors->has('rating') || $errors->has('review_content')),
            reportModalOpen: @js($errors->has('target_type') || $errors->has('target_id') || $errors->has('reason_code') || $errors->has('details')),
            reportTarget: @js($initialReportTarget)
        }"
    >

        {{--  LEFT: module content (2/3)  --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Module hero --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="relative h-40 sm:h-48 overflow-hidden" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                    @if($module->thumbnail)
                        <img src="{{ asset('storage/' . $module->thumbnail) }}"
                             alt="{{ $module->title }}"
                             class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                    @else
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                    @endif
                    <div class="absolute bottom-0 left-0 right-0 p-5">
                        <div class="flex flex-wrap gap-2 mb-2">
                            @if($module->difficulty_level)
                                <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                                    {{ $module->difficulty_level === 'beginner'
                                        ? 'bg-green-400/90 text-green-900'
                                        : ($module->difficulty_level === 'intermediate'
                                            ? 'bg-amber-400/90 text-amber-900'
                                            : 'bg-red-400/90 text-red-900') }}">
                                    {{ ucfirst($module->difficulty_level) }}
                                </span>
                            @endif
                        </div>
                        <h1 class="text-2xl font-bold text-white leading-tight">{{ $module->title }}</h1>
                        <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-white/80">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                {{ $lessons->count() }} {{ Str::plural('lesson', $lessons->count()) }}
                            </span>
                            @if($module->duration_minutes)
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                                </svg>
                                {{ $module->duration_minutes }} min
                            </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Module description --}}
                @if($module->description)
                <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1.5">About this module</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line leading-relaxed">{{ $module->description }}</p>
                </div>
                @endif

                <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                        @if($instructorPhoto)
                            <img src="{{ $instructorPhoto }}" alt="{{ $displayOwnerName }}" class="h-8 w-8 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                        @else
                            <div class="h-8 w-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center text-xs font-bold dark:bg-purple-900/40 dark:text-purple-300">
                                {{ strtoupper(substr($displayOwnerName, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Created by</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $displayOwnerName }}</p>
                            @if(!empty($ownershipDisplay['individual_attribution_text']))
                                <p class="text-xs text-purple-600 dark:text-purple-300 mt-0.5">{{ $ownershipDisplay['individual_attribution_text'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Module Curriculum --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Module Curriculum</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $lessons->count() }} {{ Str::plural('lesson', $lessons->count()) }}</p>
                    </div>
                    @if($isEnrolled && $progress->progress_percentage > 0)
                    <span class="text-xs font-semibold text-purple-600 dark:text-purple-400">
                        {{ round($progress->progress_percentage) }}% done
                    </span>
                    @endif
                </div>

                @if($lessons->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-gray-400 dark:text-gray-500">No lessons available yet.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($lessons as $index => $lesson)
                            @php
                                $isCompleted = in_array($lesson->id, $completedLessonIds);
                                $topics      = $lesson->topics()->ordered()->get();
                                $topicsCount = $topics->count();
                                $hasBreakdown = $topicsCount > 0 || (bool) $lesson->quiz;
                                $completedTopicsCount = 0;
                                if ($topicsCount > 0) {
                                    $completedTopicsCount = \App\Models\LessonTopicProgress::where('user_id', auth()->id())
                                        ->whereIn('lesson_topic_id', $topics->pluck('id'))
                                        ->where('completed', true)
                                        ->count();
                                }
                            @endphp
                            <div>
                                {{-- Lesson row --}}
                                <div
                                    class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors {{ $isEnrolled ? 'cursor-pointer' : '' }}"
                                    @if($hasBreakdown && $isEnrolled)
                                        @click="expandedLesson = expandedLesson === {{ $lesson->id }} ? null : {{ $lesson->id }}"
                                    @elseif($isEnrolled)
                                        onclick="window.location='{{ route('learner.lessons.show', $lesson) }}'"
                                    @endif
                                >
                                    {{-- Completion circle --}}
                                    @if($isCompleted)
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                            {{ $isEnrolled ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                            {{ $index + 1 }}
                                        </div>
                                    @endif

                                    {{-- Content type icon + title --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $lesson->title }}</h4>
                                        </div>
                                        <div class="flex items-center gap-3 mt-0.5">
                                            @if($topicsCount > 0)
                                                <span class="text-xs {{ $completedTopicsCount === $topicsCount ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400' }} font-medium">
                                                    {{ $completedTopicsCount }}/{{ $topicsCount }} topics
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $lesson->duration }} min</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Right icon --}}
                                    @if(!$isEnrolled)
                                        <svg class="flex-shrink-0 w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    @elseif($hasBreakdown)
                                        <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform"
                                             :class="expandedLesson === {{ $lesson->id }} ? 'rotate-180' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    @else
                                        <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    @endif
                                </div>

                                {{-- Expandable topics --}}
                                @if($hasBreakdown)
                                <div x-show="expandedLesson === {{ $lesson->id }}"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     data-lesson-breakdown="lesson-{{ $lesson->id }}"
                                     class="bg-gray-50/70 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700"
                                     style="display: none;">
                                    <div class="py-1">
                                        @if($lesson->quiz)
                                            <div data-lesson-quiz-indicator="lesson-{{ $lesson->id }}" class="px-6 sm:px-8 py-2.5">
                                                <div class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-[10px] font-semibold text-purple-700 dark:border-purple-800/50 dark:bg-purple-900/30 dark:text-purple-300">
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <span>{{ $lesson->quiz->title }}</span>
                                                </div>
                                            </div>
                                        @endif

                                        @foreach($topics as $topic)
                                            @php
                                                $isTopicCompleted = \App\Models\LessonTopicProgress::where('user_id', auth()->id())
                                                    ->where('lesson_topic_id', $topic->id)
                                                    ->where('completed', true)
                                                    ->exists();
                                            @endphp
                                            <div class="flex items-center gap-3 px-6 sm:px-8 py-2.5">
                                                <div class="flex-shrink-0">
                                                    @if($isTopicCompleted)
                                                        <div class="w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center">
                                                            <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </div>
                                                    @else
                                                        <div class="w-4 h-4 rounded-full border-2 border-gray-300 dark:border-gray-600"></div>
                                                    @endif
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate block">{{ $topic->title }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 flex-shrink-0">
                                                    @if($topic->duration)
                                                        <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $topic->duration }}m</span>
                                                    @endif
                                                    @if($topic->is_prerequisite)
                                                        <span class="text-[10px] text-gray-400 dark:text-gray-500">· Required</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($isEnrolled)
                <div class="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                            </svg>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Module Certificate</h3>
                        </div>
                        @if($moduleCertificate)
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">Issued</span>
                        @endif
                    </div>

                    <div class="p-5">
                        @if($moduleCertificate && $certificateEligible)
                            <p class="text-sm text-gray-600 dark:text-gray-400">Certificate number</p>
                            <p class="text-sm font-mono font-semibold text-gray-800 dark:text-gray-100 mt-0.5">{{ $moduleCertificate->certificate_number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Issued {{ $moduleCertificate->issued_at->format('F d, Y') }}</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('learner.certificates.show', $moduleCertificate) }}"
                                   class="inline-flex items-center justify-center gap-2 text-sm font-semibold text-white px-4 py-2.5 rounded-xl transition hover:opacity-90"
                                   style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                    View Certificate
                                </a>
                                <a href="{{ route('learner.certificates.download', $moduleCertificate) }}"
                                   class="inline-flex items-center justify-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/40 px-4 py-2.5 rounded-xl hover:bg-emerald-100 transition-colors">
                                    Download PDF
                                </a>
                            </div>
                        @elseif($certificateEligible)
                            <p class="text-sm text-gray-600 dark:text-gray-400">You completed this module. Generate your certificate now.</p>
                            <form method="POST" action="{{ route('learner.certificates.check', $module) }}" class="mt-4">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center justify-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/40 px-4 py-2.5 rounded-xl hover:bg-emerald-100 transition-colors">
                                    Get Certificate
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-gray-600 dark:text-gray-400">Complete all lessons, lesson topics, and quizzes to unlock this certificate.</p>
                        @endif
                    </div>
                </div>
            @endif

        </div>{{-- end left col --}}

        {{--  RIGHT: enrollment sidebar (1/3)  --}}
        <div class="space-y-5">

            {{-- Enrollment / progress card --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">

                @if($isEnrolled)
                    {{-- Progress --}}
                    <div class="mb-4">
                        <div class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300 mb-2 font-medium">
                            <span>Your Progress</span>
                            <span class="text-purple-600 dark:text-purple-400 font-bold">{{ round($progress->progress_percentage) }}%</span>
                        </div>
                        <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700"
                                 style="width: {{ round($progress->progress_percentage) }}%; background: linear-gradient(90deg, #A30EB2, #3B0CB1);"></div>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">
                            {{ $progress->completed_lessons }}/{{ $progress->total_lessons }} lessons completed
                        </p>
                    </div>

                    @if($lessons->isNotEmpty())
                        @if($isModuleDeactivated)
                            <button type="button"
                                    disabled
                                    class="flex items-center justify-center gap-2 w-full text-sm font-semibold text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 py-3 px-4 rounded-xl cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-12.728 12.728M9 12.75L11.25 15 15 9.75"/>
                                </svg>
                                Continue Learning Unavailable
                            </button>
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                This module is currently unavailable because it has been deactivated by the instructor.
                            </p>
                        @else
                            <a href="{{ route('learner.lessons.show', $lessons->first()) }}"
                               class="flex items-center justify-center gap-2 w-full text-sm font-semibold text-white py-3 px-4 rounded-xl transition hover:opacity-90"
                               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/>
                                </svg>
                                {{ $progress->progress_percentage > 0 ? 'Continue Learning' : 'Start Learning' }}
                            </a>
                        @endif
                    @endif

                    {{-- Certificate section --}}
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        @if($moduleCertificate && $certificateEligible)
                            <a href="{{ route('learner.certificates.show', $moduleCertificate) }}"
                               class="flex items-center gap-2 w-full text-sm font-semibold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 px-4 py-2.5 rounded-xl hover:bg-amber-100 transition-colors justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                                </svg>
                                View Certificate
                            </a>
                        @elseif($certificateEligible)
                            <form method="POST" action="{{ route('learner.certificates.check', $module) }}">
                                @csrf
                                <button type="submit"
                                        class="flex items-center gap-2 w-full text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/40 px-4 py-2.5 rounded-xl hover:bg-emerald-100 transition-colors justify-center">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                                    </svg>
                                    Get Certificate
                                </button>
                            </form>
                        @else
                            <p class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                                </svg>
                                Complete all lessons, topics, and quizzes to unlock your certificate
                            </p>
                        @endif
                    </div>

                @elseif($enrollmentStatus === 'pending' && $isPaidModule && !$hasPurchased)
                    <div class="space-y-3">
                        <div class="flex items-start gap-3 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800/40 rounded-xl">
                            <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-2.761 0-5 2.239-5 5m5-5c2.761 0 5 2.239 5 5m-5-5v10m0 0l-3-3m3 3l3-3"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-indigo-800 dark:text-indigo-300">Guardian Approved. Payment Required.</p>
                                <p class="text-xs text-indigo-700 dark:text-indigo-400 mt-0.5">Continue to checkout to unlock this paid module.</p>
                            </div>
                        </div>

                        @if($isAtCapacity)
                            <div class="p-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-sm font-semibold text-center">
                                Enrollment Closed
                            </div>
                        @else
                            <a href="{{ route('learner.modules.purchase.form', $module) }}"
                               class="flex items-center justify-center gap-2 w-full text-sm font-semibold text-white py-3 px-4 rounded-xl transition hover:opacity-90"
                               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                Pay {{ $module->display_price }}
                            </a>
                        @endif
                    </div>

                @elseif($enrollmentStatus === 'pending_parent_approval')
                    <div class="space-y-3">
                        <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 rounded-xl">
                            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-2.761 0-5 2.239-5 5m5-5c2.761 0 5 2.239 5 5m-5-5v10m0 0l-3-3m3 3l3-3"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Awaiting Guardian Approval</p>
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Your request was submitted. You cannot submit another request until your guardian approves or rejects this one.</p>
                            </div>
                        </div>
                        <a href="{{ route('learner.notifications.index') }}"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-50 dark:border-amber-800/40 dark:bg-amber-950/20 dark:text-amber-300 dark:hover:bg-amber-900/30">
                            View Request Status
                        </a>
                    </div>

                @elseif($enrollmentStatus === 'pending')
                    <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 rounded-xl">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Enrollment Pending</p>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Waiting for instructor approval. You'll be notified once approved.</p>
                        </div>
                    </div>

                @elseif($enrollmentStatus === 'rejected')
                    <div class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40 rounded-xl">
                        <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-red-800 dark:text-red-300">Enrollment Rejected</p>
                            <p class="text-xs text-red-700 dark:text-red-400 mt-0.5">Your enrollment request was not approved by the instructor.</p>
                        </div>
                    </div>

                @else
                    {{-- Not enrolled yet --}}
                    <div class="text-center mb-5">
                        <p class="text-base font-semibold text-gray-900 dark:text-white mb-1">{{ $isPaidModule ? 'Unlock this module' : 'Enroll in this module' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if($isPaidModule)
                                {{ $hasPurchased ? 'Purchase completed. Continue with enrollment steps below.' : 'Review details then complete secure checkout to access content.' }}
                            @else
                                {{ $module->enrollment_mode === 'manual' ? 'Requires instructor approval' : 'Free access start immediately' }}
                            @endif
                        </p>
                    </div>

                    @if($isPaidModule)
                        @if($isOwnedByCurrentUser)
                            <div class="flex items-start gap-3 p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800/40 rounded-xl">
                                <svg class="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M12 3.75l7.5 3v5.25c0 4.142-2.924 8.012-7.5 9-4.576-.988-7.5-4.858-7.5-9V6.75l7.5-3z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-purple-800 dark:text-purple-300">Owned by You</p>
                                    <p class="text-xs text-purple-700 dark:text-purple-400 mt-0.5">You can manage this module as its owner, but you cannot purchase your own module.</p>
                                </div>
                            </div>
                        @elseif($needsParentApproval && !$isParentApprovedForPurchase)
                            <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 rounded-xl">
                                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M5.2 19h13.6c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.468 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Guardian approval required before payment</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Request approval first, then return to complete checkout.</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('learner.modules.purchase', $module) }}" class="mt-3">
                                @csrf
                                <button type="submit"
                                        class="flex items-center justify-center gap-2 w-full text-sm font-semibold text-white py-3 px-4 rounded-xl transition hover:opacity-90"
                                        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                    Request Guardian Approval
                                </button>
                            </form>
                        @elseif($isAtCapacity)
                            <div class="p-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-sm font-semibold text-center">
                                Enrollment Closed
                            </div>
                        @elseif($canPurchase)
                            <a href="{{ route('learner.modules.purchase.form', $module) }}"
                               class="flex items-center justify-center gap-2 w-full text-sm font-semibold text-white py-3 px-4 rounded-xl transition hover:opacity-90"
                               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                Pay {{ $module->display_price }}
                            </a>
                        @else
                            <p class="text-xs text-gray-500 dark:text-gray-400 text-center">{{ $checkoutUnavailableReason ?? 'Checkout is not available right now.' }}</p>
                        @endif
                    @else
                        <form method="POST" action="{{ route('learner.modules.enroll', $module) }}">
                            @csrf
                            <button type="submit"
                                    class="flex items-center justify-center gap-2 w-full text-sm font-semibold text-white py-3 px-4 rounded-xl transition hover:opacity-90"
                                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                @if($module->enrollment_mode === 'manual')
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    Request Enrollment
                                @else
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    Enroll Now · Free
                                @endif
                            </button>
                        </form>
                    @endif

                    <ul class="mt-4 space-y-2 text-xs text-gray-500 dark:text-gray-400">
                        <li class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $lessons->count() }} {{ Str::plural('lesson', $lessons->count()) }} included
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Track your progress
                        </li>
                        @if($isPaidModule)
                        <li class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            Price: {{ $module->display_price }}
                        </li>
                        @endif
                    </ul>
                @endif
            </div>

            @if($ownerType === 'admin')
                @include('learner.modules.partials.admin-creator-info-card')
            @else
                @include('learner.modules.partials.instructor-info-card')
            @endif

            @include('learner.modules.partials.module-info-card')

            @include('learner.modules.partials.reviews-card')

        </div>{{-- end right col --}}

        @include('learner.modules.partials.review-modal')

        @include('learner.modules.partials.report-modal')

    </div>{{-- end grid --}}

</div>
@endsection

@push('scripts')
<script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('[data-review-form="true"]')?.addEventListener('submit', function () {
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
        });

        if (typeof tinymce === 'undefined') {
            return;
        }

        tinymce.remove('textarea.js-learner-rich-editor');
        tinymce.init({
            selector: 'textarea.js-learner-rich-editor',
            license_key: 'gpl',
            height: 160,
            menubar: false,
            branding: false,
            plugins: 'lists link',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
        });
    });
</script>
@endpush
