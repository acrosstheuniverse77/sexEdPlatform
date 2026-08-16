<x-auth-split-layout :showTabs="false">
    @php
        $resultStatus = $registrationResult['status'] ?? 'pending';
        $isApproved = $resultStatus === 'approved';
    @endphp

    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="h-20 w-auto mx-auto mb-3">
                <p class="text-white/90 font-semibold tracking-wide text-sm uppercase">Conscious Connections</p>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">
                {{ $isApproved ? 'Dependent account activated!' : 'Application submitted!' }}
            </h2>
            <p class="text-white/80 text-lg max-w-xs">
                {{ $isApproved
                    ? 'Monitoring access is now active.'
                    : 'Your dependent account is now pending admin verification.' }}
            </p>
        </div>
    </x-slot>

    <x-wizard-stepper :steps="[
        ['label' => 'Set Up Info',    'active' => false, 'done' => true],
        ['label' => 'Where Are You?', 'active' => false, 'done' => true],
        ['label' => 'Login Details',  'active' => false, 'done' => true],
        ['label' => 'All Set!',       'active' => true,  'done' => false],
    ]" />

    {{-- Success header --}}
    <div class="text-center mb-8">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
             style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-purple-900">
            {{ $isApproved ? $childName . "'s account is approved." : $childName . "'s account is under review." }}
        </h1>
        <p class="mt-2 text-sm text-gray-600">
            {{ $isApproved
                ? 'You can monitor progress and manage content approvals right away.'
                : 'We will notify you when this dependent account is approved or rejected by the administrator.' }}
        </p>
    </div>

    {{-- Monitoring feature cards --}}
    <div class="space-y-4 mb-8">
        <div class="flex items-start gap-4 bg-purple-50 border border-purple-100 rounded-xl p-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-purple-900">View Learning Progress</p>
                <p class="text-xs text-purple-700 mt-0.5">Track module completions, lesson views, and overall progress in real time.</p>
            </div>
        </div>

        <div class="flex items-start gap-4 bg-purple-50 border border-purple-100 rounded-xl p-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-purple-900">View Quiz Answers</p>
                <p class="text-xs text-purple-700 mt-0.5">See quiz attempts, selected answers, and scores to identify learning gaps.</p>
            </div>
        </div>

        <div class="flex items-start gap-4 bg-purple-50 border border-purple-100 rounded-xl p-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-purple-900">Content Approval</p>
                <p class="text-xs text-purple-700 mt-0.5">Review and approve modules before your dependent can access them.</p>
            </div>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center">
        <a href="{{ route('parent.children.index') }}"
           style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
           class="inline-block text-white font-semibold py-3 px-8 rounded-xl hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition shadow-lg text-sm">
            Go to My Dependents
        </a>
    </div>

</x-auth-split-layout>
