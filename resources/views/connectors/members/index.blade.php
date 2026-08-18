@extends('layouts.connector-app')

@section('title', 'Members')
@section('page-title', 'Members')

@php
    $avatarUrlFor = function ($user) {
        $path = $user?->learnerProfile?->avatar_path ?? $user?->instructorProfile?->profile_photo_path;

        return $path ? asset('storage/' . ltrim((string) $path, '/')) : null;
    };

    $roleOptions = $connector->roles->map(fn ($role) => [
        'id' => $role->id,
        'name' => $role->name,
        'is_owner' => (bool) $role->is_owner,
    ])->values();

    $activeOwnerCount = $connector->memberships
        ->where('status', 'active')
        ->filter(fn ($item) => $item->role?->is_owner)
        ->count();

    $stats = [
        ['label' => 'Total Members', 'value' => $connector->memberships->where('status', 'active')->count(), 'icon' => 'users', 'tone' => 'text-brand-700 bg-brand-50 border-brand-100'],
        ['label' => 'New Members (Last 30 Days)', 'value' => $connector->memberships->where('status', 'active')->filter(fn ($item) => $item->accepted_at?->gte(now()->subDays(30)))->count(), 'icon' => 'spark', 'tone' => 'text-sky-700 bg-sky-50 border-sky-100'],
    ];

    if ($canManageMembers) {
        $stats = [
            $stats[0],
            ['label' => 'Pending Invitations', 'value' => $connector->invitations->where('status', 'pending')->count(), 'icon' => 'mail', 'tone' => 'text-amber-700 bg-amber-50 border-amber-100'],
            ['label' => 'Removed Members', 'value' => $removedMembersCount, 'icon' => 'archive', 'tone' => 'text-gray-700 bg-gray-50 border-gray-100'],
            $stats[1],
        ];
    }

    $memberRows = $connector->memberships->values()->map(function ($membership) use ($connector, $activeOwnerCount, $avatarUrlFor) {
        $user = $membership->user;
        $avatarUrl = $avatarUrlFor($user);

        return [
            'id' => $membership->id,
            'name' => $user?->name ?? 'Unknown user',
            'email' => $user?->email ?? 'No email',
            'avatar' => $avatarUrl,
            'initial' => strtoupper(mb_substr($user?->name ?? 'U', 0, 1)),
            'role_id' => $membership->connector_role_id,
            'role' => $membership->role?->name ?? 'No role',
            'is_owner' => (bool) $membership->role?->is_owner,
            'status' => $membership->status,
            'joined' => $membership->accepted_at?->diffForHumans() ?? 'Pending',
            'joined_full' => $membership->accepted_at?->format('M d, Y g:i A') ?? 'Pending',
            'joined_value' => $membership->accepted_at?->format('Y-m-d') ?? null,
            'update_url' => route('connector.members.role', [$connector, $membership]),
            'remove_url' => route('connector.members.destroy', [$connector, $membership]),
            'can_remove' => ! ($membership->role?->is_owner && $activeOwnerCount <= 1),
            'search_blob' => strtolower(implode(' ', [$user?->name, $user?->email, $membership->role?->name, $membership->status])),
        ];
    });

    $invitationRows = $connector->invitations->where('status', 'pending')->values()->map(function ($invitation) use ($connector, $avatarUrlFor) {
        $user = $invitation->invitedUser;

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'name' => $user?->name ?? $invitation->email,
            'avatar' => $avatarUrlFor($user),
            'initial' => strtoupper(mb_substr($user?->name ?? $invitation->email, 0, 1)),
            'platform_role' => $user?->role ?? 'No platform role',
            'role' => $invitation->role?->name ?? 'No role',
            'status' => $invitation->status,
            'invited_by' => $invitation->inviter?->name ?? 'Unknown',
            'invited_at' => $invitation->created_at?->diffForHumans() ?? 'Unknown',
            'invited_at_full' => $invitation->created_at?->format('M d, Y g:i A') ?? 'Unknown',
            'expires' => $invitation->expires_at?->format('M d, Y') ?? 'No expiry',
            'resend_url' => route('connector.invitations.resend', [$connector, $invitation]),
            'cancel_url' => route('connector.invitations.destroy', [$connector, $invitation]),
            'search_blob' => strtolower(implode(' ', [$invitation->email, $user?->name, $invitation->role?->name, $invitation->status])),
        ];
    });

    $requestRows = $connector->membershipRequests->where('status', 'pending')->values()->map(function ($membershipRequest) use ($connector, $avatarUrlFor) {
        $user = $membershipRequest->user;
        $age = $user?->calculateAge();

        return [
            'id' => $membershipRequest->id,
            'name' => $user?->name ?? 'Unknown user',
            'email' => $user?->email ?? 'No email',
            'avatar' => $avatarUrlFor($user),
            'initial' => strtoupper(mb_substr($user?->name ?? 'U', 0, 1)),
            'platform_role' => $user?->role ?? 'No platform role',
            'age' => $age ? (string) $age : 'Not provided',
            'age_category' => $user?->age_bracket_cached ?? 'Not provided',
            'status' => $membershipRequest->status,
            'requested_at' => $membershipRequest->created_at?->diffForHumans() ?? 'Unknown',
            'requested_at_full' => $membershipRequest->created_at?->format('M d, Y g:i A') ?? 'Unknown',
            'approve_url' => route('connector.membership-requests.approve', [$connector, $membershipRequest]),
            'reject_url' => route('connector.membership-requests.reject', [$connector, $membershipRequest]),
            'search_blob' => strtolower(implode(' ', [$user?->name, $user?->email, $user?->role, $membershipRequest->status])),
        ];
    });

    $candidateRows = $inviteCandidates->map(fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => $avatarUrlFor($user),
        'initial' => strtoupper(mb_substr($user->name, 0, 1)),
        'role' => $user->role ?? 'No role',
        'label' => $user->name . ' - ' . $user->email,
        'search_blob' => strtolower($user->name . ' ' . $user->email . ' ' . $user->role),
    ])->values();
