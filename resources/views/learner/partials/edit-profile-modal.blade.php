{{--
    Edit Profile Modal
    Props: $learnerProfile, $currentSubscription, $currentPlan,
           $usernameCooldownDays, $isPremium,
           $hasUnlimitedQuizShields, $profileEntitlementHints
--}}
@php
    $profileEntitlementHints = $profileEntitlementHints ?? [];
@endphp
<div
    x-data="editProfileModal()"
    x-show="$store.modals.editProfile"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6"
    @keydown.escape.window="typeof $store.modals.closeEditProfile === 'function' ? $store.modals.closeEditProfile() : ($store.modals.editProfile = false)"
>
    {{-- Backdrop --}}
    <div
        class="fixed inset-0 bg-black/50 backdrop-blur-sm"
        @click="typeof $store.modals.closeEditProfile === 'function' ? $store.modals.closeEditProfile() : ($store.modals.editProfile = false)"
        x-transition:enter="transition duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    {{-- Panel --}}
    <div
        class="relative z-10 w-full max-w-2xl bg-white dark:bg-gray-900 rounded-2xl shadow-xl overflow-hidden"
        x-transition:enter="transition duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        data-bio="{{ $learnerProfile->bio ?? '' }}"
        data-username="{{ $learnerProfile->username ?? '' }}"
        x-init="init()"
    >
        {{-- Brand gradient accent bar --}}
        <div class="h-1.5 w-full" style="background: linear-gradient(90deg, #A30EB2, #730DB1, #3B0CB1);"></div>

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Edit Profile</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Manage your account settings</p>
            </div>
            <div class="hidden sm:flex items-center gap-2 mr-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $isPremium ? 'text-amber-100 bg-amber-500/80' : 'text-gray-600 bg-gray-200 dark:text-gray-200 dark:bg-gray-700' }}">
                    {{ $isPremium ? 'Premium' : 'Free' }}
                </span>
                @if($isPremium && $currentPlan)
                    <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $currentPlan->name }}</span>
                @endif
            </div>
            <button
                @click="typeof $store.modals.closeEditProfile === 'function' ? $store.modals.closeEditProfile() : ($store.modals.editProfile = false)"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                aria-label="Close"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Tab Bar --}}
        <div class="flex border-b border-gray-100 dark:border-gray-700 px-6 overflow-x-auto">
            @foreach([
                ['key' => 'profile',      'label' => 'Profile'],
                ['key' => 'password',     'label' => 'Password'],
                ['key' => 'subscription', 'label' => 'Subscription'],
            ] as $tab)
            <button
                @click="activeTab = '{{ $tab['key'] }}'"
                class="flex-shrink-0 px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap"
                :class="activeTab === '{{ $tab['key'] }}'
                    ? 'border-purple-600 text-purple-700 dark:text-purple-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            >
                {{ $tab['label'] }}
            </button>
            @endforeach
        </div>

        {{-- ──────────────────────────────────────────────────── --}}
        {{-- TAB: PROFILE                                         --}}
        {{-- ──────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'profile'" class="p-6 space-y-5">

            {{-- Success banner --}}
            <div x-show="profileSuccess" x-cloak
                 class="flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl px-4 py-3 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span x-text="profileSuccess"></span>
            </div>

            {{-- General error banner --}}
            <div x-show="(profileErrors.general ?? []).length" x-cloak
                 class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl px-4 py-3 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <span x-text="(profileErrors.general ?? [])[0]"></span>
            </div>

            {{-- Avatar upload --}}
            <div class="bg-purple-50/40 dark:bg-purple-900/10 rounded-2xl p-5 border border-purple-100/60 dark:border-purple-800/30">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3">Profile Photo</label>
                <div class="flex items-center gap-4">

                    {{-- Avatar preview / placeholder --}}
                    <div class="relative flex-shrink-0 cursor-pointer group" @click="selectAvatar()">
                        <template x-if="avatarPreview">
                            <img :src="avatarPreview" alt="Avatar preview"
                                 class="w-16 h-16 rounded-full object-cover ring-2 ring-purple-300 dark:ring-purple-700">
                        </template>
                        <template x-if="!avatarPreview">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold ring-2 ring-purple-300 dark:ring-purple-700"
                                 style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                {{ strtoupper(mb_substr($learnerProfile->username ?? Auth::user()->first_name ?? '?', 0, 1)) }}
                            </div>
                        </template>
                        {{-- Camera overlay --}}
                        <div class="absolute inset-0 rounded-full bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                            </svg>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="selectAvatar()"
                            class="text-sm font-semibold px-4 py-1.5 rounded-lg border border-purple-300 dark:border-purple-700 text-purple-700 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors"
                        >
                            Change Photo
                        </button>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">JPEG, PNG, JPG — max 2 MB</p>
                    </div>

                    <input type="file" x-ref="avatarInput" accept="image/jpeg,image/png,image/jpg" class="hidden" @change="onAvatarChange($event)">
                </div>
            </div>

            {{-- Username --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                @if($usernameCooldownDays > 0 && !$isPremium)
                    <input
                        type="text"
                        x-model="username"
                        disabled
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-400 dark:text-gray-500 cursor-not-allowed"
                    >
                    <div class="flex items-center gap-1.5 mt-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-amber-500 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs text-amber-600 dark:text-amber-400">
                            Available in {{ $usernameCooldownDays }} day(s).
                            <a href="{{ route('subscription.index') }}" class="underline font-semibold" @click="typeof $store.modals.closeEditProfile === 'function' ? $store.modals.closeEditProfile() : ($store.modals.editProfile = false)">Go Premium</a>
                            for unlimited changes.
                        </span>
                    </div>
                @else
                    <input
                        type="text"
                        x-model="username"
                        minlength="3"
                        maxlength="30"
                        placeholder="your_username"
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent transition"
                    >
                    @if($isPremium)
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Premium — unlimited username changes.</p>
                    @endif
                @endif
                <p x-show="(profileErrors.username ?? []).length" x-cloak class="text-xs text-red-500 mt-1" x-text="(profileErrors.username ?? [])[0]"></p>
            </div>

            {{-- Bio --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bio</label>
                    <span class="text-xs text-gray-400 dark:text-gray-500" :class="bioLength > 240 ? 'text-amber-500' : ''">
                        <span x-text="bioLength"></span>/255
                    </span>
                </div>
                <textarea
                    x-model="bio"
                    @input="bioLength = bio.length"
                    rows="3"
                    maxlength="500"
                    placeholder="Tell us a bit about yourself..."
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent transition resize-none"
                ></textarea>
                <p x-show="(profileErrors.bio ?? []).length" x-cloak class="text-xs text-red-500 mt-1" x-text="(profileErrors.bio ?? [])[0]"></p>
            </div>

            {{-- Save button --}}
            <div class="flex justify-end pt-1">
                <button
                    type="button"
                    @click="saveProfile()"
                    :disabled="profileLoading"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm hover:opacity-90 hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                >
                    <template x-if="profileLoading">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </template>
                    <span x-text="profileLoading ? 'Saving...' : 'Save Changes'"></span>
                </button>
            </div>

        </div>{{-- /profile tab --}}

        {{-- ──────────────────────────────────────────────────── --}}
        {{-- TAB: PASSWORD                                        --}}
        {{-- ──────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'password'" x-cloak class="p-6 space-y-5">

            {{-- Success banner --}}
            <div x-show="passwordSuccess" x-cloak
                 class="flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl px-4 py-3 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span x-text="passwordSuccess"></span>
            </div>

            {{-- General error banner --}}
            <div x-show="(passwordErrors.general ?? []).length" x-cloak
                 class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl px-4 py-3 text-sm">
                <span x-text="(passwordErrors.general ?? [])[0]"></span>
            </div>

            <div class="bg-purple-50/40 dark:bg-purple-900/10 rounded-2xl p-5 border border-purple-100/60 dark:border-purple-800/30 space-y-4">

                {{-- Current password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                    <input type="password" x-model="currentPassword" autocomplete="current-password"
                           class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent transition">
                    <p x-show="(passwordErrors.current_password ?? []).length" x-cloak class="text-xs text-red-500 mt-1" x-text="(passwordErrors.current_password ?? [])[0]"></p>
                </div>

                {{-- New password + strength bar --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                    <input type="password" x-model="newPassword" autocomplete="new-password"
                           class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent transition">

                    {{-- Strength bar --}}
                    <div x-show="newPassword" x-cloak class="mt-2 flex items-center gap-2">
                        <div class="flex-1 flex gap-1">
                            <template x-for="i in 4" :key="i">
                                <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                                     :class="{
                                        'bg-red-400':    passwordStrength() >= i && passwordStrength() === 1,
                                        'bg-amber-400':  passwordStrength() >= i && passwordStrength() === 2,
                                        'bg-yellow-400': passwordStrength() >= i && passwordStrength() === 3,
                                        'bg-green-500':  passwordStrength() >= i && passwordStrength() === 4,
                                        'bg-gray-200 dark:bg-gray-700': passwordStrength() < i,
                                     }">
                                </div>
                            </template>
                        </div>
                        <span class="text-xs font-medium w-12 text-right"
                              :class="{
                                  'text-red-500':    passwordStrength() === 1,
                                  'text-amber-500':  passwordStrength() === 2,
                                  'text-yellow-600': passwordStrength() === 3,
                                  'text-green-600':  passwordStrength() === 4,
                              }"
                              x-text="['', 'Weak', 'Fair', 'Good', 'Strong'][passwordStrength()]">
                        </span>
                    </div>

                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Min 8 chars · uppercase · lowercase · number · special character</p>
                    <p x-show="(passwordErrors.password ?? []).length" x-cloak class="text-xs text-red-500 mt-1" x-text="(passwordErrors.password ?? [])[0]"></p>
                </div>

                {{-- Confirm password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                    <input type="password" x-model="confirmPassword" autocomplete="new-password"
                           class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent transition">
                    <p x-show="confirmPassword && confirmPassword !== newPassword" x-cloak class="text-xs text-red-500 mt-1">Passwords do not match.</p>
                </div>

            </div>

            {{-- Save button --}}
            <div class="flex justify-end">
                <button
                    type="button"
                    @click="savePassword()"
                    :disabled="passwordLoading || !currentPassword || !newPassword || !confirmPassword || confirmPassword !== newPassword"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm hover:opacity-90 hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:scale-100"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                >
                    <template x-if="passwordLoading">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </template>
                    <span x-text="passwordLoading ? 'Updating...' : 'Update Password'"></span>
                </button>
            </div>

        </div>{{-- /password tab --}}

        {{-- ──────────────────────────────────────────────────── --}}
        {{-- TAB: SUBSCRIPTION                                    --}}
        {{-- ──────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'subscription'" x-cloak class="p-6 space-y-5">

            <div class="bg-purple-50/40 dark:bg-purple-900/10 rounded-2xl p-5 border border-purple-100/60 dark:border-purple-800/30">

                {{-- Plan badge --}}
                <div class="flex items-center gap-3 mb-4">
                    @if($isPremium)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-bold text-white"
                              style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20" class="w-3.5 h-3.5">
                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                            </svg>
                            PREMIUM
                        </span>
                        @if($currentPlan)
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $currentPlan->name }}</span>
                        @endif
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            FREE
                        </span>
                    @endif
                </div>

                {{-- Renewal / upgrade info --}}
                @if($isPremium && $currentSubscription)
                    @php
                        $subscriptionStatusRaw = $currentSubscription->status ?? null;
                        $subscriptionStatus = $subscriptionStatusRaw instanceof \BackedEnum
                            ? $subscriptionStatusRaw->value
                            : (string) ($subscriptionStatusRaw ?? 'inactive');

                        $subscriptionStatusLabel = match($subscriptionStatus) {
                            'scheduled_cancel' => 'Scheduled to Cancel',
                            'grace_period' => 'Grace Period',
                            default => ucfirst(str_replace('_', ' ', $subscriptionStatus)),
                        };
                        $subscriptionEndDate = $currentSubscription->ends_at ?? $currentSubscription->end_date;
                    @endphp
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                        <div class="flex items-center justify-between py-1 border-b border-purple-100/60 dark:border-purple-800/30">
                            <span class="text-gray-500 dark:text-gray-500 text-xs uppercase tracking-wide font-medium">Status</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ $subscriptionStatusLabel }}</span>
                        </div>
                        @if($subscriptionEndDate)
                            <div class="flex items-center justify-between py-1">
                                <span class="text-gray-500 dark:text-gray-500 text-xs uppercase tracking-wide font-medium">Next Billing Date</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">
                                    {{ $subscriptionEndDate->format('M d, Y') }}
                                </span>
                            </div>
                        @endif
                    </div>
                @elseif(!$isPremium)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                        Upgrade to Premium to unlock advanced learning features.
                    </p>
                @endif

                @if($learnerProfile->is_parent_account ?? false)
                    <div class="mt-3 flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2 border border-amber-100 dark:border-amber-800">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Guardian account — manages child subscriptions
                    </div>
                @endif

                @if(!empty($profileEntitlementHints))
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($profileEntitlementHints as $hint)
                            <div class="rounded-xl border px-3 py-2.5 {{ $hint['is_enabled'] ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-800 dark:bg-emerald-900/20' : 'border-gray-200 bg-white/80 dark:border-gray-700 dark:bg-gray-800/60' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $hint['label'] }}</span>
                                    <span class="text-[11px] font-bold {{ $hint['is_enabled'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-600 dark:text-gray-300' }}">{{ $hint['value'] }}</span>
                                </div>
                                <p class="mt-1 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">{{ $hint['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

            <a
                href="{{ route('subscription.index') }}"
                @click="typeof $store.modals.closeEditProfile === 'function' ? $store.modals.closeEditProfile() : ($store.modals.editProfile = false)"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm hover:opacity-90 hover:scale-[1.02] active:scale-[0.98] transition-all"
                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
            >
                Manage Subscription
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>

        </div>{{-- /subscription tab --}}

    </div>{{-- /panel --}}
</div>{{-- /root --}}

@push('scripts')
<script>
function editProfileModal() {
    return {
        activeTab: 'profile',

        // Profile tab
        profileLoading: false,
        profileSuccess: null,
        profileErrors: {},
        avatarPreview: @js($learnerProfile->avatar_path ? asset('storage/' . $learnerProfile->avatar_path) : null),
        avatarFile: null,
        bio: '',
        username: '',
        bioLength: 0,

        // Password tab
        passwordLoading: false,
        passwordSuccess: null,
        passwordErrors: {},
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',

        init() {
            this.bio      = this.$el.dataset.bio || '';
            this.username = this.$el.dataset.username || '';
            this.bioLength = this.bio.length;

            const pendingToast = sessionStorage.getItem('learner_post_reload_toast');
            if (pendingToast) {
                sessionStorage.removeItem('learner_post_reload_toast');
                if (window.toast?.success) {
                    window.toast.success(pendingToast);
                }
            }
        },

        closeModalAndRefresh(successMessage = 'Profile updated successfully!') {
            sessionStorage.setItem('learner_post_reload_toast', successMessage);

            if (typeof this.$store.modals.closeEditProfile === 'function') {
                this.$store.modals.closeEditProfile();
            } else {
                this.$store.modals.editProfile = false;
            }

            setTimeout(() => {
                window.location.reload();
            }, 250);
        },

        selectAvatar() {
            this.$refs.avatarInput.click();
        },

        onAvatarChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.avatarFile    = file;
            this.avatarPreview = URL.createObjectURL(file);
        },

        passwordStrength() {
            const p = this.newPassword;
            if (!p) return 0;
            let score = 0;
            if (p.length >= 8)         score++;
            if (/[A-Z]/.test(p))       score++;
            if (/[0-9]/.test(p))       score++;
            if (/[@$!%*?&#]/.test(p))  score++;
            return score; // 0–4
        },

        async saveProfile() {
            this.profileLoading = true;
            this.profileSuccess = null;
            this.profileErrors  = {};

            const fd = new FormData();
            fd.append('_method',  'PUT');
            fd.append('username', this.username);
            fd.append('bio',      this.bio);
            if (this.avatarFile) fd.append('avatar', this.avatarFile);
            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);

            try {
                const res  = await fetch('{{ route("profile.learner.update") }}', {
                    method:  'POST',
                    headers: { 'Accept': 'application/json' },
                    body:    fd,
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    this.avatarFile     = null;
                    if (data.data?.avatar_url) this.avatarPreview = data.data.avatar_url;
                    this.profileErrors = {};
                    this.closeModalAndRefresh(data.message || 'Profile updated successfully!');
                } else {
                    this.profileErrors = data.errors ?? {};
                }
            } catch {
                this.profileErrors = { general: ['Something went wrong. Please try again.'] };
            } finally {
                this.profileLoading = false;
            }
        },

        async savePassword() {
            this.passwordLoading = true;
            this.passwordSuccess = null;
            this.passwordErrors  = {};

            try {
                const res  = await fetch('{{ route("profile.password.update") }}', {
                    method:  'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        current_password:      this.currentPassword,
                        password:              this.newPassword,
                        password_confirmation: this.confirmPassword,
                    }),
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    this.currentPassword  = '';
                    this.newPassword      = '';
                    this.confirmPassword  = '';
                    this.passwordErrors = {};
                    this.closeModalAndRefresh(data.message || 'Password updated successfully!');
                } else {
                    this.passwordErrors = data.errors ?? {};
                }
            } catch {
                this.passwordErrors = { general: ['Something went wrong.'] };
            } finally {
                this.passwordLoading = false;
            }
        },
    };
}
</script>
@endpush
