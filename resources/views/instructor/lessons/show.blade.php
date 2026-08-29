@extends($contentPanelLayout ?? 'layouts.instructor-app')

@php
    $isAdminPanel = ($isContentAdminPanel ?? false) === true;
    $moduleOwnerType = strtolower(trim((string) ($lesson->module->content_owner_type ?? '')));

    if (!in_array($moduleOwnerType, ['admin', 'platform', 'instructor'], true)) {
        $moduleCreator = $lesson->module->creator;
        $moduleOwnerType = (($moduleCreator?->isAdmin() ?? false) || strtolower((string) ($moduleCreator?->role ?? '')) === 'admin')
            ? 'admin'
            : 'instructor';
    }

    $isInstructorOwnedModule = !in_array($moduleOwnerType, ['admin', 'platform'], true);
    $isReadOnlyAdminPanel = $isAdminPanel && $isInstructorOwnedModule;
    $ownershipRestrictionTooltip = 'Instructor-owned content is read-only in the admin panel.';
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isReadOnlyAdminPanel = @json($isReadOnlyAdminPanel);
        const el = document.getElementById('topics-sortable');
        if (el && !isReadOnlyAdminPanel) {
            Sortable.create(el, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'opacity-50',
                onEnd: function () {
                    const order = [...el.querySelectorAll('[data-topic-id]')].map(el => el.dataset.topicId);
                    fetch('{{ route($contentRoutePrefix . '.topics.reorder') }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ order }),
                    });
                }
            });
        }
    });

    function previewTopicModal(topicId) {
        const modal = document.getElementById('topicPreviewModal');
        const previewContent = document.getElementById('previewContent');
        modal.classList.remove('hidden');
        previewContent.innerHTML = `<div class="flex justify-center items-center py-12"><svg class="animate-spin h-8 w-8 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div>`;
        fetch(`/{{ $contentRoutePrefix ?? 'instructor' }}/topics/${topicId}/preview`)
            .then(r => r.json())
            .then(data => { previewContent.innerHTML = renderTopicPreview(data); })
            .catch(err => { previewContent.innerHTML = `<p class="text-center text-sm text-red-500 py-8">${err.message}</p>`; });
    }

    function closePreviewModal() {
        document.getElementById('topicPreviewModal').classList.add('hidden');
    }

    function renderTopicPreview(topic) {
        let content = `<div class="space-y-4"><div class="bg-gray-50 p-4 rounded-xl"><h4 class="text-lg font-semibold text-gray-900">${escapeHtml(topic.title || '')}</h4><div class="flex gap-3 mt-2"><span class="px-2 py-1 text-xs font-semibold rounded-full ${getTypeColor(topic.type)}">${capitalizeFirst(topic.type || 'topic')}</span><span class="text-sm text-gray-500">${topic.duration || 0} min</span></div></div>`;

        if (topic.type === 'video') {
            if (topic.video_url) {
                content += `<div class="aspect-video bg-black rounded-xl overflow-hidden"><iframe src="${topic.video_url}" class="w-full h-full" allowfullscreen></iframe></div>`;
            } else if (topic.video_file_url) {
                content += `<video controls class="w-full rounded-xl"><source src="${topic.video_file_url}" type="video/mp4"></video>`;
            } else {
                content += `<div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">No video source available for this topic.</div>`;
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
                            ${image.caption ? `<p class="text-xs text-gray-600 p-2">${escapeHtml(image.caption)}</p>` : ''}
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
                    .map((file) => `<a href="${file.url}" target="_blank" class="block text-sm text-brand-700 hover:underline">${escapeHtml(file.name || 'Worksheet File')}</a>`)
                    .join('');

                content += `<div class="rounded-xl bg-brand-50 p-4"><p class="text-sm font-semibold text-gray-900 mb-2">Worksheet Files</p>${fileList}</div>`;
            } else {
                content += `<div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">No worksheet file attached.</div>`;
            }

            if (topic.text_content) {
                content += `<div class="prose max-w-none p-4 bg-gray-50 rounded-xl">${topic.text_content}</div>`;
            }
        } else if (topic.type === 'interactive') {
            const interactiveType = topic.interactive_type || topic.interactive_config?.type || 'interactive';
            const interactiveInstructions = topic.interactive_instructions || topic.interactive_config?.instructions || topic.text_content || '';
            content += `<div class="rounded-xl border border-orange-200 bg-orange-50 p-4"><p class="text-sm font-semibold text-orange-800">Interactive Type: ${escapeHtml(capitalizeFirst(interactiveType))}</p>${interactiveInstructions ? `<div class="prose max-w-none mt-3">${interactiveInstructions}</div>` : '<p class="text-sm text-orange-700 mt-2">No instructions provided.</p>'}</div>`;
        } else {
            content += `<div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">Preview is not available for this topic type yet.</div>`;
        }

        content += `</div>`;
        return content;
    }

    function getTypeColor(type) {
        const c = { video: 'bg-red-100 text-red-800', text: 'bg-brand-100 text-brand-800', worksheet: 'bg-green-100 text-green-800', interactive: 'bg-orange-100 text-orange-800' };
        return c[type] ?? 'bg-gray-100 text-gray-800';
    }
    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
    function capitalizeFirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    document.getElementById('topicPreviewModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePreviewModal();
    });