@endphp

@section('content')
<div x-data="connectorMembersPage({
        members: @js($memberRows),
        invitations: @js($invitationRows),
        requests: @js($requestRows),
        roles: @js($roleOptions),
        candidates: @js($candidateRows),
    })"
    class="space-y-6">
    <section class="overflow-hidden rounded-[24px] border border-gray-200 bg-white shadow-theme-xs">
        <div class="border-b border-brand-100 bg-[radial-gradient(circle_at_top_left,_rgba(163,14,178,0.14),_transparent_34%),linear-gradient(180deg,#ffffff_0%,#f8f3ff_100%)] px-6 py-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-700">Access</p>
                    <h2 class="mt-1 text-xl font-bold text-gray-900">{{ $canManageMembers ? 'Members Management' : 'Members Directory' }}</h2>
                </div>
                @if($canManageMembers)
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="openInvite()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-purple-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-purple-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5"/></svg>
                        Invite Member
                    </button>
                    <a href="{{ route('connector.members.removed', $connector) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50" title="Removed members" aria-label="Removed members">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M7 7v11h10V7M9 7V5h6v2"/></svg>
                    </a>
                </div>
                @endif
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($stat['value']) }}</p>
                            </div>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border {{ $stat['tone'] }}">
                                @if($stat['icon'] === 'mail')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 8 8.25 5.5a1.5 1.5 0 0 0 1.5 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                                @elseif($stat['icon'] === 'archive')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M7 7v11h10V7M9 7V5h6v2"/></svg>
                                @elseif($stat['icon'] === 'spark')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/></svg>
                                @else
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11a4 4 0 1 0-8 0m8 0a4 4 0 1 1-8 0m8 0v1a4 4 0 0 0 4 4m-12-5v1a4 4 0 0 1-4 4m4-4h8"/></svg>
                                @endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <label class="block md:col-span-2">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
                    <input x-model.debounce.150ms="filters.search" @input="page = 1" placeholder="Name, email, role..." class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm shadow-sm">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Role</span>
                    <select x-model="filters.role" @change="page = 1" class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm shadow-sm">
                        <option value="">All roles</option>
                        <template x-for="role in roles" :key="role.id">
                            <option :value="String(role.id)" x-text="role.name"></option>
                        </template>
                    </select>
                </label>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-brand-50/45">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Profile</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Role</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Joined</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <template x-for="member in paginatedMembers" :key="member.id">
                        <tr class="transition hover:bg-brand-50/55">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <template x-if="member.avatar"><img :src="member.avatar" alt="" class="h-10 w-10 rounded-full object-cover"></template>
                                    <template x-if="!member.avatar"><span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-100 text-sm font-bold text-brand-700" x-text="member.initial"></span></template>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900" x-text="member.name"></p>
                                        <p class="text-xs text-gray-500" x-text="member.email"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-800" x-text="member.role"></td>
                            <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="statusClass(member.status)" x-text="member.status"></span></td>
                            <td class="px-6 py-4 text-sm text-gray-600" x-text="member.joined"></td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openView(member)" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100" title="View member">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S3.732 16.057 2.458 12Z"/></svg>
                                    </button>
                                    @if($canManageMembers)
                                        <button type="button" @click="openRole(member)" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100" title="Edit role">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        </button>
                                        <button type="button" @click="openRemove(member)" :disabled="!member.can_remove" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40" title="Remove member">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredMembers.length === 0" x-cloak><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No members match these filters.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page === 1" class="rounded-lg border border-brand-200 px-3 py-1.5 text-sm font-semibold text-brand-700 disabled:opacity-50">Previous</button>
            <span class="text-sm text-gray-600">Page <span x-text="safePage"></span> of <span x-text="totalPages"></span></span>
            <button type="button" @click="page = Math.min(totalPages, page + 1)" :disabled="page >= totalPages" class="rounded-lg border border-brand-200 px-3 py-1.5 text-sm font-semibold text-brand-700 disabled:opacity-50">Next</button>
        </div>
    </section>

    @if($canManageMembers)
    <section class="rounded-[24px] border border-gray-200 bg-white p-5 shadow-theme-xs">
        <h2 class="font-bold text-gray-900">Pending Invitations</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <template x-for="invitation in invitations" :key="invitation.id">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <template x-if="invitation.avatar"><img :src="invitation.avatar" alt="" class="h-10 w-10 rounded-full object-cover"></template>
                        <template x-if="!invitation.avatar"><span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-sm font-bold text-amber-700" x-text="invitation.initial"></span></template>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900" x-text="invitation.name"></p>
                            <p class="truncate text-xs text-gray-500"><span x-text="invitation.email"></span> as <span x-text="invitation.role"></span></p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="openInvitation(invitation, 'invitation-view')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50" title="View information">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S3.732 16.057 2.458 12Z"/></svg>
                        </button>
                        <button type="button" @click="openInvitation(invitation, 'invitation-resend')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100" title="Resend invitation">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 15a7 7 0 0 0 12 3M19 9A7 7 0 0 0 7 6"/></svg>
                        </button>
                        <button type="button" @click="openInvitation(invitation, 'invitation-cancel')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Cancel invitation">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </template>
            <p x-show="invitations.length === 0" class="text-sm text-gray-500">No pending invitations.</p>
        </div>
    </section>

    <section class="rounded-[24px] border border-gray-200 bg-white p-5 shadow-theme-xs">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-bold text-gray-900">Membership Requests</h2>
            <input x-model.debounce.150ms="requestSearch" placeholder="Search requests..." class="rounded-xl border-gray-300 text-sm">
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Applicant</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Requested</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <template x-for="request in filteredRequests" :key="request.id">
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <template x-if="request.avatar"><img :src="request.avatar" alt="" class="h-10 w-10 rounded-full object-cover"></template>
                                    <template x-if="!request.avatar"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-sm font-bold text-brand-700" x-text="request.initial"></span></template>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900" x-text="request.name"></p>
                                        <p class="text-xs text-gray-500" x-text="request.email"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600" x-text="request.requested_at"></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold capitalize text-amber-700" x-text="request.status"></span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openRequest(request, 'request-view')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50" title="View request">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S3.732 16.057 2.458 12Z"/></svg>
                                    </button>
                                    <button type="button" @click="openRequest(request, 'request-approve')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100" title="Approve request">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7"/></svg>
                                    </button>
                                    <button type="button" @click="openRequest(request, 'request-reject')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Reject request">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredRequests.length === 0" x-cloak><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No membership requests.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="inviteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-gray-900/50" @click="inviteOpen = false"></div>
        <form method="POST" action="{{ route('connector.invitations.store', $connector) }}" class="relative w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
            @csrf
            <h3 class="text-lg font-bold text-gray-900">Invite Member</h3>
            <p class="mt-1 text-sm text-gray-500">Search an existing user, assign a connector role, and send an invitation notification.</p>
            <div class="mt-5 space-y-4">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Search user</span>
                    <input type="text" x-model.debounce.150ms="inviteSearch" placeholder="Type a name or email" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                </label>
                <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-100">
                    <template x-for="candidate in filteredCandidates" :key="candidate.id">
                        <button type="button" @click="selectCandidate(candidate)" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm hover:bg-purple-50">
                            <span class="flex min-w-0 items-center gap-3">
                                <template x-if="candidate.avatar"><img :src="candidate.avatar" alt="" class="h-9 w-9 rounded-full object-cover"></template>
                                <template x-if="!candidate.avatar"><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-100 text-xs font-bold text-brand-700" x-text="candidate.initial"></span></template>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-gray-900" x-text="candidate.name"></span>
                                    <span class="block truncate text-xs text-gray-500"><span x-text="candidate.email"></span> - <span x-text="candidate.role"></span></span>
                                </span>
                            </span>
                            <span x-show="selectedEmail === candidate.email" class="text-xs font-bold text-purple-700">Selected</span>
                        </button>
                    </template>
                    <p x-show="filteredCandidates.length === 0" class="px-4 py-4 text-sm text-gray-500">No matching users in the current candidate list.</p>
                </div>
                <input type="hidden" name="email" :value="selectedEmail" required>
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Role</span>
                    <select name="connector_role_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm" required>
                        @foreach($connector->roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="inviteOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                <button type="submit" :disabled="!selectedEmail" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Send Invitation</button>
            </div>
        </form>
    </div>
    @endif

    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-gray-900/50" @click="modalOpen = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <template x-if="modalMode === 'view' && selectedMember">
                <div>
                    <h3 class="text-lg font-bold text-gray-900" x-text="selectedMember.name"></h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="selectedMember.email"></p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Role</dt><dd class="font-semibold" x-text="selectedMember.role"></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd class="font-semibold capitalize" x-text="selectedMember.status"></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Joined</dt><dd class="font-semibold" x-text="selectedMember.joined"></dd></div>
                    </dl>
                </div>
            </template>
            <template x-if="modalMode === 'role' && selectedMember">
                <form method="POST" :action="selectedMember.update_url">
                    @csrf
                    @method('PATCH')
                    <h3 class="text-lg font-bold text-gray-900">Edit Role</h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="selectedMember.name"></p>
                    <select name="connector_role_id" class="mt-4 w-full rounded-xl border-gray-300 text-sm" required>
                        <template x-for="role in roles" :key="role.id">
                            <option :value="role.id" :selected="role.id === selectedMember.role_id" x-text="role.name"></option>
                        </template>
                    </select>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Save Role</button>
                    </div>
                </form>
            </template>
            <template x-if="modalMode === 'remove' && selectedMember">
                <form method="POST" :action="selectedMember.remove_url">
                    @csrf
                    @method('DELETE')
                    <h3 class="text-lg font-bold text-gray-900">Remove member?</h3>
                    <p class="mt-2 text-sm text-gray-600">This removes <span class="font-semibold" x-text="selectedMember.name"></span> from the connector workspace.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Remove</button>
                    </div>
                </form>
            </template>
            <template x-if="modalMode === 'invitation-view' && selectedInvitation">
                <div>
                    <div class="flex items-center gap-3">
                        <template x-if="selectedInvitation.avatar"><img :src="selectedInvitation.avatar" alt="" class="h-12 w-12 rounded-full object-cover"></template>
                        <template x-if="!selectedInvitation.avatar"><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-base font-bold text-amber-700" x-text="selectedInvitation.initial"></span></template>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900" x-text="selectedInvitation.name"></h3>
                            <p class="text-sm text-gray-500" x-text="selectedInvitation.email"></p>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Platform role</dt><dd class="font-semibold capitalize" x-text="selectedInvitation.platform_role"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Assigned role</dt><dd class="font-semibold" x-text="selectedInvitation.role"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd class="font-semibold capitalize" x-text="selectedInvitation.status"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Invited by</dt><dd class="font-semibold" x-text="selectedInvitation.invited_by"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Invitation date</dt><dd class="font-semibold" x-text="selectedInvitation.invited_at_full"></dd></div>
                    </dl>
                </div>
            </template>
            <template x-if="modalMode === 'invitation-resend' && selectedInvitation">
                <form method="POST" :action="selectedInvitation.resend_url">
                    @csrf
                    <h3 class="text-lg font-bold text-gray-900">Resend invitation?</h3>
                    <p class="mt-2 text-sm text-gray-600">Send a fresh invitation notification to <span class="font-semibold" x-text="selectedInvitation.name"></span> and extend expiry.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Resend</button>
                    </div>
                </form>
            </template>
            <template x-if="modalMode === 'invitation-cancel' && selectedInvitation">
                <form method="POST" :action="selectedInvitation.cancel_url">
                    @csrf
                    @method('DELETE')
                    <h3 class="text-lg font-bold text-gray-900">Cancel invitation?</h3>
                    <p class="mt-2 text-sm text-gray-600">This frees the invitation slot and stops <span class="font-semibold" x-text="selectedInvitation.name"></span> from accepting this invite.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Keep</button>
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Cancel Invitation</button>
                    </div>
                </form>
            </template>
            <template x-if="modalMode === 'request-view' && selectedRequest">
                <div>
                    <div class="flex items-center gap-3">
                        <template x-if="selectedRequest.avatar"><img :src="selectedRequest.avatar" alt="" class="h-12 w-12 rounded-full object-cover"></template>
                        <template x-if="!selectedRequest.avatar"><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-100 text-base font-bold text-brand-700" x-text="selectedRequest.initial"></span></template>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900" x-text="selectedRequest.name"></h3>
                            <p class="text-sm text-gray-500" x-text="selectedRequest.email"></p>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Age</dt><dd class="font-semibold" x-text="selectedRequest.age"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Age category</dt><dd class="font-semibold capitalize" x-text="selectedRequest.age_category"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Role</dt><dd class="font-semibold capitalize" x-text="selectedRequest.platform_role"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Request date</dt><dd class="font-semibold" x-text="selectedRequest.requested_at_full"></dd></div>
                    </dl>
                </div>
            </template>
            <template x-if="modalMode === 'request-approve' && selectedRequest">
                <form method="POST" :action="selectedRequest.approve_url">
                    @csrf
                    <h3 class="text-lg font-bold text-gray-900">Approve request?</h3>
                    <p class="mt-2 text-sm text-gray-600">Add <span class="font-semibold" x-text="selectedRequest.name"></span> as an active connector member.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Approve</button>
                    </div>
                </form>
            </template>
            <template x-if="modalMode === 'request-reject' && selectedRequest">
                <form method="POST" :action="selectedRequest.reject_url">
                    @csrf
                    <h3 class="text-lg font-bold text-gray-900">Reject request?</h3>
                    <p class="mt-2 text-sm text-gray-600">Store rejection reason for audit.</p>
                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-gray-700">Reason</span>
                        <select name="rejection_reason" x-model="rejectReason" class="mt-1 w-full rounded-xl border-gray-300 text-sm" required>
                            <option value="Not eligible">Not eligible</option>
                            <option value="Capacity full">Capacity full</option>
                            <option value="Unable to verify profile">Unable to verify profile</option>
                            <option value="Other">Other</option>
                        </select>
                    </label>
                    <label x-show="rejectReason === 'Other'" class="mt-4 block">
                        <span class="text-sm font-semibold text-gray-700">Explanation</span>
                        <textarea name="rejection_note" class="mt-1 w-full rounded-xl border-gray-300 text-sm" rows="3"></textarea>
                    </label>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>

<script>
    function connectorMembersPage(config) {
        return {
            members: config.members || [],
            invitations: config.invitations || [],
            requests: config.requests || [],
            roles: config.roles || [],
            candidates: config.candidates || [],
            filters: { search: '', role: '' },
            requestSearch: '',
            page: 1,
            perPage: 10,
            inviteOpen: false,
            inviteSearch: '',
            selectedEmail: '',
            modalOpen: false,
            modalMode: 'view',
            selectedMember: null,
            selectedInvitation: null,
            selectedRequest: null,
            rejectReason: 'Not eligible',
            get filteredMembers() {
                return this.members.filter((member) => {
                    const search = this.filters.search.trim().toLowerCase();
                    return (!search || member.search_blob.includes(search))
                        && (!this.filters.role || String(member.role_id) === this.filters.role);
                });
            },
            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredMembers.length / this.perPage));
            },
            get safePage() {
                return Math.min(this.page, this.totalPages);
            },
            get paginatedMembers() {
                return this.filteredMembers.slice((this.safePage - 1) * this.perPage, this.safePage * this.perPage);
            },
            get filteredCandidates() {
                const search = this.inviteSearch.trim().toLowerCase();
                return this.candidates.filter((candidate) => !search || candidate.search_blob.includes(search)).slice(0, 12);
            },
            get filteredRequests() {
                const search = this.requestSearch.trim().toLowerCase();
                return this.requests.filter((request) => !search || request.search_blob.includes(search));
            },
            openInvite() {
                this.inviteOpen = true;
                this.inviteSearch = '';
                this.selectedEmail = '';
            },
            selectCandidate(candidate) {
                this.selectedEmail = candidate.email;
                this.inviteSearch = candidate.label;
            },
            openView(member) { this.selectedMember = member; this.modalMode = 'view'; this.modalOpen = true; },
            openRole(member) { this.selectedMember = member; this.modalMode = 'role'; this.modalOpen = true; },
            openRemove(member) { if (!member.can_remove) return; this.selectedMember = member; this.modalMode = 'remove'; this.modalOpen = true; },
            openInvitation(invitation, mode) { this.selectedInvitation = invitation; this.selectedMember = null; this.modalMode = mode; this.modalOpen = true; },
            openRequest(request, mode) { this.selectedRequest = request; this.selectedInvitation = null; this.selectedMember = null; this.modalMode = mode; this.rejectReason = 'Not eligible'; this.modalOpen = true; },
            statusClass(status) {
                return { active: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', removed: 'bg-gray-100 text-gray-600' }[status] || 'bg-gray-100 text-gray-600';
            },
        };
    }
</script>
@endsection
