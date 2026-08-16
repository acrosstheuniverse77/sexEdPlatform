@extends($contentPanelLayout ?? 'layouts.instructor-app')

@php
    $isAdminPanel = ($isContentAdminPanel ?? false) === true;
    $ownershipRestrictionTooltip = 'Instructor-owned content is read-only in the admin panel.';
    $moduleOwnerType = strtolower(trim((string) ($enrollment->module->content_owner_type ?? '')));
    $enrollmentStatus = (string) ($enrollment->status->value ?? $enrollment->status);

    $learnerAvatarPath = $enrollment->user?->learnerProfile?->avatar_path;
    $learnerAvatarUrl = null;
    if (!empty($learnerAvatarPath)) {
        if (\Illuminate\Support\Str::startsWith($learnerAvatarPath, ['http://', 'https://', '//'])) {
            $learnerAvatarUrl = $learnerAvatarPath;
        } else {
            $learnerAvatarUrl = asset('storage/' . ltrim(str_replace('storage/', '', (string) $learnerAvatarPath), '/'));
        }
    }

    $statusBadgeClasses = match ($enrollmentStatus) {
        'approved' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'pending', 'pending_parent_approval' => 'bg-amber-100 text-amber-700 border-amber-200',
        default => 'bg-rose-100 text-rose-700 border-rose-200',
    };
    $statusLabel = match ($enrollmentStatus) {
        'pending_parent_approval' => 'Pending Guardian Approval',
        default => ucfirst(str_replace('_', ' ', $enrollmentStatus)),
    };

    if (!in_array($moduleOwnerType, ['admin', 'platform', 'instructor'], true)) {
        $moduleCreator = $enrollment->module->creator;
        $moduleOwnerType = (($moduleCreator?->isAdmin() ?? false) || strtolower((string) ($moduleCreator?->role ?? '')) === 'admin')
            ? 'admin'
            : 'instructor';
    }

    $isRestrictedAdminMutation = $isAdminPanel && !in_array($moduleOwnerType, ['admin', 'platform'], true);
@endphp

