@extends('layouts.admin')

@section('title', 'Guardian & Dependent Verifications')
@section('page-title', 'Guardian & Dependent Verifications')

@section('content')
@php
    $moderationReasons = \App\Enums\ParentChildModerationReason::cases();
    $parentStatusCounts = [
        'pending' => (int) $pendingParentCount,
        'approved' => (int) $approvedParentCount,
        'rejected' => (int) $rejectedParentCount,
    ];
    $childStatusCounts = [
        'pending' => (int) $pendingChildCount,
        'approved' => (int) $approvedChildCount,
        'rejected' => (int) $rejectedChildCount,
    ];
    $relationshipStatusCounts = [
        'pending' => (int) $pendingRelationshipCount,
        'approved' => (int) $approvedRelationshipCount,
        'rejected' => (int) $rejectedRelationshipCount,
    ];
    $parentSearchRows = $parentApplications->map(function ($application) {
        $statusValue = $application->parent_verification_status ?: 'pending';
        $search = strtolower(trim(implode(' ', array_filter([
            (string) ($application->full_name ?? ''),
            (string) ($application->email ?? ''),
            (string) ($application->created_at?->format('M d, Y h:i A') ?? ''),
            (string) $statusValue,
        ]))));

        return [
            'id' => (int) $application->id,
            'status' => $statusValue,
            'search' => $search,
        ];
    })->values();
    $childSearchRows = $childApplications->map(function ($application) {
        $statusValue = $application->verification_status ?: 'pending';
        $search = strtolower(trim(implode(' ', array_filter([
            (string) ($application->parent?->full_name ?? ''),
            (string) ($application->parent?->email ?? ''),
            (string) ($application->child?->full_name ?? ''),
            (string) ($application->child?->learnerProfile?->username ?? ''),
            (string) ($application->created_at?->format('M d, Y h:i A') ?? ''),
            (string) $statusValue,
        ]))));

        return [
            'id' => (int) $application->id,
            'status' => $statusValue,
            'search' => $search,
        ];
    })->values();
    $relationshipSearchRows = $relationshipApplications->map(function ($application) {
        $statusValue = match ((string) ($application->relationship_verified_status ?: 'pending')) {
            'verified' => 'approved',
            'rejected', 'revoked' => 'rejected',
            default => 'pending',
        };
        $search = strtolower(trim(implode(' ', array_filter([
            (string) ($application->parent?->full_name ?? ''),
            (string) ($application->parent?->email ?? ''),
            (string) ($application->child?->full_name ?? ''),
            (string) ($application->child?->learnerProfile?->username ?? ''),
            (string) $application->relationshipLabel(),
            (string) $application->relationshipVerificationLabel(),
            (string) ($application->relationship_verification_submitted_at?->format('M d, Y h:i A') ?? ''),
            (string) $statusValue,
        ]))));

        return [
            'id' => (int) $application->id,
            'status' => $statusValue,
            'search' => $search,
        ];
    })->values();
@endphp

