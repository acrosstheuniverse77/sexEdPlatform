@extends('layouts.learner-app')

@section('title', 'Invitation History')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="rounded-2xl p-6 text-white"
         style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Guardian Invitation History</h1>
                <p class="text-white/80 text-sm mt-1">Review your complete guardian-link invitation timeline.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('parent.invitations.index') }}"
                   class="inline-flex items-center rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25 border border-white/20">
                    Invitation Center
                </a>
                <a href="{{ route('parent.children.index') }}"
                   class="inline-flex items-center rounded-xl bg-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/30">
                    Back to My Dependents
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">All Invitation Transactions</h2>
            <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">
                {{ $outgoingInvitations->count() }} total
            </span>
        </div>

        @if($outgoingInvitations->isEmpty())
            <p class="text-sm text-gray-500 mt-3">No invitations found yet.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach($outgoingInvitations as $invitation)
                    @php
                        $statusValue = $invitation->status instanceof \App\Enums\ParentChildInvitationStatus
                            ? $invitation->status->value
                            : (string) $invitation->status;
                        $statusClass = match ($statusValue) {
                            'accepted' => 'bg-emerald-100 text-emerald-700',
                            'rejected' => 'bg-rose-100 text-rose-700',
                            'cancelled' => 'bg-gray-100 text-gray-700',
                            'expired' => 'bg-orange-100 text-orange-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                        $parentAvatarPath = $invitation->inviterParent?->learnerProfile?->avatar_path;
                        $parentAvatarUrl = $parentAvatarPath
                            ? asset('storage/' . ltrim((string) $parentAvatarPath, '/'))
                            : null;
                        $childAvatarPath = $invitation->child?->learnerProfile?->avatar_path;
                        $childAvatarUrl = $childAvatarPath
                            ? asset('storage/' . ltrim((string) $childAvatarPath, '/'))
                            : null;
                    @endphp

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                    @if($parentAvatarUrl)
                                        <img src="{{ $parentAvatarUrl }}" alt="Inviting guardian avatar" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                    @else
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-xs font-bold text-purple-700">
                                            {{ strtoupper(substr((string) ($invitation->inviterParent?->name ?? 'P'), 0, 1)) }}
                                        </span>
                                    @endif
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invited Guardian</p>
                                        <p class="text-sm font-semibold text-gray-900">{{ $invitation->inviterParent?->name ?? 'Guardian' }}</p>
                                        <p class="text-xs text-gray-500">{{ $invitation->inviterParent?->email ?? 'No email' }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                    @if($childAvatarUrl)
                                        <img src="{{ $childAvatarUrl }}" alt="Related child avatar" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                    @else
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                            {{ strtoupper(substr((string) ($invitation->child?->name ?? 'L'), 0, 1)) }}
                                        </span>
                                    @endif
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Related Dependent</p>
                                        <p class="text-sm font-semibold text-gray-900">{{ $invitation->child?->name ?? 'Learner' }}</p>
                                        <span class="mt-1 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                            {{ $invitation->relationshipLabel() }}
                                        </span>
                                        <p class="text-xs text-gray-500">
                                            {{ $invitation->child?->email ?? 'No email' }}
                                            @if($invitation->child?->learnerProfile?->username)
                                                · {{ $invitation->child->learnerProfile->username }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($statusValue) }}
                                </span>
                                <span class="text-xs text-gray-500 whitespace-nowrap">{{ $invitation->created_at?->diffForHumans() }}</span>
                                <a href="{{ route('parent.invitations.show', $invitation) }}"
                                   class="inline-flex items-center rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
