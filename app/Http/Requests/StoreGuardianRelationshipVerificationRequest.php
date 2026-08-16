<?php

namespace App\Http\Requests;

use App\Support\GuardianRelationshipTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianRelationshipVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $relationship = $this->route('parentChildAccount');

        return [
            'document_type' => ['required', Rule::in(GuardianRelationshipTypes::acceptedDocumentTypes($relationship?->relationship_type))],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'confirm_submission' => ['accepted'],
        ];
    }
}
