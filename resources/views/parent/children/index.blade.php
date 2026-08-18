@extends('layouts.learner-app')

@section('title', 'My Dependents')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Gradient banner header --}}
    <div class="flex items-center justify-between p-6 text-white rounded-2xl"
         style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
        <div>
            <h1 class="text-2xl font-bold">My Dependents</h1>
            <p class="mt-1 text-sm text-white/80">Manage dependent applications and monitor approved accounts.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('parent.invitations.history') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition border bg-white/10 hover:bg-white/20 rounded-xl backdrop-blur-sm border-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 5h8m-8 5h5M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
                Invitation History
            </a>
            <a href="{{ route('parent.invitations.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition border bg-white/15 hover:bg-white/25 rounded-xl backdrop-blur-sm border-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-1a4 4 0 00-4-4h-1m-4 5H6a4 4 0 00-4 4v1h5m6-5a4 4 0 10-8 0 4 4 0 008 0zm6-3a3 3 0 10-6 0 3 3 0 006 0z"/>
                </svg>
                Invite Existing Learner
            </a>
            <a href="{{ route('parent.create-child') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition bg-white/20 hover:bg-white/30 rounded-xl backdrop-blur-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Add Dependent
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="px-4 py-3 text-sm text-green-800 border border-green-200 bg-green-50 rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="px-4 py-3 text-sm text-red-800 border border-red-200 bg-red-50 rounded-xl">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->has('verification_document'))
        <div class="px-4 py-3 text-sm text-red-800 border border-red-200 bg-red-50 rounded-xl">
            {{ $errors->first('verification_document') }}
        </div>
    @endif

    @php
        $pendingApprovalNotifications = $pendingApprovalNotifications ?? collect();
        $outgoingInvitations = $outgoingInvitations ?? collect();
    @endphp

    @if($pendingApprovalNotifications->isNotEmpty())
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Pending Enrollment Approvals</h2>
                    <p class="mt-1 text-sm text-gray-500">Unread child enrollment requests that need your review.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                    {{ $pendingApprovalNotifications->count() }} unread
                </span>
            </div>

            <div class="mt-4 space-y-3">
                @foreach($pendingApprovalNotifications as $notification)
                    @php
                        $childName = data_get($notification->data, 'child_name', 'Child');
                        $moduleTitle = data_get($notification->data, 'module_title', 'Module');
                        $actionUrl = data_get($notification->data, 'action_url');
                    @endphp

                    <div class="px-4 py-3 border border-gray-100 rounded-xl bg-gray-50/70">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $childName }} requested access to {{ $moduleTitle }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $notification->created_at?->diffForHumans() }}</p>
                            </div>

                            @if($actionUrl)
                                <a href="{{ route('learner.notifications.read', $notification->id) }}"
                                   class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                                    Review Enrollment Request
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($outgoingInvitations->isNotEmpty())
        @php
            $latestInvitation = $outgoingInvitations->first();
            $statusValue = $latestInvitation->status instanceof \App\Enums\ParentChildInvitationStatus
                ? $latestInvitation->status->value
                : (string) $latestInvitation->status;
            $statusClass = match ($statusValue) {
                'accepted' => 'bg-emerald-100 text-emerald-700',
                'rejected' => 'bg-rose-100 text-rose-700',
                'cancelled' => 'bg-gray-100 text-gray-700',
                'expired' => 'bg-orange-100 text-orange-700',
                default => 'bg-amber-100 text-amber-700',
            };
            $parentAvatarPath = auth()->user()?->learnerProfile?->avatar_path;
            $parentAvatarUrl = $parentAvatarPath
                ? asset('storage/' . ltrim((string) $parentAvatarPath, '/'))
                : null;
            $invitedChildAvatarPath = $latestInvitation->child?->learnerProfile?->avatar_path;
            $invitedChildAvatarUrl = $invitedChildAvatarPath
                ? asset('storage/' . ltrim((string) $invitedChildAvatarPath, '/'))
                : null;
            $statusDescription = match ($statusValue) {
                'accepted' => 'Accepted. Guardian-child link is active.',
                'rejected' => 'Rejected by learner.',
                'cancelled' => 'Cancelled by guardian.',
                'expired' => 'Expired with no response.',
                default => 'Pending learner response.',
            };
            $latestActivityAt = $latestInvitation->responded_at ?? $latestInvitation->updated_at ?? $latestInvitation->created_at;
            $sentAtText = $latestInvitation->created_at?->diffForHumans() ?? 'N/A';
            $latestActivityText = $latestActivityAt?->diffForHumans() ?? 'N/A';
        @endphp
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Latest Guardian Link Invitation</h2>
                    <p class="mt-1 text-sm text-gray-500">Showing your most recent invitation transaction.</p>
                </div>
                <a href="{{ route('parent.invitations.history') }}"
                   class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                    View Full History
                </a>
            </div>

            <div class="px-4 py-3 mt-4 border border-gray-100 rounded-xl bg-gray-50/70">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                {{ ucfirst($statusValue) }}
                            </span>
                            <p class="text-xs text-gray-600">{{ $statusDescription }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                            <span>Invited Guardian: <span class="font-semibold text-gray-700">{{ auth()->user()?->name ?? 'Guardian' }}</span></span>
                            <span>Related Dependent: <span class="font-semibold text-gray-700">{{ $latestInvitation->child?->name ?? 'Learner' }}</span></span>
                            <span>Sent: <span class="font-semibold text-gray-700">{{ $sentAtText }}</span></span>
                            <span>Last update: <span class="font-semibold text-gray-700">{{ $latestActivityText }}</span></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="hidden -space-x-2 sm:flex">
                            @if($parentAvatarUrl)
                                <img src="{{ $parentAvatarUrl }}" alt="Guardian avatar" class="object-cover w-8 h-8 border border-white rounded-full">
                            @else
                                <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-purple-700 bg-purple-100 border border-white rounded-full">
                                    {{ strtoupper(substr((string) auth()->user()?->name, 0, 1)) }}
                                </span>
                            @endif
                            @if($invitedChildAvatarUrl)
                                <img src="{{ $invitedChildAvatarUrl }}" alt="Invited child avatar" class="object-cover w-8 h-8 border border-white rounded-full">
                            @else
                                <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-indigo-700 bg-indigo-100 border border-white rounded-full">
                                    {{ strtoupper(substr((string) ($latestInvitation->child?->name ?? 'L'), 0, 1)) }}
                                </span>
                            @endif
                        </div>
                        <a href="{{ route('parent.invitations.show', $latestInvitation) }}"
                           class="inline-flex items-center rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50">
                            View
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Children list --}}
    @if($children->isEmpty())
        {{-- Empty state --}}
        <div class="p-12 text-center bg-white border border-gray-200 shadow-sm rounded-2xl">
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-5 rounded-full bg-purple-50">
                <svg class="w-10 h-10 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3 class="mb-2 text-xl font-semibold text-gray-900">No dependents added yet</h3>
            <p class="max-w-sm mx-auto mb-6 text-sm text-gray-500">
                Create a learning account for your dependent. You'll be able to monitor their progress and quiz results.
            </p>
            <a href="{{ route('parent.create-child') }}"
               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
               class="inline-flex items-center gap-2 text-white font-semibold py-2.5 px-6 rounded-xl hover:opacity-90 transition text-sm shadow-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Add Your First Dependent
            </a>
        </div>
    @else
        {{-- Children cards grid --}}
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @foreach($children as $child)
                @php
                    $verificationStatus = $child->pivot->verification_status ?? 'pending';
                    $isApprovedChild = $verificationStatus === 'approved';
                    $childAvatarUrl = $child->learnerProfile?->avatar_path
                        ? asset('storage/' . ltrim((string) $child->learnerProfile->avatar_path, '/'))
                        : null;
                    $canUseChat = auth()->user()?->can('access chat') ?? false;
                    $relationshipLabel = \App\Support\GuardianRelationshipTypes::label($child->pivot->relationship_type ?? null, $child->pivot->relationship_custom ?? null);
                    $relationshipStatus = $child->pivot->relationship_status ?? 'active';
                    $relationshipVerificationStatus = $child->pivot->relationship_verified_status ?? 'not_required';
                    $relationshipVerificationLabel = \App\Support\GuardianRelationshipTypes::statusLabel($relationshipVerificationStatus);
                    $relationshipVerificationNeedsAction = in_array($relationshipVerificationStatus, ['pending', 'rejected', 'resubmission_required'], true);
                    $relationshipVerificationClass = match ($relationshipVerificationStatus) {
                        'verified', 'not_required' => 'bg-emerald-100 text-emerald-700',
                        'rejected', 'revoked' => 'bg-rose-100 text-rose-700',
                        'resubmission_required' => 'bg-orange-100 text-orange-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <div class="relative overflow-hidden transition bg-white border border-gray-200 shadow-sm rounded-2xl hover:shadow-md">

                    @if($isApprovedChild && $canUseChat)
                        <button type="button"
                                onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $child->id }}, conversation_type: "direct", name: @json($child->full_name ?: $child->name) } }))'
                                class="absolute z-10 inline-flex items-center justify-center text-purple-700 border border-purple-200 rounded-lg right-4 top-4 h-9 w-9 bg-purple-50 hover:bg-purple-100"
                                title="Message {{ $child->full_name }}"
                                aria-label="Message {{ $child->full_name }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h5m-9 6l1.683-3.367A2 2 0 014 15.764V6a2 2 0 012-2h12a2 2 0 012 2v9.764a2 2 0 01-1.683 1.969L20 20l-4.317-2.267a2 2 0 00-.934-.233H6.934A2 2 0 015 18.764V20z"/>
                            </svg>
                        </button>
                    @endif

                    {{-- Card top: avatar + info + badge --}}
                    <div class="flex items-center gap-4 p-5 pr-14">
                        {{-- Avatar initials --}}
                        @if($childAvatarUrl)
                            <img src="{{ $childAvatarUrl }}"
                                 alt="{{ $child->full_name }} avatar"
                                 class="flex-shrink-0 object-cover border border-gray-200 rounded-full shadow w-14 h-14">
                        @else
                            <div class="flex items-center justify-center flex-shrink-0 text-xl font-bold text-white rounded-full shadow w-14 h-14"
                                 style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                {{ strtoupper(substr($child->first_name ?? $child->name, 0, 1)) }}{{ strtoupper(substr($child->last_name ?? '', 0, 1)) }}
                            </div>
                        @endif

                        {{-- Name & age --}}
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">{{ $child->full_name }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if($child->learnerProfile?->birthdate)
                                    {{ \Carbon\Carbon::parse($child->learnerProfile->birthdate)->age }} years old.
                                @endif
                                Added {{ $child->created_at->diffForHumans() }}
                            </p>
                            @if($child->learnerProfile?->username)
                                <p class="text-xs text-purple-600 mt-0.5">{{ $child->learnerProfile->username }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                    {{ $relationshipLabel }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                    {{ ucfirst($relationshipStatus) }}
                                </span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $relationshipVerificationClass }}">
                                    {{ $relationshipVerificationLabel }}
                                </span>
                            </div>
                        </div>

                        {{-- Consent + verification badges --}}
                        <div class="flex flex-col items-end gap-1">
                            @if(!$child->learnerProfile?->requires_parental_consent)
                                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Teen</span>
                            @endif

                            @if(!$isApprovedChild)
                                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full {{ $verificationStatus === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ ucfirst($verificationStatus) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if(!$isApprovedChild)
                        <div class="px-5 py-3 border-t border-gray-100 {{ $verificationStatus === 'rejected' ? 'bg-rose-50' : 'bg-amber-50' }}">
                            <p class="text-xs {{ $verificationStatus === 'rejected' ? 'text-rose-700' : 'text-amber-800' }}">
                                @if($verificationStatus === 'rejected')
                                    Verification needs correction.
                                    @if(!empty($child->pivot->verification_rejection_reason))
                                        Reason: {{ trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags((string) $child->pivot->verification_rejection_reason), ENT_QUOTES | ENT_HTML5, 'UTF-8')))) }}
                                    @endif
                                @else
                                    Pending verification.
                                @endif
                            </p>
                        </div>
                    @endif

                    {{-- Stats row --}}
                    <div class="grid grid-cols-3 py-3 text-center border-t border-gray-100 divide-x divide-gray-100 bg-gray-50/50">
                        <div class="px-2">
                            <p class="text-lg font-bold text-purple-700">{{ $child->moduleEnrollments()->count() }}</p>
                            <p class="text-xs text-gray-500">Modules</p>
                        </div>
                        <div class="px-2">
                            <p class="text-lg font-bold text-purple-700">{{ $child->quizAttempts()->count() }}</p>
                            <p class="text-xs text-gray-500">Quizzes</p>
                        </div>
                        <div class="px-2">
                            <p class="text-lg font-bold text-purple-700">{{ $child->achievements()->count() }}</p>
                            <p class="text-xs text-gray-500">Achievements</p>
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    @if($child->moduleEnrollments()->count() > 0)
                        @php
                            $enrolledModuleIds = $child->moduleEnrollments()
                                ->whereIn('status', ['approved', 'completed'])
                                ->pluck('module_id');

                            $totalLessons = \App\Models\Lesson::query()
                                ->whereIn('module_id', $enrolledModuleIds)
                                ->count();

                            $completedLessons = \App\Models\UserProgress::query()
                                ->where('user_id', $child->id)
                                ->whereIn('module_id', $enrolledModuleIds)
                                ->where('completed', true)
                                ->distinct('lesson_id')
                                ->count('lesson_id');

                            $pct = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;
                        @endphp
                        <div class="px-5 py-3 border-t border-gray-100">
                            <div class="flex justify-between mb-1 text-xs text-gray-500">
                                <span>Overall Progress</span>
                                <span class="font-medium text-gray-700">{{ $pct }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full transition-all"
                                     style="width: {{ $pct }}%; background: linear-gradient(90deg, #A30EB2, #3B0CB1);">
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
                        @if($isApprovedChild)
                            <div class="flex items-center gap-2">
                                @if($relationshipVerificationNeedsAction)
                                    <a href="{{ route('parent.relationship-verifications.show', $child->pivot->id) }}"
                                       class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 hover:text-amber-900">
                                        Review Verification
                                    </a>
                                @endif
                                <a href="{{ route('parent.children.show', $child->id) }}"
                                   class="inline-flex items-center gap-1.5 text-sm font-medium text-purple-700 hover:text-purple-900">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    View Dependent Dashboard
                                </a>
                            </div>

                            <span class="text-sm text-gray-400">
                                {{ $child->quizAttempts()->count() > 0 ? 'Quiz activity available' : 'No quiz data yet' }}
                            </span>
                        @else
                            @if($verificationStatus === 'rejected')
                                <form method="POST"
                                      action="{{ route('parent.children.verification.resubmit', $child) }}"
                                      enctype="multipart/form-data"
                                      class="w-full space-y-2">
                                    @csrf
                                    <label for="verification_document_{{ $child->id }}" class="block text-xs font-medium text-gray-700">
                                        Upload corrected PSA document
                                    </label>
                                    <input id="verification_document_{{ $child->id }}"
                                           name="verification_document"
                                           type="file"
                                           required
                                           accept=".jpg,.jpeg,.png,.pdf"
                                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-xs text-gray-900 file:mr-2 file:rounded-md file:border-0 file:bg-purple-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-purple-700">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs text-gray-500">JPG, PNG, or PDF up to 5MB.</span>
                                        <button type="submit"
                                                class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-white"
                                                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                            Resubmit Child Verification
                                        </button>
                                    </div>
                                </form>
                            @else
                                <span class="text-sm text-gray-500">Pending verification</span>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
