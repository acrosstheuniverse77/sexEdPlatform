<?php

namespace App\Http\Requests\Community;

use App\Enums\CommunityPostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'post_type' => ['required', Rule::in(CommunityPostType::values())],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'resource_url' => ['nullable', 'url', 'max:2048'],
            'seminar_id' => ['nullable', 'integer', 'exists:seminars,id'],
        ];
    }
}
