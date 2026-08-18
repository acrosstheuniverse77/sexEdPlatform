<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="h-20 w-auto mx-auto mb-3 drop-shadow-lg">
                <p class="text-white/90 font-semibold tracking-wide text-sm uppercase">Conscious Connections</p>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Young learner?</h2>
            <p class="text-white/80 text-lg max-w-xs">A guardian account is needed first</p>
        </div>
    </x-slot>

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-purple-900">Guardian Required</h2>
        <p class="mt-2 text-sm text-gray-600">Young learners under 13 years old need a guardian to create their account.</p>
    </div>

    <!-- Purple info block -->
    <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-6">
        <p class="text-sm text-purple-800">
            For young learner safety and online privacy compliance, we require guardian consent for users under 13 years old.
        </p>
    </div>

    <!-- Steps -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">How It Works:</h3>
        <div class="space-y-4">
            <!-- Step 1 -->
            <div class="flex items-start">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-brand-purple-primary text-white flex items-center justify-center font-bold">
                    1
                </div>
                <div class="ml-4">
                    <h4 class="text-sm font-semibold text-gray-900">Guardian Registers</h4>
                    <p class="text-sm text-gray-600">Create your guardian account and upload a valid government-issued ID (must be 18+ years old)</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="flex items-start">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-brand-purple-primary text-white flex items-center justify-center font-bold">
                    2
                </div>
                <div class="ml-4">
                    <h4 class="text-sm font-semibold text-gray-900">Admin Reviews Guardian Verification</h4>
                    <p class="text-sm text-gray-600">After email verification, your guardian account goes to pending admin review</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="flex items-start">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-brand-purple-primary text-white flex items-center justify-center font-bold">
                    3
                </div>
                <div class="ml-4">
                    <h4 class="text-sm font-semibold text-gray-900">Create Dependent Account</h4>
                    <p class="text-sm text-gray-600">Once approved, create a dependent account and upload a supporting document</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="flex items-start">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-brand-purple-primary text-white flex items-center justify-center font-bold">
                    4
                </div>
                <div class="ml-4">
                    <h4 class="text-sm font-semibold text-gray-900">Monitor Progress</h4>
                    <p class="text-sm text-gray-600">After dependent approval, track learning progress and quiz results</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="space-y-3">
        <a href="{{ route('parent.register') }}"
           style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
           class="w-full inline-flex justify-center items-center gap-2 px-8 py-3 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all duration-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Register as Guardian
        </a>

        <a href="{{ route('login') }}" class="w-full inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-purple-primary transition">
            Already have an account? Login
        </a>
    </div>

    <!-- Privacy Notice -->
    <div class="mt-8 p-4 bg-gray-50 rounded-lg">
        <p class="text-xs text-gray-600 text-center">
             We take young learner privacy seriously. Read our 
            <a href="{{ route('privacy') }}" class="text-brand-purple-primary hover:text-brand-purple-light underline" target="_blank">Privacy Policy</a> 
            and <a href="{{ route('terms') }}" class="text-brand-purple-primary hover:text-brand-purple-light underline" target="_blank">Terms of Service</a>.
        </p>
    </div>
</x-auth-split-layout>
