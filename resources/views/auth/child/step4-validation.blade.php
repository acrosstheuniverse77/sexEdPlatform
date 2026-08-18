<x-auth-split-layout :showTabs="false">
    <x-slot name="panel">
        <div class="h-full flex flex-col items-center justify-center p-12 text-center">
            <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="h-20 w-auto mx-auto mb-3">
            <h2 class="text-4xl font-bold text-white mb-4 leading-tight">Dependent validation</h2>
            <p class="text-white/80 text-lg max-w-xs">Upload the dependent document for admin review.</p>
        </div>
    </x-slot>

    <x-wizard-stepper :steps="[
        ['label' => 'Dependent Info', 'active' => false, 'done' => true],
        ['label' => 'Location', 'active' => false, 'done' => true],
        ['label' => 'Credentials', 'active' => false, 'done' => true],
        ['label' => 'Validation', 'active' => true, 'done' => false],
        ['label' => 'Review', 'active' => false, 'done' => false],
    ]" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-purple-900">Dependent Validation</h1>
        <p class="mt-1 text-sm text-gray-600">Upload PSA birth certificate so administrators can verify the dependent account request.</p>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <ul class="text-sm text-red-700 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('parent.create-child.validation.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @php
            $existingName = $tempChildVerificationUpload['original_name'] ?? null;
        @endphp

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <label for="verification_document" class="block text-sm font-semibold text-gray-800">PSA Birth Certificate <span class="text-red-500">*</span></label>
            <input id="verification_document" name="verification_document" type="file" accept=".jpg,.jpeg,.png,.pdf"
                   @if(!($hasChildVerificationUpload ?? false)) required @endif
                   class="mt-3 block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-purple-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-purple-700">
            @if($existingName)
                <p data-testid="child-verification-preview" class="mt-3 text-sm text-emerald-700">Uploaded: {{ $existingName }}</p>
            @endif
            <p class="mt-2 text-xs text-gray-500">Accepted: JPG, PNG, PDF. Max size: 5MB.</p>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <a href="{{ route('parent.create-child.credentials') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
            <button type="submit" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);" class="inline-flex items-center justify-center px-8 py-3.5 font-semibold text-white rounded-xl shadow-md hover:opacity-90">
                Continue
            </button>
        </div>
    </form>
</x-auth-split-layout>
