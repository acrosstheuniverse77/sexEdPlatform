<?php

namespace App\Services\Content;

use App\Models\Module;
use App\Models\User;
use App\Services\Instructor\InstructorPlanCapabilityService;
use InvalidArgumentException;

class ContentAuthoringService
{
    public function __construct(private readonly InstructorPlanCapabilityService $instructorPlanCapabilityService)
    {
    }

    /**
     * Build normalized module payload from form data.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function toInstructorDraftPayload(array $validated, int $authorId, ?Module $existing = null): array
    {
        $payload = $this->normalizeCommonPayload($validated, $existing);
        $payload = $this->enforceInstructorPlanConstraints($payload, $authorId, $existing);

        return $payload + [
            'created_by' => $authorId,
            'content_owner_type' => 'instructor',
            'current_review_status' => 'draft',
            'is_published' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function toAdminPayload(array $validated, ?Module $existing = null): array
    {
        $payload = $this->normalizeCommonPayload($validated, $existing);

        return $payload + [
            'is_premium' => false,
            'price_amount' => null,
            'price_currency' => 'PHP',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeCommonPayload(array $validated, ?Module $existing = null): array
    {
        $learnerCategories = Module::normalizeLearnerCategories(
            (array) ($validated['age_brackets'] ?? [($validated['age_bracket'] ?? 'teens')])
        );
        [$minAge, $maxAge] = Module::ageRangeForLearnerCategories($learnerCategories);

        $accessType = (string) ($validated['access_type'] ?? ($existing?->access_type ?? 'free'));
        $priceCurrency = strtoupper((string) ($validated['price_currency'] ?? ($existing?->price_currency ?? 'PHP')));
        $priceAmount = $accessType === 'paid'
            ? ($validated['price_amount'] ?? $existing?->price_amount)
            : null;

        $order = (int) ($validated['order'] ?? ($existing?->order ?? ((Module::query()->max('order') ?? 0) + 1)));

        return [
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'enrollment_mode' => (string) ($validated['enrollment_mode'] ?? ($existing?->enrollment_mode ?? 'auto')),
            'enrollment_limit' => $validated['enrollment_limit'] ?? $existing?->enrollment_limit,
            'access_type' => $accessType,
            'price_amount' => $priceAmount,
            'price_currency' => $priceCurrency,
            'is_premium' => $accessType === 'paid',
            'duration_minutes' => (int) ($validated['duration_minutes'] ?? ($existing?->duration_minutes ?? 0)),
            'order' => $order,
            'certificate_pass_score' => (int) ($validated['certificate_pass_score'] ?? ($existing?->certificate_pass_score ?? 70)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enforceInstructorPlanConstraints(array $payload, int $authorId, ?Module $existing = null): array
    {
        $instructor = User::query()->find($authorId);
        if (!$instructor) {
            return $payload;
        }

        $isCreateFlow = !$existing || !$existing->exists;

        if ($isCreateFlow && !$this->instructorPlanCapabilityService->canCreateModule($instructor)) {
            throw new InvalidArgumentException($this->instructorPlanCapabilityService->reachedModuleLimitMessage($instructor));
        }

        $accessType = (string) ($payload['access_type'] ?? 'free');

        if (
            $accessType === 'paid'
            && !$this->instructorPlanCapabilityService->canPublishPaidModules($instructor)
            && $this->instructorPlanCapabilityService->isStrictRolloutMode()
        ) {
            throw new InvalidArgumentException(
                'Your current instructor plan does not allow publishing paid modules. Upgrade your instructor subscription to continue.'
            );
        }

        if (
            $accessType === 'paid'
            && !$this->instructorPlanCapabilityService->canReceivePaidEnrollments($instructor)
            && $this->instructorPlanCapabilityService->isStrictRolloutMode()
        ) {
            throw new InvalidArgumentException(
                'Your current instructor plan cannot receive paid enrollments yet. Upgrade your instructor subscription to continue.'
            );
        }

        $planCap = $this->instructorPlanCapabilityService->getLearnerCapForModule($instructor, $accessType);
        if (
            $planCap !== null
            && array_key_exists('enrollment_limit', $payload)
            && $payload['enrollment_limit'] !== null
        ) {
            $payload['enrollment_limit'] = min((int) $payload['enrollment_limit'], $planCap);
        }

        return $payload;
    }

}
