@extends('layouts.learner-app')

@section('title', 'Guardian Invitations')

@section('content')
@php
    $totalOutgoingInvitations = $totalOutgoingInvitations ?? $outgoingInvitations->count();
    $relationshipOptions = \App\Support\GuardianRelationshipTypes::options();
    $verificationRequiredTypes = array_values(array_filter(array_keys($relationshipOptions), fn ($type) => \App\Support\GuardianRelationshipTypes::requiresVerification($type)));
    $relationshipDocumentTypeMap = collect(array_keys($relationshipOptions))
        ->mapWithKeys(fn ($type) => [$type => \App\Support\GuardianRelationshipTypes::documentTypeOptions($type)])
        ->all();
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <div class="rounded-2xl p-6 text-white"
         style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Guardian Invitation Center</h1>
                <p class="text-white/80 text-sm mt-1">Send invitations and track recent guardian-link activity.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('parent.invitations.history') }}"
                   class="inline-flex items-center rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25 border border-white/20">
                    Full History
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
        <h2 class="text-lg font-semibold text-gray-900">Send New Invitation</h2>
        <p class="text-sm text-gray-500 mt-1">Enter the learner's username or email address and define your guardian relationship.</p>

        <form method="POST" action="{{ route('parent.invitations.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3"
              x-data="{ relationshipType: @js(old('relationship_type', '')), verificationRequiredTypes: @js($verificationRequiredTypes), documentTypeMap: @js($relationshipDocumentTypeMap), relationshipDocumentName: 'No file selected', supportingDocumentName: 'No file selected' }">
            @csrf
            <div>
                <label for="identifier" class="block text-sm font-medium text-gray-700 mb-1">Learner Username or Email</label>
                <input id="identifier"
                       name="identifier"
                       type="text"
                       required
                       value="{{ old('identifier') }}"
                       placeholder="e.g. learnerusername or learner@email.com"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                @error('identifier')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="relationship_type" class="block text-sm font-medium text-gray-700 mb-1">Guardian Relationship</label>
                    <select id="relationship_type"
                            name="relationship_type"
                            required
                            x-model="relationshipType"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                        <option value="">Select relationship</option>
                        @foreach($relationshipOptions as $value => $label)
                            @continue($value === \App\Support\GuardianRelationshipTypes::LEGACY_PARENT)
                            <option value="{{ $value }}" @selected(old('relationship_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('relationship_type')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p x-show="verificationRequiredTypes.includes(relationshipType)" x-cloak class="mt-1 text-xs text-amber-700">
                        Supporting documentation is required before this relationship can be reviewed.
                    </p>
                </div>

                <div x-show="relationshipType === 'other'" x-cloak>
                    <label for="relationship_custom" class="block text-sm font-medium text-gray-700 mb-1">Custom Relationship</label>
                    <input id="relationship_custom"
                           name="relationship_custom"
                           type="text"
                           maxlength="120"
                           value="{{ old('relationship_custom') }}"
                           placeholder="Specify relationship"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                    @error('relationship_custom')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div x-show="verificationRequiredTypes.includes(relationshipType)" x-cloak class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-100 bg-gradient-to-r from-amber-50 to-purple-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Required verification</p>
                    <h3 class="mt-1 text-base font-semibold text-purple-950">Relationship documents</h3>
                    <p class="mt-1 text-xs text-gray-600">Upload proof for legal, adoption, foster, or court-appointed relationships. Admins will review it before approval.</p>
                </div>

                <div class="grid gap-3 p-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Document Type
                        <select name="relationship_document_type"
                                :required="verificationRequiredTypes.includes(relationshipType)"
                                class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                            <option value="">Select document</option>
                            <template x-for="(label, value) in (documentTypeMap[relationshipType] || {})" :key="value">
                                <option :value="value" x-text="label" :selected="value === @js(old('relationship_document_type'))"></option>
                            </template>
                        </select>
                        @error('relationship_document_type')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="block text-sm font-medium text-gray-700">
                        Required Document
                        <label class="mt-1 flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-purple-200 bg-purple-50/50 px-3 transition hover:border-purple-400 hover:bg-purple-50 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-100">
                            <input name="relationship_document"
                                   type="file"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp"
                                   :required="verificationRequiredTypes.includes(relationshipType)"
                                   class="sr-only"
                                   @change="relationshipDocumentName = $event.target.files[0]?.name || 'No file selected'">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-purple-700 ring-1 ring-purple-100">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0 4 4m-4-4-4 4M5 16.5v1.75A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25V16.5" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-purple-950">Choose file</span>
                            <span class="max-w-[45%] truncate text-xs font-medium text-gray-600" x-text="relationshipDocumentName"></span>
                        </label>
                        @error('relationship_document')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="block text-sm font-medium text-gray-700 sm:col-span-2">
                        Optional Supporting Document
                        <label class="mt-1 flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 transition hover:border-purple-300 hover:bg-purple-50/60 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-100">
                            <input name="relationship_supporting_document" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only" @change="supportingDocumentName = $event.target.files[0]?.name || 'No file selected'">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-gray-600 ring-1 ring-gray-100">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0 4 4m-4-4-4 4M5 16.5v1.75A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25V16.5" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900">Choose file</span>
                            <span class="max-w-[45%] truncate text-xs font-medium text-gray-600" x-text="supportingDocumentName"></span>
                        </label>
                        @error('relationship_supporting_document')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <input type="checkbox" name="confirm_relationship_verification" value="1" :required="verificationRequiredTypes.includes(relationshipType)" class="mt-0.5 rounded border-amber-300 text-purple-700 focus:ring-purple-500" @checked(old('confirm_relationship_verification'))>
                            <span>I confirm these documents support the requested relationship and should be submitted for administrative review.</span>
                        </label>
                        @error('confirm_relationship_verification')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message (optional)</label>
                <textarea id="message"
                          name="message"
                          rows="3"
                          maxlength="500"
                          placeholder="Add a short context for the learner"
                          class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                    Send Invitation
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">Recent Invitations</h2>
            <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">
                {{ $totalOutgoingInvitations }} total
            </span>
        </div>

        @if($outgoingInvitations->isEmpty())
            <p class="text-sm text-gray-500 mt-3">No invitations sent yet.</p>
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
                        $childAvatarPath = $invitation->child?->learnerProfile?->avatar_path;
                        $childAvatarUrl = $childAvatarPath
                            ? asset('storage/' . ltrim((string) $childAvatarPath, '/'))
                            : null;
                    @endphp

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex items-center gap-3">
                                @if($childAvatarUrl)
                                    <img src="{{ $childAvatarUrl }}" alt="Invited learner avatar" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                @else
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                        {{ strtoupper(substr((string) ($invitation->child?->name ?? 'L'), 0, 1)) }}
                                    </span>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $invitation->child?->name ?? 'Learner' }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $invitation->child?->email ?? 'No email' }}
                                        @if($invitation->child?->learnerProfile?->username)
                                            · {{ $invitation->child->learnerProfile->username }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">Sent {{ $invitation->created_at?->diffForHumans() }}</p>
                                    <span class="mt-2 inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                        {{ $invitation->relationshipLabel() }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($statusValue) }}
                                </span>
                                <a href="{{ route('parent.invitations.show', $invitation) }}"
                                   class="inline-flex items-center rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($totalOutgoingInvitations > $outgoingInvitations->count())
                <div class="mt-4 flex justify-end">
                    <a href="{{ route('parent.invitations.history') }}"
                       class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100">
                        View Full History
                    </a>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
