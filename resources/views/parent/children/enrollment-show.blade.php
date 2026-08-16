@extends('layouts.learner-app')

@section('title', 'Lesson Details')

@section('content')
@php
    $module = $enrollment->module;
    $creator = $module?->creator;
    $instructorProfile = $creator?->instructorProfile;
    $statusValue = $enrollment->status instanceof \App\Enums\EnrollmentStatus
        ? $enrollment->status->value
        : (string) $enrollment->status;

    $statusMeta = [
        'pending_parent_approval' => ['label' => 'Pending Guardian Approval', 'class' => 'bg-amber-100 text-amber-800 border-amber-200'],
        'pending' => ['label' => 'Pending Instructor Review', 'class' => 'bg-blue-100 text-blue-800 border-blue-200'],
        'approved' => ['label' => 'Approved', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
        'rejected' => ['label' => 'Rejected', 'class' => 'bg-rose-100 text-rose-800 border-rose-200'],
    ][$statusValue] ?? ['label' => ucfirst(str_replace('_', ' ', $statusValue ?: 'unknown')), 'class' => 'bg-gray-100 text-gray-700 border-gray-200'];

    $moduleThumbnail = $module?->thumbnail_url;
    $instructorPhoto = $instructorProfile?->profile_photo_path
        ? asset('storage/' . ltrim((string) $instructorProfile->profile_photo_path, '/'))
        : null;

    $canTakeAction = $canApproveContent && $statusValue === 'pending_parent_approval';
    $rejectionReasonOptions = [
        'age_not_suitable' => 'Age suitability concerns',
        'not_ready_for_topic' => 'Not ready for this topic yet',
        'others' => 'Other appropriate reason',
    ];
    $shouldOpenRejectModal = $errors->has('reason_code') || $errors->has('custom_reason');
    $moduleLessons = ($module?->lessons ?? collect())->sortBy('order')->values();
    $moduleLevelQuizzes = ($module?->quizzes ?? collect())->whereNull('lesson_id')->values();

    $publicUrl = static function (?string $path): ?string {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        return asset('storage/' . $normalized);
    };

    $topicPreviewPayloads = $moduleLessons
        ->flatMap(function ($lesson) use ($publicUrl) {
            return ($lesson->topics ?? collect())->map(function ($topic) use ($publicUrl) {
                $worksheetFiles = collect($topic->worksheet_files ?? [])
                    ->filter()
                    ->map(function ($path) use ($publicUrl) {
                        $url = $publicUrl((string) $path);

                        if (! $url) {
                            return null;
                        }

                        return [
                            'name' => basename((string) $path),
                            'url' => $url,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                $imageAttachments = collect($topic->image_attachments ?? [])
                    ->map(function ($image) use ($publicUrl) {
                        if (is_array($image)) {
                            $path = (string) ($image['path'] ?? $image['url'] ?? '');
                            $caption = isset($image['caption']) ? (string) $image['caption'] : null;
                        } else {
                            $path = (string) $image;
                            $caption = null;
                        }

                        $url = $publicUrl($path);

                        if (! $url) {
                            return null;
                        }

                        return [
                            'url' => $url,
                            'caption' => $caption,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => (int) $topic->id,
                    'title' => (string) ($topic->title ?: 'Untitled topic'),
                    'type' => (string) ($topic->type ?? 'topic'),
                    'duration' => (int) ($topic->duration ?? 0),
                    'text_content' => (string) ($topic->text_content ?? ''),
                    'video_url' => $topic->video_embed_url,
                    'video_file_url' => $topic->video_file_url,
                    'worksheet_files' => $worksheetFiles,
                    'worksheet_file_url' => $publicUrl($topic->file_path),
                    'image_attachments' => $imageAttachments,
                ];
            });
        })
        ->keyBy('id')
        ->all();
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('parent.children.show', $child) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Dependent Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Lesson Details</h1>
            <p class="text-sm text-gray-500 mt-1">
                Review {{ $child->full_name ?: $child->name }}'s request for
                <span class="font-semibold text-gray-700">{{ $module?->title ?? 'this module' }}</span>.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $statusMeta['class'] }}">
                {{ $statusMeta['label'] }}
            </span>
            <button type="button"
                    onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $child->id }}, conversation_type: "direct", name: @json($child->full_name ?: $child->name) } }))'
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                Message Dependent
            </button>
        </div>
    </div>

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

    @if($openedFromNotification ?? false)
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Opened from notification.
            <a href="{{ \Illuminate\Support\Facades\Route::has('notifications.index') ? route('notifications.index') : route('learner.notifications.index') }}" class="ml-2 font-semibold underline">Return to notifications</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex items-start gap-4">
                <div class="w-24 h-24 rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-50 flex-shrink-0">
                    @if($moduleThumbnail)
                        <img src="{{ $moduleThumbnail }}" alt="{{ $module?->title }} thumbnail" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-xs text-gray-400 px-3 text-center">
                            No thumbnail
                        </div>
                    @endif
                </div>

                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $module?->title ?? 'Module' }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2 line-clamp-4">
                        {{ $module?->description ?: 'No module description available.' }}
                    </p>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 px-3 py-2">
                    <p class="text-xs text-gray-500">Requested On</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ $enrollment->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 px-3 py-2">
                    <p class="text-xs text-gray-500">Age Recommendation</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">
                        @if($module && $module->min_age !== null && $module->max_age !== null)
                            Ages {{ $module->min_age }}-{{ $module->max_age }}
                        @else
                            Not specified
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 px-3 py-2">
                    <p class="text-xs text-gray-500">Lessons</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ $module?->lessons?->count() ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 px-3 py-2">
                    <p class="text-xs text-gray-500">Quizzes</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ $module?->quizzes?->count() ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 px-3 py-2">
                    <p class="text-xs text-gray-500">Access Type</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ ucfirst((string) ($module?->access_type ?? 'free')) }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 dark:border-gray-700 px-3 py-2">
                    <p class="text-xs text-gray-500">Enrollment Flow</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ ucfirst((string) ($module?->enrollment_mode ?? 'auto')) }}</p>
                </div>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Instructor Transparency</h3>

                @if($creator)
                    <div class="mt-3 flex items-start gap-3">
                        @if($instructorPhoto)
                            <img src="{{ $instructorPhoto }}" alt="{{ $creator->name }}" class="w-11 h-11 rounded-full object-cover border border-gray-200 dark:border-gray-600">
                        @else
                            <div class="w-11 h-11 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center text-sm font-bold">
                                {{ strtoupper(substr((string) $creator->name, 0, 1)) }}
                            </div>
                        @endif

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $creator->name }}</p>
                            <p class="text-xs text-gray-500 mt-1 line-clamp-3">
                                {{ $instructorProfile?->professional_background ?: ($instructorProfile?->bio ?: 'Instructor profile information is being updated.') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('learner.instructors.show', $creator) }}"
                           class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                            View Instructor Profile
                        </a>
                        <button
                            type="button"
                            onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $creator->id }}, conversation_type: "module_chat", module_id: {{ (int) $module->id }}, name: @json($creator->name) } }))'
                            class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                        >
                            Message Instructor
                        </button>
                    </div>
                @else
                    <p class="mt-3 text-xs text-gray-500">Instructor details are unavailable for this enrollment request.</p>
                @endif
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Request Timeline</h3>
                <ul class="mt-3 space-y-2 text-xs text-gray-600 dark:text-gray-300">
                    <li class="flex items-start justify-between gap-3">
                        <span>Dependent submitted request</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $enrollment->created_at?->format('M d, Y') ?? 'N/A' }}</span>
                    </li>
                    <li class="flex items-start justify-between gap-3">
                        <span>Current state</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $statusMeta['label'] }}</span>
                    </li>
                    @if($enrollment->enrolled_at)
                        <li class="flex items-start justify-between gap-3">
                            <span>Enrollment activated</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $enrollment->enrolled_at->format('M d, Y') }}</span>
                        </li>
                    @endif
                </ul>
            </section>
        </aside>
    </div>

    <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 space-y-4">
        <div class="flex flex-col gap-2">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Learning Content Review</h3>
            <p class="text-sm text-gray-500">Review lesson topics one by one using the same topic-preview pattern used in instructor lessons.</p>
        </div>

        @if($moduleLessons->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                No lesson content is available yet for this module.
            </div>
        @endif

        @foreach($moduleLessons as $lessonIndex => $lesson)
            @php
                $lessonTopics = ($lesson->topics ?? collect())->sortBy('order')->values();
                $lessonQuizzes = ($lesson->quizzes ?? collect())->values();
            @endphp

            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lesson {{ $lesson->order ?? ($lessonIndex + 1) }}</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $lesson->title ?: 'Untitled lesson' }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">
                        {{ (int) ($lesson->duration ?? 0) }} min
                    </span>
                </div>

                @if($lesson->description)
                    <div class="rounded-lg border border-gray-100 bg-white px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lesson Description</p>
                        <p class="mt-1 text-sm text-gray-700">{{ $lesson->description }}</p>
                    </div>
                @endif

                <div class="rounded-lg border border-gray-100 bg-white p-3 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Topics</p>
                            <p class="text-sm text-gray-600">Use Review to open the topic preview modal.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                            {{ $lessonTopics->count() }} topic{{ $lessonTopics->count() === 1 ? '' : 's' }}
                        </span>
                    </div>

                    @if($lessonTopics->isEmpty())
                        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-2 text-sm text-gray-500">
                            No topics available in this lesson.
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($lessonTopics as $topic)
                                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $topic->title ?: 'Untitled topic' }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            {{ ucfirst((string) ($topic->type ?? 'topic')) }}
                                            @if((int) ($topic->duration ?? 0) > 0)
                                                · {{ (int) $topic->duration }} min
                                            @endif
                                        </p>
                                    </div>
                                    <button type="button"
                                            onclick="previewParentTopicModal({{ (int) $topic->id }})"
                                            class="inline-flex items-center justify-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                                        Review Topic
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($lessonQuizzes->isNotEmpty())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Lesson Quizzes</p>
                        <p class="mt-1 text-sm text-amber-900">
                            {{ $lessonQuizzes->count() }} quiz{{ $lessonQuizzes->count() === 1 ? '' : 'zes' }} available in this lesson.
                        </p>
                    </div>
                @endif
            </div>
        @endforeach

        @if($moduleLevelQuizzes->isNotEmpty())
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Module-Level Quizzes</p>
                <p class="mt-1 text-sm text-blue-900">
                    {{ $moduleLevelQuizzes->count() }} quiz{{ $moduleLevelQuizzes->count() === 1 ? '' : 'zes' }} available at module level.
                </p>
            </div>
        @endif
    </section>

    <div id="parentTopicPreviewModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm hidden overflow-y-auto h-full w-full z-[1001]">
        <div class="relative top-6 mx-auto p-0 border w-[95%] max-w-3xl shadow-xl rounded-2xl bg-white mb-6">
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Topic Preview</h3>
                <button type="button"
                        onclick="closeParentTopicPreviewModal()"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="parentTopicPreviewContent" class="p-6"></div>
        </div>
    </div>

    @if($canTakeAction)
        <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5"
                 x-data="{
                    approveModalOpen: false,
                    rejectModalOpen: @js($shouldOpenRejectModal),
                    reasonCode: @js((string) old('reason_code', '')),
                 }"
                 x-effect="
                    if (rejectModalOpen && reasonCode === 'others') {
                        window.initParentEnrollmentReasonEditor?.($refs.customReasonEditor);
                    } else {
                        window.destroyParentEnrollmentReasonEditor?.($refs.customReasonEditor);
                    }
                 "
                 @keydown.escape.window="approveModalOpen = false; rejectModalOpen = false">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Guardian Decision</h3>
            <p class="text-sm text-gray-500 mt-1">
                Choose whether {{ $child->full_name ?: $child->name }} can proceed with this module request.
            </p>

            <div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-center">
                <button type="button"
                        @click="approveModalOpen = true"
                        class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                    Approve Request
                </button>

                <button type="button"
                        @click="rejectModalOpen = true"
                        class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50">
                    Reject Request
                </button>
            </div>

            <p class="mt-3 text-xs text-gray-500">
                Approval and rejection now require confirmation before submission.
            </p>

            <div x-show="approveModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60" @click="approveModalOpen = false"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl">
                    <h4 class="text-base font-semibold text-gray-900">Confirm Approval</h4>
                    <p class="mt-2 text-sm text-gray-600">
                        Approve this enrollment request for {{ $child->full_name ?: $child->name }}?
                    </p>

                    <form method="POST"
                          action="{{ route('parent.children.enrollments.approve', [$child, $enrollment]) }}"
                          class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        @csrf
                        <button type="button"
                                @click="approveModalOpen = false"
                                class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            Confirm Approval
                        </button>
                    </form>
                </div>
            </div>

            <div x-show="rejectModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60" @click="rejectModalOpen = false"></div>
                <div class="relative w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
                    <h4 class="text-base font-semibold text-gray-900">Reject Enrollment Request</h4>
                    <p class="mt-2 text-sm text-gray-600">
                        Select a reason and add details so your child understands why this request was declined.
                    </p>

                    <form method="POST"
                          action="{{ route('parent.children.enrollments.reject', [$child, $enrollment]) }}"
                          class="mt-4 space-y-4"
                          @submit="window.syncParentEnrollmentReasonEditor?.()">
                        @csrf

                        <div>
                            <label for="reason_code" class="block text-xs font-medium text-gray-600 mb-1">Rejection Reason</label>
                            <select id="reason_code"
                                    name="reason_code"
                                    x-model="reasonCode"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                                <option value="">Select appropriate rejection reason for guardian content approval</option>
                                @foreach($rejectionReasonOptions as $reasonCode => $reasonLabel)
                                    <option value="{{ $reasonCode }}">{{ $reasonLabel }}</option>
                                @endforeach
                            </select>
                            @error('reason_code')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="reasonCode === 'others'" x-cloak>
                            <label for="custom_reason" class="block text-xs font-medium text-gray-600 mb-1">Rejection Reason Details</label>
                            <textarea id="custom_reason"
                                      name="custom_reason"
                                      x-ref="customReasonEditor"
                                      class="js-guardian-enrollment-reason-editor w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100"
                                      rows="6">{{ old('custom_reason') }}</textarea>
                            @error('custom_reason')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                            <button type="button"
                                    @click="rejectModalOpen = false"
                                    class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                Confirm Rejection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-2xl border border-gray-100 bg-gray-50 dark:bg-gray-900/30 dark:border-gray-700 px-5 py-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                @if($statusValue === 'approved')
                    This request has already been approved.
                @elseif($statusValue === 'rejected')
                    This request was already rejected.
                @elseif(!$canApproveContent)
                    You have monitoring access for this dependent, but content approval permission is disabled.
                @else
                    This request is currently waiting for the next review stage.
                @endif
            </p>
        </section>
    @endif
