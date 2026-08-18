<x-auth-split-layout :showTabs="false">
    @php
        $status = $user->parent_verification_status ?? 'pending';
        $isApproved = $isApproved ?? ($status === 'approved');
        $showApprovedModal = (bool) session('show_parent_approved_modal', false);
        $parentReasonRaw = (string) ($user->parent_verification_rejection_reason ?? '');
        $parentRejectionReasonText = trim((string) preg_replace(
            '/\s+/u',
            ' ',
            str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags($parentReasonRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ));
    @endphp

    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="h-20 w-auto mx-auto mb-3 drop-shadow-lg">
                <p class="text-white/90 font-semibold tracking-wide text-sm uppercase">Conscious Connections</p>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">
                {{ $isApproved ? 'Verification Approved' : 'Guardian Verification' }}
            </h2>
            <p class="text-white/80 text-lg max-w-xs">
                {{ $isApproved
                    ? 'Your guardian account is approved. You can start learning or create a dependent account.'
                    : 'Your account is being reviewed by an administrator.' }}
            </p>
        </div>
    </x-slot>

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-purple-900">Verification Status</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ $isApproved
                ? 'Your guardian verification is complete. Choose what you want to do next.'
                : 'We are reviewing your guardian identity document.' }}
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if($isApproved)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 mb-4">
            <p class="font-semibold">Verification result: Approved</p>
            <p class="mt-1">Your guardian account is now active. You can continue learning and manage dependent accounts.</p>
        </div>
    @elseif($status === 'rejected')
        <div class="mb-4 overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm">
            <div class="border-b border-red-100 bg-red-50 px-4 py-3">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-700" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10A8 8 0 114.906 3.765a.75.75 0 11-.812 1.26A6.5 6.5 0 1016.5 10a.75.75 0 011.5 0zm-8.75-3.25a.75.75 0 011.5 0v3a.75.75 0 01-1.5 0v-3zM10 13a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-red-800">Verification result: Rejected</p>
                        <p class="mt-0.5 text-xs text-red-700">Your last submission needs correction before we can continue verification.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3 px-4 py-4 text-sm text-gray-700">
                <div class="rounded-xl border border-red-100 bg-red-50/70 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Admin Feedback</p>
                    @if($parentRejectionReasonText !== '')
                        <p class="mt-1 break-words text-sm text-red-900">{{ $parentRejectionReasonText }}</p>
                    @else
                        <p class="mt-1 text-sm text-red-900">No reason provided by administrator.</p>
                    @endif
                </div>

                <div class="rounded-xl border border-purple-100 bg-purple-50/60 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-purple-800">Next Steps</p>
                    <ul class="mt-1 space-y-1 text-xs text-purple-900">
                        <li>1. Prepare a clear and readable government-issued ID.</li>
                        <li>2. Ensure details are complete and not cropped.</li>
                        <li>3. Upload and submit for another admin review.</li>
                    </ul>
                </div>
            </div>
        </div>

        <a href="{{ route('guardian.verification.create') }}"
           class="mb-6 w-full inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:shadow-md"
           style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
            Resubmit Guardian Verification
        </a>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">
            <p class="font-semibold">Verification result: Pending Review</p>
            <p class="mt-1">You will receive an email once your guardian account has been approved.</p>
        </div>
        <p class="text-sm text-gray-600 mb-6">
            While this is pending, dependent account creation and guardian management features are disabled.
        </p>
    @endif

    <div class="space-y-3" x-data="{ showApprovedModal: {{ $isApproved && $showApprovedModal ? 'true' : 'false' }} }">
        @if($isApproved)
            <a href="{{ route('learner.dashboard') }}"
               class="w-full inline-flex justify-center items-center px-6 py-3 text-base font-medium rounded-xl text-white transition"
               style="background: linear-gradient(135deg, #0EA5E9, #2563EB);">
                Start Learning
            </a>

            <a href="{{ route('parent.create-child') }}"
               class="w-full inline-flex justify-center items-center px-6 py-3 text-base font-medium rounded-xl text-white transition"
               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                Create Dependent Account
            </a>

            <div x-cloak
                 x-show="showApprovedModal"
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
                     @click.away="showApprovedModal = false">
                    <h3 class="text-xl font-bold text-purple-900">Guardian Verification Approved</h3>
                    <p class="mt-2 text-sm text-gray-600">Your guardian account is now active. Choose what you want to do next.</p>

                    <div class="mt-5 space-y-3">
                        <a href="{{ route('learner.dashboard') }}"
                           class="w-full inline-flex justify-center items-center px-6 py-3 text-base font-medium rounded-xl text-white transition"
                           style="background: linear-gradient(135deg, #0EA5E9, #2563EB);">
                            Start Learning
                        </a>

                        <a href="{{ route('parent.create-child') }}"
                           class="w-full inline-flex justify-center items-center px-6 py-3 text-base font-medium rounded-xl text-white transition"
                           style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            Create Dependent Account
                        </a>
                    </div>

                </div>
            </div>
        @else
            <a href="https://mail.google.com"
               target="_blank"
               rel="noopener"
               class="w-full inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                Open Gmail Inbox
            </a>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                Log Out
            </button>
        </form>
    </div>
</x-auth-split-layout>