<div class="mx-auto max-w-7xl px-4 py-8"
     x-data="{
        activeType: @js($type),
        activeStatus: @js($status),
        parentCounts: @js($parentStatusCounts),
        childCounts: @js($childStatusCounts),
        relationshipCounts: @js($relationshipStatusCounts),
        parentSearchRows: @js($parentSearchRows),
        childSearchRows: @js($childSearchRows),
        relationshipSearchRows: @js($relationshipSearchRows),
        searchQuery: '',
        page: 1,
        perPage: 10,
        flashMessage: '',
        flashType: 'success',
        previewOpen: false,
        previewUrl: '',
        previewTitle: '',
        previewType: 'file',
        previewCompareUrl: '',
        previewCompareTitle: '',
        previewCompareType: 'file',
        previewDetails: {},
        setType(type) {
            this.activeType = type;
            this.navigate();
        },
        setStatus(status) {
            this.activeStatus = status;
            this.navigate();
        },
        navigate() {
            const params = new URLSearchParams();
            params.set('type', this.activeType);
            params.set('status', this.activeStatus);
            window.location.assign(window.location.pathname + '?' + params.toString());
        },
        normalizedSearchQuery() {
            return String(this.searchQuery || '').trim().toLowerCase();
        },
        rowMatchesSearch(searchableText) {
            const query = this.normalizedSearchQuery();

            if (query === '') {
                return true;
            }

            return String(searchableText || '').toLowerCase().includes(query);
        },
        rowsFor(type) {
            if (type === 'parents') {
                return this.parentSearchRows;
            }

            if (type === 'relationships') {
                return this.relationshipSearchRows;
            }

            return this.childSearchRows;
        },
        filteredRowsFor(type, status = null) {
            const query = this.normalizedSearchQuery();

            return this.rowsFor(type).filter((row) => {
                const rowStatus = row.status || 'pending';

                if (status && rowStatus !== status) {
                    return false;
                }

                if (query === '') {
                    return true;
                }

                return String(row.search || '').includes(query);
            });
        },
        filteredCountFor(type, status) {
            return this.filteredRowsFor(type, status).length;
        },
        countFor(type, status) {
            const query = this.normalizedSearchQuery();

            if (query !== '') {
                return this.filteredCountFor(type, status);
            }

            const counts = type === 'parents' ? this.parentCounts : (type === 'relationships' ? this.relationshipCounts : this.childCounts);
            return Number(counts[status] || 0);
        },
        totalCountFor(type) {
            const counts = type === 'parents' ? this.parentCounts : (type === 'relationships' ? this.relationshipCounts : this.childCounts);

            return Number(counts.pending || 0)
                + Number(counts.approved || 0)
                + Number(counts.rejected || 0);
        },
        totalRowsForCurrent() {
            return this.filteredRowsFor(this.activeType, this.activeStatus).length;
        },
        totalPages() {
            const pages = Math.ceil(this.totalRowsForCurrent() / this.perPage);

            return pages > 0 ? pages : 1;
        },
        safePage() {
            return Math.min(Math.max(this.page, 1), this.totalPages());
        },
        setPage(page) {
            const targetPage = Number(page);

            if (!Number.isFinite(targetPage)) {
                return;
            }

            this.page = Math.min(Math.max(Math.floor(targetPage), 1), this.totalPages());
        },
        prevPage() {
            this.setPage(this.safePage() - 1);
        },
        nextPage() {
            this.setPage(this.safePage() + 1);
        },
        formatNumber(value) {
            const numeric = Number(value || 0);

            return Number.isFinite(numeric) ? numeric.toLocaleString() : '0';
        },
        rowOnCurrentPage(type, rowId) {
            const rows = this.filteredRowsFor(type, this.activeStatus);
            const safePage = this.safePage();
            const start = (safePage - 1) * this.perPage;
            const visibleRows = rows.slice(start, start + this.perPage);

            return visibleRows.some((row) => Number(row.id) === Number(rowId));
        },
        rowNumberFor(type, rowId) {
            const rows = this.filteredRowsFor(type, this.activeStatus);
            const index = rows.findIndex((row) => Number(row.id) === Number(rowId));

            return index >= 0 ? index + 1 : '-';
        },
        hasRowsForCurrent() {
            return this.totalRowsForCurrent() > 0;
        },
        notify(message, type = 'success') {
            if (window.toast && typeof window.toast[type] === 'function') {
                window.toast[type](message);
                return;
            }

            this.flashMessage = message;
            this.flashType = type;
            setTimeout(() => {
                this.flashMessage = '';
            }, 3500);
        },
        handleModerationUpdated(detail) {
            const countsKey = detail.type === 'parents' ? 'parentCounts' : (detail.type === 'relationships' ? 'relationshipCounts' : 'childCounts');
            const rowsKey = detail.type === 'parents' ? 'parentSearchRows' : (detail.type === 'relationships' ? 'relationshipSearchRows' : 'childSearchRows');
            const fromStatus = detail.oldStatus || 'pending';
            const toStatus = detail.newStatus || 'pending';
            const rowId = Number(detail.rowId || 0);

            if (fromStatus !== toStatus) {
                this[countsKey][fromStatus] = Math.max(Number(this[countsKey][fromStatus] || 0) - 1, 0);
                this[countsKey][toStatus] = Number(this[countsKey][toStatus] || 0) + 1;
            }

            if (rowId > 0) {
                const row = this[rowsKey].find((item) => Number(item.id) === rowId);

                if (row) {
                    row.status = toStatus;
                }
            }

            this.page = this.safePage();

            this.notify(detail.message || 'Moderation decision saved successfully.', 'success');
        },
        handleModerationError(detail) {
            this.notify(detail.message || 'Failed to save moderation decision.', 'error');
        },
        openPreview(url, title, type, details = {}, compare = null) {
            this.previewUrl = url;
            this.previewTitle = title;
            this.previewType = type;
            this.previewCompareUrl = compare?.url || '';
            this.previewCompareTitle = compare?.title || '';
            this.previewCompareType = compare?.type || 'file';
            this.previewDetails = details || {};
            this.previewOpen = true;
        },
        closePreview() {
            this.previewOpen = false;
            this.previewUrl = '';
            this.previewTitle = '';
            this.previewType = 'file';
            this.previewCompareUrl = '';
            this.previewCompareTitle = '';
            this.previewCompareType = 'file';
            this.previewDetails = {};
        }
     }"
     @moderation-updated.window="handleModerationUpdated($event.detail)"
     @moderation-error.window="handleModerationError($event.detail)">
    <div :class="previewOpen ? 'blur-[2px] scale-[0.995] pointer-events-none select-none' : ''" class="space-y-6 transition duration-300 ease-out">
        <template x-if="flashMessage !== ''">
            <div class="rounded-2xl border px-4 py-3 text-sm"
                 :class="flashType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                 x-text="flashMessage"></div>
        </template>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            <article class="min-h-[116px] rounded-[28px] border border-brand-200 bg-gradient-to-br from-brand-50 via-white to-brand-100/70 p-5 shadow-theme-xs">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-700" x-text="activeType === 'parents' ? 'Guardian Applications' : (activeType === 'relationships' ? 'Relationship Verifications' : 'Dependent Applications')">Guardian Applications</p>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 via-brand-700 to-brand-900 text-white shadow-lg shadow-brand-200">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                </div>
                <p class="mt-3 text-4xl leading-none font-bold text-gray-900" x-text="formatNumber(totalCountFor(activeType))">{{ number_format($pendingParentCount + $approvedParentCount + $rejectedParentCount) }}</p>
            </article>
            <article class="min-h-[116px] rounded-[28px] border border-brand-100 bg-gradient-to-br from-white via-brand-50/70 to-brand-100/60 p-5 shadow-theme-xs">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-600">Pending</p>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 via-brand-600 to-brand-800 text-white shadow-lg shadow-brand-200">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-4xl leading-none font-bold text-gray-900" x-text="formatNumber(countFor(activeType, 'pending'))">{{ number_format($pendingParentCount) }}</p>
            </article>
            <article class="min-h-[116px] rounded-[28px] border border-brand-200 bg-gradient-to-br from-brand-100/60 via-white to-brand-50 p-5 shadow-theme-xs">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-800">Approved</p>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 text-white shadow-lg shadow-brand-300">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-4xl leading-none font-bold text-gray-900" x-text="formatNumber(countFor(activeType, 'approved'))">{{ number_format($approvedParentCount) }}</p>
            </article>
            <article class="min-h-[116px] rounded-[28px] border border-brand-300 bg-gradient-to-br from-brand-100 via-white to-brand-200/70 p-5 shadow-theme-xs">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brand-900">Rejected</p>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white shadow-lg shadow-brand-300">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </span>
                </div>
                <p class="mt-3 text-4xl leading-none font-bold text-gray-900" x-text="formatNumber(countFor(activeType, 'rejected'))">{{ number_format($rejectedParentCount) }}</p>
            </article>
        </section>

                <section class="overflow-hidden rounded-[30px] border border-gray-200 bg-white shadow-theme-xs">
            <div class="border-b border-brand-100 bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.17),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,12,177,0.14),_transparent_32%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h2 class="mt-2 text-xl font-bold text-gray-900">Verifications Table</h2>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" data-testid="admin-table-filter-bar">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Account Type</span>
                            <select x-model="activeType" @change="setType($event.target.value)" class="w-full px-4 py-3 text-sm text-gray-900 transition bg-white border border-brand-100 shadow-sm outline-none rounded-2xl focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                                <option value="children">Dependent Verifications</option>
                                <option value="parents">Guardian Verifications</option>
                                <option value="relationships">Relationship Verifications</option>
                            </select>
                        </label>
                        <label class="block xl:col-span-2">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
                            <input type="text" x-model.debounce.300ms="searchQuery" placeholder="Name, email, ID number..." class="w-full px-4 py-3 text-sm text-gray-900 transition bg-white border border-brand-100 shadow-sm outline-none rounded-2xl focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span>
                            <select x-model="activeStatus" @change="setStatus($event.target.value)" class="w-full px-4 py-3 text-sm text-gray-900 transition bg-white border border-brand-100 shadow-sm outline-none rounded-2xl focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </label>
                        <div class="hidden xl:block"></div>
                        <div class="hidden xl:block"></div>
                        <div class="hidden xl:block"></div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto" x-show="activeType === 'parents'" x-cloak>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-brand-50/45">
                        <tr>
                            <th class="w-[8%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">No. #</th>
                            <th class="w-[24%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Guardian</th>
                            <th class="w-[18%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">ID Type</th>
                            <th class="w-[14%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500 whitespace-nowrap">Status</th>
                            <th class="w-[18%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500 whitespace-nowrap">Submitted</th>
                            <th class="w-[14%] px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.18em] text-gray-500 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($parentApplications as $application)
                            @php
                                $statusValue = $application->parent_verification_status ?: 'pending';
                                $parentDocumentPath = (string) ($application->parent_id_document_path ?? '');
                                $hasParentDocument = $parentDocumentPath !== '';
                                $parentDocumentUrl = $hasParentDocument ? route('admin.parent-verifications.parents.document', [$application, 'front']) : null;
                                $parentIdTypeLabels = [
                                    'national_id' => 'National ID (PhilSys)',
                                    'passport' => 'Passport',
                                    'drivers_license' => "Driver's License",
                                    'umid' => 'UMID',
                                    'philhealth' => 'PhilHealth ID',
                                    'postal_id' => 'Postal ID',
                                    'senior_citizen_id' => 'Senior Citizen ID',
                                    'pwd_id' => 'PWD ID',
                                    'prc_id' => 'PRC ID',
                                    'voters_id' => "Voter's ID (if applicable)",
                                    'other' => $application->parent_id_type_other ?: 'Other',
                                ];
                                $parentIdTypeLabel = $parentIdTypeLabels[$application->parent_id_type] ?? 'Not submitted';
                                $parentDocumentExtension = $hasParentDocument ? strtolower(pathinfo($parentDocumentPath, PATHINFO_EXTENSION)) : null;
                                $parentPreviewType = $hasParentDocument
                                    ? (in_array($parentDocumentExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                                        ? 'image'
                                        : ($parentDocumentExtension === 'pdf' ? 'pdf' : 'file'))
                                    : 'file';
                                $parentRejectionReason = trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags((string) ($application->parent_verification_rejection_reason ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                                $parentPreviewDetails = [
                                    'Queue' => 'Guardian Verification',
                                    'Guardian Name' => $application->full_name,
                                    'Email' => $application->email,
                                    'Status' => ucfirst($statusValue),
                                    'Submitted At' => $application->created_at?->format('M d, Y h:i A') ?? 'N/A',
                                    'Rejection Reason' => $parentRejectionReason !== '' ? $parentRejectionReason : 'N/A',
                                ];
                                $parentSearchText = strtolower(trim(implode(' ', array_filter([
                                    (string) ($application->full_name ?? ''),
                                    (string) ($application->email ?? ''),
                                    (string) ($application->created_at?->format('M d, Y h:i A') ?? ''),
                                    (string) $statusValue,
                                ]))));
                                $linkedChild = $application->children->first();
                                $linkedChildName = $linkedChild?->full_name ?? 'No linked child yet';
                                $linkedChildSubtext = $linkedChild?->learnerProfile?->username
                                    ? '@' . $linkedChild->learnerProfile->username
                                    : ($linkedChild?->email ?? null);
                                $guardianAvatarPath = $application->learnerProfile?->avatar_path;
                                $guardianAvatarUrl = $guardianAvatarPath
                                    ? asset('storage/' . ltrim((string) $guardianAvatarPath, '/'))
                                    : null;
                                $guardianInitials = collect(preg_split('/\s+/', trim((string) ($application->full_name ?: $application->name ?: 'Guardian'))))
                                    ->filter()
                                    ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                            @endphp
                                <tr x-data="{
                                    currentStatus: @js($statusValue),
                                    rejectionReason: @js($parentRejectionReason),
                                    processingApprove: false,
                                    processingReject: false,
                                    reviewModalOpen: false,
                                    actionConfirmOpen: false,
                                    actionType: 'archive',
                                    rejectModalOpen: false,
                                    modalReasonCode: '',
                                    modalCustomReason: '',
                                    openReviewModal() {
                                        this.reviewModalOpen = true;
                                    },
                                    closeReviewModal() {
                                        this.reviewModalOpen = false;
                                    },
                                    openActionConfirm(type) {
                                        this.actionType = type;
                                        this.actionConfirmOpen = true;
                                    },
                                    closeActionConfirm() {
                                        this.actionConfirmOpen = false;
                                    },
                                    submitAction() {
                                        if (this.actionType === 'delete') {
                                            this.$refs.deleteForm.submit();
                                            return;
                                        }

                                        this.$refs.archiveForm.submit();
                                    },
                                    openRejectModal() {
                                        if (this.currentStatus !== 'pending') {
                                            return;
                                        }

                                        this.modalReasonCode = '';
                                        this.modalCustomReason = '';
                                        this.rejectModalOpen = true;
                                    },
                                    closeRejectModal() {
                                        this.rejectModalOpen = false;
                                        this.modalReasonCode = '';
                                        this.modalCustomReason = '';
                                        if (typeof window.destroyParentChildModerationEditors === 'function') {
                                            window.destroyParentChildModerationEditors();
                                        }
                                    },
                                    async submitRejectModal(actionUrl) {
                                        if (this.currentStatus !== 'pending') {
                                            return;
                                        }

                                        if (!this.modalReasonCode) {
                                            this.$dispatch('moderation-error', {
                                                message: 'Please select a rejection reason.',
                                            });
                                            return;
                                        }

                                        if (this.modalReasonCode === 'others' && this.$refs.customReasonEditor && typeof tinymce !== 'undefined') {
                                            const editorId = this.$refs.customReasonEditor.id || '';
                                            const editorInstance = editorId ? tinymce.get(editorId) : null;

                                            if (editorInstance) {
                                                this.modalCustomReason = editorInstance.getContent({ format: 'html' }).trim();
                                            }
                                        }

                                        if (this.modalReasonCode === 'others' && !this.modalCustomReason.trim()) {
                                            this.$dispatch('moderation-error', {
                                                message: 'Please provide a custom rejection reason.',
                                            });
                                            return;
                                        }

                                        this.processingReject = true;

                                        try {
                                            const csrfToken = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '';
                                            const formData = new FormData();
                                            formData.append('reason_code', this.modalReasonCode);

                                            if (this.modalReasonCode === 'others') {
                                                formData.append('custom_reason', this.modalCustomReason.trim());
                                            }

                                            const response = await fetch(actionUrl, {
                                                method: 'POST',
                                                headers: {
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': csrfToken,
                                                },
                                                body: formData,
                                            });

                                            const payload = await response.json().catch(() => ({}));

                                            if (!response.ok) {
                                                throw new Error(payload.message || 'Unable to save moderation decision.');
                                            }

                                            const previousStatus = this.currentStatus;
                                            this.currentStatus = payload.status || 'rejected';
                                            this.rejectionReason = payload.rejection_reason || null;

                                            this.closeRejectModal();
                                            this.closeReviewModal();

                                            this.$dispatch('moderation-updated', {
                                                type: 'parents',
                                                rowId: {{ (int) $application->id }},
                                                oldStatus: previousStatus,
                                                newStatus: this.currentStatus,
                                                message: payload.message,
                                            });
                                        } catch (error) {
                                            this.$dispatch('moderation-error', {
                                                message: error.message,
                                            });
                                        } finally {
                                            this.processingReject = false;
                                        }
                                    },
                                    async submitApprove(actionUrl) {
                                        if (this.currentStatus !== 'pending') {
                                            return;
                                        }

                                        this.processingApprove = true;

                                        try {
                                            const csrfToken = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '';
                                            const response = await fetch(actionUrl, {
                                                method: 'POST',
                                                headers: {
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': csrfToken,
                                                },
                                            });

                                            const payload = await response.json().catch(() => ({}));

                                            if (!response.ok) {
                                                throw new Error(payload.message || 'Unable to save moderation decision.');
                                            }

                                            const previousStatus = this.currentStatus;
                                            this.currentStatus = payload.status || 'approved';
                                            this.rejectionReason = payload.rejection_reason || null;
                                            this.closeReviewModal();

                                            this.$dispatch('moderation-updated', {
                                                type: 'parents',
                                                rowId: {{ (int) $application->id }},
                                                oldStatus: previousStatus,
                                                newStatus: this.currentStatus,
                                                message: payload.message,
                                            });
                                        } catch (error) {
                                            this.$dispatch('moderation-error', {
                                                message: error.message,
                                            });
                                        } finally {
                                            this.processingApprove = false;
                                        }
                                    }
                                }"
                                x-show="activeStatus === currentStatus && rowMatchesSearch(@js($parentSearchText)) && rowOnCurrentPage('parents', {{ (int) $application->id }})"
                                x-cloak
                                class="transition hover:bg-brand-50/55">
                                <td class="px-4 py-3 align-top text-sm font-semibold text-gray-500" x-text="rowNumberFor('parents', {{ (int) $application->id }})"></td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-3">
                                        @if($guardianAvatarUrl)
                                            <img src="{{ $guardianAvatarUrl }}" alt="{{ $application->full_name }} avatar" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span aria-label="{{ $application->full_name }} avatar fallback" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $guardianInitials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 break-words">{{ $application->full_name }}</p>
                                            <p class="text-xs text-gray-500 break-words">{{ $application->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="text-sm font-semibold text-gray-900 break-words">{{ $parentIdTypeLabel }}</p>
                                    <p class="text-xs text-gray-500 break-words">Guardian identity</p>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
                                          :class="currentStatus === 'approved' ? 'bg-emerald-100 text-emerald-700' : (currentStatus === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700')">
                                        <span x-text="currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)"></span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-gray-700 whitespace-nowrap">
                                    {{ $application->created_at->format('M d, Y h:i A') }}
                                </td>
                                <td class="px-4 py-3 align-top whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.parent-verifications.parents.show', $application) }}"
                                           class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-gray-200 bg-white text-gray-700 transition hover:bg-gray-50"
                                           title="View Details"
                                           aria-label="View Details">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h7l3 3v13H7z" />
                                            </svg>
                                        </a>
                                        <button type="button"
                                                @click="openActionConfirm('archive')"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 transition hover:bg-amber-100"
                                                title="Archive Application"
                                                aria-label="Archive Application">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M6 8l1 10h10l1-10M9 8V6a1 1 0 011-1h4a1 1 0 011 1v2" />
                                            </svg>
                                        </button>

                                        <button type="button"
                                                @click="openActionConfirm('delete')"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100"
                                                title="Delete Application"
                                                aria-label="Delete Application">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                     <div x-show="actionConfirmOpen"
                                         x-cloak
                                         @keydown.escape.window="closeActionConfirm()"
                                         class="fixed inset-0 z-[60] flex items-center justify-center p-4 text-left sm:p-6 lg:p-8">
                                        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeActionConfirm()"></div>

                                        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white text-left shadow-2xl">
                                            <div class="border-b border-gray-100 px-5 py-4">
                                                <h3 class="text-sm font-semibold text-gray-900" x-text="actionType === 'delete' ? 'Delete Application?' : 'Archive Application?'"></h3>
                                            </div>
                                            <div class="px-5 py-5">
                                                <p class="text-sm text-gray-700" x-show="actionType === 'archive'" x-cloak>Archive this guardian verification application?</p>
                                                <p class="text-sm text-gray-700" x-show="actionType === 'delete'" x-cloak>Permanently delete this guardian verification application?</p>
                                            </div>
                                            <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4">
                                                <button type="button"
                                                        @click="closeActionConfirm()"
                                                        class="inline-flex rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                                                    Cancel
                                                </button>
                                                <button type="button"
                                                        @click="submitAction()"
                                                        :class="actionType === 'delete' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'"
                                                        class="inline-flex rounded-lg px-3 py-1.5 text-xs font-semibold text-white">
                                                    Confirm
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('admin.parent-verifications.parents.archive', $application) }}" x-ref="archiveForm" class="hidden">
                                        @csrf
                                    </form>

                                    <form method="POST" action="{{ route('admin.parent-verifications.parents.destroy', $application) }}" x-ref="deleteForm" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                </td>
                            </tr>
                        @empty
                        @endforelse
                        <tr x-show="!hasRowsForCurrent()">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No guardian verification records found for this status. Try another status tab or switch account type.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto" x-show="activeType === 'children'" x-cloak>
                <table class="w-full table-fixed divide-y divide-gray-200">
                    <thead class="bg-brand-50/45">
                        <tr>
                            <th class="w-[8%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">No. #</th>
                            <th class="w-[22%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Guardian</th>
                            <th class="w-[24%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Child</th>
                            <th class="w-[14%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500 whitespace-nowrap">Status</th>
                            <th class="w-[18%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500 whitespace-nowrap">Submitted</th>
                            <th class="w-[14%] px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.18em] text-gray-500 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($childApplications as $application)
                            @php
                                $statusValue = $application->verification_status ?: 'pending';
                                $verificationDocumentPath = (string) ($application->verification_document_path ?? '');
                                $hasVerificationDocument = $verificationDocumentPath !== '';
                                $verificationDocumentUrl = $hasVerificationDocument ? asset('storage/' . $verificationDocumentPath) : null;
                                $verificationDocumentExtension = $hasVerificationDocument ? strtolower(pathinfo($verificationDocumentPath, PATHINFO_EXTENSION)) : null;
                                $verificationPreviewType = $hasVerificationDocument
                                    ? (in_array($verificationDocumentExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                                        ? 'image'
                                        : ($verificationDocumentExtension === 'pdf' ? 'pdf' : 'file'))
                                    : 'file';
                                $parentComparisonPath = (string) ($application->parent?->parent_id_document_path ?? '');
                                $hasParentComparisonDocument = $parentComparisonPath !== '';
                                $parentComparisonUrl = $hasParentComparisonDocument ? asset('storage/' . $parentComparisonPath) : null;
                                $parentComparisonExtension = $hasParentComparisonDocument ? strtolower(pathinfo($parentComparisonPath, PATHINFO_EXTENSION)) : null;
                                $parentComparisonType = $hasParentComparisonDocument
                                    ? (in_array($parentComparisonExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                                        ? 'image'
                                        : ($parentComparisonExtension === 'pdf' ? 'pdf' : 'file'))
                                    : 'file';
                                $comparisonPreviewPayload = $hasParentComparisonDocument
                                    ? [
                                        'url' => $parentComparisonUrl,
                                        'type' => $parentComparisonType,
                                        'title' => 'Guardian Government ID - ' . ($application->parent?->full_name ?? 'Guardian'),
                                    ]
                                    : null;
                                $childRejectionReason = trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags((string) ($application->verification_rejection_reason ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                                $childAge = $application->child?->age;
                                if ($childAge === null && $application->child?->birthdate) {
                                    $childAge = \Carbon\Carbon::parse($application->child->birthdate)->age;
                                }
                                $childPreviewDetails = [
                                    'Queue' => 'Child Verification',
                                    'Guardian Name' => $application->parent?->full_name ?? 'Unknown guardian',
                                    'Guardian Email' => $application->parent?->email ?? 'N/A',
                                    'Child Name' => $application->child?->full_name ?? 'Unknown child',
                                    'Child Username' => $application->child?->learnerProfile?->username ?? 'N/A',
                                    'Child Age' => $childAge !== null ? $childAge . ' years old' : 'N/A',
                                    'Status' => ucfirst($statusValue),
                                    'Submitted At' => $application->created_at?->format('M d, Y h:i A') ?? 'N/A',
                                    'Rejection Reason' => $childRejectionReason !== '' ? $childRejectionReason : 'N/A',
                                ];
                                $childSearchText = strtolower(trim(implode(' ', array_filter([
                                    (string) ($application->parent?->full_name ?? ''),
                                    (string) ($application->parent?->email ?? ''),
                                    (string) ($application->child?->full_name ?? ''),
                                    (string) ($application->child?->learnerProfile?->username ?? ''),
                                    (string) ($application->created_at?->format('M d, Y h:i A') ?? ''),
                                    (string) $statusValue,
                                ]))));
                                $guardianAvatarPath = $application->parent?->learnerProfile?->avatar_path;
                                $guardianAvatarUrl = $guardianAvatarPath
                                    ? asset('storage/' . ltrim((string) $guardianAvatarPath, '/'))
                                    : null;
                                $guardianName = $application->parent?->full_name ?? 'Unknown guardian';
                                $guardianInitials = collect(preg_split('/\s+/', trim((string) $guardianName)))
                                    ->filter()
                                    ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                                $dependentAvatarPath = $application->child?->learnerProfile?->avatar_path;
                                $dependentAvatarUrl = $dependentAvatarPath
                                    ? asset('storage/' . ltrim((string) $dependentAvatarPath, '/'))
                                    : null;
                                $dependentName = $application->child?->full_name ?? 'Unknown dependent';
                                $dependentInitials = collect(preg_split('/\s+/', trim((string) $dependentName)))
                                    ->filter()
                                    ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                            @endphp
                                <tr x-data="{
                                    currentStatus: @js($statusValue),
                                    rejectionReason: @js($childRejectionReason),
                                    processingApprove: false,
                                    processingReject: false,
                                    reviewModalOpen: false,
                                    actionConfirmOpen: false,
                                    actionType: 'archive',
                                    rejectModalOpen: false,
                                    modalReasonCode: '',
                                    modalCustomReason: '',
                                    openReviewModal() {
                                        this.reviewModalOpen = true;
                                    },
                                    closeReviewModal() {
                                        this.reviewModalOpen = false;
                                    },
                                    openActionConfirm(type) {
                                        this.actionType = type;
                                        this.actionConfirmOpen = true;
                                    },
                                    closeActionConfirm() {
                                        this.actionConfirmOpen = false;
                                    },
                                    submitAction() {
                                        if (this.actionType === 'delete') {
                                            this.$refs.deleteForm.submit();
                                            return;
                                        }

                                        this.$refs.archiveForm.submit();
                                    },
                                    openRejectModal() {
                                        if (this.currentStatus !== 'pending') {
                                            return;
                                        }

                                        this.modalReasonCode = '';
                                        this.modalCustomReason = '';
                                        this.rejectModalOpen = true;
                                    },
                                    closeRejectModal() {
                                        this.rejectModalOpen = false;
                                        this.modalReasonCode = '';
                                        this.modalCustomReason = '';
                                        if (typeof window.destroyParentChildModerationEditors === 'function') {
                                            window.destroyParentChildModerationEditors();
                                        }
                                    },
                                    async submitRejectModal(actionUrl) {
                                        if (this.currentStatus !== 'pending') {
                                            return;
                                        }

                                        if (!this.modalReasonCode) {
                                            this.$dispatch('moderation-error', {
                                                message: 'Please select a rejection reason.',
                                            });
                                            return;
                                        }

                                        if (this.modalReasonCode === 'others' && this.$refs.customReasonEditor && typeof tinymce !== 'undefined') {
                                            const editorId = this.$refs.customReasonEditor.id || '';
                                            const editorInstance = editorId ? tinymce.get(editorId) : null;

                                            if (editorInstance) {
                                                this.modalCustomReason = editorInstance.getContent({ format: 'html' }).trim();
                                            }
                                        }

                                        if (this.modalReasonCode === 'others' && !this.modalCustomReason.trim()) {
                                            this.$dispatch('moderation-error', {
                                                message: 'Please provide a custom rejection reason.',
                                            });
                                            return;
                                        }

                                        this.processingReject = true;

                                        try {
                                            const csrfToken = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '';
                                            const formData = new FormData();
                                            formData.append('reason_code', this.modalReasonCode);

                                            if (this.modalReasonCode === 'others') {
                                                formData.append('custom_reason', this.modalCustomReason.trim());
                                            }

                                            const response = await fetch(actionUrl, {
                                                method: 'POST',
                                                headers: {
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': csrfToken,
                                                },
                                                body: formData,
                                            });

                                            const payload = await response.json().catch(() => ({}));

                                            if (!response.ok) {
                                                throw new Error(payload.message || 'Unable to save moderation decision.');
                                            }

                                            const previousStatus = this.currentStatus;
                                            this.currentStatus = payload.status || 'rejected';
                                            this.rejectionReason = payload.rejection_reason || null;

                                            this.closeRejectModal();
                                            this.closeReviewModal();

                                            this.$dispatch('moderation-updated', {
                                                type: 'children',
                                                rowId: {{ (int) $application->id }},
                                                oldStatus: previousStatus,
                                                newStatus: this.currentStatus,
                                                message: payload.message,
                                            });
                                        } catch (error) {
                                            this.$dispatch('moderation-error', {
                                                message: error.message,
                                            });
                                        } finally {
                                            this.processingReject = false;
                                        }
                                    },
                                    async submitApprove(actionUrl) {
                                        if (this.currentStatus !== 'pending') {
                                            return;
                                        }

                                        this.processingApprove = true;

                                        try {
                                            const csrfToken = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '';
                                            const response = await fetch(actionUrl, {
                                                method: 'POST',
                                                headers: {
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': csrfToken,
                                                },
                                            });

                                            const payload = await response.json().catch(() => ({}));

                                            if (!response.ok) {
                                                throw new Error(payload.message || 'Unable to save moderation decision.');
                                            }

                                            const previousStatus = this.currentStatus;
                                            this.currentStatus = payload.status || 'approved';
                                            this.rejectionReason = payload.rejection_reason || null;
                                            this.closeReviewModal();

                                            this.$dispatch('moderation-updated', {
                                                type: 'children',
                                                rowId: {{ (int) $application->id }},
                                                oldStatus: previousStatus,
                                                newStatus: this.currentStatus,
                                                message: payload.message,
                                            });
                                        } catch (error) {
                                            this.$dispatch('moderation-error', {
                                                message: error.message,
                                            });
                                        } finally {
                                            this.processingApprove = false;
                                        }
                                    }
                                }"
                                x-show="activeStatus === currentStatus && rowMatchesSearch(@js($childSearchText)) && rowOnCurrentPage('children', {{ (int) $application->id }})"
                                x-cloak
                                class="transition hover:bg-brand-50/55">
                                <td class="px-4 py-3 align-top text-sm font-semibold text-gray-500" x-text="rowNumberFor('children', {{ (int) $application->id }})"></td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-3">
                                        @if($guardianAvatarUrl)
                                            <img src="{{ $guardianAvatarUrl }}" alt="{{ $guardianName }} avatar" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span aria-label="{{ $guardianName }} avatar fallback" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $guardianInitials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 break-words">{{ $guardianName }}</p>
                                            <p class="text-xs text-gray-500 break-words">{{ $application->parent?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-3">
                                        @if($dependentAvatarUrl)
                                            <img src="{{ $dependentAvatarUrl }}" alt="{{ $dependentName }} avatar" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span aria-label="{{ $dependentName }} avatar fallback" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">{{ $dependentInitials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 break-words">{{ $dependentName }}</p>
                                            <p class="text-xs text-gray-500 break-words">{{ $application->child?->learnerProfile?->username ?: 'No username' }}</p>
                                            @if(!is_null($childAge))
                                                <p class="text-xs text-gray-500 break-words">{{ $childAge }} years old</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
                                          :class="currentStatus === 'approved' ? 'bg-emerald-100 text-emerald-700' : (currentStatus === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700')">
                                        <span x-text="currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)"></span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-gray-700 whitespace-nowrap">
                                    {{ $application->created_at?->format('M d, Y h:i A') ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 align-top whitespace-nowrap text-right">
                                    <div class="flex flex-nowrap items-center justify-end gap-2">
                                        <button type="button"
                                                :title="currentStatus === 'approved' ? 'View Approved Application' : (currentStatus === 'rejected' ? 'View Rejected Application' : 'Review Application')"
                                                @click="openReviewModal()"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border transition"
                                                :class="currentStatus === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : (currentStatus === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100')"
                                                aria-label="Review application">
                                            <svg x-show="currentStatus === 'pending'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg x-show="currentStatus === 'approved'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg x-show="currentStatus === 'rejected'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>

                                        <button type="button"
                                                @click="openActionConfirm('archive')"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 transition hover:bg-amber-100"
                                                title="Archive Application"
                                                aria-label="Archive Application">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M6 8l1 10h10l1-10M9 8V6a1 1 0 011-1h4a1 1 0 011 1v2" />
                                            </svg>
                                        </button>

                                        <button type="button"
                                                @click="openActionConfirm('delete')"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100"
                                                title="Delete Application"
                                                aria-label="Delete Application">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                     <div x-show="actionConfirmOpen"
                                         x-cloak
                                         @keydown.escape.window="closeActionConfirm()"
                                         class="fixed inset-0 z-[60] flex items-center justify-center p-4 text-left sm:p-6 lg:p-8">
                                        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeActionConfirm()"></div>

                                        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white text-left shadow-2xl">
                                            <div class="border-b border-gray-100 px-5 py-4">
                                                <h3 class="text-sm font-semibold text-gray-900" x-text="actionType === 'delete' ? 'Delete Application?' : 'Archive Application?'"></h3>
                                            </div>
                                            <div class="px-5 py-5">
                                                <p class="text-sm text-gray-700" x-show="actionType === 'archive'" x-cloak>Archive this child verification application?</p>
                                                <p class="text-sm text-gray-700" x-show="actionType === 'delete'" x-cloak>Permanently delete this child verification application?</p>
                                            </div>
                                            <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4">
                                                <button type="button"
                                                        @click="closeActionConfirm()"
                                                        class="inline-flex rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                                                    Cancel
                                                </button>
                                                <button type="button"
                                                        @click="submitAction()"
                                                        :class="actionType === 'delete' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'"
                                                        class="inline-flex rounded-lg px-3 py-1.5 text-xs font-semibold text-white">
                                                    Confirm
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('admin.parent-verifications.children.archive', $application) }}" x-ref="archiveForm" class="hidden">
                                        @csrf
                                    </form>

                                    <form method="POST" action="{{ route('admin.parent-verifications.children.destroy', $application) }}" x-ref="deleteForm" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                     <div x-show="reviewModalOpen"
                                         x-cloak
                                         @keydown.escape.window="if (reviewModalOpen) closeReviewModal()"
                                         class="fixed inset-0 z-[100100] flex items-start justify-center overflow-y-auto p-4 pt-14 text-left sm:p-6 sm:pt-16 lg:p-8 lg:pt-20">
                                        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeReviewModal()"></div>

                                        <div class="relative z-10 flex w-full max-w-5xl max-h-[calc(100vh-4rem)] flex-col overflow-hidden rounded-2xl bg-white text-left shadow-2xl sm:max-h-[calc(100vh-5rem)] lg:max-h-[calc(100vh-6rem)]">
                                            <div class="shrink-0 border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600">Verification Review</p>
                                                        <h2 class="mt-1 text-lg font-bold text-gray-900">Child Verification - {{ $application->child?->full_name ?? 'Unknown child' }}</h2>
                                                        <p class="text-sm text-gray-500">Submitted {{ $application->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                                                    </div>
                                                    <button type="button" @click="closeReviewModal()" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-white px-6 py-5">
                                                <section class="rounded-2xl border border-gray-200 bg-white p-4" x-data="{ open: true }">
                                                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                                        <h3 class="text-base font-bold text-gray-900">Section 1 - Application Details</h3>
                                                        <button type="button" @click="open = !open" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" x-text="open ? 'Hide' : 'Show'"></button>
                                                    </div>

                                                    <dl x-show="open" x-cloak class="mt-4 grid gap-3 sm:grid-cols-2">
                                                        @foreach($childPreviewDetails as $label => $value)
                                                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                                                <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500">{{ $label }}</dt>
                                                                @if($label === 'Status')
                                                                    <dd class="mt-1">
                                                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold"
                                                                              :class="currentStatus === 'approved' ? 'bg-emerald-100 text-emerald-700' : (currentStatus === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700')"
                                                                              x-text="currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)"></span>
                                                                    </dd>
                                                                @elseif($label === 'Rejection Reason')
                                                                    <dd class="mt-1 text-sm font-medium text-gray-900 break-words" x-text="rejectionReason || 'N/A'"></dd>
                                                                @else
                                                                    <dd class="mt-1 text-sm font-medium text-gray-900 break-words">{{ $value ?: 'N/A' }}</dd>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                </section>

                                                <section class="rounded-2xl border border-gray-200 bg-white p-4" x-data="{ open: true }">
                                                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                                        <h3 class="text-base font-bold text-gray-900">Section 2 - Submitted Documents</h3>
                                                        <button type="button" @click="open = !open" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" x-text="open ? 'Hide' : 'Show'"></button>
                                                    </div>

                                                    <div x-show="open" x-cloak class="mt-4 grid gap-4 lg:grid-cols-2">
                                                        <article class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-gray-500">Child Verification Document</p>

                                                            @if($hasVerificationDocument && $verificationPreviewType === 'image')
                                                                <img src="{{ $verificationDocumentUrl }}" alt="Child verification document" class="mx-auto max-h-[48vh] w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain">
                                                            @elseif($hasVerificationDocument && $verificationPreviewType === 'pdf')
                                                                <iframe src="{{ $verificationDocumentUrl }}#toolbar=0&navpanes=0" class="h-[50vh] w-full rounded-lg border border-gray-200 bg-white" title="Child verification document"></iframe>
                                                            @elseif($hasVerificationDocument)
                                                                <div class="rounded-xl border border-gray-200 bg-white p-5 text-center">
                                                                    <p class="text-sm text-gray-600">Inline preview is not available for this file type.</p>
                                                                </div>
                                                            @else
                                                                <div class="rounded-xl border border-gray-200 bg-white p-5 text-center">
                                                                    <p class="text-sm text-gray-600">No document uploaded.</p>
                                                                </div>
                                                            @endif

                                                            @if($hasVerificationDocument)
                                                                <div class="mt-3">
                                                                    <a href="{{ $verificationDocumentUrl }}" download class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">Download document</a>
                                                                </div>
                                                            @endif
                                                        </article>

                                                        <article class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-gray-500">Guardian Government ID</p>

                                                            @if($hasParentComparisonDocument && $parentComparisonType === 'image')
                                                                <img src="{{ $parentComparisonUrl }}" alt="Guardian comparison document" class="mx-auto max-h-[48vh] w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain">
                                                            @elseif($hasParentComparisonDocument && $parentComparisonType === 'pdf')
                                                                <iframe src="{{ $parentComparisonUrl }}#toolbar=0&navpanes=0" class="h-[50vh] w-full rounded-lg border border-gray-200 bg-white" title="Guardian comparison document"></iframe>
                                                            @elseif($hasParentComparisonDocument)
                                                                <div class="rounded-xl border border-gray-200 bg-white p-5 text-center">
                                                                    <p class="text-sm text-gray-600">Inline preview is not available for this file type.</p>
                                                                </div>
                                                            @else
                                                                <div class="rounded-xl border border-gray-200 bg-white p-5 text-center">
                                                                    <p class="text-sm text-gray-600">No guardian document available for comparison.</p>
                                                                </div>
                                                            @endif

                                                            @if($hasParentComparisonDocument)
                                                                <div class="mt-3">
                                                                    <a href="{{ $parentComparisonUrl }}" download class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">Download document</a>
                                                                </div>
                                                            @endif
                                                        </article>
                                                    </div>
                                                </section>

                                                <section class="rounded-2xl border border-gray-200 bg-white p-4" x-data="{ open: true }">
                                                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                                        <h3 class="text-base font-bold text-gray-900">Section 3 - Moderation Actions</h3>
                                                        <button type="button" @click="open = !open" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" x-text="open ? 'Hide' : 'Show'"></button>
                                                    </div>

                                                    <div x-show="open" x-cloak class="mt-4">
                                                    <div class="flex flex-wrap items-center gap-2" x-show="currentStatus === 'pending'" x-cloak>
                                                        <button type="button"
                                                            data-testid="submit-approval-action"
                                                                @click="submitApprove(@js(route('admin.parent-verifications.children.approve', $application)))"
                                                                :disabled="processingApprove || processingReject"
                                                                class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                                                            <span x-text="processingApprove ? 'Approving...' : 'Approve Application'"></span>
                                                        </button>
                                                        <button type="button"
                                                                @click="openRejectModal()"
                                                                :disabled="processingApprove || processingReject"
                                                                class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60">
                                                            Reject Application
                                                        </button>
                                                    </div>

                                                    <p class="mt-3 text-sm text-emerald-700" x-show="currentStatus === 'approved'" x-cloak>
                                                        This child verification has already been approved.
                                                    </p>
                                                    <p class="mt-3 text-sm text-rose-700" x-show="currentStatus === 'rejected'" x-cloak>
                                                        This child verification has already been rejected.
                                                    </p>
                                                    </div>
                                                </section>
                                            </div>

                                        </div>
                                    </div>

                                    @include('admin.parent-verifications.partials.moderation-modal-shell', [
                                        'title' => 'Reject Child Verification',
                                        'submitUrl' => route('admin.parent-verifications.children.reject', $application),
                                        'moderationReasons' => $moderationReasons,
                                    ])

                                </td>
                            </tr>
                        @empty
                        @endforelse
                        <tr x-show="!hasRowsForCurrent()">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No dependent verification records found for this status. Try another status tab or switch account type.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto" x-show="activeType === 'relationships'" x-cloak>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-brand-50/45">
                        <tr>
                            <th class="w-[8%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">No. #</th>
                            <th class="w-[22%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Guardian</th>
                            <th class="w-[22%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Dependent</th>
                            <th class="w-[18%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Relationship</th>
                            <th class="w-[14%] px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Status</th>
                            <th class="w-[16%] px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.18em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($relationshipApplications as $application)
                            @php
                                $statusValue = match ((string) ($application->relationship_verified_status ?: 'pending')) {
                                    'verified' => 'approved',
                                    'rejected', 'revoked' => 'rejected',
                                    default => 'pending',
                                };
                                $statusClass = match ($statusValue) {
                                    'approved' => 'bg-emerald-100 text-emerald-700',
                                    'rejected' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-amber-100 text-amber-700',
                                };
                                $guardianName = $application->parent?->full_name ?? 'Guardian';
                                $guardianAvatarPath = $application->parent?->learnerProfile?->avatar_path;
                                $guardianAvatarUrl = $guardianAvatarPath
                                    ? asset('storage/' . ltrim((string) $guardianAvatarPath, '/'))
                                    : null;
                                $guardianInitials = collect(preg_split('/\s+/', trim((string) $guardianName)))
                                    ->filter()
                                    ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                                $dependentName = $application->child?->full_name ?? 'Dependent';
                                $dependentAvatarPath = $application->child?->learnerProfile?->avatar_path;
                                $dependentAvatarUrl = $dependentAvatarPath
                                    ? asset('storage/' . ltrim((string) $dependentAvatarPath, '/'))
                                    : null;
                                $dependentInitials = collect(preg_split('/\s+/', trim((string) $dependentName)))
                                    ->filter()
                                    ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                            @endphp
                            <tr x-show="rowOnCurrentPage('relationships', {{ (int) $application->id }})" x-cloak>
                                <td class="px-4 py-4 text-sm text-gray-500" x-text="rowNumberFor('relationships', {{ (int) $application->id }})"></td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        @if($guardianAvatarUrl)
                                            <img src="{{ $guardianAvatarUrl }}" alt="{{ $guardianName }} avatar" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span aria-label="{{ $guardianName }} avatar fallback" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $guardianInitials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900">{{ $guardianName }}</div>
                                            <div class="text-xs text-gray-500">{{ $application->parent?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        @if($dependentAvatarUrl)
                                            <img src="{{ $dependentAvatarUrl }}" alt="{{ $dependentName }} avatar" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span aria-label="{{ $dependentName }} avatar fallback" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">{{ $dependentInitials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900">{{ $dependentName }}</div>
                                            <div class="text-xs text-gray-500">{{ $application->child?->learnerProfile?->username ?? $application->child?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $application->relationshipLabel() }}</span>
                                    <div class="mt-1 text-xs text-gray-500">Submitted {{ $application->relationship_verification_submitted_at?->format('M d, Y') ?? 'not submitted' }}</div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $application->relationshipVerificationLabel() }}</span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.parent-verifications.relationships.show', $application) }}" class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">View</a>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                        <tr x-show="!hasRowsForCurrent()">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No relationship verification records found for this status. Try another status tab or switch account type.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="prevPage()"
                            :disabled="safePage() === 1"
                            class="rounded-lg border border-brand-200 px-3 py-1.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50">
                        Previous
                    </button>
                    <span class="text-sm text-gray-600">Page <span class="font-semibold" x-text="safePage()"></span> of <span class="font-semibold" x-text="totalPages()"></span></span>
                    <button type="button"
                            @click="nextPage()"
                            :disabled="safePage() >= totalPages()"
                            class="rounded-lg border border-brand-200 px-3 py-1.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50">
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>

    <div x-show="previewOpen"
         x-cloak
         @keydown.escape.window="closePreview()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div x-show="previewOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closePreview()"></div>

        <div x-show="previewOpen"
             x-transition:enter="ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-3 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-3 sm:scale-95"
             class="relative z-10 w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900" x-text="previewTitle"></h2>
                <div class="flex items-center gap-2">
                    <button type="button" @click="closePreview()" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="max-h-[78vh] overflow-auto bg-gray-50 p-5">
                <div x-show="Object.keys(previewDetails).length > 0" x-cloak class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Verification Details</p>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        <template x-for="([label, value], index) in Object.entries(previewDetails)" :key="`${label}-${index}`">
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500" x-text="label"></dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900 break-words" x-text="value || 'N/A'"></dd>
                            </div>
                        </template>
                    </dl>
                </div>

                <div class="grid gap-4" :class="previewCompareUrl ? 'lg:grid-cols-2' : 'grid-cols-1'">
                    <div class="rounded-xl border border-gray-200 bg-white p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-gray-500" x-text="previewTitle"></p>

                        <template x-if="previewType === 'image'">
                            <img :src="previewUrl" alt="Primary document preview" class="mx-auto max-h-[55vh] w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain">
                        </template>

                        <template x-if="previewType === 'pdf'">
                            <iframe :src="previewUrl + '#toolbar=0&navpanes=0'" class="h-[55vh] w-full rounded-lg border border-gray-200 bg-white" title="Primary document preview"></iframe>
                        </template>

                        <template x-if="previewType === 'file'">
                            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center">
                                <p class="text-sm text-gray-600">Inline preview is not available for this file type.</p>
                                <a :href="previewUrl" download class="mt-4 inline-flex rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">Download file</a>
                            </div>
                        </template>
                    </div>

                    <div x-show="previewCompareUrl" x-cloak class="rounded-xl border border-gray-200 bg-white p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-gray-500" x-text="previewCompareTitle || 'Guardian Comparison Document'"></p>

                        <template x-if="previewCompareType === 'image'">
                            <img :src="previewCompareUrl" alt="Comparison document preview" class="mx-auto max-h-[55vh] w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain">
                        </template>

                        <template x-if="previewCompareType === 'pdf'">
                            <iframe :src="previewCompareUrl + '#toolbar=0&navpanes=0'" class="h-[55vh] w-full rounded-lg border border-gray-200 bg-white" title="Comparison document preview"></iframe>
                        </template>

                        <template x-if="previewCompareType === 'file'">
                            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center">
                                <p class="text-sm text-gray-600">Inline preview is not available for this file type.</p>
                                <a :href="previewCompareUrl" download class="mt-4 inline-flex rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">Download file</a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
    <script>
        window.initParentChildModerationEditor = function (textarea, retries) {
            const attemptCount = Number(retries || 0);

            if (typeof tinymce === 'undefined' || !textarea) {
                if (attemptCount >= 20) {
                    return;
                }

                window.setTimeout(function () {
                    window.initParentChildModerationEditor(textarea, attemptCount + 1);
                }, 50);
                return;
            }

            if (textarea.offsetParent === null) {
                if (attemptCount >= 20) {
                    return;
                }

                window.setTimeout(function () {
                    window.initParentChildModerationEditor(textarea, attemptCount + 1);
                }, 50);
                return;
            }

            if (!textarea.id) {
                textarea.id = 'guardian-child-moderation-editor-' + Math.random().toString(36).slice(2, 10);
            }

            const existingInstance = tinymce.get(textarea.id);
            if (existingInstance) {
                existingInstance.remove();
            }

            tinymce.init({
                selector: '#' + textarea.id,
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

        window.destroyParentChildModerationEditor = function (textarea) {
            if (typeof tinymce === 'undefined' || !textarea || !textarea.id) {
                return;
            }

            const editor = tinymce.get(textarea.id);

            if (!editor) {
                return;
            }

            editor.save();
            editor.remove();
        };

        window.destroyParentChildModerationEditors = function () {
            if (typeof tinymce === 'undefined') {
                return;
            }

            tinymce.remove('textarea.js-guardian-child-moderation-editor');
        };

        window.initParentChildModerationEditors = function () {
            if (typeof tinymce === 'undefined') {
                return;
            }

            window.destroyParentChildModerationEditors();

            tinymce.init({
                selector: 'textarea.js-guardian-child-moderation-editor',
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
    </script>
@endpush
