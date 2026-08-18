<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="h-20 w-auto mx-auto mb-3">
                <p class="text-white/90 font-semibold tracking-wide text-sm uppercase">Conscious Connections</p>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Set up their account</h2>
            <p class="text-white/80 text-lg max-w-xs">Register your dependent and get them learning safely.</p>
        </div>
    </x-slot>

    <x-wizard-stepper :steps="[
        ['label' => 'Set Up Info',    'active' => true,  'done' => false],
        ['label' => 'Where Are You?', 'active' => false, 'done' => false],
        ['label' => 'Login Details',  'active' => false, 'done' => false],
        ['label' => 'All Set!',       'active' => false, 'done' => false],
    ]" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-purple-900">Dependent Information</h1>
        <p class="mt-1 text-sm text-gray-600">Tell us a little about your dependent.</p>
    </div>


        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

    <form method="POST" action="{{ route('parent.create-child.store') }}"
          x-data="{
              birthdate: '{{ old('birthdate', $pendingChild['birthdate'] ?? '') }}',
              relationshipType: '{{ old('relationship_type', $pendingChild['relationship_type'] ?? '') }}',
              verificationRequiredTypes: @js(array_values(array_filter(array_keys($relationshipOptions ?? \App\Support\GuardianRelationshipTypes::options()), fn ($type) => \App\Support\GuardianRelationshipTypes::requiresVerification($type)))),
              age: null,
              calculateAge() {
                  if (!this.birthdate) { this.age = null; return; }
                  const today = new Date();
                  const birth = new Date(this.birthdate);
                  let a = today.getFullYear() - birth.getFullYear();
                  const m = today.getMonth() - birth.getMonth();
                  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) a--;
                  this.age = a;
              }
          }"
          x-init="calculateAge()">
        @csrf

        @if(!empty($pendingChild))
            <div class="flex items-center gap-2 bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 mb-5">
                <svg class="w-4 h-4 text-purple-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-xs text-purple-800">Pre-filled from your child's registration — modify if needed.</p>
            </div>
        @endif

                    <!-- Child's Personal Information -->
                    <div class="mb-6">
                        {{-- Row 1: First Name + Last Name --}}
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input id="first_name" name="first_name" type="text" required
                                       value="{{ old('first_name', $pendingChild['first_name'] ?? '') }}"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition"
                                       placeholder="Maria">
                                @error('first_name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input id="last_name" name="last_name" type="text" required
                                       value="{{ old('last_name', $pendingChild['last_name'] ?? '') }}"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition"
                                       placeholder="Santos">
                                @error('last_name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 2: Middle Initial + Suffix --}}
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-1">
                                    Middle Initial <span class="text-gray-400 font-normal text-xs">(Optional)</span>
                                </label>
                                <input id="middle_initial" name="middle_initial" type="text"
                                       value="{{ old('middle_initial', $pendingChild['middle_initial'] ?? '') }}"
                                       maxlength="10"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition"
                                       placeholder="C.">
                                @error('middle_initial')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">
                                    Suffix <span class="text-gray-400 font-normal text-xs">(Optional)</span>
                                </label>
                                <select id="suffix" name="suffix"
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                                    <option value="">None</option>
                                    @foreach(['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'] as $s)
                                        <option value="{{ $s }}" {{ old('suffix', $pendingChild['suffix'] ?? '') == $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                                @error('suffix')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 3: Birthdate + Gender --}}
                        <div class="grid grid-cols-2 gap-4 mb-2">
                            <div>
                                <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-1">
                                    Birthdate <span class="text-red-500">*</span>
                                </label>
                                <input id="birthdate" name="birthdate" type="date" required
                                       x-model="birthdate"
                                       @change="calculateAge()"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                                <p class="mt-1 text-xs text-gray-500">Use the dependent's legal birthdate.</p>
                                <div x-show="age !== null" class="mt-2">
                                    <template x-if="age >= 0">
                                        <p class="text-xs text-green-700 bg-green-50 rounded-lg px-3 py-2">
                                            Age <strong x-text="age"></strong> recorded for this dependent.
                                        </p>
                                    </template>
                                    <template x-if="age !== null && age < 0">
                                        <p class="text-xs text-red-700 bg-red-50 rounded-lg px-3 py-2">
                                            Birthdate cannot be in the future.
                                        </p>
                                    </template>
                                </div>
                                @error('birthdate')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select id="gender" name="gender" required
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                                    <option value="">Select gender</option>
                                    <option value="male"              {{ old('gender', $pendingChild['gender'] ?? '') === 'male'              ? 'selected' : '' }}>Male</option>
                                    <option value="female"            {{ old('gender', $pendingChild['gender'] ?? '') === 'female'            ? 'selected' : '' }}>Female</option>
                                    <option value="prefer_not_to_say" {{ old('gender', $pendingChild['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' }}>Prefer not to say</option>
                                </select>
                                @error('gender')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                            <div>
                                <label for="relationship_type" class="block text-sm font-medium text-gray-700 mb-1">
                                    Your Relationship <span class="text-red-500">*</span>
                                </label>
                                <select id="relationship_type" name="relationship_type" required x-model="relationshipType"
                                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition">
                                    <option value="">Select relationship</option>
                                    @foreach(($relationshipOptions ?? \App\Support\GuardianRelationshipTypes::options()) as $value => $label)
                                        @continue($value === \App\Support\GuardianRelationshipTypes::LEGACY_PARENT)
                                        <option value="{{ $value }}" {{ old('relationship_type', $pendingChild['relationship_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('relationship_type')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                <p x-show="verificationRequiredTypes.includes(relationshipType)" x-cloak class="mt-1 text-xs text-amber-700">
                                    This relationship requires supporting documentation before full guardian features unlock.
                                </p>
                            </div>

                            <div x-show="relationshipType === 'other'" x-cloak>
                                <label for="relationship_custom" class="block text-sm font-medium text-gray-700 mb-1">
                                    Custom Relationship
                                </label>
                                <input id="relationship_custom" name="relationship_custom" type="text" maxlength="120"
                                       value="{{ old('relationship_custom', $pendingChild['relationship_custom'] ?? '') }}"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-purple-primary focus:border-transparent transition"
                                       placeholder="Specify relationship">
                                @error('relationship_custom')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <a href="{{ route('parent.children.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to My Dependents</a>
                        <button type="submit"
                                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                                class="inline-flex items-center justify-center gap-2 px-8 py-3.5 font-semibold text-white rounded-xl shadow-md hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all duration-200">
                            Continue
                        </button>
                    </div>
                </form>

</x-auth-split-layout>

