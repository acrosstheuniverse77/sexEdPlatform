@extends('layouts.learner-app')

@section('title', 'Dashboard')

@section('content')
@php
    $showParentApprovedDashboardModal = (bool) session('show_parent_approved_dashboard_modal', false);
    $showParentApprovedDashboardModal = $showParentApprovedDashboardModal
        && Auth::user()?->isParentRegistration()
        && Auth::user()?->isParentVerificationApproved();
    $canUseChat = Auth::user()?->can('access chat') ?? false;
@endphp

<div x-data="{ showParentApprovedDashboardModal: {{ $showParentApprovedDashboardModal ? 'true' : 'false' }} }" class="relative">
<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    {{-- ══════════════════════════════════════════════════════════════════
         CENTER COLUMN
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="space-y-8 xl:col-span-2">

        {{-- ── Hero banner ── --}}
        <div class="relative overflow-hidden border shadow-sm rounded-2xl border-purple-200/60 dark:border-purple-800/40">
            {{-- Gradient background --}}
            <div class="absolute inset-0" style="background: linear-gradient(135deg, #A30EB2 0%, #730DB1 50%, #3B0CB1 100%);"></div>
            {{-- Subtle dot-grid overlay --}}
            <div class="absolute inset-0 opacity-10"
                 style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 20px 20px;"></div>
            {{-- Decorative blobs --}}
            <div class="absolute w-40 h-40 rounded-full -top-6 -right-6 opacity-20" style="background: radial-gradient(circle, #fff, transparent);"></div>
            <div class="absolute w-32 h-32 rounded-full -bottom-8 -left-4 opacity-10" style="background: radial-gradient(circle, #fff, transparent);"></div>

            {{-- Content --}}
            <div class="relative z-10 flex items-center justify-between gap-4 px-6 py-5">
                <div>
                    <p class="mb-1 text-xs font-medium tracking-widest text-purple-200 uppercase">Welcome back</p>
                    <h1 class="text-2xl font-bold tracking-tight text-white">
                        {{ $greeting }}, {{ $learnerProfile->username ?? Auth::user()->first_name ?? 'Learner' }}!
                    </h1>
                    <p class="mt-1 text-sm text-purple-200">Continue your learning journey today.</p>
                    {{-- Learner type badge --}}
                    @php
                        $ageBracket = $learnerProfile->getAgeBracket();
                        $isParentUser = (bool) ($learnerProfile->is_parent_account ?? false);
                        $typeBadge = match(true) {
                            $isParentUser              => ['label' => 'Guardian Account',  'class' => 'bg-amber-400/20 text-amber-200 border-amber-400/30'],
                            $ageBracket === 'kids'     => ['label' => 'Young Learner',   'class' => 'bg-green-400/20 text-green-200 border-green-400/30'],
                            $ageBracket === 'teens'    => ['label' => 'Teen Learner',    'class' => 'bg-blue-400/20 text-blue-100 border-blue-400/30'],
                            $ageBracket === 'adults'   => ['label' => 'Adult Learner',   'class' => 'bg-white/10 text-purple-100 border-white/20'],
                            default                    => null,
                        };
                    @endphp
                    @if($typeBadge)
                        <span class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-0.5 rounded-full border uppercase tracking-widest {{ $typeBadge['class'] }}">
                            {{ $typeBadge['label'] }}
                        </span>
                    @endif
                </div>
                {{-- Right side illustration-style icon --}}
                <div class="flex-col items-center flex-shrink-0 hidden gap-2 sm:flex">
                    <div class="flex items-center justify-center w-16 h-16 border rounded-2xl bg-white/10 border-white/20 backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                        </svg>
                    </div>
                    <a href="{{ route('learner.modules.index') }}"
                       class="text-[11px] font-semibold text-white/80 hover:text-white transition-colors">
                        My Modules &rarr;
                    </a>
                </div>
            </div>
        </div>

        <section class="p-5 border bg-white dark:bg-gray-800 rounded-2xl border-gray-100 dark:border-gray-700">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="pl-3 border-l-4 border-purple-400">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Connector Organization</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Register a barangay, school, health group, or advocacy organization for admin review.</p>
                </div>
                <a href="{{ route('connectors.register') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-800">
                    Register Connector
                </a>
            </div>
        </section>

        @if(($incomingParentInvitations ?? collect())->isNotEmpty())
            <section class="p-5 border bg-amber-50/60 dark:bg-amber-900/10 rounded-2xl border-amber-100/60 dark:border-amber-800/30">
                <div class="flex items-center justify-between mb-4">
                    <div class="pl-3 border-l-4 border-amber-400">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Guardian Invitation Requests</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Review pending invitations from guardian accounts.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach($incomingParentInvitations as $invitation)
                        <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $invitation->inviterParent?->name ?? 'Guardian' }} invited you to link accounts
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $invitation->created_at?->diffForHumans() }}</p>
                                </div>
                                <a href="{{ route('parent.invitations.show', $invitation) }}"
                                   class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                                    Review Invitation
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if(($approvedParentLinks ?? collect())->isNotEmpty())
            <section class="p-5 border bg-indigo-50/40 dark:bg-indigo-900/10 rounded-2xl border-indigo-100/60 dark:border-indigo-800/30">
                <div class="flex items-center justify-between mb-4">
                    <div class="pl-3 border-l-4 border-indigo-400">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Guardian</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Linked guardian accounts you can contact directly.</p>
                    </div>
                    <a href="{{ route('learner.parent.index') }}"
                       class="group inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white hover:scale-105 hover:shadow-md hover:shadow-indigo-300/40 transition-all duration-200 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-600 dark:hover:text-white">
                        View My Guardian
                    </a>
                </div>

                <div class="space-y-3">
                    @foreach($approvedParentLinks as $parentLink)
                        @php
                            $parentUser = $parentLink->parent;
                            $parentAvatar = $parentUser?->learnerProfile?->avatar_path
                                ? asset('storage/' . ltrim((string) $parentUser->learnerProfile->avatar_path, '/'))
                                : null;
                            $parentBirthdate = $parentUser?->birthdate ?? $parentUser?->learnerProfile?->birthdate;
                            $parentAge = $parentBirthdate
                                ? \Carbon\Carbon::parse($parentBirthdate)->age
                                : null;
                        @endphp

                        @if($parentUser)
                            <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($parentAvatar)
                                            <img src="{{ $parentAvatar }}" alt="{{ $parentUser->name }} avatar" class="h-10 w-10 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold dark:bg-indigo-900/30 dark:text-indigo-300">
                                                {{ strtoupper(substr((string) $parentUser->name, 0, 1)) }}
                                            </div>
                                        @endif

                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $parentUser->name }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                                @if(!is_null($parentAge))
                                                    <span>{{ $parentAge }} years old</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if($canUseChat)
                                        <button type="button"
                                            onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $parentUser->id }}, conversation_type: "direct", name: @json($parentUser->name) } }))'
                                            class="inline-flex items-center justify-center px-3.5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                                            Message Guardian
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Active Learning Modules --}}
        <section class="p-5 border bg-purple-50/40 dark:bg-purple-900/10 rounded-2xl border-purple-100/60 dark:border-purple-800/30">
            <div class="flex items-center justify-between mb-4">
                <div class="pl-3 border-l-4 border-purple-400">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Active Learning Modules</h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Continue your learning journey</p>
                </div>
                <a href="{{ route('learner.modules.index') }}"
                   class="group inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-purple-100 text-purple-700 hover:bg-purple-600 hover:text-white hover:scale-105 hover:shadow-md hover:shadow-purple-300/40 transition-all duration-200 dark:bg-purple-900/40 dark:text-purple-300 dark:hover:bg-purple-600 dark:hover:text-white">
                    View All
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 transition-transform duration-150 group-hover:translate-x-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            @if($enrollmentData->isEmpty())
                <div class="flex flex-col items-center p-10 text-center bg-white border border-gray-200 border-dashed dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                    <div class="flex items-center justify-center w-16 h-16 mb-4 rounded-full"
                         style="background: linear-gradient(135deg,#f3e8ff,#ede9fe);">
                        <svg class="w-8 h-8 text-purple-500" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">No active modules yet</h3>
                    <p class="mt-1 mb-4 text-sm text-gray-400 dark:text-gray-500">Enroll in a module below to start your journey.</p>
                    <a href="{{ route('learner.modules.index') }}"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm hover:opacity-90 transition-opacity"
                       style="background: linear-gradient(135deg,#A30EB2,#3B0CB1);">
                        Explore Modules
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach($enrollmentData as $data)
                        <x-learner.module-card-active :moduleData="$data" />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Recommended For You --}}
        <section class="p-5 border bg-indigo-50/30 dark:bg-indigo-900/10 rounded-2xl border-indigo-100/50 dark:border-indigo-800/30">
            <div class="flex items-center justify-between mb-4">
                <div class="pl-3 border-l-4 border-indigo-400">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recommended For You</h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Age-appropriate modules picked for you</p>
                </div>
                <a href="{{ route('learner.modules.index') }}"
                   class="group inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white hover:scale-105 hover:shadow-md hover:shadow-indigo-300/40 transition-all duration-200 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-600 dark:hover:text-white">
                    Browse All
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 transition-transform duration-150 group-hover:translate-x-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            @if($recommendedModules->isEmpty())
                <div class="p-8 text-center bg-white border border-gray-200 border-dashed dark:bg-gray-800 rounded-2xl dark:border-gray-700">
                    <p class="text-sm text-gray-400 dark:text-gray-500">
                        You've explored all available modules for your age group — great work!
                    </p>
                </div>
            @else
                <div class="flex gap-4 pb-3 overflow-x-auto snap-x snap-mandatory scroll-smooth scrollbar-thin">
                    @foreach($recommendedModules as $module)
                        <div class="flex-shrink-0 w-72 snap-start">
                            <x-learner.module-card-recommended :module="$module" />
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         RIGHT COLUMN — profile + gamification + calendar
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="space-y-4">

        <div class="overflow-hidden border-t-4 border-purple-500 shadow-sm rounded-2xl">
            <x-learner.gamification-panel
                :user="Auth::user()"
                :learnerProfile="$learnerProfile"
                :gamification="$gamification"
                :xpInLevel="$xpInLevel"
                :xpPercent="$xpPercent"
                :xpLevelSpan="$xpLevelSpan"
                :totalEnrolled="$totalEnrolled"
                :shieldsRemaining="$shieldsRemaining"
                :shieldCap="$shieldCap"
                :recentAchievements="$recentAchievements"
            />
        </div>

        <x-learner.streak-card
            :gamification="$gamification"
            :streakActiveDays="$streakActiveDays"
            :longestStreak="$longestStreak"
            :streakSavers="$streakSavers"
            :maxStreakSavers="$maxStreakSavers"
            :streakSaverCost="$streakSaverCost"
            :score="$gamification?->score ?? 0"
        />

        <div class="overflow-hidden border-t-4 border-indigo-400 shadow-sm rounded-2xl">
            <x-learner.mini-calendar />
        </div>

    </div>
