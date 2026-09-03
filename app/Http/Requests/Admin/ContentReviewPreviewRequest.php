<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentReviewPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'node_type' => ['required', Rule::in(['topic', 'quiz', 'activity'])],
            'node_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
