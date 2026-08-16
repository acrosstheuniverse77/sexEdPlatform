{{-- Module Creation Modal --}}
{{-- Triggered via: $store.modals.openModuleModal() --}}

{{-- Backdrop --}}
<div x-show="$store.modals.moduleModal"
     x-cloak
     class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-40 transition-opacity"
     @click="$store.modals.closeModuleModal()"
     @keydown.escape.window="$store.modals.closeModuleModal()"></div>

{{-- Dialog --}}
<div x-show="$store.modals.moduleModal"
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
     class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
     @keydown.escape.window="$store.modals.closeModuleModal()">

        <div class="max-h-[92vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 flex flex-col"
            data-testid="module-modal-shell"
         @click.stop
         x-data="{
             mode: 'create',
             editModuleId: null,
             title: '',
             description: '',
             ageBrackets: [],
             enrollmentMode: 'auto',
             accessType: 'free',
             priceAmount: '',
             priceCurrency: 'PHP',
             enrollmentLimit: '',
             freeModuleCap: @js($instructorCapabilitySnapshot['free_module_learner_cap'] ?? null),
             paidModuleCap: @js($instructorCapabilitySnapshot['paid_module_learner_cap'] ?? null),
             isPublished: false,
             actionChoice: '{{ ($isContentAdminPanel ?? false) ? 'publish' : 'draft' }}',
             thumbnailPreview: null,
             actionBase: '{{ url($contentRoutePrefix . '/modules') }}',
             syncFromStore() {
                 const draft = $store.modals.moduleModalDraft;
                 if (!draft) {
                     this.mode = 'create';
                     this.editModuleId = null;
                     this.title = '';
                     this.description = '';
                     this.ageBrackets = [];
                     this.enrollmentMode = 'auto';
                     this.accessType = 'free';
                     this.priceAmount = '';
                     this.priceCurrency = 'PHP';
                     this.enrollmentLimit = '';
                     this.isPublished = false;
                     this.actionChoice = '{{ ($isContentAdminPanel ?? false) ? 'publish' : 'draft' }}';
                     this.thumbnailPreview = null;
                     return;
                 }

                 this.mode = 'edit';
                 this.editModuleId = draft.id;
                 this.title = draft.title || '';
                 this.description = draft.description || '';
                 this.ageBrackets = Array.isArray(draft.age_brackets)
                     ? draft.age_brackets
                     : (draft.age_bracket ? [draft.age_bracket] : []);
                 this.enrollmentMode = draft.enrollment_mode || 'auto';
                 this.accessType = draft.access_type || 'free';
                 this.priceAmount = draft.price_amount ?? '';
                 this.priceCurrency = 'PHP';
                 this.enrollmentLimit = draft.enrollment_limit ?? '';
                 this.isPublished = !!draft.is_published;
                 this.actionChoice = draft.action || (this.isPublished ? 'publish' : 'draft');
                 this.thumbnailPreview = draft.thumbnail_url || null;
             },
             get isEdit() {
                 return this.mode === 'edit' && this.editModuleId !== null;
             },
             get formAction() {
                 return this.isEdit ? `${this.actionBase}/${this.editModuleId}` : '{{ route($contentRoutePrefix . '.modules.store') }}';
             },
             effectiveEnrollmentCap() {
                 return this.accessType === 'paid' ? this.paidModuleCap : this.freeModuleCap;
             },
             previewImage(event) {
                 const file = event.target.files[0];
                 if (file) {
                     this.thumbnailPreview = URL.createObjectURL(file);
                 }
            },
            clearThumbnail(input) {
                 this.thumbnailPreview = null;
                 if (input) {
                     input.value = '';
                 }
             },
             init() {
                 this.syncFromStore();
                 this.$watch('$store.modals.moduleModalDraft', () => { this.syncFromStore(); });
                 this.$watch('$store.modals.moduleModal', open => {
                     if (open) this.syncFromStore();
                 });
             }
         }">

        {{-- Modal Header --}}
           <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-t-2xl shrink-0"
               data-testid="module-modal-header">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white" x-text="isEdit ? 'Edit Module' : 'Create New Module'"></h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500" x-text="isEdit ? 'Update module details and status' : 'Fill in the details below to create your module'"></p>
                </div>
            </div>
            <button @click="$store.modals.closeModuleModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Modal Body --}}
        <form id="moduleModalForm" method="POST" :action="formAction" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
            @csrf
            <template x-if="isEdit">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-5" data-testid="module-modal-body">

            {{-- Title --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                    Module Title <span class="text-red-500">*</span>
                </label>
                  <input type="text" name="title" x-model="title" required
                       placeholder="e.g., Understanding Your Body"
                       class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white placeholder-gray-400 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                @error('title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                    Description <span class="text-red-500">*</span>
                </label>
                <textarea name="description" x-model="description" rows="3" required
                          placeholder="Briefly describe what learners will discover in this module..."
                          class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white placeholder-gray-400 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors resize-none"></textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Thumbnail --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                    Cover Thumbnail
                    <span class="text-gray-400 font-normal normal-case tracking-normal ml-1">(optional, shows on card)</span>
                </label>

                <div class="flex items-start gap-4">
                    {{-- Preview box --}}
                    <div class="flex-shrink-0 w-24 h-16 rounded-xl overflow-hidden border-2 border-dashed border-gray-200 dark:border-gray-600 flex items-center justify-center bg-gray-50 dark:bg-gray-800 transition-colors"
                         :class="thumbnailPreview ? 'border-purple-300' : ''">
                        <template x-if="thumbnailPreview">
                            <img :src="thumbnailPreview" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!thumbnailPreview">
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </template>
                    </div>
                    {{-- Upload input --}}
                    <div class="flex-1">
                        <input type="file" name="thumbnail" accept="image/*"
                               x-ref="thumbnailInput"
                               @change="previewImage($event)"
                               class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 dark:file:bg-purple-900/20 dark:file:text-purple-300 hover:file:bg-purple-100 dark:hover:file:bg-purple-900/40 transition-colors cursor-pointer">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG, GIF up to 2MB.</p>
                        <button type="button"
                                x-show="thumbnailPreview"
                                x-cloak
                                @click="clearThumbnail($refs.thumbnailInput)"
                                class="mt-2 inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                            Remove thumbnail
                        </button>
                    </div>
                </div>
                @error('thumbnail')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Learner Categories + Enrollment Mode (2-col) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Eligible Learner Categories <span class="text-red-500">*</span>
                    </label>
                    <div class="grid gap-2">
                        @foreach(\App\Models\Module::learnerCategoryLabelsMap() as $category => $label)
                            <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-purple-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    name="age_brackets[]"
                                    value="{{ $category }}"
                                    x-model="ageBrackets"
                                    class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('age_brackets')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('age_brackets.*')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Enrollment Mode <span class="text-red-500">*</span>
                    </label>
                    <select name="enrollment_mode" x-model="enrollmentMode" required
                            class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                        <option value="auto">Auto (open enrollment)</option>
                        <option value="manual">Manual (requires approval)</option>
                    </select>
                    @error('enrollment_mode')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Access Type + Pricing + Capacity --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Access Type <span class="text-red-500">*</span>
                    </label>
                    <select name="access_type" x-model="accessType" required
                            class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                        <option value="free">Free</option>
                        <option value="paid">Paid</option>
                    </select>
                    @error('access_type')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Enrollment Limit
                        <span class="text-[10px] font-normal normal-case tracking-normal text-gray-400 ml-1"
                              x-show="effectiveEnrollmentCap() !== null"
                              x-text="`(max ${effectiveEnrollmentCap()} learners)`"></span>
                    </label>
                    <input type="number" name="enrollment_limit" x-model="enrollmentLimit" min="1" :max="effectiveEnrollmentCap()" required
                           placeholder="Enter learner capacity"
                           class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white placeholder-gray-400 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                    @error('enrollment_limit')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="accessType === 'paid'" x-cloak>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Price Amount (PHP)
                    </label>
                    <input type="number" name="price_amount" x-model="priceAmount" min="0.01" step="0.01"
                           class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white placeholder-gray-400 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                    @error('price_amount')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Price Currency
                    </label>
                    <div class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-200 px-3.5 py-2.5">
                        PHP (Peso)
                    </div>
                    <input type="hidden" name="price_currency" value="PHP">
                    @error('price_currency')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if(!empty($effectiveCommissionPolicy))
                    <div class="sm:col-span-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                        <p class="text-sm font-semibold text-indigo-900">
                            Platform commission currently applied to your paid modules: {{ number_format((float) $effectiveCommissionPolicy['commission_percent'], 2) }}%
                        </p>
                        <p class="mt-1 text-xs text-indigo-800">
                            Estimated net earnings per sale: Price - (Price x {{ number_format((float) $effectiveCommissionPolicy['commission_percent'], 2) }}%).
                        </p>
                    </div>
                @endif
            </div>

            {{-- Lifecycle / publication control --}}
            @if(($isContentAdminPanel ?? false) === true)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50/50 dark:bg-gray-800/50">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5">
                        Module Status
                    </label>
                    <select name="action" x-model="actionChoice"
                            class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 transition-colors">
                        <option value="publish">Publish</option>
                        <option value="draft">Save as Draft</option>
                        <option value="archive">Archive</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Set lifecycle state for this platform module.</p>
                </div>
            @else
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50/50 dark:bg-gray-800/50">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Governed publication</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Instructor modules save as drafts and become learner-visible only after admin approval.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="hidden" name="action" value="draft">
                            <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-600">Draft</div>
                        </label>
                    </div>
                </div>
            @endif

            </div>

            {{-- Form Actions --}}
            <div class="flex shrink-0 items-center justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900"
                 data-testid="module-modal-footer">
                <button type="button" @click="$store.modals.closeModuleModal()"
                        class="px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 active:scale-[0.98] transition-all shadow-sm"
                        style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span x-text="isEdit ? 'Save Module Changes' : 'Create Module'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
