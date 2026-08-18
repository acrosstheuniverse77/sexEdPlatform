<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guardian Onboarding | Conscious Connections</title>
    <link rel="icon" type="image/png" href="{{ asset('media/Logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-100">
@php
    $profileAvatarUrl = $learnerProfile?->avatar_path ? asset('storage/' . ltrim((string) $learnerProfile->avatar_path, '/')) : '';
@endphp

<main class="min-h-screen overflow-y-auto bg-gray-100">
    <div class="fixed inset-0">
        <div class="h-40 bg-gradient-to-r from-fuchsia-700 via-purple-700 to-indigo-700"></div>
        <div class="mx-auto -mt-16 grid max-w-6xl gap-5 px-6 opacity-45 blur-[1px] lg:grid-cols-[16rem_1fr]">
            <aside class="hidden p-4 border shadow-sm rounded-2xl border-white/70 bg-white/80 lg:block">
                <div class="w-10 h-10 bg-purple-100 rounded-xl"></div>
                <div class="mt-6 space-y-3">
                    <div class="h-3 bg-gray-200 rounded w-28"></div>
                    <div class="bg-purple-100 h-9 rounded-xl"></div>
                    <div class="bg-gray-100 h-9 rounded-xl"></div>
                    <div class="bg-gray-100 h-9 rounded-xl"></div>
                </div>
            </aside>
            <section class="p-6 border shadow-sm rounded-2xl border-white/70 bg-white/80">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="h-24 bg-gray-100 rounded-2xl"></div>
                    <div class="h-24 bg-gray-100 rounded-2xl"></div>
                    <div class="h-24 bg-gray-100 rounded-2xl"></div>
                </div>
                <div class="mt-5 bg-gray-100 h-72 rounded-2xl"></div>
            </section>
        </div>
    </div>

    <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-8">
        <form method="POST"
              action="{{ route('guardian.onboarding.complete') }}"
              enctype="multipart/form-data"
              class="w-full max-w-4xl bg-white border shadow-2xl rounded-3xl border-white/70"
              x-data="{
                  step: 1,
                  nextAction: 'dashboard',
                  username: @js(old('username', $learnerProfile?->username ?? '')),
                  usernameState: 'idle',
                  usernameMessage: '',
                  usernameTimer: null,
                  cityCode: @js(old('city_code', $learnerProfile?->city_code ?? '')),
                  selectedBarangayCode: @js(old('barangay_code', $learnerProfile?->barangay_code ?? '')),
                  barangays: [],
                  loadingBarangays: false,
                  bio: @js(old('bio', $learnerProfile?->bio ?? '')),
                  avatarPreview: @js($profileAvatarUrl),
                  avatarName: '',
                  removeAvatarFlag: false,
                  showExitConfirm: false,
                  go(nextStep) { this.step = Math.max(1, Math.min(4, nextStep)); },
                  normalizeUsername() { this.username = (this.username || '').toLowerCase().replace(/\s+/g, ''); },
                  usernameFormatError() {
                      if (!this.username) return '';
                      if (this.username.length < 3 || this.username.length > 30) return 'Username must be between 3 and 30 characters.';
                      if (!/^[a-z0-9_-]+$/.test(this.username)) return 'Use only lowercase letters, numbers, underscores, and hyphens.';
                      return '';
                  },
                  scheduleUsernameCheck() {
                      clearTimeout(this.usernameTimer);
                      this.normalizeUsername();
                      const formatError = this.usernameFormatError();
                      if (!this.username) { this.usernameState = 'idle'; this.usernameMessage = ''; return; }
                      if (formatError) { this.usernameState = 'error'; this.usernameMessage = formatError; return; }
                      this.usernameState = 'checking';
                      this.usernameTimer = setTimeout(() => this.checkUsername(), 300);
                  },
                  async checkUsername() {
                      try {
                          const response = await fetch(@js(route('profile.username-availability')) + '?username=' + encodeURIComponent(this.username), {
                              headers: { 'Accept': 'application/json' },
                              credentials: 'same-origin',
                          });
                          const payload = await response.json().catch(() => ({}));
                          this.usernameState = payload.available ? 'success' : 'error';
                          this.usernameMessage = payload.message || '';
                      } catch (error) {
                          this.usernameState = 'error';
                          this.usernameMessage = 'Unable to validate username right now.';
                      }
                  },
                  async loadBarangays(code) {
                      code = String(code || '').trim();
                      this.cityCode = code;
                      if (!code) { this.barangays = []; this.selectedBarangayCode = ''; return; }
                      this.loadingBarangays = true;
                      try {
                          const response = await fetch('/api/barangays/' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' } });
                          this.barangays = response.ok ? await response.json() : [];
                          if (this.selectedBarangayCode && !this.barangays.some((b) => b.code === this.selectedBarangayCode)) this.selectedBarangayCode = '';
                      } finally {
                          this.loadingBarangays = false;
                      }
                  },
                  chooseAvatar(event) {
                      const file = event.target.files && event.target.files.length ? event.target.files[0] : null;
                      if (!file) return;
                      if (this.avatarPreview && this.avatarPreview.startsWith('blob:')) URL.revokeObjectURL(this.avatarPreview);
                      this.avatarName = file.name;
                      this.avatarPreview = URL.createObjectURL(file);
                      this.removeAvatarFlag = false;
                  },
                  removeAvatar() {
                      if (this.avatarPreview && this.avatarPreview.startsWith('blob:')) URL.revokeObjectURL(this.avatarPreview);
                      this.avatarPreview = '';
                      this.avatarName = '';
                      this.removeAvatarFlag = true;
                      if (this.$refs.avatarInput) this.$refs.avatarInput.value = '';
                  }
              }"
              x-init="if (cityCode) loadBarangays(cityCode); scheduleUsernameCheck();">
            @csrf
            <input type="hidden" name="next_action" :value="nextAction">
            <input type="hidden" name="remove_avatar" :value="removeAvatarFlag ? 1 : 0">

            <div class="px-6 py-5 border-b border-gray-100">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-purple-700">Guardian Setup</p>
                        <h1 class="mt-1 text-2xl font-bold text-gray-950">Welcome to Conscious Connections</h1>
                    </div>
                    <button type="button" @click="showExitConfirm = true" class="px-3 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50">
                        Exit
                    </button>
                </div>
                <div class="grid gap-2 mt-5 sm:grid-cols-4" data-testid="guardian-onboarding-stepper">
                    @foreach(['Welcome', 'Profile', 'Workspace', 'Finish'] as $index => $label)
                        <button type="button" @click="go({{ $index + 1 }})" class="px-3 py-2 text-xs font-semibold transition rounded-full"
                                :class="step >= {{ $index + 1 }} ? 'bg-purple-700 text-white' : 'bg-gray-100 text-gray-500'">
                            {{ $index + 1 }}. {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if($errors->any())
                <div class="px-4 py-3 mx-6 mt-5 text-sm border rounded-xl border-rose-200 bg-rose-50 text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="min-h-[30rem] px-6 py-6">
                <section x-show="step === 1" x-cloak class="grid gap-6 md:grid-cols-[1fr_18rem]">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-950">Your Guardian account is verified.</h2>
                        <p class="mt-3 text-gray-600">Before you begin, personalize your profile and review the workspace. This only takes a minute.</p>
                    </div>
                </section>

                <section x-show="step === 2" x-cloak class="grid gap-6 lg:grid-cols-[1fr_18rem]">
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-semibold text-gray-800">Username</label>
                            <input id="username" name="username" x-model="username" @input="scheduleUsernameCheck()" required minlength="3" maxlength="30" pattern="^[a-z0-9_\-]{3,30}$"
                                   class="mt-2 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-200">
                            <p x-show="usernameMessage" x-cloak class="mt-1 text-xs"
                               :class="usernameState === 'success' ? 'text-emerald-600' : (usernameState === 'checking' ? 'text-gray-500' : 'text-rose-600')"
                               x-text="usernameMessage"></p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="city_code" class="block text-sm font-semibold text-gray-800">City / Municipality</label>
                                <select id="city_code" name="city_code" x-model="cityCode" @change="loadBarangays($event.target.value)" required
                                        class="mt-2 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-200">
                                    <option value="">Select city</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->code }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="barangay_code" class="block text-sm font-semibold text-gray-800">Barangay</label>
                                <select id="barangay_code" name="barangay_code" x-model="selectedBarangayCode" :disabled="!cityCode || loadingBarangays" required
                                        class="mt-2 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-200 disabled:opacity-50">
                                    <option value="" x-text="!cityCode ? 'Select city first' : (loadingBarangays ? 'Loading...' : 'Select barangay')"></option>
                                    <template x-for="barangay in barangays" :key="barangay.code">
                                        <option :value="barangay.code" x-text="barangay.name"></option>
                                    </template>
                                    @foreach($barangays as $barangay)
                                        <option value="{{ $barangay->code }}">{{ $barangay->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="bio" class="block text-sm font-semibold text-gray-800">Bio</label>
                            <textarea id="bio" name="bio" rows="4" maxlength="500" x-model="bio"
                                      class="mt-2 w-full resize-none rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-200"
                                      placeholder="Short introduction for your profile."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-800">Profile Avatar</label>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" x-ref="avatarInput" @change="chooseAvatar($event)"
                                   class="mt-2 block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-purple-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-purple-700">
                            <button type="button" x-show="avatarPreview" x-cloak @click="removeAvatar()" class="mt-2 text-xs font-semibold text-rose-600">Remove avatar</button>
                        </div>
                    </div>

                    <aside class="p-5 border border-gray-100 rounded-2xl bg-gray-50">
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Profile</p>
                        <div class="flex items-center gap-3 mt-5">
                            <div class="flex items-center justify-center w-16 h-16 overflow-hidden text-lg font-bold text-purple-700 bg-white shadow-sm rounded-2xl">
                                <template x-if="avatarPreview"><img :src="avatarPreview" alt="Avatar preview" class="object-cover w-full h-full"></template>
                                <span x-show="!avatarPreview" x-text="(username || '{{ auth()->user()->first_name ?? 'G' }}').slice(0, 1).toUpperCase()"></span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold truncate text-gray-950" x-text="username ? '@' + username : '@guardian'"></p>
                                <p class="text-sm text-gray-500">{{ auth()->user()->full_name }}</p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-gray-600" x-text="bio || 'Your short Guardian bio appears here.'"></p>
                    </aside>
                </section>

                <section x-show="step === 3" x-cloak>
                    <h2 class="text-2xl font-bold text-gray-950">Your Guardian workspace</h2>
                    <div class="grid gap-4 mt-5 sm:grid-cols-2">
                        @foreach([
                            ['Manage dependent accounts', 'Create and organize dependent access.'],
                            ['Monitor learning progress', 'Review activity and completion signals.'],
                            ['Communicate safely', 'Use platform messaging and notifications.'],
                            ['Access Guardian tools', 'Find invitations, child pages, and approvals.'],
                        ] as [$title, $copy])
                            <div class="p-4 border border-gray-100 rounded-2xl bg-gray-50">
                                <p class="font-semibold text-gray-950">{{ $title }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $copy }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section x-show="step === 4" x-cloak>
                    <h2 class="text-2xl font-bold text-gray-950">Choose your next action</h2>
                    <div class="grid gap-4 mt-5 sm:grid-cols-2">
                        <label class="p-5 transition border cursor-pointer rounded-2xl" :class="nextAction === 'dependent' ? 'border-purple-600 bg-purple-50' : 'border-gray-200 bg-white'">
                            <input type="radio" class="sr-only" value="dependent" x-model="nextAction">
                            <span class="font-semibold text-gray-950">Create a Dependent</span>
                        </label>
                        <label class="p-5 transition border cursor-pointer rounded-2xl" :class="nextAction === 'dashboard' ? 'border-purple-600 bg-purple-50' : 'border-gray-200 bg-white'">
                            <input type="radio" class="sr-only" value="dashboard" x-model="nextAction">
                            <span class="font-semibold text-gray-950">Explore the Platform First</span>
                        </label>
                    </div>
                </section>
            </div>

            <div class="flex items-center justify-between px-6 py-5 border-t border-gray-100">
                <button type="button" @click="go(step - 1)" x-show="step > 1" x-cloak class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Previous
                </button>
                <span x-show="step === 1"></span>

                <button type="button" @click="go(step + 1)" x-show="step < 4" x-cloak class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-purple-800">
                    Continue
                </button>
                <button type="submit" x-show="step === 4" x-cloak class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-purple-800">
                    Finish Setup
                </button>
            </div>

            <div x-show="showExitConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/50">
                <div class="w-full max-w-sm p-5 bg-white shadow-xl rounded-2xl">
                    <h2 class="text-lg font-bold text-gray-950">Finish setup first?</h2>
                    <p class="mt-2 text-sm text-gray-600">Guardian tools stay locked until onboarding is complete.</p>
                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" @click="showExitConfirm = false" class="px-4 py-2 text-sm font-semibold text-gray-700 border border-gray-200 rounded-xl">Stay</button>
                        <a href="{{ route('learner.dashboard') }}" class="px-4 py-2 text-sm font-semibold text-white bg-gray-900 rounded-xl">Leave</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
</body>
</html>
