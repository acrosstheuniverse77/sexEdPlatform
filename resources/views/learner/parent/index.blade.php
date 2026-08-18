@extends('layouts.learner-app')

@section('title', 'My Guardian')

@section('content')
@php
    $totalParents = $parentLinks->count();
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <section class="flex flex-col items-start justify-between gap-3 p-6 text-white rounded-2xl md:flex-row md:items-center"
        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
        <div>
            <h1 class="text-2xl font-bold">My Guardian</h1>
            <p class="mt-1 text-sm text-white/80">
                Connected account{{ $totalParents === 1 ? '' : 's' }} and relationship details.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-xs font-semibold">
                {{ $totalParents }} Linked
            </span>
            <a href="{{ route('learner.dashboard') }}"
               class="inline-flex items-center rounded-xl border border-white/25 bg-white/15 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-white/25">
                Back to Dashboard
            </a>
        </div>
    </section>

    <section class="grid items-start grid-cols-1 gap-5 md:grid-cols-2">
        @foreach($parentLinks as $parentLink)
            @php
                $parentUser = $parentLink->parent;
                $profile = $parentUser?->learnerProfile;
                $parentAvatar = $profile?->avatar_path
                    ? asset('storage/' . ltrim((string) $profile->avatar_path, '/'))
                    : null;
                $parentBirthdate = $parentUser?->birthdate ?? $profile?->birthdate;
                $parentAge = $parentBirthdate
                    ? \Carbon\Carbon::parse($parentBirthdate)->age
                    : null;
                $parentGender = $profile?->gender
                    ? ucfirst(str_replace('_', ' ', (string) $profile->gender))
                    : null;
                $parentUsername = $profile?->username;
                $cityName = $profile?->city?->name;
                $barangayName = $profile?->barangay?->name;
                $locationLabel = $barangayName && $cityName
                    ? $barangayName . ', ' . $cityName
                    : ($cityName ?: $barangayName);
                $locationShort = $cityName ?: ($barangayName ?: 'N/A');
                $verificationLabel = ucfirst((string) ($parentLink->verification_status ?? 'approved'));
                $aboutTextRaw = $profile?->about ?: $profile?->bio;
                $aboutText = $aboutTextRaw
                    ? \Illuminate\Support\Str::limit(trim((string) $aboutTextRaw), 140)
                    : null;
                $fullName = $parentUser?->full_name ?: $parentUser?->name;
                $initials = strtoupper(substr((string) $parentUser?->name, 0, 1));
                $linkedAgo = $parentLink->relationship_verified_at?->diffForHumans();
                $canUseChat = auth()->user()?->can('access chat') ?? false;
                $statusClass = strtolower((string) $verificationLabel) === 'approved'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
            @endphp

            @if($parentUser)
                <article x-data="{ showDetails: false }" class="relative overflow-hidden transition bg-white border border-gray-200 shadow-sm rounded-2xl hover:shadow-md dark:bg-gray-800 dark:border-gray-700">
                    @if($canUseChat)
                        <button type="button"
                                @click="window.dispatchEvent(new CustomEvent('open-global-chat', { detail: { target_user_id: {{ (int) $parentUser->id }}, conversation_type: 'direct', name: @js($fullName) } }))"
                                class="absolute z-10 inline-flex items-center justify-center text-purple-700 border border-purple-200 rounded-lg right-4 top-4 h-9 w-9 bg-purple-50 hover:bg-purple-100"
                                title="Message {{ $fullName }}"
                                aria-label="Message {{ $fullName }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h5m-9 6l1.683-3.367A2 2 0 014 15.764V6a2 2 0 012-2h12a2 2 0 012 2v9.764a2 2 0 01-1.683 1.969L20 20l-4.317-2.267a2 2 0 00-.934-.233H6.934A2 2 0 015 18.764V20z"/>
                            </svg>
                        </button>
                    @endif

                    <div class="flex items-center gap-4 p-5 pr-14">
                        @if($parentAvatar)
                            <img src="{{ $parentAvatar }}"
                                 alt="{{ $fullName }} avatar"
                                 class="object-cover border border-gray-200 rounded-full shadow w-14 h-14 dark:border-gray-600">
                        @else
                            <div class="flex items-center justify-center text-xl font-bold text-white rounded-full shadow w-14 h-14"
                                 style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                {{ $initials }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="font-semibold text-gray-900 truncate dark:text-white">{{ $fullName }}</h2>
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClass }}">
                                    {{ $verificationLabel }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                                @if(!is_null($parentAge)){{ $parentAge }} years old.@endif
                                @if($linkedAgo) Linked {{ $linkedAgo }} @endif
                            </p>
                            @if($parentUsername)
                                <p class="text-xs text-purple-600 dark:text-purple-300 mt-0.5">{{ '@' . $parentUsername }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-3 py-3 text-center border-t border-b border-gray-100 divide-x divide-gray-100 bg-gray-50/60 dark:border-gray-700 dark:divide-gray-700 dark:bg-gray-900/30">
                        <div class="px-2">
                            <p class="text-lg font-bold text-purple-700 dark:text-purple-300">{{ !is_null($parentAge) ? $parentAge : '-' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Age</p>
                        </div>
                        <div class="px-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $parentGender ?: '-' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Gender</p>
                        </div>
                        <div class="px-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $locationShort }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Location</p>
                        </div>
                    </div>

                    <div x-cloak x-show="showDetails" x-transition.opacity.duration.200ms class="px-5 py-4 space-y-2 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $parentUser->email }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Username</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $parentUsername ? '@' . $parentUsername : 'N/A' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Birthdate</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $parentBirthdate ? \Carbon\Carbon::parse($parentBirthdate)->format('M d, Y') : 'N/A' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Gender</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $parentGender ?: 'N/A' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Location</span>
                            <span class="font-medium text-right text-gray-900 dark:text-gray-100">{{ $locationLabel ?: 'N/A' }}</span>
                        </div>

                        @if($aboutText)
                            <p class="pt-1 text-xs text-gray-500 dark:text-gray-400">{{ $aboutText }}</p>
                        @endif
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 dark:border-gray-700 dark:bg-gray-900/30">
                        <button type="button"
                                @click="showDetails = !showDetails"
                                class="inline-flex items-center justify-center w-full gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-xl transition-all hover:opacity-90 active:scale-[0.99]"
                                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            <svg class="w-4 h-4 transition-transform duration-200" :class="showDetails ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <span x-text="showDetails ? 'Hide Details' : 'View Details'"></span>
                        </button>
                    </div>
                </article>
            @endif
        @endforeach
    </section>
</div>
@endsection
