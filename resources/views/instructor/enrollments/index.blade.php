@extends($contentPanelLayout ?? 'layouts.instructor-app')

@php
    $isAdminPanel = ($isContentAdminPanel ?? false) === true;
    $ownershipRestrictionTooltip = 'Instructor-owned content is read-only in the admin panel.';
    $statusFilter = $statusFilter ?? 'all';
    $search = $search ?? '';
    $moduleFilter = $moduleFilter ?? 0;
    $statusCounts = $statusCounts ?? ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    $modulesForFilter = $modulesForFilter ?? collect();
    $rowOffset = ($enrollments->currentPage() - 1) * $enrollments->perPage();

    $statusOptions = [
        'all' => 'All statuses',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ];
@endphp

@section('content')
<div x-data="{
    viewModalOpen: false,
    confirmModalOpen: false,
    viewPayload: null,
    confirmForm: null,
    confirmMessage: '',
    confirmButtonLabel: 'Confirm',
    openView(payload) {
        this.viewPayload = payload;
        this.viewModalOpen = true;
    },
    closeView() {
        this.viewPayload = null;
        this.viewModalOpen = false;
    },
    openConfirm(form, message, label) {
        this.confirmForm = form;
        this.confirmMessage = message;
        this.confirmButtonLabel = label;
        this.confirmModalOpen = true;
    },
    closeConfirm() {
        this.confirmForm = null;
        this.confirmMessage = '';
        this.confirmButtonLabel = 'Confirm';
        this.confirmModalOpen = false;
    },
    confirmAction() {
        if (this.confirmForm) {
            this.confirmForm.submit();
        }
    }
}" class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Enrollments</h1>
            <p class="text-sm text-gray-500">Track learner requests and enrollment records.</p>
        </div>
    </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <a href="{{ route($contentRoutePrefix . '.enrollments.index', array_filter(array_merge(request()->query(), ['status' => 'all', 'page' => null]))) }}" class="transition hover:border-brand-300 {{ $statusFilter === 'all' ? 'ring-2 ring-brand-400 border-brand-400' : '' }} min-h-[116px] rounded-[28px] border border-brand-200 bg-gradient-to-br from-brand-50 via-white to-brand-100/70 p-5 shadow-theme-xs block">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-700">All</p>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 via-brand-700 to-brand-900 text-white shadow-lg shadow-brand-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <p class="mt-3 text-4xl leading-none font-bold text-gray-900">{{ $statusCounts['all'] ?? 0 }}</p>
        </a>
        <a href="{{ route($contentRoutePrefix . '.enrollments.index', array_filter(array_merge(request()->query(), ['status' => 'pending', 'page' => null]))) }}" class="transition hover:border-brand-300 {{ $statusFilter === 'pending' ? 'ring-2 ring-brand-400 border-brand-400' : '' }} min-h-[116px] rounded-[28px] border border-brand-100 bg-gradient-to-br from-white via-brand-50/70 to-brand-100/60 p-5 shadow-theme-xs block">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-600">Pending</p>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 via-brand-600 to-brand-800 text-white shadow-lg shadow-brand-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="mt-3 text-4xl leading-none font-bold text-gray-900">{{ $statusCounts['pending'] ?? 0 }}</p>
        </a>
        <a href="{{ route($contentRoutePrefix . '.enrollments.index', array_filter(array_merge(request()->query(), ['status' => 'approved', 'page' => null]))) }}" class="transition hover:border-brand-300 {{ $statusFilter === 'approved' ? 'ring-2 ring-brand-400 border-brand-400' : '' }} min-h-[116px] rounded-[28px] border border-brand-200 bg-gradient-to-br from-brand-100/60 via-white to-brand-50 p-5 shadow-theme-xs block">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-800">Approved</p>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 text-white shadow-lg shadow-brand-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="mt-3 text-4xl leading-none font-bold text-gray-900">{{ $statusCounts['approved'] ?? 0 }}</p>
        </a>
        <a href="{{ route($contentRoutePrefix . '.enrollments.index', array_filter(array_merge(request()->query(), ['status' => 'rejected', 'page' => null]))) }}" class="transition hover:border-brand-300 {{ $statusFilter === 'rejected' ? 'ring-2 ring-brand-400 border-brand-400' : '' }} min-h-[116px] rounded-[28px] border border-brand-300 bg-gradient-to-br from-brand-100 via-white to-brand-200/70 p-5 shadow-theme-xs block">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-900">Rejected</p>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white shadow-lg shadow-brand-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="mt-3 text-4xl leading-none font-bold text-gray-900">{{ $statusCounts['rejected'] ?? 0 }}</p>
        </a>
        <a href="{{ route($contentRoutePrefix . '.enrollments.index', array_filter(array_merge(request()->query(), ['status' => 'archived', 'page' => null]))) }}" class="transition hover:border-brand-300 {{ $statusFilter === 'archived' ? 'ring-2 ring-brand-400 border-brand-400' : '' }} min-h-[116px] rounded-[28px] border border-gray-200 bg-gradient-to-br from-gray-50 via-white to-gray-100 p-5 shadow-theme-xs block">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gray-700">Archived</p>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-gray-500 via-gray-600 to-gray-700 text-white shadow-lg shadow-gray-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M5 7l1 12h12l1-12M9 7V4h6v3"/></svg>
                </span>
            </div>
            <p class="mt-3 text-4xl leading-none font-bold text-gray-900">{{ $statusCounts['archived'] ?? 0 }}</p>
        </a>
    </div>

    <section class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs mb-6" data-enrollment-list>
        <div class="border-b border-brand-100 bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="mt-2 text-xl font-bold text-gray-900">Enrollments Table</h2>
                </div>
                <form method="GET" action="{{ route($contentRoutePrefix . '.enrollments.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" data-testid="admin-table-filter-bar">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <label class="block xl:col-span-2">
                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
                        <input type="text" name="search" value="{{ $search ?? '' }}"
                               placeholder="Search learner..."
                               x-data
                               @input.debounce.500ms="$el.form.submit()"
                               class="w-full px-4 py-3 text-sm text-gray-900 transition bg-white border border-brand-100 shadow-sm outline-none rounded-2xl focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                    </label>
                    <label class="block xl:col-span-2">
                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Module</span>
                        <select name="module_id" onchange="this.form.submit()" class="w-full px-4 py-3 text-sm text-gray-900 transition bg-white border border-brand-100 shadow-sm outline-none rounded-2xl focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                            <option value="0">All modules</option>
                            @foreach($modulesForFilter as $moduleOption)
                                <option value="{{ $moduleOption->id }}" {{ (int) $moduleFilter === (int) $moduleOption->id ? 'selected' : '' }}>{{ Str::limit($moduleOption->title, 40) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="hidden xl:block"></div>
                    <div class="hidden xl:flex xl:items-center xl:justify-end">
                        @if($search || $moduleFilter)
                            <a href="{{ route($contentRoutePrefix . '.enrollments.index', array_merge(request()->query(), ['search' => null, 'module_id' => null, 'page' => null])) }}"
                               class="text-sm font-semibold text-brand-600 hover:text-brand-500 transition">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-brand-50/45">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">No.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Learner Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Module Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Enrollment Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Enrollment Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($enrollments as $index => $enrollment)
                        @php
                            $learner = $enrollment->user;
                            $profile = $learner?->learnerProfile;
                            $module = $enrollment->module;
                            $isArchived = (string) $enrollment->rejection_reason_code === 'archived_enrollment';
                            $isPendingDecision = in_array((string) $enrollment->status->value, ['pending', 'pending_parent_approval'], true);

                            $statusLabel = $isArchived
                                ? 'Archived'
                                : match((string) $enrollment->status->value) {
                                    'pending_parent_approval' => 'Pending Guardian Approval',
                                    'pending' => 'Pending',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected',
                                    default => ucfirst((string) $enrollment->status->value),
                                };

                            $statusClasses = $isArchived
                                ? 'bg-gray-100 text-gray-700 border-gray-200'
                                : match((string) $enrollment->status->value) {
                                    'pending', 'pending_parent_approval' => 'bg-amber-100 text-amber-700 border-amber-200',
                                    'approved' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    default => 'bg-rose-100 text-rose-700 border-rose-200',
                                };

                            $payload = [
                                'name' => (string) ($learner?->full_name ?: $learner?->name ?: 'Learner'),
                                'email' => (string) ($learner?->email ?: 'N/A'),
                                'username' => (string) ($profile?->username ?: 'N/A'),
                                'age_bracket' => (string) ($profile?->age_range ?: 'N/A'),
                                'module' => (string) ($module?->title ?: 'Unknown module'),
                                'status' => $statusLabel,
                                'enrolled_at' => optional($enrollment->enrolled_at ?: $enrollment->created_at)->format('M d, Y h:i A'),
                            ];

                            $moduleOwnerType = strtolower(trim((string) ($module?->content_owner_type ?? '')));
                            if (!in_array($moduleOwnerType, ['admin', 'platform', 'instructor'], true)) {
                                $moduleCreator = $module?->creator;
                                $moduleOwnerType = (($moduleCreator?->isAdmin() ?? false) || strtolower((string) ($moduleCreator?->role ?? '')) === 'admin')
                                    ? 'admin'
                                    : 'instructor';
                            }
                            $isRestrictedAdminMutation = $isAdminPanel && !in_array($moduleOwnerType, ['admin', 'platform'], true);
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $rowOffset + $index + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $learner?->full_name ?: $learner?->name ?: 'Learner' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $learner?->email ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $module?->title ?: 'Unknown module' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ optional($enrollment->enrolled_at ?: $enrollment->created_at)->format('M d, Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route($contentRoutePrefix . '.enrollments.show', $enrollment) }}"
                                       title="View details"
                                       class="inline-flex items-center justify-center w-10 h-10 transition border rounded-2xl border-brand-200 bg-white hover:bg-brand-50 text-gray-700">
                                        <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    @if($isPendingDecision)
                                        <form method="POST" action="{{ route($contentRoutePrefix . '.enrollments.approve', $enrollment) }}" class="inline"
                                              @submit.prevent="@if($isRestrictedAdminMutation) false @else openConfirm($event.target, 'Approve this enrollment request?', 'Approve') @endif">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    @if($isRestrictedAdminMutation) disabled @endif
                                                    title="{{ $isRestrictedAdminMutation ? $ownershipRestrictionTooltip : 'Approve' }}"
                                                    class="h-8 w-8 rounded-lg text-gray-400 transition-colors {{ $isRestrictedAdminMutation ? 'cursor-not-allowed opacity-50' : 'hover:bg-emerald-50 hover:text-emerald-700' }}">
                                                <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route($contentRoutePrefix . '.enrollments.reject', $enrollment) }}" class="inline"
                                              @submit.prevent="@if($isRestrictedAdminMutation) false @else openConfirm($event.target, 'Reject this enrollment request?', 'Reject') @endif">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="rejection_reason_code" value="did_not_meet_requirements">
                                            <input type="hidden" name="rejection_reason_note" value="Rejected via enrollment management quick action.">
                                            <button type="submit"
                                                    @if($isRestrictedAdminMutation) disabled @endif
                                                    title="{{ $isRestrictedAdminMutation ? $ownershipRestrictionTooltip : 'Reject' }}"
                                                    class="h-8 w-8 rounded-lg text-gray-400 transition-colors {{ $isRestrictedAdminMutation ? 'cursor-not-allowed opacity-50' : 'hover:bg-rose-50 hover:text-rose-700' }}">
                                                <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    @if((string) $enrollment->status->value === 'approved' && $statusFilter === 'approved')
                                        <form method="POST" action="{{ route($contentRoutePrefix . '.enrollments.archive', $enrollment) }}" class="inline"
                                              @submit.prevent="@if($isRestrictedAdminMutation) false @else openConfirm($event.target, 'Archive this enrollment record?', 'Archive') @endif">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    @if($isRestrictedAdminMutation) disabled @endif
                                                    title="{{ $isRestrictedAdminMutation ? $ownershipRestrictionTooltip : 'Archive' }}"
                                                    class="h-8 w-8 rounded-lg text-gray-400 transition-colors {{ $isRestrictedAdminMutation ? 'cursor-not-allowed opacity-50' : 'hover:bg-amber-50 hover:text-amber-700' }}">
                                                <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7l1 12h12l1-12M9 7V4h6v3"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($statusFilter, ['rejected', 'archived'], true))
                                        <form method="POST" action="{{ route($contentRoutePrefix . '.enrollments.destroy', $enrollment) }}" class="inline"
                                              @submit.prevent="@if($isRestrictedAdminMutation) false @else openConfirm($event.target, 'Permanently delete this enrollment record?', 'Delete') @endif">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    @if($isRestrictedAdminMutation) disabled @endif
                                                    title="{{ $isRestrictedAdminMutation ? $ownershipRestrictionTooltip : 'Delete' }}"
                                                    class="h-8 w-8 rounded-lg text-gray-400 transition-colors {{ $isRestrictedAdminMutation ? 'cursor-not-allowed opacity-50' : 'hover:bg-rose-50 hover:text-rose-700' }}">
                                                <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">No enrollments found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @if($enrollments->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $enrollments->links() }}
            </div>
        @endif
    </div>
    </section>

    <div x-show="viewModalOpen" x-cloak class="fixed inset-0 z-40 bg-gray-900/60" @click="closeView()"></div>
    <div x-show="viewModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Learner Enrollment Details</h3>
                <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="closeView()">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 text-sm">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Learner</p>
                    <p class="mt-1 font-medium text-gray-900" x-text="viewPayload?.name"></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Email</p>
                    <p class="mt-1 text-gray-900" x-text="viewPayload?.email"></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Username</p>
                    <p class="mt-1 text-gray-900" x-text="viewPayload?.username"></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Age Bracket</p>
                    <p class="mt-1 text-gray-900" x-text="viewPayload?.age_bracket"></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Module</p>
                    <p class="mt-1 text-gray-900" x-text="viewPayload?.module"></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Enrollment Status</p>
                    <p class="mt-1 text-gray-900" x-text="viewPayload?.status"></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Enrollment Date</p>
                    <p class="mt-1 text-gray-900" x-text="viewPayload?.enrolled_at"></p>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" @click="closeView()" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Close</button>
            </div>
        </div>
    </div>

    <div x-show="confirmModalOpen" x-cloak class="fixed inset-0 z-40 bg-gray-900/60" @click="closeConfirm()"></div>
    <div x-show="confirmModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900">Confirm Action</h3>
            <p class="mt-2 text-sm text-gray-600" x-text="confirmMessage"></p>
            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" @click="closeConfirm()" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Cancel</button>
                <button type="button" @click="confirmAction()" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700" x-text="confirmButtonLabel"></button>
            </div>
        </div>
</div>
@endsection

