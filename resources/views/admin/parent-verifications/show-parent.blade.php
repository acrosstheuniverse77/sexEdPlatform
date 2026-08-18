@extends('layouts.admin')

@section('title', 'Guardian Identity Review')

@section('content')
@php
    $approvalReasons = [
        'identity_matches_submission' => 'Identity matches submitted account details',
        'government_id_valid' => 'Government ID is valid and readable',
        'guardian_eligibility_confirmed' => 'Guardian eligibility is confirmed',
    ];
    $rejectionReasons = \App\Enums\ParentChildModerationReason::cases();
@endphp

<div class="space-y-5" x-data="{ approvalModalOpen: false, rejectionModalOpen: false }">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-700">Guardian Verification</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">Guardian Identity Review</h1>
            <p class="mt-1 text-sm text-gray-500">Review identity documents before unlocking Guardian features.</p>
        </div>
        <a href="{{ route('admin.parent-verifications.index', ['type' => 'parents']) }}"
           class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Back
        </a>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-1">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Identity</h2>
            <div class="mt-4 flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">
                    {{ strtoupper(substr((string) ($guardian->name ?? 'G'), 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900">{{ $guardian->full_name ?: $guardian->name }}</p>
                    <p class="text-sm text-gray-500">{{ $guardian->email }}</p>
                </div>
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                <div><dt class="text-gray-500">Username</dt><dd class="font-medium text-gray-900">{{ $guardian->learnerProfile?->username ?? 'N/A' }}</dd></div>
                <div><dt class="text-gray-500">Birthdate</dt><dd class="font-medium text-gray-900">{{ optional($guardian->birthdate)->format('M d, Y') ?? 'N/A' }}</dd></div>
                <div><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-900">{{ ucfirst((string) ($guardian->parent_verification_status ?? 'not submitted')) }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Government ID</h2>
            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $idTypeLabel }}</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-4">
                    <p class="text-sm font-semibold text-gray-800">Front Image</p>
                    @if($guardian->parent_id_document_path)
                        <img src="{{ route('admin.parent-verifications.parents.document', [$guardian, 'front']) }}" alt="Guardian ID front" class="mt-3 h-48 w-full rounded-lg border border-gray-100 bg-gray-50 object-contain">
                        <div class="mt-3 flex gap-2">
                            <a href="{{ route('admin.parent-verifications.parents.document', [$guardian, 'front']) }}" target="_blank" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Preview</a>
                            <a href="{{ route('admin.parent-verifications.parents.document', [$guardian, 'front']) }}" download class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download</a>
                        </div>
                    @else
                        <p class="mt-3 text-sm text-gray-500">Not uploaded</p>
                    @endif
                </div>
                <div class="rounded-xl border border-gray-200 p-4">
                    <p class="text-sm font-semibold text-gray-800">Back Image</p>
                    @if($guardian->parent_id_document_back_path)
                        <img src="{{ route('admin.parent-verifications.parents.document', [$guardian, 'back']) }}" alt="Guardian ID back" class="mt-3 h-48 w-full rounded-lg border border-gray-100 bg-gray-50 object-contain">
                        <div class="mt-3 flex gap-2">
                            <a href="{{ route('admin.parent-verifications.parents.document', [$guardian, 'back']) }}" target="_blank" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Preview</a>
                            <a href="{{ route('admin.parent-verifications.parents.document', [$guardian, 'back']) }}" download class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download</a>
                        </div>
                    @else
                        <p class="mt-3 text-sm text-gray-500">Not uploaded</p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Verification Timeline</h2>
        <dl class="mt-4 grid gap-4 text-sm md:grid-cols-3">
            <div><dt class="text-gray-500">Registration Date</dt><dd class="font-medium text-gray-900">{{ optional($guardian->created_at)->format('M d, Y g:i A') }}</dd></div>
            <div><dt class="text-gray-500">Email Verified Date</dt><dd class="font-medium text-gray-900">{{ optional($guardian->email_verified_at)->format('M d, Y g:i A') ?? 'N/A' }}</dd></div>
            <div><dt class="text-gray-500">Verification Submitted Date</dt><dd class="font-medium text-gray-900">{{ optional($guardian->parent_verification_submitted_at)->format('M d, Y g:i A') ?? 'N/A' }}</dd></div>
        </dl>
    </section>

    <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        @php
            $onboardingStatus = $guardian->guardian_onboarding_status
                ? str($guardian->guardian_onboarding_status)->replace('_', ' ')->title()->toString()
                : ($guardian->hasCompletedProfile() ? 'Completed' : 'Not Started');
        @endphp
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Guardian Onboarding</h2>
                <dl class="mt-4 grid gap-4 text-sm md:grid-cols-3">
                    <div><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-900">{{ $onboardingStatus }}</dd></div>
                    <div><dt class="text-gray-500">Started Date</dt><dd class="font-medium text-gray-900">{{ optional($guardian->guardian_onboarding_started_at)->format('M d, Y g:i A') ?? 'N/A' }}</dd></div>
                    <div><dt class="text-gray-500">Completed Date</dt><dd class="font-medium text-gray-900">{{ optional($guardian->guardian_onboarding_completed_at)->format('M d, Y g:i A') ?? 'N/A' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">Last updated: {{ optional($guardian->updated_at)->format('M d, Y g:i A') ?? 'N/A' }}</p>
            </div>
            <form method="POST" action="{{ route('admin.parent-verifications.parents.reset-onboarding', $guardian) }}" onsubmit="return confirm('Reset Guardian onboarding for this account?')">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Reset Onboarding
                </button>
            </form>
        </div>
    </section>

    @if(($guardian->parent_verification_status ?? 'pending') === 'pending')
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Moderation Actions</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button"
                        @click="approvalModalOpen = true"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Approve
                </button>
                <button type="button"
                        @click="rejectionModalOpen = true"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                    Reject
                </button>
            </div>
        </section>

        <div x-show="approvalModalOpen"
             x-cloak
             @keydown.escape.window="approvalModalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="approvalModalOpen = false"></div>

            <form method="POST"
                  action="{{ route('admin.parent-verifications.parents.approve', $guardian) }}"
                  data-testid="guardian-approval-confirm-modal"
                  class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                @csrf
                <div class="border-b border-gray-100 px-6 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Confirm Guardian Approval</h3>
                            <p class="mt-2 text-sm text-gray-600">Select the reason that best supports approving this guardian application.</p>
                        </div>
                        <button type="button" @click="approvalModalOpen = false" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <label for="approval_reason" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Approval Reason</label>
                    <select id="approval_reason" name="approval_reason" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">Select approval reason</option>
                        @foreach($approvalReasons as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="approvalModalOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Confirm Approval</button>
                </div>
            </form>
        </div>

        <div x-show="rejectionModalOpen"
             x-cloak
             @keydown.escape.window="rejectionModalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="rejectionModalOpen = false"></div>

            <form method="POST"
                  action="{{ route('admin.parent-verifications.parents.reject', $guardian) }}"
                  data-testid="guardian-rejection-confirm-modal"
                  class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                @csrf
                <div class="border-b border-gray-100 px-6 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Confirm Guardian Rejection</h3>
                            <p class="mt-2 text-sm text-gray-600">Select the reason the guardian can use to correct the application.</p>
                        </div>
                        <button type="button" @click="rejectionModalOpen = false" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-5" x-data="{ reasonCode: '' }">
                    <div>
                        <label for="reason_code" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Rejection Reason</label>
                        <select id="reason_code" name="reason_code" x-model="reasonCode" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                            <option value="">Select rejection reason</option>
                            @foreach($rejectionReasons as $reason)
                                <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="reasonCode === 'others'" x-cloak>
                        <label for="custom_reason" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Custom Reason</label>
                        <textarea id="custom_reason" name="custom_reason" rows="3" maxlength="1000" :required="reasonCode === 'others'" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" placeholder="Enter custom rejection reason"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="rejectionModalOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirm Rejection</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
