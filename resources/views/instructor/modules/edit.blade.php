@extends($contentPanelLayout ?? 'layouts.instructor-app')

@section('content')
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if(($isRestricted ?? false) === true)
                        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
                            <p class="text-sm font-semibold text-rose-900">Module actions are temporarily restricted</p>
                            <p class="mt-1 text-xs text-rose-700">{{ $restrictionMessage }}</p>
                            <p class="mt-1 text-xs text-rose-700">Restriction ends: {{ optional($restrictionProfile?->restriction_ends_at)->toDayDateTimeString() ?? 'until further notice' }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route($contentRoutePrefix . '.modules.update', $module) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" value="{{ old('title', $module->title) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" rows="3" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description', $module->description) }}</textarea>
                            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Thumbnail Image</label>
                            @if($module->thumbnail)
                                <img src="{{ asset('storage/' . $module->thumbnail) }}" alt="Current thumbnail" class="mb-2 h-32 rounded">
                            @endif
                            <input type="file" name="thumbnail" accept="image/*"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                            <p class="mt-1 text-sm text-gray-500">Leave empty to keep current image</p>
                            @error('thumbnail')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @php
                            $currentCategories = old('age_brackets', $module->learnerCategoryKeys());
                        @endphp

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Eligible Learner Categories <span class="text-red-500">*</span></label>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                @foreach(\App\Models\Module::learnerCategoryLabelsMap() as $category => $label)
                                    <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm">
                                        <input type="checkbox" name="age_brackets[]" value="{{ $category }}" @checked(in_array($category, $currentCategories, true)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Select at least one learner category this module is designed for</p>
                            @error('age_brackets')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('age_brackets.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Enrollment Mode <span class="text-red-500">*</span></label>
                            <div class="space-y-3">
                                <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="enrollment_mode" value="auto" 
                                        {{ old('enrollment_mode', $module->enrollment_mode ?? 'auto') === 'auto' ? 'checked' : '' }}
                                        class="mt-1 h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300">
                                    <div class="ml-3">
                                        <span class="font-medium text-gray-900">Open Enrollment (Auto-approve)</span>
                                        <p class="text-sm text-gray-500">Learners can enroll immediately and access module content right away</p>
                                    </div>
                                </label>
                                <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="enrollment_mode" value="manual" 
                                        {{ old('enrollment_mode', $module->enrollment_mode) === 'manual' ? 'checked' : '' }}
                                        class="mt-1 h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300">
                                    <div class="ml-3">
                                        <span class="font-medium text-gray-900">Manual Approval (Gated Access)</span>
                                        <p class="text-sm text-gray-500">You must review and approve each enrollment request before learners can access content</p>
                                    </div>
                                </label>
                            </div>
                            @error('enrollment_mode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Access Type <span class="text-red-500">*</span></label>
                            <select name="access_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                <option value="free" {{ old('access_type', $module->access_type ?? 'free') === 'free' ? 'selected' : '' }}>Free</option>
                                <option value="paid" {{ old('access_type', $module->access_type) === 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                            @error('access_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price Amount (PHP)</label>
                                <input type="number" name="price_amount" min="0.01" step="0.01" value="{{ old('price_amount', $module->price_amount) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @error('price_amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price Currency</label>
                                <input type="text" name="price_currency" value="{{ old('price_currency', $module->price_currency ?? 'PHP') }}" maxlength="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @error('price_currency')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @if(!empty($effectiveCommissionPolicy))
                            <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
                                <p class="text-sm font-semibold text-indigo-900">
                                    Platform commission currently applied to your paid modules: {{ number_format((float) $effectiveCommissionPolicy['commission_percent'], 2) }}%
                                </p>
                                <p class="mt-1 text-xs text-indigo-800">
                                    Estimated net earnings per sale: Price - (Price x {{ number_format((float) $effectiveCommissionPolicy['commission_percent'], 2) }}%).
                                </p>
                            </div>
                        @endif

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700">Enrollment Limit</label>
                            <input type="number" name="enrollment_limit" min="1" max="20" value="{{ old('enrollment_limit', $module->enrollment_limit) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Leave empty for unlimited">
                            <p class="mt-1 text-xs text-gray-500">Temporary cap: up to 20 learners per module.</p>
                            @error('enrollment_limit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Duration</label>
                            <div class="mt-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm text-gray-600">
                                {{ $module->lessons()->sum('duration') ?? 0 }} minutes (auto-calculated from lessons)
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Duration is automatically calculated based on lesson durations</p>
                        </div>

                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                            @if(($isContentAdminPanel ?? false) === true)
                                <p class="text-sm font-semibold text-amber-900">Platform module lifecycle control</p>
                                <p class="mt-1 text-xs text-amber-800">Choose whether this module should be published, saved as draft, or archived.</p>
                            @else
                                <p class="text-sm font-semibold text-amber-900">Publication is now admin-governed</p>
                                <p class="mt-1 text-xs text-amber-800">Instructor edits stay in authoring until you submit the full module package for review.</p>
                            @endif
                        </div>

                        @if(($isContentAdminPanel ?? false) === true)
                            @php
                                $adminStatus = old('action', $module->trashed() ? 'archive' : ($module->is_published ? 'publish' : 'draft'));
                            @endphp
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700">Module Status</label>
                                <select name="action" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="publish" {{ $adminStatus === 'publish' ? 'selected' : '' }}>Publish</option>
                                    <option value="draft" {{ $adminStatus === 'draft' ? 'selected' : '' }}>Save as Draft</option>
                                    <option value="archive" {{ $adminStatus === 'archive' ? 'selected' : '' }}>Archive</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Update the module lifecycle state.</p>
                                @error('action')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route($contentRoutePrefix . '.modules.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                            <button type="submit"
                                data-testid="restricted-edit-submit"
                                @disabled(($isRestricted ?? false) === true)
                                class="bg-brand-600 hover:bg-brand-700 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded">{{ ($isContentAdminPanel ?? false) === true ? 'Save Module Changes' : 'Update Draft' }}</button>
                        </div>
                    </form>
                </div>
            </div>
@endsection