</div>
@endsection

@push('scripts')
    <script>
        window.parentTopicPreviewPayloads = @json($topicPreviewPayloads);

        function previewParentTopicModal(topicId) {
            const modal = document.getElementById('parentTopicPreviewModal');
            const previewContent = document.getElementById('parentTopicPreviewContent');
            const topic = window.parentTopicPreviewPayloads[String(topicId)] || window.parentTopicPreviewPayloads[topicId];

            if (!modal || !previewContent) {
                return;
            }

            modal.classList.remove('hidden');

            if (!topic) {
                previewContent.innerHTML = '<p class="text-center text-sm text-red-500 py-8">Topic preview is unavailable.</p>';
                return;
            }

            previewContent.innerHTML = renderParentTopicPreview(topic);
        }

        function closeParentTopicPreviewModal() {
            document.getElementById('parentTopicPreviewModal')?.classList.add('hidden');
        }

        function renderParentTopicPreview(topic) {
            let content = `<div class="space-y-4"><div class="bg-gray-50 p-4 rounded-xl"><h4 class="text-lg font-semibold text-gray-900">${escapeParentTopicHtml(topic.title || '')}</h4><div class="flex gap-3 mt-2"><span class="px-2 py-1 text-xs font-semibold rounded-full ${getParentTopicTypeColor(topic.type)}">${capitalizeParentTopicLabel(topic.type || 'topic')}</span><span class="text-sm text-gray-500">${topic.duration || 0} min</span></div></div>`;

            if (topic.type === 'video') {
                if (topic.video_url) {
                    content += `<div class="aspect-video bg-black rounded-xl overflow-hidden"><iframe src="${topic.video_url}" class="w-full h-full" allowfullscreen></iframe></div>`;
                } else if (topic.video_file_url) {
                    content += `<video controls class="w-full rounded-xl"><source src="${topic.video_file_url}" type="video/mp4"></video>`;
                } else {
                    content += '<div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">No video source available for this topic.</div>';
                }

                if (topic.text_content) {
                    content += `<div class="prose max-w-none p-4 bg-gray-50 rounded-xl">${topic.text_content}</div>`;
                }
            } else if (topic.type === 'text') {
                if (topic.text_content) {
                    content += `<div class="prose max-w-none p-4 bg-gray-50 rounded-xl">${topic.text_content}</div>`;
                }

                if (Array.isArray(topic.image_attachments) && topic.image_attachments.length > 0) {
                    const imageTiles = topic.image_attachments
                        .filter((image) => !!image.url)
                        .map((image) => `
                            <div class="rounded-lg overflow-hidden border border-gray-200 bg-white">
                                <img src="${image.url}" alt="Topic image" class="w-full h-40 object-cover">
                                ${image.caption ? `<p class="text-xs text-gray-600 p-2">${escapeParentTopicHtml(image.caption)}</p>` : ''}
                            </div>
                        `)
                        .join('');

                    if (imageTiles) {
                        content += `<div><p class="text-sm font-semibold text-gray-700 mb-2">Images</p><div class="grid grid-cols-1 sm:grid-cols-2 gap-3">${imageTiles}</div></div>`;
                    }
                }
            } else if (topic.type === 'worksheet') {
                const files = Array.isArray(topic.worksheet_files) ? topic.worksheet_files : [];
                const fallbackFile = topic.worksheet_file_url
                    ? [{ name: 'Worksheet File', url: topic.worksheet_file_url }]
                    : [];
                const worksheetFiles = files.length > 0 ? files : fallbackFile;

                if (worksheetFiles.length > 0) {
                    const fileList = worksheetFiles
                        .filter((file) => !!file.url)
                        .map((file) => `<a href="${file.url}" target="_blank" class="block text-sm text-purple-700 hover:underline">${escapeParentTopicHtml(file.name || 'Worksheet File')}</a>`)
                        .join('');

                    content += `<div class="rounded-xl bg-purple-50 p-4"><p class="text-sm font-semibold text-gray-900 mb-2">Worksheet Files</p>${fileList}</div>`;
                } else {
                    content += '<div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">No worksheet file attached.</div>';
                }

                if (topic.text_content) {
                    content += `<div class="prose max-w-none p-4 bg-gray-50 rounded-xl">${topic.text_content}</div>`;
                }
            } else if (topic.type === 'interactive') {
                content += '<div class="rounded-xl border border-orange-200 bg-orange-50 p-4"><p class="text-sm text-orange-700">Interactive topic preview is available inside the lesson view.</p></div>';
            } else {
                content += '<div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">Preview is not available for this topic type yet.</div>';
            }

            content += '</div>';
            return content;
        }

        function getParentTopicTypeColor(type) {
            const colors = {
                video: 'bg-red-100 text-red-800',
                text: 'bg-purple-100 text-purple-800',
                worksheet: 'bg-green-100 text-green-800',
                interactive: 'bg-orange-100 text-orange-800',
            };

            return colors[type] || 'bg-gray-100 text-gray-800';
        }

        function escapeParentTopicHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');
        }

        function capitalizeParentTopicLabel(value) {
            return String(value).charAt(0).toUpperCase() + String(value).slice(1);
        }

        document.getElementById('parentTopicPreviewModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeParentTopicPreviewModal();
            }
        });
    </script>

    <script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
    <script>
        (function () {
            window.initParentEnrollmentReasonEditor = function (textarea, retries) {
                const target = textarea || document.getElementById('custom_reason');
                const attemptCount = Number(retries || 0);

                if (!target || typeof tinymce === 'undefined') {
                    if (attemptCount >= 20) {
                        return;
                    }

                    window.setTimeout(function () {
                        window.initParentEnrollmentReasonEditor(target, attemptCount + 1);
                    }, 50);
                    return;
                }

                if (target.offsetParent === null) {
                    if (attemptCount >= 20) {
                        return;
                    }

                    window.setTimeout(function () {
                        window.initParentEnrollmentReasonEditor(target, attemptCount + 1);
                    }, 50);
                    return;
                }

                if (!target.id) {
                    target.id = 'custom_reason';
                }

                if (tinymce.get(target.id)) {
                    return;
                }

                tinymce.init({
                    selector: '#' + target.id,
                    license_key: 'gpl',
                    menubar: false,
                    branding: false,
                    height: 180,
                    plugins: 'lists link code',
                    toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat | code',
                    content_style: 'body { font-family: Poppins, sans-serif; font-size:14px }',
                    setup: function (editor) {
                        const sync = function () {
                            editor.save();
                            const element = editor.getElement();

                            if (element) {
                                element.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        };

                        editor.on('init', sync);
                        editor.on('change keyup SetContent', sync);
                    },
                });
            };

            window.syncParentEnrollmentReasonEditor = function () {
                if (typeof tinymce !== 'undefined') {
                    tinymce.triggerSave();
                }
            };

            window.destroyParentEnrollmentReasonEditor = function (textarea) {
                const target = textarea || document.getElementById('custom_reason');

                if (typeof tinymce === 'undefined' || !target || !target.id) {
                    return;
                }

                const editor = tinymce.get(target.id);
                if (editor) {
                    editor.save();
                    editor.remove();
                }
            };

            document.addEventListener('DOMContentLoaded', function () {
                @if($shouldOpenRejectModal && old('reason_code') === 'others')
                    window.initParentEnrollmentReasonEditor(document.getElementById('custom_reason'));
                @endif
            });

            window.addEventListener('beforeunload', function () {
                if (typeof tinymce !== 'undefined') {
                    tinymce.remove('textarea.js-guardian-enrollment-reason-editor');
                }
            });
        })();
    </script>
@endpush
