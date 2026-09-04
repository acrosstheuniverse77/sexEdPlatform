<?php

declare(strict_types=1);

namespace App\Services\Learning\InteractiveActivities;

use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use Illuminate\Support\Facades\Log;
use Throwable;

class InteractiveActivityPresenter
{
    public function __construct(private readonly InteractiveActivityRegistry $registry) {}

    /** @return array<string, mixed> */
    public function present(
        InteractiveActivity $activity,
        ?InteractiveActivityProgress $progress,
        bool $practice = false,
    ): array {
        $status = $practice ? 'practice' : ($progress?->status ?? 'new');
        $attemptCount = (int) ($progress?->attempt_count ?? 0);

        try {
            $handler = $this->registry->for($activity->activity_type);
            $configuration = $handler->normalize($activity->configuration, $activity->configuration);
            $workingState = $practice || ! is_array($progress?->working_state)
                ? $handler->initialWorkingState($configuration, new \Random\Randomizer)
                : $progress->working_state;

            return [
                'available' => true,
                'id' => $activity->id,
                'type' => $activity->activity_type->value,
                'revision' => (int) $activity->revision,
                'title' => $activity->title,
                'instructions' => $activity->instructions,
                'status' => $status,
                'attempt_count' => $attemptCount,
                'payload' => $handler->learnerPayload($configuration, $workingState),
                'explanation' => ! $practice && $progress?->status === 'completed'
                    ? $activity->explanation
                    : null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Interactive activity presentation unavailable.', [
                'activity_id' => $activity->id,
                'lesson_topic_id' => $activity->lesson_topic_id,
                'activity_type' => $activity->activity_type?->value ?? (string) $activity->activity_type,
                'validation_message' => $exception->getMessage(),
            ]);

            return [
                'available' => false,
                'id' => $activity->id,
                'type' => $activity->activity_type?->value ?? (string) $activity->activity_type,
                'revision' => (int) $activity->revision,
                'title' => $activity->title,
                'instructions' => $activity->instructions,
                'status' => $status,
                'attempt_count' => $attemptCount,
                'payload' => null,
                'explanation' => null,
                'message' => 'This activity is temporarily unavailable.',
            ];
        }
    }
}
