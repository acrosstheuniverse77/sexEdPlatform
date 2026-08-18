<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="h-20 w-auto mx-auto mb-3 drop-shadow-lg">
                <p class="text-white/90 font-semibold tracking-wide text-sm uppercase">Conscious Connections</p>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Guide their journey</h2>
            <p class="text-white/80 text-lg max-w-xs">Register as a guardian</p>
        </div>
    </x-slot>

    <x-wizard-stepper />

    <!-- Header -->
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-purple-900">Personal Information</h2>
        <p class="mt-1 text-sm text-gray-500">Tell us about yourself</p>
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

    <form method="POST" action="{{ route('parent.register.store') }}" enctype="multipart/form-data"
          x-data="{
              birthdate: '{{ old('birthdate', $parentInfo['birthdate'] ?? '') }}',
              age: null,
              calculateAge() {
                  if (!this.birthdate) { this.age = null; return; }
                  const today = new Date();
                  const birth = new Date(this.birthdate);
                  let age = today.getFullYear() - birth.getFullYear();
                  const m = today.getMonth() - birth.getMonth();
                  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                  this.age = age;
              }
          }"
          x-init="calculateAge()">
        @csrf

        <div class="space-y-4">

            <!-- First Name + Last Name -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input id="first_name" name="first_name" type="text" required value="{{ old('first_name', $parentInfo['first_name'] ?? '') }}"
                           placeholder="Juan"
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                    @error('first_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input id="last_name" name="last_name" type="text" required value="{{ old('last_name', $parentInfo['last_name'] ?? '') }}"
                           placeholder="dela Cruz"
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                    @error('last_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <!-- Middle Initial + Suffix -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-1">Middle Initial <span class="text-gray-400 font-normal text-xs">(Optional)</span></label>
                    <input id="middle_initial" name="middle_initial" type="text" value="{{ old('middle_initial', $parentInfo['middle_initial'] ?? '') }}"
                           maxlength="10" placeholder="D."
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                    @error('middle_initial')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix <span class="text-gray-400 font-normal text-xs">(Optional)</span></label>
                    <select id="suffix" name="suffix"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                        @php $psfx = old('suffix', $parentInfo['suffix'] ?? ''); @endphp
                        <option value="">-- None --</option>
                        <option value="Jr." {{ $psfx == 'Jr.' ? 'selected' : '' }}>Jr.</option>
                        <option value="Sr." {{ $psfx == 'Sr.' ? 'selected' : '' }}>Sr.</option>
                        <option value="II"  {{ $psfx == 'II'  ? 'selected' : '' }}>II</option>
                        <option value="III" {{ $psfx == 'III' ? 'selected' : '' }}>III</option>
                        <option value="IV"  {{ $psfx == 'IV'  ? 'selected' : '' }}>IV</option>
                        <option value="V"   {{ $psfx == 'V'   ? 'selected' : '' }}>V</option>
                    </select>
                    @error('suffix')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <!-- Birthdate -->
            <div>
                <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                <input id="birthdate" name="birthdate" type="date"
                       x-model="birthdate" @change="calculateAge()"
                       value="{{ old('birthdate', $parentInfo['birthdate'] ?? '') }}" required
                       min="{{ now()->subYears(100)->format('Y-m-d') }}"
                       max="{{ now()->subYears(18)->format('Y-m-d') }}"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">

                <div x-show="age !== null" class="mt-2">
                    <div x-show="age >= 18" class="flex items-center text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 mr-1.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        You are <strong class="mx-1" x-text="age"></strong> years old — eligible!
                    </div>
                    <div x-show="age < 18" class="flex items-center text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 mr-1.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        You must be 18 or older to register as a guardian.
                    </div>
                </div>
                @error('birthdate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <!-- Continue button -->
            <div class="pt-2">
                <button type="submit"
                        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                        class="w-full flex items-center justify-center gap-2 px-8 py-3 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all duration-200">
                    Continue
                </button>
                <div class="mt-4 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-brand-purple-primary font-medium hover:underline">Login</a>
                </div>
            </div>

        </div>
    </form>

</x-auth-split-layout>
