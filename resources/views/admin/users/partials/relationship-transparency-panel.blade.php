@php
    $canUseChat = auth()->user()?->can('access chat') ?? false;
@endphp

<h3 class="mb-4 text-sm font-semibold tracking-wide text-gray-700 uppercase">Guardian-Dependent Information</h3>

@if($parentRelationships->isNotEmpty())
    <div class="mb-4 space-y-3">
        @foreach($parentRelationships as $relationship)
            @php
                $parent = $relationship->parent;
                $parentAvatarPath = $parent?->learnerProfile?->avatar_path;
                $parentAvatarUrl = $parentAvatarPath
                    ? asset('storage/' . ltrim((string) $parentAvatarPath, '/'))
                    : null;
                $parentBirthdate = $parent?->birthdate ?? $parent?->learnerProfile?->birthdate;
                $parentAge = $parentBirthdate
                    ? \Carbon\Carbon::parse($parentBirthdate)->age
                    : null;
            @endphp

            <div class="p-4 border rounded-xl border-sky-200 bg-sky-50">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start min-w-0 gap-3">
                        @if($parentAvatarUrl)
                            <img src="{{ $parentAvatarUrl }}" alt="{{ $parent?->name ?? 'Guardian' }} avatar" class="object-cover w-10 h-10 border rounded-full border-sky-200">
                        @else
                            <div class="flex items-center justify-center w-10 h-10 text-xs font-bold border rounded-full bg-sky-100 text-sky-700 border-sky-200">
                                {{ strtoupper(substr((string) ($parent?->name ?? 'P'), 0, 1)) }}
                            </div>
                        @endif

                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Guardian Account</p>
                            <p class="text-sm font-semibold text-gray-900 break-words">{{ $parent?->name ?? 'Unknown Guardian' }}</p>
                            <p class="text-xs text-gray-600 break-words">{{ $parent?->email }}</p>
                            @if(!is_null($parentAge))
                                <p class="text-xs text-gray-600">{{ $parentAge }} years old</p>
                            @endif
                            <p class="text-[11px] text-indigo-700 mt-1">Relationship: {{ $relationship->relationshipLabel() }}</p>
                            <p class="text-[11px] text-gray-500 mt-1">Verification: {{ $relationship->relationship_verified_at ? 'Verified' : 'Unverified' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 shrink-0">
                        @if($canUseChat && $parent)
                            <button type="button"
                                    onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $parent->id }}, conversation_type: "direct", name: @json($parent->name) } }))'
                                    class="inline-flex items-center justify-center bg-white border rounded-lg h-9 w-9 border-sky-200 text-sky-700 hover:bg-sky-100"
                                    title="Message {{ $parent->name }}"
                                    aria-label="Message {{ $parent->name }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-8 8 3.7-3H19a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        @endif

                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $relationship->relationship_verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $relationship->relationship_verified_at ? 'Verified' : 'Unverified' }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($childRelationships->isNotEmpty())
    <div class="mb-4 space-y-3">
        @foreach($childRelationships as $relationship)
            @php
                $child = $relationship->child;
                $childAvatarPath = $child?->learnerProfile?->avatar_path;
                $childAvatarUrl = $childAvatarPath
                    ? asset('storage/' . ltrim((string) $childAvatarPath, '/'))
                    : null;
                $childBirthdate = $child?->birthdate ?? $child?->learnerProfile?->birthdate;
                $childAge = $childBirthdate
                    ? \Carbon\Carbon::parse($childBirthdate)->age
                    : null;
            @endphp

            <div class="p-4 border rounded-xl border-emerald-200 bg-emerald-50">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start min-w-0 gap-3">
                        @if($childAvatarUrl)
                            <img src="{{ $childAvatarUrl }}" alt="{{ $child?->name ?? 'Child' }} avatar" class="object-cover w-10 h-10 border rounded-full border-emerald-200">
                        @else
                            <div class="flex items-center justify-center w-10 h-10 text-xs font-bold border rounded-full bg-emerald-100 text-emerald-700 border-emerald-200">
                                {{ strtoupper(substr((string) ($child?->name ?? 'C'), 0, 1)) }}
                            </div>
                        @endif

                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Child Account</p>
                            <p class="text-sm font-semibold text-gray-900 break-words">{{ $child?->name ?? 'Unknown Child' }}</p>
                            <p class="text-xs text-gray-600 break-words">{{ $child?->email }}</p>
                            @if(!is_null($childAge))
                                <p class="text-xs text-gray-600">{{ $childAge }} years old</p>
                            @endif
                            <p class="text-[11px] text-indigo-700 mt-1">Relationship: {{ $relationship->relationshipLabel() }}</p>
                            <p class="text-[11px] text-gray-500 mt-1">Verification: {{ $relationship->relationship_verified_at ? 'Verified' : 'Unverified' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 shrink-0">
                        @if($canUseChat && $child)
                            <button type="button"
                                    onclick='window.dispatchEvent(new CustomEvent("open-global-chat", { detail: { target_user_id: {{ (int) $child->id }}, conversation_type: "direct", name: @json($child->name) } }))'
                                    class="inline-flex items-center justify-center bg-white border rounded-lg h-9 w-9 border-emerald-200 text-emerald-700 hover:bg-emerald-100"
                                    title="Message {{ $child->name }}"
                                    aria-label="Message {{ $child->name }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-8 8 3.7-3H19a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        @endif

                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $relationship->relationship_verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $relationship->relationship_verified_at ? 'Verified' : 'Unverified' }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
