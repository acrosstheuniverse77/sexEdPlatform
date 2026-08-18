@extends($contentPanelLayout ?? 'layouts.instructor-app')

@section('title', 'Manage Modules')

@section('content')
@php
    $prefillModule = null;
    $prefillModulePayload = null;
    if (request()->filled('edit_module')) {
        $prefillModule = $modules->firstWhere('id', (int) request('edit_module'));
        if ($prefillModule) {
            $prefillModulePayload = [
                'id' => $prefillModule->id,
                'title' => $prefillModule->title,
                'description' => $prefillModule->description,
                'age_brackets' => $prefillModule->learnerCategoryKeys(),
                'age_bracket' => $prefillModule->learnerCategoryKeys()[0] ?? 'teens',
                'enrollment_mode' => $prefillModule->enrollment_mode,
                'access_type' => $prefillModule->access_type,
                'price_amount' => $prefillModule->price_amount,
                'price_currency' => $prefillModule->price_currency,
                'enrollment_limit' => $prefillModule->enrollment_limit,
                'is_published' => (bool) $prefillModule->is_published,
                'thumbnail_url' => $prefillModule->thumbnail_url,
            ];
        }
    }
@endphp
<div x-data="{
    q: '',
    deleteModalOpen: false,
    deleteForm: null,
    withdrawModalOpen: false,
    withdrawForm: null,
    openDeleteConfirm(form) {
        this.deleteForm = form;
        this.deleteModalOpen = true;
    },
    closeDeleteConfirm() {
        this.deleteModalOpen = false;
        this.deleteForm = null;
    },
    confirmDelete() {
        if (this.deleteForm) {
            this.deleteForm.submit();
        }
    },
    openWithdrawConfirm(form) {
        this.withdrawForm = form;
        this.withdrawModalOpen = true;
    },
    closeWithdrawConfirm() {
        this.withdrawModalOpen = false;
        this.withdrawForm = null;
    },
    confirmWithdraw() {
        if (this.withdrawForm) {
            this.withdrawForm.submit();
        }
    }
}"
@if(request()->boolean('create_module'))
    x-init="$store.modals.openModuleModal()"
@elseif($prefillModule)
    x-init="$store.modals.openModuleModal({{ Js::from($prefillModulePayload) }})"