</div>

<div x-cloak
     x-show="showParentApprovedDashboardModal"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
         @click.away="showParentApprovedDashboardModal = false">
        <h3 class="text-xl font-bold text-purple-900">Guardian Verification Approved</h3>
        <p class="mt-2 text-sm text-gray-600">Your guardian account is now active. You are now on your dashboard.</p>

        <div class="mt-5 space-y-3">
            <a href="{{ route('learner.modules.index') }}"
               class="w-full inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                Start Learning
            </a>

            <a href="{{ route('parent.create-child') }}"
               class="w-full inline-flex justify-center items-center px-6 py-3 text-base font-medium rounded-xl text-white transition"
               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                Create Dependent Account
            </a>
        </div>
    </div>
</div>

@include('learner.partials.edit-profile-modal', [
    'learnerProfile'       => $learnerProfile,
    'currentSubscription'  => $currentSubscription,
    'currentPlan'          => $currentPlan,
    'usernameCooldownDays' => $usernameCooldownDays,
    'isPremium'            => $isPremium,
    'hasUnlimitedQuizShields' => $hasUnlimitedQuizShields,
    'profileEntitlementHints' => $profileEntitlementHints,
])

@if(request()->boolean('open_edit_profile'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const openModal = function () {
                    if (!window.Alpine || typeof window.Alpine.store !== 'function') {
                        return false;
                    }

                    const modals = window.Alpine.store('modals');
                    if (!modals) {
                        return false;
                    }

                    if (typeof modals.openEditProfile === 'function') {
                        modals.openEditProfile();
                    } else {
                        modals.editProfile = true;
                    }

                    return true;
                };

                if (!openModal()) {
                    let attempts = 0;
                    const maxAttempts = 20;
                    const timer = setInterval(function () {
                        attempts++;
                        if (openModal() || attempts >= maxAttempts) {
                            clearInterval(timer);
                        }
                    }, 75);
                }
            });
        </script>
    @endpush
@endif
</div>
@endsection
