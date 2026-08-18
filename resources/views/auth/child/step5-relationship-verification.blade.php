<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="flex flex-col items-center justify-center h-full p-12 text-center">
            <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="w-auto h-20 mx-auto mb-3">
            <h2 class="mb-4 text-4xl font-bold leading-tight text-white">Relationship review</h2>
            <p class="max-w-xs text-lg text-white/80">Submit required relationship documents.</p>
        </div>
    </x-slot>

    <x-wizard-stepper :steps="[
        ['label' => 'Dependent Info', 'active' => false, 'done' => true],
        ['label' => 'Location', 'active' => false, 'done' => true],
        ['label' => 'Credentials', 'active' => false, 'done' => true],
        ['label' => 'Validation', 'active' => false, 'done' => true],
        ['label' => 'Relationship', 'active' => true, 'done' => false],
    ]" />

    <div class="p-5 mb-6 border border-purple-100 rounded-2xl bg-purple-50/60">
        <p class="text-xs font-semibold tracking-wide text-purple-600 uppercase">Dependent setup</p>
        <h1 class="mt-1 text-2xl font-bold text-purple-950">Relationship Verification</h1>
    </div>

    @if ($errors->any())
        <div class="p-4 mb-6 border-l-4 border-red-500 rounded-lg bg-red-50">
            <ul class="text-sm text-red-700 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('parent.create-child.relationship-verification.store') }}" enctype="multipart/form-data" class="space-y-5"
          x-data="{ relationshipDocumentName: 'No file selected', supportingDocumentName: 'No file selected' }">
        @csrf

        <div class="overflow-hidden bg-white border border-purple-700 shadow-sm rounded-2xl">
            <div class="px-5 py-4 border-b border-amber-100 bg-gradient-to-r from-amber-50 to-purple-50">
                <p class="text-xs font-semibold tracking-wide uppercase text-amber-700">Required verification</p>
                <h2 class="mt-1 text-base font-semibold text-purple-950">Relationship documents</h2>
                <p class="mt-1 text-xs text-gray-600">Upload the required document so the request can move to administrative review.</p>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <label class="block text-sm font-medium text-gray-700">
                    Document Type <span class="text-red-500">*</span>
                    <select name="relationship_document_type" required class="w-full px-3 py-2 mt-1 text-sm text-gray-900 border border-gray-200 rounded-xl bg-gray-50 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-100">
                        <option value="">Select document</option>
                        @foreach($relationshipDocumentTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('relationship_document_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="block text-sm font-medium text-gray-700">
                    Required Document <span class="text-red-500">*</span>
                    <label class="mt-1 flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-purple-200 bg-purple-50/50 px-3 transition hover:border-purple-400 hover:bg-purple-50 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-100">
                        <input name="relationship_document" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only" @change="relationshipDocumentName = $event.target.files[0]?.name || 'No file selected'">
                        <span class="inline-flex items-center justify-center w-7 h-7 text-purple-700 bg-white shrink-0 rounded-lg ring-1 ring-purple-100">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0 4 4m-4-4-4 4M5 16.5v1.75A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25V16.5" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-purple-950">Choose file</span>
                        <span class="max-w-[45%] truncate text-xs font-medium text-gray-600" x-text="relationshipDocumentName"></span>
                    </label>
                </div>

                <div class="block text-sm font-medium text-gray-700 sm:col-span-2">
                    Optional Supporting Document
                    <label class="mt-1 flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 transition hover:border-purple-300 hover:bg-purple-50/60 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-100">
                        <input name="relationship_supporting_document" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only" @change="supportingDocumentName = $event.target.files[0]?.name || 'No file selected'">
                        <span class="inline-flex items-center justify-center w-7 h-7 text-gray-600 bg-white shrink-0 rounded-lg ring-1 ring-gray-100">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0 4 4m-4-4-4 4M5 16.5v1.75A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25V16.5" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900">Choose file</span>
                        <span class="max-w-[45%] truncate text-xs font-medium text-gray-600" x-text="supportingDocumentName"></span>
                    </label>
                </div>

                <label class="flex items-start gap-2 px-3 py-2 text-xs border rounded-xl border-amber-200 bg-amber-50 text-amber-900 sm:col-span-2">
                    <input type="checkbox" name="confirm_relationship_verification" value="1" required class="mt-0.5 rounded border-amber-300 text-purple-700 focus:ring-purple-500" @checked(old('confirm_relationship_verification'))>
                    <span>I confirm these documents support the requested guardian-dependent relationship.</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <a href="{{ route('parent.create-child.validation') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
            <button type="submit" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);" class="inline-flex items-center justify-center px-8 py-3.5 font-semibold text-white rounded-xl shadow-md hover:opacity-90">
                Submit Request
            </button>
        </div>
    </form>
</x-auth-split-layout>
