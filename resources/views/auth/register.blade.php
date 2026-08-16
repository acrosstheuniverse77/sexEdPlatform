<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="h-20 w-auto mx-auto mb-3 drop-shadow-lg">
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Start your journey</h2>
            <p class="text-white/80 text-lg max-w-xs">A safe space to grow and learn</p>
        </div>
    </x-slot>

    <x-wizard-stepper :steps="[
        ['label' => 'Personal Info', 'active' => true,  'done' => false],
        ['label' => 'Account Info',  'active' => false, 'done' => false],
        ['label' => 'Verify Email',  'active' => false, 'done' => false],
        ['label' => 'Profile',       'active' => false, 'done' => false],
    ]" />

    <!-- Heading -->
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-purple-900">Create your account</h2>
        <p class="mt-1 text-sm text-gray-900">Personal Information</p>
    </div>

    <!-- Registration Form -->
                <form method="POST" action="{{ route('register') }}" x-data="{
                    birthdate: '{{ old('birthdate', $personalInfo['birthdate'] ?? '') }}',
                    age: null,
                    loading: false,
                    calculateAge() {
                        if (this.birthdate) {
                            const today = new Date();
                            const birth = new Date(this.birthdate);
                            let age = today.getFullYear() - birth.getFullYear();
                            const monthDiff = today.getMonth() - birth.getMonth();
                            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                                age--;
                            }
                            this.age = age > 0 && age < 120 ? age : null;
                        } else {
                            this.age = null;
                        }
                    }
                }" x-init="calculateAge()" @submit="loading = true">
                    @csrf

                    <div class="space-y-3">

                        <!-- First Name + Last Name (2-col) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input 
                                    id="first_name" 
                                    type="text" 
                                    name="first_name" 
                                    value="{{ old('first_name', $personalInfo['first_name'] ?? '') }}"
                                    required 
                                    autofocus 
                                    autocomplete="given-name"
                                    placeholder="Juan"
                                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200 text-sm"
                                />
                                @error('first_name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input 
                                    id="last_name" 
                                    type="text" 
                                    name="last_name" 
                                    value="{{ old('last_name', $personalInfo['last_name'] ?? '') }}"
                                    required 
                                    autocomplete="family-name"
                                    placeholder="dela Cruz"
                                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200 text-sm"
                                />
                                @error('last_name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Middle Initial + Suffix (2-col) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-1">Middle Initial <span class="text-gray-400 font-normal">(Optional)</span></label>
                                <input 
                                    id="middle_initial" 
                                    type="text" 
                                    name="middle_initial" 
                                    value="{{ old('middle_initial', $personalInfo['middle_initial'] ?? '') }}"
                                    maxlength="10"
                                    autocomplete="additional-name"
                                    placeholder="D."
                                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200 text-sm"
                                />
                                @error('middle_initial')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix <span class="text-gray-400 font-normal">(Optional)</span></label>
                                <select 
                                    id="suffix" 
                                    name="suffix"
                                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200 text-sm"
                                >
                                    <option value="">-- None --</option>
                                    @php $sfx = old('suffix', $personalInfo['suffix'] ?? ''); @endphp
                                    <option value="Jr." {{ $sfx == 'Jr.' ? 'selected' : '' }}>Jr.</option>
                                    <option value="Sr." {{ $sfx == 'Sr.' ? 'selected' : '' }}>Sr.</option>
                                    <option value="II"  {{ $sfx == 'II'  ? 'selected' : '' }}>II</option>
                                    <option value="III" {{ $sfx == 'III' ? 'selected' : '' }}>III</option>
                                    <option value="IV"  {{ $sfx == 'IV'  ? 'selected' : '' }}>IV</option>
                                    <option value="V"   {{ $sfx == 'V'   ? 'selected' : '' }}>V</option>
                                </select>
                                @error('suffix')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Birth Date -->
                        <div>
                            <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-1">Birth Date</label>
                            <input 
                                type="date" 
                                id="birthdate" 
                                name="birthdate" 
                                x-model="birthdate"
                                @change="calculateAge()"
                                value="{{ old('birthdate', $personalInfo['birthdate'] ?? '') }}"
                                required 
                                min="{{ now()->subYears(100)->format('Y-m-d') }}"
                                max="{{ now()->subYears(5)->format('Y-m-d') }}"
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition-all duration-200 text-sm"
                            />
                            
                            <!-- Age Display -->
                            <div x-show="age !== null" class="mt-2">
                                <div x-show="age >= 13" class="flex items-center text-sm text-green-600">
                                    <svg class="w-5 h-5 mr-1.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>You are <strong x-text="age"></strong> years old — eligible to register!</span>
                                </div>
                                <div x-show="age < 13" class="flex items-center text-sm text-orange-600">
                                    <svg class="w-5 h-5 mr-1.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>You are <strong x-text="age"></strong> years old — a guardian/guardian must register for you</span>
                                </div>
                            </div>

                            <p class="mt-1 text-xs text-gray-500">You must be at least 5 years old to use this platform</p>
                            @error('birthdate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Next Button -->
                        <div class="pt-2">
                            <button 
                                type="submit"
                                :disabled="loading"
                                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                                class="w-full flex items-center justify-center gap-2 py-3.5 px-6 font-semibold text-white rounded-xl shadow-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span x-show="!loading">Next</span>
                                <span x-show="loading" class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Checking...</span>
                                </span>
                            </button>

                            <div class="mt-4 text-center">
                                <p class="text-sm text-gray-600">
                                    Already registered? 
                                    <a href="{{ route('login') }}" class="text-brand-purple-primary font-medium hover:text-brand-purple-dark transition-colors">Login</a>
                                </p>
                            </div>
                        </div>

                    </div>

                    <!-- Footer Links -->
                    <div class="mt-6 pt-4 border-t border-gray-200">
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
