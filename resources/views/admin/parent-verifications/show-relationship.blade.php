@extends('layouts.admin')

@section('title', 'Review Relationship Verification')
@section('page-title', 'Review Relationship Verification')

@section('content')
@php
    $status = (string) ($relationship->relationship_verified_status ?: 'pending');
    $relationshipStatus = (string) ($relationship->relationship_status ?: 'pending');
    $dependentStatus = (string) ($relationship->verification_status ?: 'pending');
    $guardianStatus = (string) ($relationship->parent?->parent_verification_status ?: 'unknown');
    $canApprove = in_array($status, ['pending', 'under_review', 'resubmission_required'], true);
    $canRevoke = $status === 'verified';
    $canReject = in_array($status, ['pending', 'under_review', 'resubmission_required'], true);

    $badge = function (?string $value): string {
        return match ((string) $value) {
            'approved', 'verified', 'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'under_review', 'pending', 'resubmission_required' => 'border-amber-200 bg-amber-50 text-amber-700',
            'rejected', 'revoked', 'inactive' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-gray-200 bg-gray-50 text-gray-600',
        };
    };

    $humanStatus = fn (?string $value): string => str((string) ($value ?: 'unknown'))->replace('_', ' ')->title()->toString();
    $initial = fn ($user, string $fallback): string => strtoupper(substr((string) ($user?->name ?: $fallback), 0, 1));
    $documentLabels = [];
    $guardianIdentityDocuments = collect([
        'front' => [
            'label' => 'Front of guardian ID',
            'path' => (string) ($relationship->parent?->parent_id_document_path ?? ''),
        ],
        'back' => [
            'label' => 'Back of guardian ID',
            'path' => (string) ($relationship->parent?->parent_id_document_back_path ?? ''),
        ],
    ])->map(function (array $document, string $side) use ($relationship): array {
        $extension = strtolower(pathinfo($document['path'], PATHINFO_EXTENSION));

        return [
            ...$document,
            'url' => $document['path'] === '' ? null : route('admin.parent-verifications.parents.document', [$relationship->parent, $side]),
            'is_pdf' => $extension === 'pdf',
        ];
    });
@endphp

