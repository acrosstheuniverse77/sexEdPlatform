<?php

namespace App\Http\Requests\Admin;

use App\Support\GuardianRelationshipTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachParentChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage user relationships');
    }

    public function rules(): array
    {
        return [
            'parent_user_id' => ['required', 'integer', 'exists:users,id', 'different:child_user_id'],
            'child_user_id' => ['required', 'integer', 'exists:users,id', 'different:parent_user_id'],
            'relationship_type' => ['required', Rule::in(GuardianRelationshipTypes::values())],
            'relationship_custom' => ['nullable', 'required_if:relationship_type,other', 'string', 'max:120'],
            'relationship_notes' => ['nullable', 'string', 'max:1000'],
            'can_view_progress' => ['nullable', 'boolean'],
            'can_view_quiz_answers' => ['nullable', 'boolean'],
            'can_approve_content' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
        ];
    }
}
