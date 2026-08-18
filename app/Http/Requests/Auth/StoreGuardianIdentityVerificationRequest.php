<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianIdentityVerificationRequest extends FormRequest
{
    public const ID_TYPES = [
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->isParentRegistration()
            && (bool) $user?->hasVerifiedEmail()
            && ! $user->isParentVerificationPending()
            && ! $user->isParentVerificationApproved();
    }

    public function rules(): array
    {
        $idTypes = array_keys(config('guardian_identity.id_types', []));
        $selectedType = (string) $this->input('government_id_type');
        $requiresBack = (bool) data_get(config('guardian_identity.id_types', []), $selectedType.'.requires_back', false);

        return [
            'government_id_type' => ['required', Rule::in($idTypes)],
            'government_id_type_other' => ['nullable', 'string', 'max:80', 'required_if:government_id_type,other'],
            'government_id_front' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'government_id_back' => [$requiresBack ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'confirm_submission' => ['accepted'],
        ];
    }
}
