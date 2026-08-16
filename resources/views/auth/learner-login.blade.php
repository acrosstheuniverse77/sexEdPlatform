<x-auth-split-layout
    logo="/media/Logo.png"
>
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-8">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="h-40 w-auto mx-auto mb-4 drop-shadow-lg">
                <p class="text-4xl font-bold text-white mb-4 leading-tight">Conscious Connections</p>
            </div>
        </div>
    </x-slot>
    {{-- Session Status (hidden - using toasts instead) --}}
    <div style="display: none;">
        <x-auth-session-status class="mb-6" :status="session('status')" />
    </div>

    <!-- Heading -->
    <div class="mb-8">
        <h2 class="text-4xl font-bold text-purple-900 flex items-center gap-2">
            Login to
            <img src="{{ asset('/media/Logo.png') }}" alt="Taboo" class="w-28 h-24 object-contain inline-block" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Ctext y=%27.9em%27 font-size=%2790%27%3E♂️%3C/text%3E%3C/svg%3E%27">
        </h2>
        <h2 class="text-4xl font-bold text-purple-900 flex items-center gap-2">your account</h2>
    </div>

    <!-- Login Form -->
    <form method="POST" action="{{ route('login') }}" x-data="{ 
        showPassword: false,
        loading: false 
    }" @submit="loading = true">
        @csrf

        <!-- Email/Username Input -->
        <div class="mb-4">
            <label for="email" class="sr-only">Email or Username</label>
            <div class="relative">
                <input 
                    id="email" 
                    type="text" 
                    name="email" 
                    value="{{ old('email') }}" 
                    required 
                    autofocus 
                    autocomplete="username"
                    placeholder="Email or username"
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200"
                />
            </div>
            {{-- Errors shown via toast notifications --}}
        </div>

        <!-- Password Input with Toggle -->
        <div class="mb-2">
            <label for="password" class="sr-only">Password</label>
            <div class="relative">
                <input 
                    id="password" 
                    :type="showPassword ? 'text' : 'password'"
                    name="password" 
                    required 
                    autocomplete="current-password"
                    placeholder="Password"
                    class="w-full px-4 py-3 pr-12 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200"
                />
                <!-- Eye Icon Toggle -->
                <button 
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                >
                    <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Forgot Password Link -->
        <div class="text-right mb-6">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-gray-500 hover:text-brand-purple-primary transition-colors">
                    Forgot Password?
                </a>
            @endif
        </div>
        <!-- Login Button -->
        <button 
            type="submit"
            :disabled="loading"
            style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
            class="w-full flex items-center justify-center gap-2 px-8 py-3 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <svg x-show="loading" x-cloak class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-text="loading ? 'Logging in...' : 'Login'">Login</span>
        </button>

        <!-- Register Links -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Don't have an account yet? 
                <a href="{{ route('register') }}" class="font-semibold text-brand-purple-primary hover:text-brand-purple-light transition-colors">
                    Create a Learner Account
                </a>
            </p>
            <p class="mt-2 text-sm text-gray-600">
                Need a guardian account?
                <a href="{{ route('parent.register') }}" class="font-semibold text-brand-purple-primary hover:text-brand-purple-light transition-colors">
                    Create a Guardian Account
                </a>
            </p>
        </div>

        <!-- Footer Links -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="flex items-center justify-center gap-4 text-xs text-gray-500">
                <button type="button" @click="$dispatch('open-help')" class="hover:text-brand-purple-primary transition-colors">Help</button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="$dispatch('open-terms')" class="hover:text-brand-purple-primary transition-colors">Terms</button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="$dispatch('open-privacy')" class="hover:text-brand-purple-primary transition-colors">Privacy</button>
            </div>
        </div>
    </form>
</x-auth-split-layout>
