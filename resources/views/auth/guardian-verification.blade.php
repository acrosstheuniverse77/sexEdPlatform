<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="flex flex-col items-center justify-center h-full p-12 text-center">
            <div class="mb-6">
                <img src="{{ asset('/media/Logo.png') }}" alt="Conscious Connections" class="w-auto h-20 mx-auto mb-3 drop-shadow-lg">
                <p class="text-sm font-semibold tracking-wide uppercase text-white/90">Conscious Connections</p>
            </div>
            <h2 class="mb-4 text-4xl font-bold leading-tight text-white">Guardian Verification</h2>
            <p class="max-w-xs text-lg text-white/80">Verify your identity to unlock Guardian features.</p>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-700">Guardian Verification</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">Verify Your Identity</h1>
        </div>

        @if($errors->any())
            <div class="px-4 py-3 mb-5 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50">
                Please review the highlighted fields and try again.
            </div>
        @endif

        <form method="POST"
              action="{{ route('guardian.verification.store') }}"
              enctype="multipart/form-data"
              class="space-y-6"
              x-data="{
                  idType: @js(old('government_id_type', '')),
                  idTypeRequirements: @js($idTypeRequirements ?? []),
                  previewOpen: false,
                  previewUrl: '',
                  previewTitle: '',
                  documents: {
                      front: { name: '', previewUrl: '' },
                      back: { name: '', previewUrl: '' },
                  },
                  requiresBack() {
                      return Boolean(this.idTypeRequirements[this.idType]?.requires_back);
                  },
                  requirementLabel() {
                      if (!this.idType) return 'Select an ID type to see image requirements.';
                      return this.requiresBack() ? 'Front and back images required.' : 'Front image only required.';
                  },
                  setDocument(side, event) {
                      const file = event.target.files && event.target.files.length ? event.target.files[0] : null;
                      this.removeDocument(side, false);

                      if (!file) {
                          return;
                      }

                      this.documents[side].name = file.name;
                      this.documents[side].previewUrl = URL.createObjectURL(file);
                  },
                  removeDocument(side, clearInput = true) {
                      if (this.documents[side].previewUrl) {
                          URL.revokeObjectURL(this.documents[side].previewUrl);
                      }

                      this.documents[side].name = '';
                      this.documents[side].previewUrl = '';

                      if (clearInput && this.$refs[side + 'Input']) {
                          this.$refs[side + 'Input'].value = '';
                      }
                  },
                  openPreview(side) {
                      if (!this.documents[side].previewUrl) return;
                      this.previewUrl = this.documents[side].previewUrl;
                      this.previewTitle = side === 'front' ? 'Front ID Image' : 'Back ID Image';
                      this.previewOpen = true;
                  },
                  closePreview() {
                      this.previewOpen = false;
                      this.previewUrl = '';
                      this.previewTitle = '';
                  }
              }">
            @csrf

            <div>
                <label for="government_id_type" class="block text-sm font-semibold text-gray-800">Government-Issued Identification Type</label>
                <select id="government_id_type"
                        name="government_id_type"
                        x-model="idType"
                        @change="if (!requiresBack()) removeDocument('back')"
                        class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <option value="">Select ID type</option>
                    @foreach($idTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('government_id_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-gray-600" x-text="requirementLabel()"></p>
                @error('government_id_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="idType === 'other'" x-cloak>
                <label for="government_id_type_other" class="block text-sm font-semibold text-gray-800">Specify ID Type</label>
                <input id="government_id_type_other" name="government_id_type_other" value="{{ old('government_id_type_other') }}"
                       class="mt-2 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                @error('government_id_type_other')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="government_id_front" class="block text-sm font-semibold text-gray-800">Front Image <span class="text-red-500">*</span></label>
                    <input id="government_id_front" name="government_id_front" type="file" accept="image/jpeg,image/png,image/webp" required
                           capture="environment"
                           x-ref="frontInput"
                           @change="setDocument('front', $event)"
                           class="mt-2 block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700">
                    <div x-show="documents.front.previewUrl" x-cloak class="p-3 mt-3 bg-white border border-gray-200 rounded-xl" data-testid="guardian-id-front-preview">
                        <button type="button" @click="openPreview('front')" class="block w-full cursor-zoom-in" title="Preview front ID image">
                            <img :src="documents.front.previewUrl" alt="Front ID preview" class="object-contain w-full rounded-lg h-36 bg-gray-50">
                        </button>
                        <div class="flex items-center justify-between gap-3 mt-2">
                            <p class="text-xs font-medium text-gray-600 truncate" x-text="documents.front.name"></p>
                            <button type="button" @click="removeDocument('front')" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-red-600 hover:bg-red-50" title="Remove front image" aria-label="Remove front image">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    @error('government_id_front')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div x-show="requiresBack()" x-cloak>
                    <label for="government_id_back" class="block text-sm font-semibold text-gray-800">Back Image <span class="text-red-500">*</span></label>
                    <input id="government_id_back" name="government_id_back" type="file" accept="image/jpeg,image/png,image/webp"
                           :required="requiresBack()"
                           capture="environment"
                           x-ref="backInput"
                           @change="setDocument('back', $event)"
                           class="mt-2 block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700">
                    <div x-show="documents.back.previewUrl" x-cloak class="p-3 mt-3 bg-white border border-gray-200 rounded-xl" data-testid="guardian-id-back-preview">
                        <button type="button" @click="openPreview('back')" class="block w-full cursor-zoom-in" title="Preview back ID image">
                            <img :src="documents.back.previewUrl" alt="Back ID preview" class="object-contain w-full rounded-lg h-36 bg-gray-50">
                        </button>
                        <div class="flex items-center justify-between gap-3 mt-2">
                            <p class="text-xs font-medium text-gray-600 truncate" x-text="documents.back.name"></p>
                            <button type="button" @click="removeDocument('back')" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-red-600 hover:bg-red-50" title="Remove back image" aria-label="Remove back image">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    @error('government_id_back')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <label class="flex items-start gap-3 p-4 text-sm text-gray-700 border border-gray-200 rounded-xl">
                <input type="checkbox" name="confirm_submission" value="1" class="mt-1 border-gray-300 rounded text-brand-600 focus:ring-brand-500">
                <span>I confirm these identity documents belong to me and may be reviewed by authorized administrators.</span>
            </label>
            @error('confirm_submission')<p class="-mt-4 text-xs text-red-600">{{ $message }}</p>@enderror

            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    Submit Verification
                </button>
            </div>

            <div x-show="previewOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4" @keydown.escape.window="closePreview()">
                <div class="absolute inset-0" @click="closePreview()"></div>
                <div class="relative z-10 w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900" x-text="previewTitle"></h2>
                        <button type="button" @click="closePreview()" class="rounded-full p-2 text-gray-500 hover:bg-gray-100" title="Close preview" aria-label="Close preview">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="max-h-[78vh] overflow-auto bg-gray-50 p-4">
                        <img :src="previewUrl" alt="Government ID preview" class="mx-auto max-h-[72vh] w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain">
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-auth-split-layout>