</script>
@endpush

@section('content')
@php
    $moduleReviewStatus = (string) ($lesson->module->current_review_status ?? 'draft');
    $lessonStatusLabel = $lesson->is_published ? 'Published' : 'Draft';
    $lessonStatusClass = $lesson->is_published
        ? 'bg-green-100 text-green-700'
        : 'bg-gray-200 text-gray-600';
    $lessonStatusDotClass = $lesson->is_published ? 'bg-green-500' : 'bg-gray-400';

    if ($lesson->module->trashed()) {
        $lessonStatusLabel = 'Module Archived';
        $lessonStatusClass = 'bg-red-100 text-red-700';
        $lessonStatusDotClass = 'bg-red-500';
    } elseif ($moduleReviewStatus === 'in_review') {
        $lessonStatusLabel = 'Module Under Review';
        $lessonStatusClass = 'bg-amber-100 text-amber-700';
        $lessonStatusDotClass = 'bg-amber-500';
    } elseif ($moduleReviewStatus === 'submitted') {
        $lessonStatusLabel = 'Submission Pending';
        $lessonStatusClass = 'bg-orange-100 text-orange-700';
        $lessonStatusDotClass = 'bg-orange-500';
    } elseif ($moduleReviewStatus === 'needs_revision') {
        $lessonStatusLabel = 'Needs Revision';
        $lessonStatusClass = 'bg-rose-100 text-rose-700';
        $lessonStatusDotClass = 'bg-rose-500';
    } elseif (!$lesson->module->is_published) {
        $lessonStatusLabel = 'Module Inactive';
        $lessonStatusClass = 'bg-gray-100 text-gray-600';
        $lessonStatusDotClass = 'bg-gray-500';
    }
