<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class ModerateCommunityContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