<div
    class="space-y-5"
    x-data="{
        decisionModal: null,
        previewDoc: null,
        zoom: 1,
        fit: true,
        openPreview(doc) {
            this.previewDoc = doc;
            this.zoom = 1;
            this.fit = true;
        },
        closePreview() {
            this.previewDoc = null;
            this.zoom = 1;
            this.fit = true;
        },
        zoomIn() {
            this.fit = false;
            this.zoom = Math.min(this.zoom + 0.25, 3);
        },
        zoomOut() {
            this.fit = false;
            this.zoom = Math.max(this.zoom - 0.25, 0.5);
        },
        resetZoom() {
            this.fit = false;
            this.zoom = 1;
        },
        fitToScreen() {
            this.fit = true;
            this.zoom = 1;
        }
    }"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.parent-verifications.index', ['type' => 'relationships']) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">
            Back to Guardian & Dependent Verification
        </a>
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 sm:text-right">Administrative Decision</p>
            <div class="flex flex-wrap gap-2">
            @if($canApprove)
                <button type="button" @click="decisionModal = 'approve'" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-emerald-600 hover:bg-emerald-700">
                    Approve
                </button>
            @endif
            @if($canReject)
                <button type="button" @click="decisionModal = 'reject'" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-rose-600 hover:bg-rose-700">
                    Reject / Request Resubmission
                </button>
            @endif
            @if($canRevoke)
                <button type="button" @click="decisionModal = 'revoke'" class="px-4 py-2 text-sm font-semibold text-gray-800 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Revoke
                </button>
            @endif
            </div>
        </div>
    </div>

    <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-700">Verification Status</p>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $relationship->relationshipVerificationLabel() }}</h1>
                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $badge($status) }}">
                        Overall relationship status: {{ $humanStatus($status) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Submitted {{ $relationship->relationship_verification_submitted_at?->format('M d, Y h:i A') ?? 'not submitted' }}
                    @if($relationship->relationship_verification_reviewed_at)
                        <span class="mx-1 text-gray-300">|</span>
                        Reviewed {{ $relationship->relationship_verification_reviewed_at->format('M d, Y h:i A') }}
                    @endif
                </p>
            </div>
        </div>
    </section>

    <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl">
        <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">Verification Requirements</h2>
        <dl class="grid gap-3 mt-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Guardian verification</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $humanStatus($guardianStatus) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Dependent validation</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $humanStatus($dependentStatus) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Relationship verification</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $humanStatus($status) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Overall relationship</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $humanStatus($relationshipStatus) }}</dd>
            </div>
        </dl>
    </section>

    <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl">
        <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">Guardian + Dependent Identity</h2>
        <div class="grid gap-4 mt-4 lg:grid-cols-2">
            <article class="p-4 border border-gray-100 rounded-xl bg-gray-50/70">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center text-lg font-bold rounded-full h-14 w-14 shrink-0 bg-brand-50 text-brand-700">
                        {{ $initial($relationship->parent, 'G') }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-gray-900">{{ $relationship->parent?->name ?? 'Guardian' }}</h3>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $badge($guardianStatus) }}">{{ $humanStatus($guardianStatus) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 truncate">{{ $relationship->parent?->email }}</p>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-gray-400">Guardian</p>
                    </div>
                </div>
            </article>

            <article class="p-4 border border-gray-100 rounded-xl bg-gray-50/70">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center text-lg font-bold rounded-full h-14 w-14 shrink-0 bg-sky-50 text-sky-700">
                        {{ $initial($relationship->child, 'D') }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-gray-900">{{ $relationship->child?->name ?? 'Dependent' }}</h3>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $badge($dependentStatus) }}">{{ $humanStatus($dependentStatus) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 truncate">{{ $relationship->child?->email }}</p>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-gray-400">Dependent</p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Guardian identity documents</h2>
            <p class="mt-1 text-sm text-gray-500">Compare the guardian’s ID with the relationship evidence below.</p>
        </div>

        <div class="grid gap-4 mt-4 lg:grid-cols-2">
            @foreach($guardianIdentityDocuments as $document)
                <article class="overflow-hidden border border-gray-200 rounded-xl bg-gray-50">
                    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-white">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $document['label'] }}</h3>
                        @if($document['url'])
                            <div class="flex items-center gap-2">
                                <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Preview</a>
                                <a href="{{ $document['url'] }}" download class="text-xs font-semibold text-brand-700 hover:text-brand-900">Download</a>
                            </div>
                        @endif
                    </div>

                    @if($document['url'])
                        <div class="p-3 bg-gray-100">
                            @if($document['is_pdf'])
                                <iframe src="{{ $document['url'] }}#toolbar=0&amp;navpanes=0" title="{{ $document['label'] }}" class="h-72 w-full rounded-lg border border-gray-200 bg-white"></iframe>
                            @else
                                <img src="{{ $document['url'] }}" alt="{{ $document['label'] }}" class="h-72 w-full rounded-lg border border-gray-200 bg-white object-contain">
                            @endif
                        </div>
                    @else
                        <p class="p-6 text-sm text-center text-gray-500">Not submitted</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Submitted Documents</h2>
                <p class="mt-1 text-sm text-gray-500">Review document type, submitter, side, and current verification state in one place.</p>
            </div>
            <span class="text-sm font-semibold text-gray-500">{{ $relationship->verificationDocuments->count() }} submitted</span>
        </div>

        <div class="grid gap-3 mt-4 xl:grid-cols-2">
            @forelse($relationship->verificationDocuments as $document)
                @php
                    $documentTypeLabel = (string) config('guardian_relationships.document_types.' . $document->document_type, $humanStatus($document->document_type));
                    $documentLabels[$document->document_type] = ($documentLabels[$document->document_type] ?? 0) + 1;
                    $sideLabel = $documentLabels[$document->document_type] === 1 ? 'Front' : 'Back';
                    $documentUrl = route('admin.parent-verifications.relationships.documents.show', [$relationship, $document]);
                    $isImage = str_starts_with((string) $document->mime_type, 'image/');
                @endphp
                <article class="p-4 border border-gray-200 rounded-xl bg-gray-50" data-testid="relationship-document-card">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ $documentTypeLabel }}</h3>
                                <span class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-600">{{ $sideLabel }}</span>
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $badge($status) }}">{{ $humanStatus($status) }}</span>
                            </div>
                            <dl class="grid gap-2 mt-3 text-sm text-gray-600 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-400">Submitted by</dt>
                                    <dd class="mt-1 font-medium text-gray-800">{{ $document->uploadedBy?->name ?? $relationship->parent?->name ?? 'Guardian' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button
                                type="button"
                                @click="openPreview({
                                    label: @js($documentTypeLabel . ' - ' . $sideLabel),
                                    url: @js($documentUrl),
                                    mime: @js((string) $document->mime_type),
                                    isImage: @js($isImage)
                                })"
                                class="px-3 py-2 text-sm font-semibold text-white rounded-lg bg-brand-600 hover:bg-brand-700"
                            >
                                Preview
                            </button>
                            <a href="{{ $documentUrl }}" download class="px-3 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Download
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <p class="px-4 py-8 text-sm text-center text-gray-500 border border-gray-200 border-dashed rounded-xl bg-gray-50">No documents submitted.</p>
            @endforelse
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">Relationship</h2>
            <dl class="grid gap-4 mt-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500">Relationship type</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $relationship->relationshipLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Relationship status</dt>
                    <dd class="mt-1"><span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $badge($relationshipStatus) }}">{{ $humanStatus($relationshipStatus) }}</span></dd>
                </div>
            </dl>
        </section>
    </div>
    <section class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl">
        <h2 class="text-lg font-semibold text-gray-900">Audit Trail</h2>
        <div class="mt-3 space-y-2">
            @forelse($relationship->verificationAudits as $audit)
                <div class="px-4 py-3 text-sm text-gray-700 border border-gray-100 rounded-xl bg-gray-50">
                    <span class="font-semibold">{{ $humanStatus($audit->action) }}</span>
                    <span class="mx-1 text-gray-300">|</span>
                    {{ $audit->previous_status ?? 'none' }} to {{ $audit->new_status ?? 'none' }}
                    <span class="mx-1 text-gray-300">|</span>
                    {{ $audit->actor?->name ?? 'System' }}
                    <span class="mx-1 text-gray-300">|</span>
                    {{ $audit->created_at?->format('M d, Y h:i A') }}
                </div>
            @empty
                <p class="text-sm text-gray-500">No audit entries yet.</p>
            @endforelse
        </div>
    </section>

    <div x-show="previewDoc" x-cloak @keydown.escape.window="closePreview()" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70">
        <div class="absolute inset-0" @click="closePreview()"></div>
        <div class="relative z-10 flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex flex-col gap-3 px-4 py-3 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="font-semibold text-gray-900" x-text="previewDoc?.label"></h3>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="zoomIn()" class="px-3 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Zoom in</button>
                    <button type="button" @click="zoomOut()" class="px-3 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Zoom out</button>
                    <button type="button" @click="resetZoom()" class="px-3 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Reset zoom</button>
                    <button type="button" @click="fitToScreen()" class="px-3 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Fit to screen</button>
                    <a :href="previewDoc?.url" download class="px-3 py-2 text-sm font-semibold text-white rounded-lg bg-brand-600 hover:bg-brand-700">Download</a>
                    <button type="button" @click="closePreview()" class="px-3 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
                </div>
            </div>
            <div class="flex-1 p-4 overflow-auto bg-gray-100">
                <template x-if="previewDoc?.isImage">
                    <img :src="previewDoc.url" :alt="previewDoc.label" class="object-contain max-w-full max-h-full mx-auto origin-top" :style="fit ? '' : `transform: scale(${zoom});`">
                </template>
                <template x-if="previewDoc && !previewDoc.isImage">
                    <iframe :src="previewDoc.url" :title="previewDoc.label" class="mx-auto h-full min-h-[640px] w-full origin-top rounded-lg bg-white" :style="fit ? '' : `transform: scale(${zoom}); width: ${100 / zoom}%; height: ${100 / zoom}%;`"></iframe>
                </template>
            </div>
        </div>
    </div>

    <div x-show="decisionModal === 'approve'" x-cloak @keydown.escape.window="decisionModal = null" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50" @click="decisionModal = null"></div>
        <form method="POST" action="{{ route('admin.parent-verifications.relationships.approve', $relationship) }}" class="relative z-10 w-full max-w-lg overflow-hidden bg-white shadow-2xl rounded-xl">
            @csrf
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Confirm approval</h3>
                <p class="mt-2 text-sm text-gray-600">Approve this guardian-dependent relationship verification as a single administrative decision.</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4">
                <button type="button" @click="decisionModal = null" class="px-4 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-emerald-600 hover:bg-emerald-700">Approve</button>
            </div>
        </form>
    </div>

    <div x-show="decisionModal === 'reject'" x-cloak @keydown.escape.window="decisionModal = null" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50" @click="decisionModal = null"></div>
        <form method="POST" action="{{ route('admin.parent-verifications.relationships.reject', $relationship) }}" class="relative z-10 w-full max-w-2xl overflow-hidden bg-white shadow-2xl rounded-xl">
            @csrf
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Reject or request resubmission</h3>
                <p class="mt-2 text-sm text-gray-600">Choose a built-in reason and clearly identify what the guardian needs to correct or re-upload.</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label for="relationship-reject-reason" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Reason</label>
                    <select id="relationship-reject-reason" name="reason_code" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">Select reason</option>
                        @foreach($rejectionReasons as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <fieldset class="p-4 border border-gray-200 rounded-xl">
                    <legend class="px-1 text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Needs correction or re-upload</legend>
                    <div class="grid gap-2 mt-2 text-sm text-gray-700 sm:grid-cols-2">
                        <label class="flex items-center gap-2"><input type="checkbox" name="correction_items[]" value="guardian_identity" class="border-gray-300 rounded"> Guardian identity details</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="correction_items[]" value="dependent_identity" class="border-gray-300 rounded"> Dependent identity details</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="correction_items[]" value="relationship_document" class="border-gray-300 rounded"> Relationship document</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="correction_items[]" value="supporting_document" class="border-gray-300 rounded"> Supporting document</label>
                    </div>
                </fieldset>
                <div>
                    <label for="relationship-reject-note" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Optional explanation</label>
                    <textarea id="relationship-reject-note" name="note" rows="4" maxlength="1000" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" placeholder="Add specific instructions for correction or re-upload."></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" name="allow_resubmission" value="1" checked class="border-gray-300 rounded">
                    Request resubmission instead of final rejection
                </label>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                <button type="button" @click="decisionModal = null" class="px-4 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-rose-600 hover:bg-rose-700">Save Decision</button>
            </div>
        </form>
    </div>

    <div x-show="decisionModal === 'revoke'" x-cloak @keydown.escape.window="decisionModal = null" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50" @click="decisionModal = null"></div>
        <form method="POST" action="{{ route('admin.parent-verifications.relationships.revoke', $relationship) }}" class="relative z-10 w-full max-w-lg overflow-hidden bg-white shadow-2xl rounded-xl">
            @csrf
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Revoke verification</h3>
                <p class="mt-2 text-sm text-gray-600">This removes the verified relationship status. A structured reason is required for the audit trail.</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label for="relationship-revoke-reason" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Structured reason</label>
                    <select id="relationship-revoke-reason" name="reason_code" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">Select reason</option>
                        @foreach($rejectionReasons as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="relationship-revoke-note" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Revocation reason</label>
                    <textarea id="relationship-revoke-note" name="note" rows="4" maxlength="1000" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" placeholder="Explain why this verified relationship must be revoked."></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                <button type="button" @click="decisionModal = null" class="px-4 py-2 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800">Revoke</button>
            </div>
        </form>
    </div>
</div>
@endsection
