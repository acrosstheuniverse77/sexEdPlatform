<?php

namespace App\Http\Requests\Learner;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleFeedbackRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->filled('feedback_type')) {
            $this->merge(['feedback_type' => 'module']);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isLearner() ?? false;
    }

    public function rules(): array
    {
        return [
            'feedback_type' => ['required', 'string', 'in:module,instructor'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_content' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
