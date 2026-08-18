<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="h-20 w-auto mx-auto mb-3 drop-shadow-lg">
                <p class="text-white/90 font-semibold tracking-wide text-sm uppercase">Conscious Connections</p>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Your credentials</h2>
            <p class="text-white/80 text-lg max-w-xs">Secure your guardian account</p>
        </div>
    </x-slot>

    <x-wizard-stepper />

    <!-- Header -->
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-purple-900">Account Information</h2>
        <p class="mt-1 text-sm text-gray-500">Set up your login credentials</p>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-5 rounded-lg">
            <ul class="text-sm text-red-700 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('parent.register.account.store') }}"
          x-data="{
              showPassword: false,
              showConfirm: false,
              password: '',
              passwordConfirmation: '',
              checks() {
                  return {
                      length: this.password.length >= 8,
                      lower: /[a-z]/.test(this.password),
                      upper: /[A-Z]/.test(this.password),
                      number: /\d/.test(this.password),
                      symbol: /[^A-Za-z0-9]/.test(this.password),
                  };
              },
              score() {
                  const checks = this.checks();
                  return Object.values(checks).filter(Boolean).length;
              },
              strengthLabel() {
                  const score = this.score();
                  if (score <= 2) return 'Weak';
                  if (score <= 4) return 'Good';
                  return 'Strong';
              },
          }">
        @csrf

        <div class="space-y-4">

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email Address
                </label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                       placeholder="guardian@gmail.com"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                <p class="mt-1 text-xs text-gray-500">Must be a Gmail address. A verification link will be sent here.</p>
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Password
                </label>
                <div class="relative">
                    <input id="password" name="password" :type="showPassword ? 'text' : 'password'" required
                              x-model="password"
                           placeholder="••••••••"
                           class="w-full px-3 py-2 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                    <button type="button" @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                <div x-show="password" x-cloak class="mt-2">
                    <div class="h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full transition-all duration-300"
                             :style="`width: ${password ? (score() / 5) * 100 : 0}%`"
                             :class="score() <= 2 ? 'bg-red-500' : (score() <= 4 ? 'bg-amber-500' : 'bg-emerald-500')"></div>
                    </div>
                    <div class="mt-1.5 min-h-[18px] flex items-center justify-between gap-2 text-xs">
                        <span class="font-medium"
                              :class="password ? (score() <= 2 ? 'text-red-600' : (score() <= 4 ? 'text-amber-600' : 'text-emerald-600')) : 'text-gray-500'"
                              x-text="password ? `Strength: ${strengthLabel()}` : 'Strength: -'"></span>
                        <span class="text-right"
                              :class="password ? (score() === 5 ? 'text-emerald-600' : 'text-gray-500') : 'text-gray-500'"
                              x-text="password ? (score() === 5 ? 'All requirements met' : 'Use upper, lower, number, symbol') : 'Min 8 chars'"></span>
                    </div>
                </div>
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    Confirm Password
                </label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required
                              x-model="passwordConfirmation"
                           placeholder="••••••••"
                           class="w-full px-3 py-2 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                    <button type="button" @click="showConfirm = !showConfirm"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                     <p class="mt-1 min-h-[18px] text-xs"
                         :class="!passwordConfirmation ? 'text-gray-500' : (passwordConfirmation === password ? 'text-emerald-600' : 'text-red-600')"
                         x-text="!passwordConfirmation ? 'Confirm your password.' : (passwordConfirmation === password ? 'Passwords match.' : 'Passwords do not match.')"></p>
            </div>

            <!-- Terms -->
            <div>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" required
                           class="mt-0.5 h-4 w-4 text-brand-purple-primary border-gray-300 rounded focus:ring-brand-purple-primary">
                    <span class="text-sm text-gray-600">
                        I agree to the
                        <button type="button" @click="$dispatch('open-terms')" class="text-brand-purple-primary hover:underline font-medium">Terms of Service</button>
                        and
                        <button type="button" @click="$dispatch('open-privacy')" class="text-brand-purple-primary hover:underline font-medium">Privacy Policy</button>
                    </span>
                </label>
            </div>

            <!-- Buttons -->
            <div class="pt-2 space-y-3">
                <button type="submit"
                        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                        class="w-full flex items-center justify-center gap-2 px-8 py-3 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all duration-200">
                    Create Guardian Account
                </button>
                <a href="{{ route('parent.register') }}"
                   class="w-full block text-center py-2.5 px-6 rounded-xl text-sm font-medium text-gray-600 bg-gray-50 border border-gray-200 hover:bg-gray-100 transition">
                    Back to Personal Info
                </a>
            </div>

        </div>
    </form>

</x-auth-split-layout>
