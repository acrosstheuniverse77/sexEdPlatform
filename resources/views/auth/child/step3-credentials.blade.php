<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="h-20 w-auto mx-auto mb-3">
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Login details</h2>
            <p class="text-white/80 text-lg max-w-xs">Create credentials your dependent can use to sign in.</p>
        </div>
    </x-slot>

    <x-wizard-stepper :steps="[
        ['label' => 'Dependent Info', 'active' => false, 'done' => true],
        ['label' => 'Location', 'active' => false, 'done' => true],
        ['label' => 'Credentials', 'active' => true, 'done' => false],
        ['label' => 'Validation', 'active' => false, 'done' => false],
        ['label' => 'Review', 'active' => false, 'done' => false],
    ]" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-purple-900">Create Login Credentials</h1>
        <p class="mt-1 text-sm text-gray-600">Setting up login for <strong>{{ $step1['first_name'] ?? 'your dependent' }}</strong>.</p>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <ul class="text-sm text-red-700 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('parent.create-child.credentials.store') }}"
          x-data="{
              showPass: false,
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
                  return Object.values(this.checks()).filter(Boolean).length;
              },
              strengthLabel() {
                  const score = this.score();
                  if (score <= 2) return 'Weak';
                  if (score <= 4) return 'Medium';
                  return 'Strong';
              },
          }">
        @csrf

        <div class="mb-4">
            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
            <input id="username" name="username" type="text" required value="{{ old('username') }}" pattern="[a-z0-9_\-]+" minlength="3" maxlength="30"
                   class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-purple-500">
            <p class="mt-1 text-xs text-gray-500">Lowercase letters, numbers, underscores, and hyphens only.</p>
        </div>

        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
            <div class="relative">
                <input id="password" name="password" :type="showPass ? 'text' : 'password'" required x-model="password"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-purple-500 pr-10">
                <button type="button" @click="showPass = !showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-purple-700">Show</button>
            </div>
            <div x-show="password" x-cloak class="mt-2">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full transition-all duration-300"
                         :style="`width: ${password ? (score() / 5) * 100 : 0}%`"
                         :class="score() <= 2 ? 'bg-red-500' : (score() <= 4 ? 'bg-amber-500' : 'bg-emerald-500')"></div>
                </div>
                <div class="mt-1.5 flex min-h-[18px] items-center justify-between gap-2 text-xs">
                    <span class="font-medium"
                          :class="password ? (score() <= 2 ? 'text-red-600' : (score() <= 4 ? 'text-amber-600' : 'text-emerald-600')) : 'text-gray-500'"
                          x-text="password ? `Strength: ${strengthLabel()}` : 'Strength: -'"></span>
                    <span class="text-right"
                          :class="password ? (score() === 5 ? 'text-emerald-600' : 'text-gray-500') : 'text-gray-500'"
                          x-text="password ? (score() === 5 ? 'All requirements met' : 'Use upper, lower, number, symbol') : 'Min 8 chars'"></span>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Use upper/lowercase letters, a number, and a symbol.</p>
        </div>

        <div class="mb-6">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
            <div class="relative">
                <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required x-model="passwordConfirmation"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-purple-500 pr-10">
                <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-purple-700">Show</button>
            </div>
            <p class="mt-1 min-h-[18px] text-xs" :class="!passwordConfirmation ? 'text-gray-500' : (passwordConfirmation === password ? 'text-emerald-600' : 'text-red-600')" x-text="!passwordConfirmation ? 'Confirm your password.' : (passwordConfirmation === password ? 'Passwords match.' : 'Passwords do not match.')"></p>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <a href="{{ route('parent.create-child.location') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
            <button type="submit" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);" class="inline-flex items-center justify-center px-8 py-3.5 font-semibold text-white rounded-xl shadow-md hover:opacity-90">
                Continue
            </button>
        </div>
    </form>
</x-auth-split-layout>