@section('content')
    <div x-data="{
        confirmModalOpen: false,
        confirmForm: null,
        confirmMessage: '',
        confirmButtonLabel: 'Confirm',
        confirmTone: 'approve',
        openDecisionConfirm(form, message, label, tone = 'approve') {
            this.confirmForm = form;
            this.confirmMessage = message;
            this.confirmButtonLabel = label;
            this.confirmTone = tone;
            this.confirmModalOpen = true;
        },
        closeDecisionConfirm() {
            this.confirmModalOpen = false;
            this.confirmForm = null;
            this.confirmMessage = '';
            this.confirmButtonLabel = 'Confirm';
            this.confirmTone = 'approve';
        },
        submitDecisionConfirm() {
            if (this.confirmForm) {
                this.confirmForm.submit();
            }
        }
    }" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content - Learner Profile -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-center gap-3">
                                    @if($learnerAvatarUrl)
                                        <img src="{{ $learnerAvatarUrl }}" alt="Learner avatar" class="h-14 w-14 rounded-full border border-gray-200 object-cover">
                                    @else
                                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700">
                                            {{ strtoupper(substr((string) ($enrollment->user->name ?? 'L'), 0, 1)) }}
                                        </span>
                                    @endif

                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Learner Information</h3>
                                        <p class="text-sm text-gray-500">Profile, enrollment context, and learning activity snapshot.</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusBadgeClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">
                                        {{ $enrollment->module->title }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Full Name</label>
                                    <p class="text-gray-900">{{ $enrollment->user->name }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Username</label>
                                    <p class="text-gray-900">
                                        @if($enrollment->user->learnerProfile)
                                            {{ $enrollment->user->learnerProfile->username }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Email</label>
                                    <p class="text-gray-900">{{ $enrollment->user->email }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Age</label>
                                    <p class="text-gray-900">
                                        @if($enrollment->user->learnerProfile)
                                            {{ $enrollment->user->learnerProfile->getAge() }} years old
                                            @php
                                                $age = $enrollment->user->learnerProfile->getAge();
                                                $moduleMinAge = $enrollment->module->min_age;
                                                $moduleMaxAge = $enrollment->module->max_age;
                                                $isAgeAppropriate = $age >= $moduleMinAge && $age <= $moduleMaxAge;
                                            @endphp
                                            @if($isAgeAppropriate)
                                                <span class="ml-2 text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded">✓ Age-appropriate</span>
                                            @else
                                                <span class="ml-2 text-xs bg-rose-100 text-rose-800 px-2 py-1 rounded">⚠ Outside target age range</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Gender</label>
                                    <p class="text-gray-900">
                                        @if($enrollment->user->learnerProfile)
                                            {{ ucfirst(str_replace('_', ' ', $enrollment->user->learnerProfile->gender)) }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Account Created</label>
                                    <p class="text-gray-900">{{ $enrollment->user->created_at->format('M d, Y') }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-sm font-medium text-gray-500">Location</label>
                                    <p class="text-gray-900">
                                        @if($enrollment->user->learnerProfile)
                                            @php
                                                $profile = $enrollment->user->learnerProfile;
                                                $barangayName = '';
                                                $cityName = '';
                                                
                                                // Get barangay name
                                                if ($profile->barangay && is_object($profile->barangay)) {
                                                    $barangayName = $profile->barangay->name;
                                                } elseif (is_string($profile->barangay)) {
                                                    $barangayName = $profile->barangay;
                                                }
                                                
                                                // Get city name
                                                if ($profile->city) {
                                                    $cityName = $profile->city->name;
                                                }
                                            @endphp
                                            
                                            @if($barangayName && $cityName)
                                                {{ $barangayName }}, {{ $cityName }}
                                            @elseif($cityName)
                                                {{ $cityName }}
                                            @else
                                                -
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V9a2 2 0 00-2-2h-4m1 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v15h5m10 0H7"/>
                                </svg>
                                Guardian-Child Connection
                            </h3>

                            @if(!empty($parentConnections))
                                <div class="space-y-3">
                                    @foreach($parentConnections as $connection)
                                        @php
                                            $verificationTone = match ((string) ($connection['verification_status'] ?? 'pending')) {
                                                'approved' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                                'rejected' => 'bg-rose-100 text-rose-700 border-rose-200',
                                                default => 'bg-amber-100 text-amber-700 border-amber-200',
                                            };
                                        @endphp
                                        <article class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="text-sm font-semibold text-gray-900">{{ $connection['parent_name'] }}</p>
                                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $verificationTone }}">
                                                    {{ ucfirst((string) ($connection['verification_status'] ?? 'pending')) }}
                                                </span>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-700 border border-gray-200">
                                                    {{ !empty($connection['can_view_progress']) ? 'Can view progress' : 'No progress access' }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-700 border border-gray-200">
                                                    {{ !empty($connection['can_view_quiz_answers']) ? 'Can view quiz answers' : 'No quiz-answer access' }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-700 border border-gray-200">
                                                    {{ !empty($connection['can_approve_content']) ? 'Can approve content' : 'No approval access' }}
                                                </span>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No linked guardian account was found for this learner profile.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Module Requested -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                Module Requested
                            </h3>

                            <div class="flex gap-4">
                                @if($enrollment->module->thumbnail)
                                    <img src="{{ asset('storage/' . $enrollment->module->thumbnail) }}" 
                                         alt="{{ $enrollment->module->title }}" 
                                         class="w-24 h-24 object-cover rounded">
                                @endif
                                <div class="flex-1">
                                    <h4 class="font-semibold text-lg">{{ $enrollment->module->title }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ Str::limit($enrollment->module->description, 150) }}</p>
                                    <div class="flex gap-3 mt-2 text-xs text-gray-500">
                                        <span>Age: {{ $enrollment->module->min_age }}-{{ $enrollment->module->max_age }} years</span>
                                        <span>•</span>
                                        <span>{{ $moduleLessonCount ?? 0 }} lessons</span>
                                        <span>•</span>
                                        <span>Requested {{ $enrollment->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Learning History -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Recent Learning Activity
                            </h3>

                            @if($recentEnrollments->count() > 0)
                                <div class="space-y-3">
                                    @foreach($recentEnrollments as $recent)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                            <div>
                                                <p class="font-medium text-sm">{{ $recent->module->title }}</p>
                                                <p class="text-xs text-gray-500">
                                                    Enrolled {{ $recent->enrolled_at?->diffForHumans() ?? $recent->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                @if($recent->completed_at)
                                                    <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded">Completed</span>
                                                @else
                                                    <span class="text-xs bg-brand-100 text-brand-800 px-2 py-1 rounded">{{ $recent->completion_percentage }}% Progress</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500 text-center py-4">No previous enrollments</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sidebar - Stats & Actions -->
                <div class="space-y-6">
                    <!-- Quick Stats -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Learner Statistics</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm text-gray-500">Total Enrollments</label>
                                    <p class="text-2xl font-bold text-gray-900">{{ $totalEnrollments }}</p>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Completed Modules</label>
                                    <p class="text-2xl font-bold text-emerald-600">{{ $completedModules }}</p>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Completion Rate</label>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                                            <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ $completionRate }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold">{{ $completionRate }}%</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Current Module Progress</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                                            <div class="bg-brand-600 h-2 rounded-full" style="width: {{ $moduleProgressPercent }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold">{{ $moduleProgressPercent }}%</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ $completedLessonCount }} of {{ $moduleLessonCount }} lessons completed</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    @if(in_array($enrollmentStatus, ['pending', 'pending_parent_approval'], true))
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">Review Decision</h3>
                                
                                <div class="space-y-3">
                                    <form method="POST" action="{{ route($contentRoutePrefix . '.enrollments.approve', $enrollment) }}"
                                          @submit.prevent="@if($isRestrictedAdminMutation) false @else openDecisionConfirm($event.target, 'Approve this enrollment request?', 'Approve Enrollment', 'approve') @endif">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                @if($isRestrictedAdminMutation) disabled @endif
                                                title="{{ $isRestrictedAdminMutation ? $ownershipRestrictionTooltip : 'Approve enrollment' }}"
                                                class="w-full text-white font-semibold py-3 px-4 rounded-lg transition {{ $isRestrictedAdminMutation ? 'cursor-not-allowed opacity-50 bg-emerald-600' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                                            ✓ Approve Enrollment
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route($contentRoutePrefix . '.enrollments.reject', $enrollment) }}"
                                          @submit.prevent="@if($isRestrictedAdminMutation) false @else openDecisionConfirm($event.target, 'Reject this enrollment request? The learner will be notified.', 'Reject Request', 'reject') @endif">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                @if($isRestrictedAdminMutation) disabled @endif
                                                title="{{ $isRestrictedAdminMutation ? $ownershipRestrictionTooltip : 'Reject enrollment' }}"
                                                class="w-full text-white font-semibold py-3 px-4 rounded-lg transition {{ $isRestrictedAdminMutation ? 'cursor-not-allowed opacity-50 bg-rose-600' : 'bg-rose-600 hover:bg-rose-700' }}">
                                            ✗ Reject Request
                                        </button>
                                    </form>

                                    <a href="{{ route($contentRoutePrefix . '.enrollments.index') }}" 
                                       class="block w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-4 rounded-lg transition">
                                        ← Back to Requests
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="text-center">
                                    @if($enrollmentStatus === 'approved')
                                        <div class="bg-emerald-50 border-2 border-emerald-200 rounded-lg p-4">
                                            <svg class="w-12 h-12 text-emerald-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <p class="text-emerald-800 font-semibold">Already Approved</p>
                                            <p class="text-xs text-emerald-600 mt-1">{{ $enrollment->enrolled_at?->format('M d, Y') ?? 'Date unavailable' }}</p>
                                        </div>
                                    @else
                                        <div class="bg-rose-50 border-2 border-rose-200 rounded-lg p-4">
                                            <svg class="w-12 h-12 text-rose-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <p class="text-rose-800 font-semibold">Request Rejected</p>
                                        </div>
                                    @endif

                                    <a href="{{ route($contentRoutePrefix . '.enrollments.index') }}" 
                                       class="block w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-4 rounded-lg transition mt-4">
                                        ← Back to Requests
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div x-show="confirmModalOpen" x-cloak class="fixed inset-0 z-40 bg-gray-900/60" @click="closeDecisionConfirm()"></div>
            <div x-show="confirmModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl" @click.stop>
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Action</h3>
                    <p class="mt-2 text-sm text-gray-600" x-text="confirmMessage"></p>
                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button" @click="closeDecisionConfirm()" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Cancel</button>
                        <button type="button"
                                @click="submitDecisionConfirm()"
                                :class="confirmTone === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-white"
                                x-text="confirmButtonLabel"></button>
                    </div>
                </div>
            </div>
    </div>
@endsection
