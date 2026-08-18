@extends('layouts.admin')
@section('title', 'Relationship Management')
@section('page-title', 'Relationship Management')

@section('content')
<div class="space-y-5">
    <div class="rounded-2xl bg-white border border-gray-200 shadow-theme-xs p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Guardian-Dependent Relationships</h2>
                <p class="text-sm text-gray-500">Manage guardian-dependent links, verification state, and transparency visibility.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                Back to Users
            </a>
        </div>

        <form method="GET" action="{{ route('admin.users.relationships.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search guardian/child name, email, or ID"
                class="md:col-span-2 px-3 py-2 rounded-lg border border-gray-200 text-sm"
            >
            <select name="verification" class="px-3 py-2 rounded-lg border border-gray-200 text-sm">
                <option value="all" @selected(($filters['verification'] ?? 'all') === 'all')>All verification</option>
                <option value="verified" @selected(($filters['verification'] ?? 'all') === 'verified')>Verified</option>
                <option value="unverified" @selected(($filters['verification'] ?? 'all') === 'unverified')>Unverified</option>
            </select>
            <select name="relationship_type" class="px-3 py-2 rounded-lg border border-gray-200 text-sm">
                <option value="all" @selected(($filters['relationship_type'] ?? 'all') === 'all')>All relationships</option>
                @foreach($relationshipOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['relationship_type'] ?? 'all') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="per_page" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                    @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold transition-colors">Apply</button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white border border-gray-200 shadow-theme-xs p-5">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Attach New Relationship</h3>

        <form method="POST" action="{{ route('admin.users.relationships.attach') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3"
              x-data="{ relationshipType: @js(old('relationship_type', '')) }">
            @csrf
            <label class="block text-xs font-medium text-gray-600">
                Guardian Account
                <select name="parent_user_id" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" required>
                    <option value="">Select guardian</option>
                    @foreach($parentCandidates as $candidate)
                        <option value="{{ $candidate->id }}" @selected((int) old('parent_user_id') === $candidate->id)>
                            #{{ $candidate->id }} - {{ $candidate->name }} ({{ $candidate->email }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block text-xs font-medium text-gray-600">
                Guardian Relationship
                <select name="relationship_type" x-model="relationshipType" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" required>
                    <option value="">Select relationship</option>
                    @foreach($relationshipOptions as $value => $label)
                        @continue($value === \App\Support\GuardianRelationshipTypes::LEGACY_PARENT)
                        <option value="{{ $value }}" @selected(old('relationship_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-xs font-medium text-gray-600" x-show="relationshipType === 'other'" x-cloak>
                Custom Relationship
                <input name="relationship_custom" value="{{ old('relationship_custom') }}" maxlength="120" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
            </label>

            <label class="block text-xs font-medium text-gray-600 md:col-span-2">
                Notes
                <textarea name="relationship_notes" rows="2" maxlength="1000" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">{{ old('relationship_notes') }}</textarea>
            </label>

            <label class="block text-xs font-medium text-gray-600">
                Dependent Account
                <select name="child_user_id" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" required>
                    <option value="">Select child</option>
                    @foreach($childCandidates as $candidate)
                        <option value="{{ $candidate->id }}" @selected((int) old('child_user_id') === $candidate->id)>
                            #{{ $candidate->id }} - {{ $candidate->name }} ({{ $candidate->email }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-gray-600 md:col-span-2">
                <input type="checkbox" name="is_verified" value="1" class="rounded border-gray-300" @checked(old('is_verified'))>
                Mark relationship as verified on creation
            </label>

            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold transition-colors">Attach Relationship</button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white border border-gray-200 shadow-theme-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Existing Relationships</h3>
        </div>

        @if($relationships->isEmpty())
            <div class="px-5 py-10 text-sm text-gray-500">No relationships found for the current filters.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Guardian</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Dependent</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Relationship</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Verification</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($relationships as $relationship)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div class="font-semibold text-gray-900">{{ $relationship->parent?->name ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">#{{ $relationship->parent_user_id }} • {{ $relationship->parent?->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div class="font-semibold text-gray-900">{{ $relationship->child?->name ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">#{{ $relationship->child_user_id }} • {{ $relationship->child?->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                                        {{ $relationship->relationshipLabel() }}
                                    </span>
                                    <div class="mt-1 text-xs text-gray-500">{{ ucfirst($relationship->relationship_status ?? 'active') }}</div>
                                    @if($relationship->requiresRelationshipVerification())
                                        <a href="{{ route('admin.parent-verifications.relationships.show', $relationship) }}" class="mt-1 inline-flex text-xs font-semibold text-purple-700 hover:text-purple-900">
                                            {{ $relationship->relationshipVerificationLabel() }}
                                        </a>
                                    @else
                                        <div class="mt-1 text-xs text-gray-500">{{ $relationship->relationshipVerificationLabel() }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $relationship->relationship_verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $relationship->relationship_verified_at ? 'Verified' : 'Unverified' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.users.relationships.verification') }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="parent_user_id" value="{{ $relationship->parent_user_id }}">
                                            <input type="hidden" name="child_user_id" value="{{ $relationship->child_user_id }}">
                                            <input type="hidden" name="is_verified" value="{{ $relationship->relationship_verified_at ? 0 : 1 }}">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Toggle Verification</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.relationships.detach') }}" onsubmit="return confirm('Detach this guardian-dependent relationship?')">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="parent_user_id" value="{{ $relationship->parent_user_id }}">
                                            <input type="hidden" name="child_user_id" value="{{ $relationship->child_user_id }}">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-100 text-rose-700 text-xs font-semibold hover:bg-rose-200 transition-colors">Detach</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="px-5 py-3 border-t border-gray-100">
            {{ $relationships->links() }}
        </div>
    </div>
</div>
@endsection
