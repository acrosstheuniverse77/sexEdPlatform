@extends('layouts.learner-app')

@section('title', 'Relationship Verification')

@section('content')
@php
    $status = $relationship->relationship_verified_status ?? 'pending';
    $statusClass = match ($status) {
        'verified', 'not_required' => 'bg-emerald-100 text-emerald-700',
        'rejected', 'revoked' => 'bg-rose-100 text-rose-700',
        'resubmission_required' => 'bg-orange-100 text-orange-700',
        default => 'bg-amber-100 text-amber-700',
    };
    $canSubmit = $requiresVerification && in_array($status, ['pending', 'rejected', 'resubmission_required'], true);
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <div class="rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Relationship Verification</h1>
                <p class="text-white/80 text-sm mt-1">{{ $relationship->child?->name ?? 'Dependent' }} · {{ $relationship->relationshipLabel() }}</p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-white/20 text-white">
                {{ $relationship->relationshipVerificationLabel() }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Relationship</h2>
            <p class="mt-2 text-lg font-semibold text-gray-900">{{ $relationship->relationshipLabel() }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ $relationship->child?->name ?? 'Dependent' }}</p>
            <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                {{ $relationship->relationshipVerificationLabel() }}
            </span>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Timeline</h2>
            <div class="mt-3 space-y-2 text-sm text-gray-600">
                <p>Created: {{ $relationship->created_at?->format('M d, Y h:i A') }}</p>
                <p>Submitted: {{ $relationship->relationship_verification_submitted_at?->format('M d, Y h:i A') ?? 'Not submitted' }}</p>
                <p>Reviewed: {{ $relationship->relationship_verification_reviewed_at?->format('M d, Y h:i A') ?? 'Not reviewed' }}</p>
            </div>
        </div>
    </div>

    @if($relationship->relationship_verification_rejection_reason)
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
            <p class="font-semibold">Review reason</p>
            <p class="mt-1">{{ config('guardian_relationships.rejection_reasons.' . $relationship->relationship_verification_rejection_reason, $relationship->relationship_verification_rejection_reason) }}</p>
            @if($relationship->relationship_verification_rejection_note)
                <p class="mt-2">{{ $relationship->relationship_verification_rejection_note }}</p>
            @endif
        </div>
    @endif

    @if($requiresVerification)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-900">Submitted Documents</h2>
                <span class="text-xs text-gray-500">{{ $relationship->verificationDocuments->count() }} file(s)</span>
            </div>
            <div class="mt-3 space-y-2">
                @forelse($relationship->verificationDocuments as $document)
                    <a href="{{ route('parent.relationship-verifications.documents.show', [$relationship, $document]) }}" class="block rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm font-semibold text-purple-700 hover:bg-purple-50">
                        {{ $document->original_name }} · {{ config('guardian_relationships.document_types.' . $document->document_type, $document->document_type) }}
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No documents submitted yet.</p>
                @endforelse
            </div>
        </div>

        @if($canSubmit)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Submit Supporting Documentation</h2>
                <p class="mt-1 text-sm text-gray-500">This relationship requires additional verification before full relationship-sensitive features unlock.</p>

                <form method="POST" action="{{ route('parent.relationship-verifications.store', $relationship) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                        <select name="document_type" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <option value="">Select document type</option>
                            @foreach($documentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Document</label>
                            <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            @error('document')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Supporting Document</label>
                            <input type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            @error('supporting_document')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <label class="flex gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="confirm_submission" value="1" required class="mt-1 rounded border-gray-300">
                        I confirm these documents support this specific Guardian-Dependent relationship.
                    </label>
                    @error('confirm_submission')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                    <button class="rounded-xl px-4 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                        Submit for Verification
                    </button>
                </form>
            </div>
        @endif
    @endif
</div>
@endsection