@endif>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="border-l-4 pl-3" style="border-color: #730DB1;">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Manage Modules</h1>
            <p class="text-xs text-gray-400 dark:text-gray-500">Build and manage your learning modules</p>
        </div>
        @if(($isRestricted ?? false) === true)
            <button type="button"
                    data-testid="create-module-disabled"
                    disabled
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-gray-500 bg-gray-200 rounded-xl cursor-not-allowed opacity-80"
                    title="{{ $restrictionMessage }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                Create Module (Restricted)
            </button>
        @else
            <button @click="$store.modals.openModuleModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 active:scale-[0.98] transition-all shadow-sm"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Create Module
            </button>
        @endif
    </div>

    @if(($isRestricted ?? false) === true)
        <div class="rounded-2xl bg-rose-50 border border-rose-200 px-5 py-3.5 mb-5">
            <p class="text-sm font-semibold text-rose-900">Module actions are temporarily restricted</p>
            <p class="text-xs text-rose-700 mt-1">{{ $restrictionMessage }}</p>
            <p class="text-xs text-rose-700 mt-1">
                Restriction ends:
                {{ optional($restrictionProfile?->restriction_ends_at)->toDayDateTimeString() ?? 'until further notice' }}
            </p>
        </div>
    @endif

    {{-- Controls: Tabs + Search --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5">

        {{-- Tab Filter (server-side URL params) --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 flex-shrink-0">
        @foreach([['all','All'], ['published','Published'], ['draft','Draft'], ['archived','Archived']] as [$val, $label])
            <a href="{{ request()->fullUrlWithQuery(['status' => $val]) }}"
               class="{{ $status === $val
                    ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm font-semibold'
                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 font-medium' }} px-3 py-1.5 rounded-lg text-sm transition-all">
                {{ $label }}
            </a>
            @endforeach
        </div>

        {{-- Client-side Search within current page --}}
        <div class="relative w-full sm:w-64" id="modules-local-search">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" x-model.debounce.300ms="q"
                   placeholder="Search modules..."
                   class="w-full pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
        </div>
    </div>

    {{-- Pending Enrollments Alert --}}
    @if($pendingCount > 0)
    <div class="rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 px-5 py-3.5 mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
            </div>
            <p class="text-sm text-amber-800 dark:text-amber-200">
                <span class="font-semibold">{{ $pendingCount }}</span> enrollment request{{ $pendingCount > 1 ? 's' : '' }} awaiting your review
            </p>
        </div>
        <a href="{{ route($contentRoutePrefix . '.enrollments.index') }}"
           class="flex-shrink-0 text-xs font-semibold text-amber-700 dark:text-amber-300 hover:text-amber-900 dark:hover:text-amber-100 transition-colors whitespace-nowrap">
            Review Now →
        </a>
    </div>
    @endif

    {{-- Module Cards Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

        @forelse($modules as $module)
        @php
            $searchKey = strtolower($module->title . ' ' . strip_tags($module->description ?? ''));
            $moduleReviewStatus = (string) ($module->current_review_status ?? 'draft');
            $moduleReviewLabel = match ($moduleReviewStatus) {
                'submitted' => 'Submitted',
                'in_review' => 'Under Review',
                'needs_revision' => 'Needs Revision',
                'approved' => 'Approved',
                default => 'Draft',
            };
            $moduleModalPayload = [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'age_brackets' => $module->learnerCategoryKeys(),
                'age_bracket' => $module->learnerCategoryKeys()[0] ?? 'teens',
                'enrollment_mode' => $module->enrollment_mode,
                'access_type' => $module->access_type,
                'price_amount' => $module->price_amount,
                'price_currency' => $module->price_currency,
                'enrollment_limit' => $module->enrollment_limit,
                'is_published' => (bool) $module->is_published,
                'thumbnail_url' => $module->thumbnail_url,
            ];
        @endphp

        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden flex flex-col transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
             x-show="!q || {{ json_encode($searchKey) }}.includes(q.toLowerCase())"
             x-transition:leave="transition duration-150 ease-in"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            {{-- Card Top Zone: gradient bg + optional thumbnail --}}
            <div class="relative h-36 overflow-hidden flex-shrink-0">
                @if($module->thumbnail_url)
                    <img src="{{ $module->thumbnail_url }}"
                         alt="{{ $module->title }}"
                         class="w-full h-full object-cover">
                @else
                    <div class="absolute inset-0" style="background: linear-gradient(135deg, #A30EB2 0%, #730DB1 50%, #3B0CB1 100%);"></div>
                    <div class="absolute inset-0 opacity-10"
                         style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 16px 16px;"></div>
                    <div class="absolute -bottom-6 -right-6 w-24 h-24 rounded-full opacity-10"
                         style="background: radial-gradient(circle, #fff, transparent);"></div>
                @endif

                {{-- Enrollment mode chip (top-left, only if not archived) --}}
                @unless($module->trashed())
                <div class="absolute top-3 left-3">
                    <span class="inline-flex items-center text-[10px] font-medium uppercase tracking-widest px-2 py-0.5 rounded-full bg-white/20 text-white border border-white/30 backdrop-blur-sm">
                        {{ $module->enrollment_mode === 'auto' ? 'Open' : 'Manual' }}
                    </span>
                </div>
                @endunless

                {{-- Status Badge (top-right) --}}
                <div class="absolute top-3 right-3">
                    @if($module->trashed())
                        <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-red-100 text-red-600 border border-red-200">Archived</span>
                    @elseif($module->is_published)
                        <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200">Published</span>
                    @else
                        <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">Draft</span>
                    @endif
                </div>
            </div>

            {{-- Card Body --}}
            <div class="p-4 flex-1 flex flex-col gap-2">
                <h3 class="font-semibold text-sm text-gray-900 dark:text-white leading-snug line-clamp-2">{{ $module->title }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed line-clamp-2">{{ Str::limit(strip_tags($module->description ?? 'No description provided.'), 120) }}</p>
                <div class="rounded-xl border border-purple-100 bg-purple-50/60 px-3 py-2 dark:border-purple-900/40 dark:bg-purple-900/10">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-purple-500">Review Status</p>
                    <p class="mt-1 text-xs font-medium text-purple-800 dark:text-purple-200">{{ $moduleReviewLabel }}</p>
                </div>

                {{-- Stats Row --}}
                <div class="flex items-center gap-2 mt-auto pt-2 flex-wrap">
                    <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 font-medium module-meta-status-inline">
                    @if($module->is_published)
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Active
                    @else
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Draft
                    @endif
                    </span>
                    <span class="text-gray-200 dark:text-gray-600 text-xs">·</span>
                    <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        {{ $module->lessons_count }} lesson{{ $module->lessons_count !== 1 ? 's' : '' }}
                    </span>
                    <span class="text-gray-200 dark:text-gray-600 text-xs">·</span>
                    <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $module->quizzes_count }} quiz{{ $module->quizzes_count !== 1 ? 'zes' : '' }}
                    </span>
                    <span class="text-gray-200 dark:text-gray-600 text-xs">·</span>
                    <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                        <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                        </svg>
                        {{ $module->enrolled_count }} enrolled
                    </span>
                </div>
            </div>

            {{-- Card Footer: Action Icons --}}
            <div class="border-t border-gray-100 dark:border-gray-700 px-4 py-2.5 flex items-center gap-0.5 bg-gray-50/50 dark:bg-gray-800/60">

                @if($module->trashed())
                    {{-- Archived: show restore button only --}}
                    <form action="{{ route($contentRoutePrefix . '.modules.restore', $module->id) }}" method="POST">
                        @csrf @method('PATCH')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-800/40 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Restore
                        </button>
                    </form>
                    <span class="ml-auto text-[10px] text-gray-400 dark:text-gray-500">{{ $module->deleted_at->diffForHumans() }}</span>

                @else
                    {{-- View --}}
                    <a href="{{ route($contentRoutePrefix . '.modules.show', $module) }}"
                       title="View module"
                              class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors action-icon-standard instructor-icon-readable">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </a>

                    {{-- Edit --}}
                    <button type="button"
                            data-edit-module-trigger
                            @click="$store.modals.openModuleModal({{ Js::from($moduleModalPayload) }})"
                            title="Edit module"
                              class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition-colors action-icon-standard">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </button>

                    {{-- Activate / Deactivate --}}
                    @if($module->is_published)
                        <form action="{{ route($contentRoutePrefix . '.modules.deactivate', $module) }}" method="POST" class="inline-flex">
                            @csrf @method('PATCH')
                            <button type="submit" title="Deactivate module"
                                    class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors action-icon-standard">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </button>
                        </form>
                    @else
                        @if(($contentRoutePrefix ?? 'instructor') === 'instructor')
                            @if($moduleReviewStatus === 'needs_revision')
                                <form action="{{ route($contentRoutePrefix . '.modules.review.resubmit', $module) }}" method="POST" class="inline-flex">
                                    @csrf
                                    <button type="submit" title="Resubmit for review"
                                            class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors action-icon-standard">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0111.95-4.95L20 7M19 15a7 7 0 01-11.95 4.95L4 17"/>
                                        </svg>
                                    </button>
                                </form>
                            @elseif($moduleReviewStatus === 'submitted')
                                  <form action="{{ route($contentRoutePrefix . '.modules.review.withdraw', $module) }}" method="POST" class="inline-flex"
                                      @submit.prevent="openWithdrawConfirm($event.target)">
                                    @csrf
                                    <button type="submit" title="Withdraw submission"
                                            class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors action-icon-standard">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l-4-4m0 0l4-4m-4 4h14"/>
                                        </svg>
                                    </button>
                                </form>
                            @elseif($moduleReviewStatus === 'in_review')
                                <div title="Admin is currently reviewing this module"
                                     class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            @else
                                <form action="{{ route($contentRoutePrefix . '.modules.review.submit', $module) }}" method="POST" class="inline-flex">
                                    @csrf
                                    <button type="submit" title="Submit for review"
                                            class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors action-icon-standard">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endif

                    <div class="flex-1"></div>

                    {{-- Delete: only visible when no enrolled learners --}}
                    @if($module->enrolled_count === 0)
                        <form action="{{ route($contentRoutePrefix . '.modules.destroy', $module) }}" method="POST" class="inline-flex"
                            @submit.prevent="openDeleteConfirm($event.target)">
                            @csrf @method('DELETE')
                            <button type="submit" title="Delete module"
                                    class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors action-icon-standard">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    @else
                        <div title="Cannot delete — {{ $module->enrolled_count }} enrolled learner(s)"
                             class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-200 dark:text-gray-600 cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                    @endif
                @endif

            </div>
        </div>
        @empty

        {{-- Empty State --}}
        <div class="col-span-full">
            <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 py-16 px-8 text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl flex items-center justify-center mb-4"
                     style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white mb-1">
                    @if($status === 'archived') No archived modules
                    @elseif($status === 'published') No published modules yet
                    @elseif($status === 'draft') No draft modules
                    @else No modules yet
                    @endif
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-5">
                    @if($status === 'all') Start by creating your first learning module.
                    @else Switch to the "All" tab to see all modules.
                    @endif
                </p>
                @if($status === 'all')
                    @if(($isRestricted ?? false) === true)
                        <button type="button"
                                data-testid="create-module-disabled"
                                disabled
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-500 bg-gray-200 rounded-xl cursor-not-allowed opacity-80">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            Create First Module (Restricted)
                        </button>
                    @else
                        <button @click="$store.modals.openModuleModal()"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 transition-opacity shadow-sm"
                                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create First Module
                        </button>
                    @endif
                @endif
            </div>
        </div>
        @endforelse

    </div>

    {{-- Pagination --}}
    @if($modules->hasPages())
    <div class="mt-6">
        {{ $modules->appends(request()->query())->links() }}
    </div>
    @endif

    <div x-show="deleteModalOpen" x-cloak class="fixed inset-0 z-40 bg-gray-900/50" @click="closeDeleteConfirm()"></div>
    <div x-show="deleteModalOpen" x-cloak id="modules-delete-confirm-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-xl border border-gray-100 dark:border-gray-700" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Module Deletion</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">This action permanently removes the selected module and related content.</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" data-delete-confirm-cancel @click="closeDeleteConfirm()" class="px-4 py-2 text-sm font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                <button type="button" data-delete-confirm-submit @click="confirmDelete()" class="px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors">Delete</button>
            </div>
        </div>
    </div>

    <div x-show="withdrawModalOpen" x-cloak class="fixed inset-0 z-40 bg-gray-900/50" @click="closeWithdrawConfirm()"></div>
    <div x-show="withdrawModalOpen" x-cloak id="modules-withdraw-confirm-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-xl border border-gray-100 dark:border-gray-700" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Submission Withdrawal</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">This module will be moved back to draft so you can revise and submit again later.</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="closeWithdrawConfirm()" class="px-4 py-2 text-sm font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                <button type="button" @click="confirmWithdraw()" class="px-4 py-2 text-sm font-semibold rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors">Confirm</button>
            </div>
        </div>
    </div>

</div>

{{-- Module Creation Modal --}}
@include('instructor.modules.partials.module-modal')

@endsection
