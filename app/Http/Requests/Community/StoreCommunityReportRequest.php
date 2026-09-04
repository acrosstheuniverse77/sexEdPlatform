<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityReportRequest extends FormRequest
{
    private const ALLOWED_DETAILS_TAGS = '<p><br><strong><b><em><i><ul><ol><li><a>';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $details = $this->input('details');

        if (is_string($details)) {
            $this->merge([
                'details' => trim(strip_tags($details, self::ALLOWED_DETAILS_TAGS)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:80', Rule::in(array_keys(config('community_feed.report_reasons', [])))],
            'details' => ['nullable', 'required_if:reason_code,other', 'string', 'max:2000'],
            'community_comment_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