@endphp
<div class="space-y-5" x-data="{
    removeTopicOpen: false,
    removeTopicTitle: '',
    removeTopicAction: '',
    removeTopicTrigger: null,
    openRemoveTopic(title, action, trigger) {
        this.removeTopicTitle = title;
        this.removeTopicAction = action;
        this.removeTopicTrigger = trigger;
        this.removeTopicOpen = true;
        this.$nextTick(() => this.$refs.removeTopicCancel.focus());
    },
    closeRemoveTopic() {
        if (!this.removeTopicOpen) return;
        this.removeTopicOpen = false;
        this.$nextTick(() => this.removeTopicTrigger?.focus());
    },
}">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route($contentRoutePrefix . '.modules.show', $lesson->module) }}"
               class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">{{ $lesson->module->title }}</p>
                <h1 class="text-xl font-bold text-gray-900">{{ $lesson->title }}</h1>
            </div>
        </div>
        <a href="{{ $isReadOnlyAdminPanel ? '#' : route($contentRoutePrefix . '.lessons.edit', $lesson) }}"
           @if($isReadOnlyAdminPanel) aria-disabled="true" tabindex="-1" @click.prevent @endif
           title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Edit Lesson' }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 rounded-xl transition-colors flex-shrink-0 {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-200' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Lesson
        </a>
    </div>

    {{-- Lesson Info Card --}}
    <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Module</p>
                <p class="text-sm font-semibold text-gray-900 truncate">{{ $lesson->module->title }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Lesson Order</p>
                <p class="text-sm font-semibold text-gray-900">Lesson {{ $lesson->order ?? 1 }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Duration</p>
                <p class="text-sm font-semibold text-gray-900">{{ $lesson->topics->sum('duration') ?: ($lesson->duration ?? 0) }} min</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">Status</p>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold rounded-full {{ $lessonStatusClass }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $lessonStatusDotClass }}"></span>
                    {{ $lessonStatusLabel }}
                </span>
            </div>
        </div>
        @if($lesson->description)
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">{{ $lesson->description }}</p>
        </div>
        @endif
    </div>

    {{-- Topics Section --}}
    <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">

        {{-- Section header --}}
        <div class="flex items-start justify-between gap-4 mb-5">
            <div class="border-l-4 pl-3" style="border-color: #730DB1;">
                <h2 class="text-base font-semibold text-gray-900">Lesson Topics</h2>
                <p class="text-xs text-gray-400 mt-0.5">Drag to reorder</p>
            </div>
            <div class="flex items-center gap-2.5">
                <button @if($isReadOnlyAdminPanel) disabled @else @click="$store.modals.openQuizModal()" @endif
                        title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Add quiz' }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-xl border border-gray-200 text-gray-700 transition-colors {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Add Quiz
                </button>
                <a href="{{ $isReadOnlyAdminPanel ? '#' : route($contentRoutePrefix . '.topics.create', ['lesson' => $lesson->id]) }}"
                   @if($isReadOnlyAdminPanel) aria-disabled="true" tabindex="-1" @click.prevent @endif
                   title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Add topic' }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold text-white rounded-xl transition-all shadow-sm {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:opacity-90 active:scale-[0.98]' }}"
                   style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Topic
                </a>
            </div>
        </div>

        {{-- Learner progress chip --}}
        <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3 mb-5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-medium">Learner Progress</p>
                <p class="text-sm font-semibold text-gray-900">{{ $completedCount }} / {{ $enrolledCount }} completed</p>
            </div>
            <div class="flex-1 max-w-[120px] h-1.5 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all"
                     style="background: linear-gradient(to right, #A30EB2, #3B0CB1); width: {{ $completionRate }}%"></div>
            </div>
            <span class="text-xs font-bold text-gray-500 flex-shrink-0">{{ $completionRate }}%</span>
        </div>

        {{-- Topics list --}}
        @if($lesson->topics->isEmpty())
        <div class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
            <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm text-gray-500">No topics added yet</p>
            <p class="text-xs text-gray-400 mt-1">Click <strong>Add Topic</strong> to create content sections</p>
        </div>
        @else
        <ul id="topics-sortable" class="space-y-2">
            @foreach($lesson->topics as $topic)
            <li data-topic-id="{{ $topic->id }}"
                class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-purple-50/30 transition-colors">

                {{-- Drag handle --}}
                 <div class="drag-handle w-5 flex items-center justify-center text-gray-300 flex-shrink-0 {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:text-gray-400 cursor-grab active:cursor-grabbing' }}"
                     title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Drag to reorder' }}">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                    </svg>
                </div>

                {{-- Order badge --}}
                <div class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                     style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                    {{ $topic->order }}
                </div>

                {{-- Title --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $topic->title }}</p>
                </div>

                {{-- Type badge --}}
                <span class="hidden sm:inline-flex px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0
                    @if($topic->type === 'video') bg-red-100 text-red-700
                    @elseif($topic->type === 'text') bg-brand-100 text-brand-700
                    @elseif($topic->type === 'worksheet') bg-green-100 text-green-700
                    @elseif($topic->type === 'interactive') bg-orange-100 text-orange-700
                    @else bg-gray-100 text-gray-600 @endif">
                    {{ ucfirst($topic->type) }}
                </span>

                {{-- Duration --}}
                <span class="hidden md:flex items-center gap-0.5 text-xs text-gray-400 flex-shrink-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ $topic->duration }}m
                </span>

                {{-- Prerequisite badge --}}
                @if($topic->is_prerequisite)
                <span class="hidden sm:inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 flex-shrink-0">Req</span>
                @endif

                {{-- Actions --}}
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button onclick="previewTopicModal({{ $topic->id }})"
                            title="Preview"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-purple-600 hover:bg-purple-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                    <a href="{{ $isReadOnlyAdminPanel ? '#' : route($contentRoutePrefix . '.topics.edit', $topic) }}"
                       @if($isReadOnlyAdminPanel) aria-disabled="true" tabindex="-1" @click.prevent @endif
                       title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Edit' }}"
                       class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 transition-colors {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:text-brand-600 hover:bg-brand-50' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    <button type="button"
                            @if($isReadOnlyAdminPanel) disabled @else @click="openRemoveTopic(@js($topic->title), @js(route($contentRoutePrefix . '.topics.destroy', $topic)), $event.currentTarget)" @endif
                            title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Remove topic' }}"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 transition-colors {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:bg-red-50 hover:text-red-600' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
                    </button>
                </div>
                @foreach($topic->checkpointQuestions->whereNotNull('checkpoint_block_uuid') as $checkpoint)
                    <a href="{{ $isReadOnlyAdminPanel ? '#' : route($contentRoutePrefix . '.topics.checkpoints.edit', [$topic, $checkpoint]) }}"
                        @if($isReadOnlyAdminPanel) aria-disabled="true" tabindex="-1" @click.prevent @endif
                        class="basis-full rounded-xl border border-purple-100 bg-purple-50 px-3 py-2 text-xs font-semibold text-purple-700 {{ $isReadOnlyAdminPanel ? 'pointer-events-none opacity-50' : 'hover:bg-purple-100' }}">
                        Edit checkpoint: {{ \Illuminate\Support\Str::limit(strip_tags($checkpoint->question_text), 55) }}
                    </a>
                @endforeach
            </li>
            @endforeach
        </ul>
        @endif
    </div>

    {{-- Quiz Section --}}
    @php $lessonQuizzes = $lesson->quizzes()->with('questions')->latest()->get(); @endphp
    <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">
        <div class="border-l-4 pl-3 mb-5" style="border-color: #730DB1;">
            <h2 class="text-base font-semibold text-gray-900">Lesson Quizzes</h2>
            <p class="text-xs text-gray-400 mt-0.5">Quizzes linked to this lesson</p>
        </div>

        @if($lessonQuizzes->isEmpty())
        <div class="text-center py-8 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
            <svg class="mx-auto w-9 h-9 text-gray-300 mb-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-sm text-gray-500">No quizzes yet.</p>
            <button @if($isReadOnlyAdminPanel) disabled @else @click="$store.modals.openQuizModal()" @endif
                    title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Create one now' }}"
                    class="mt-2 text-sm font-semibold {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:underline' }}" style="color: #730DB1;">
                Create one now
            </button>
        </div>
        @else
        <div class="space-y-2">
            @foreach($lessonQuizzes as $lq)
            <div class="flex items-center justify-between p-3.5 rounded-xl bg-gray-50/50 border border-gray-100">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 bg-purple-100">
                        <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $lq->title }}</p>
                        <p class="text-xs text-gray-400">{{ $lq->questions->count() }} questions &bull; {{ $lq->passing_score }}% passing</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 flex-shrink-0 ml-4">
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                        {{ $lq->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $lq->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <a href="{{ route($contentRoutePrefix . '.quizzes.show', $lq) }}"
                       class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-purple-600 hover:bg-purple-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </a>
                    <a href="{{ $isReadOnlyAdminPanel ? '#' : route($contentRoutePrefix . '.quizzes.edit', $lq) }}"
                       @if($isReadOnlyAdminPanel) aria-disabled="true" tabindex="-1" @click.prevent @endif
                       title="{{ $isReadOnlyAdminPanel ? $ownershipRestrictionTooltip : 'Edit quiz' }}"
                       class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 transition-colors {{ $isReadOnlyAdminPanel ? 'cursor-not-allowed opacity-50' : 'hover:text-brand-600 hover:bg-brand-50' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div x-show="removeTopicOpen" x-cloak @keydown.escape.window="closeRemoveTopic()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/55 p-4 backdrop-blur-sm"
         role="dialog" aria-modal="true" aria-labelledby="remove-topic-title" aria-describedby="remove-topic-description">
        <div @click.outside="closeRemoveTopic()" class="w-full max-w-md overflow-hidden rounded-2xl border border-red-100 bg-white shadow-2xl">
            <div class="border-b border-red-100 bg-red-50 px-6 py-4">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-600">Destructive action</p>
                <h2 id="remove-topic-title" class="mt-1 text-lg font-bold text-gray-900">Remove Topic</h2>
            </div>
            <div class="px-6 py-5">
                <p id="remove-topic-description" class="text-sm leading-6 text-gray-600">
                    Remove <strong class="font-semibold text-gray-900" x-text="removeTopicTitle"></strong>? Its associated inside-topic checkpoints will also be removed. This cannot be undone.
                </p>
                <form :action="removeTopicAction" method="POST" class="mt-6 flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button x-ref="removeTopicCancel" type="button" @click="closeRemoveTopic()" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700">Remove Topic</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Topic Preview Modal --}}
<div id="topicPreviewModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-16 mx-auto p-5 w-11/12 md:w-3/4 max-w-2xl">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Topic Preview</h3>
                <button onclick="closePreviewModal()"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="previewContent" class="p-6"></div>
        </div>
    </div>
</div>

{{-- Quiz Creation Modal --}}
@if(!$isReadOnlyAdminPanel)
    @include('instructor.lessons.partials.quiz-modal')
@endif
@endsection
