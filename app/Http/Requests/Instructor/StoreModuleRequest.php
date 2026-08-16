<?php

namespace App\Http\Requests\Instructor;

use App\Services\Instructor\InstructorPlanCapabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enrollmentLimitCap = $this->resolveEnrollmentLimitCap();

        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048',
            'age_bracket' => ['nullable', Rule::in(['kids', 'teens', 'adults'])],
            'age_brackets' => ['required_without:age_bracket', 'array', 'min:1'],
            'age_brackets.*' => ['required', Rule::in(['kids', 'teens', 'adults'])],
            'enrollment_mode' => 'required|in:auto,manual',
            'action' => 'nullable|in:publish,draft,archive',
            'order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'access_type' => 'nullable|in:free,paid',
            'price_amount' => 'nullable|numeric|min:0.01|required_if:access_type,paid',
            'price_currency' => 'nullable|in:PHP',
            'enrollment_limit' => $enrollmentLimitCap !== null
                ? 'nullable|integer|min:1|max:' . $enrollmentLimitCap
                : 'nullable|integer|min:1',
        ];
    }

    private function resolveEnrollmentLimitCap(): ?int
    {
        $user = $this->user();
        if (!$user || !$user->hasRole('instructor')) {
            return null;
        }

        $accessType = (string) ($this->input('access_type') ?: 'free');

        return app(InstructorPlanCapabilityService::class)->getLearnerCapForModule($user, $accessType);
    }
}
