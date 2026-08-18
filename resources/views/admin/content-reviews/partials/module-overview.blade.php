<div class="bg-white shadow-sm rounded-xl border border-gray-200 p-5" x-data="{ overviewOpen: true, summaryOpen: true, thumbnailPreviewOpen: false }">
    @php
        $isPlatformOwner = (bool) data_get($workspace, 'instructor.is_platform_owner', in_array((string) data_get($workspace, 'module.content_owner_type', ''), ['admin', 'platform'], true));
        $creatorRoleLabel = $isPlatformOwner ? 'Platform Developer' : 'Instructor';
        $creatorAffiliation = (string) data_get($workspace, 'instructor.affiliation', $isPlatformOwner ? 'Conscious Connections Team' : 'Independent Instructor');
    @endphp
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Module Overview</h2>
    </div>

    <!-- Always Visible Header: Thumbnail, Name, Publisher -->
    <div class="mt-4 flex flex-col gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            @if(data_get($workspace, 'module.thumbnail_url'))
                <button type="button"
                    class="group relative overflow-hidden rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-300"
                    @click="thumbnailPreviewOpen = true"
                    aria-label="Preview module thumbnail">
                    <img src="{{ data_get($workspace, 'module.thumbnail_url') }}" alt="Module thumbnail" class="h-16 w-24 object-cover transition-transform duration-200 group-hover:scale-105">
                    <span class="absolute inset-0 flex items-center justify-center bg-gray-900/0 text-[11px] font-semibold text-white opacity-0 transition group-hover:bg-gray-900/35 group-hover:opacity-100">
                        View
                    </span>
                </button>
            @else
                <div class="flex h-16 w-24 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white text-xs font-semibold uppercase tracking-wide text-gray-500">
                    No Image
                </div>
            @endif

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Module Name</p>
                <p class="text-base font-semibold text-gray-900">{{ data_get($workspace, 'module.title', $reviewRequest->module_title) }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                @if(data_get($workspace, 'instructor.avatar'))
                    <img src="{{ data_get($workspace, 'instructor.avatar') }}" alt="Instructor avatar" class="h-full w-full object-cover">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(data_get($workspace, 'instructor.name', 'U'), 0, 1)) }}
                @endif
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Creator Information</p>
                <p class="text-sm font-semibold text-gray-900">{{ data_get($workspace, 'instructor.name', 'Unknown Instructor') }}</p>
                <p class="text-[11px] text-gray-500">{{ $creatorRoleLabel }} · {{ $creatorAffiliation }}</p>
            </div>

            <button type="button"
                class="ml-2 inline-flex items-center rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100"
                @click="instructorPreviewOpen = true; instructorPreviewTab = 'profile'">
                View Creator Profile
            </button>
        </div>
    </div>

    <!-- Toggle for Module Information -->
    <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-gray-900">Module Information</h3>
        <button type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50"    
            @click="overviewOpen = !overviewOpen">
            <span x-text="overviewOpen ? 'Hide' : 'Show'"></span>
            <svg class="h-3.5 w-3.5 transition-transform" :class="overviewOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">      
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

    <!-- Collapsible Module Information -->
    <div x-show="overviewOpen" class="space-y-4" style="display:none;">
        <dl class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Eligible Learners</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.age_group', 'Not specified') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Enrollment Mode</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.enrollment_mode', 'N/A') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Access Type</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.access_type', 'N/A') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Enrollment Limit</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.enrollment_limit') !== null ? data_get($workspace, 'module.enrollment_limit') : 'Unlimited' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Price Amount</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.price_amount') !== null ? number_format((float) data_get($workspace, 'module.price_amount'), 2) : '0.00' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Price Currency</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.price_currency', 'PHP') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Submission Date</dt>
                <dd class="font-semibold text-gray-900">{{ optional($reviewRequest->submitted_at)->toDayDateTimeString() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Current Status</dt>
                <dd class="font-semibold text-gray-900">{{ data_get($workspace, 'module.status_label', ucfirst(str_replace('_', ' ', $reviewRequest->status))) }}</dd>
            </div>
        </dl>

        <div class="mt-4 rounded-xl border border-gray-100 bg-white p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Module Structure Summary</p>
                <button type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50"                @click="summaryOpen = !summaryOpen">
                    <span x-text="summaryOpen ? 'Hide' : 'Show'"></span>
                    <svg class="h-3.5 w-3.5 transition-transform" :class="summaryOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">   
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
            <div x-show="summaryOpen" style="display:none;">
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">                <dt class="text-gray-500">Total Lessons</dt>
                    <dd class="text-lg font-semibold text-gray-900">{{ data_get($workspace, 'hierarchy.lesson_count', 0) }}</dd>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">                <dt class="text-gray-500">Total Lesson Topics</dt>
                    <dd class="text-lg font-semibold text-gray-900">{{ data_get($workspace, 'hierarchy.lesson_topic_count', 0) }}</dd>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">                <dt class="text-gray-500">Total Quizzes</dt>
                    <dd class="text-lg font-semibold text-gray-900">{{ data_get($workspace, 'hierarchy.quiz_count', 0) }}</dd>
                </div>
            </dl>
            </div>
        </div>

        @if(data_get($workspace, 'module.description'))
            <div class="mt-4 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Description</p>
                <p class="mt-1 text-sm text-gray-700">{{ data_get($workspace, 'module.description') }}</p>
            </div>
        @endif
    </div>

    @if(data_get($workspace, 'module.thumbnail_url'))
        <div x-show="thumbnailPreviewOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4"
            @click.self="thumbnailPreviewOpen = false">
            <div class="w-full max-w-4xl rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Module Thumbnail Preview</h3>
                    <button type="button"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        @click="thumbnailPreviewOpen = false"
                        aria-label="Close thumbnail preview">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="bg-gray-50 p-4 sm:p-6">
                    <img src="{{ data_get($workspace, 'module.thumbnail_url') }}"
                        alt="Module thumbnail full preview"
                        class="max-h-[78vh] w-full rounded-xl border border-gray-200 object-contain bg-white">
                </div>
            </div>
        </div>
    @endif
</div>
