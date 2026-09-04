<?php

declare(strict_types=1);

namespace App\Services\Learning\InteractiveActivities;

use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

class InteractiveActivityProgressService
{
    public function __construct(
        private readonly InteractiveActivityRegistry $registry,
        private readonly InteractiveActivityPresenter $presenter,
    ) {}

    public function stateFor(User $user, InteractiveActivity $activity): InteractiveActivityProgress
    {
        return DB::transaction(function () use ($user, $activity): InteractiveActivityProgress {
            [, $progress] = $this->lockedCurrentProgress($user, $activity);

            return $progress;
        });
    }

    /** @param array<string, mixed> $answer @return array<string, mixed> */
    public function evaluate(User $user, InteractiveActivity $activity, array $answer, bool $practice = false): array
    {
        if ($practice) {
            return $this->evaluatePractice($activity, $answer);
        }

        return DB::transaction(function () use ($user, $activity, $answer): array {
            [$lockedActivity, $progress] = $this->lockedCurrentProgress($user, $activity);
            $handler = $this->registry->for($lockedActivity->activity_type);
            $configuration = $handler->normalize($lockedActivity->configuration, $lockedActivity->configuration);

            if ($progress->status === 'completed') {
                return [
                    'accepted' => false,
                    'is_correct' => true,
                    'is_complete' => true,
                    'working_state' => $progress->working_state,
                    'progress' => $progress,
                    'status' => 'completed',
                    'attempt_count' => (int) $progress->attempt_count,
                    'explanation' => $lockedActivity->explanation,
                ];
            }

            $result = $handler->evaluate(
                $configuration,
                $answer,
                is_array($progress->working_state) ? $progress->working_state : [],
            );

            if ($result['accepted'] === true) {
                $progress->working_state = $result['working_state'];
                $progress->attempt_count = (int) $progress->attempt_count + 1;
                $progress->status = $result['is_complete'] === true ? 'completed' : 'in_progress';
                $progress->completed_at = $result['is_complete'] === true ? now() : null;
                $progress->skipped_at = null;
                $progress->started_at ??= now();
                $progress->save();
            }

            return [
                ...$result,
                'progress' => $progress,
                'status' => $progress->status,
                'attempt_count' => (int) $progress->attempt_count,
                'explanation' => $result['is_complete'] === true ? $lockedActivity->explanation : null,
            ];
        });
    }

    /** @param array<string, mixed> $state */
    public function saveWorkingState(User $user, InteractiveActivity $activity, array $state): InteractiveActivityProgress
    {
        return DB::transaction(function () use ($user, $activity, $state): InteractiveActivityProgress {
            [, $progress] = $this->lockedCurrentProgress($user, $activity);

            if ($progress->status !== 'completed') {
                $progress->working_state = $state;
                $progress->status = 'in_progress';
                $progress->skipped_at = null;
                $progress->started_at ??= now();
                $progress->save();
            }

            return $progress;
        });
    }

    public function skip(User $user, InteractiveActivity $activity): InteractiveActivityProgress
    {
        return DB::transaction(function () use ($user, $activity): InteractiveActivityProgress {
            [, $progress] = $this->lockedCurrentProgress($user, $activity);

            if ($progress->status !== 'completed' && $progress->status !== 'skipped') {
                $progress->status = 'skipped';
                $progress->completed_at = null;
                $progress->skipped_at = now();
                $progress->save();
            }

            return $progress;
        });
    }

    public function resume(User $user, InteractiveActivity $activity): InteractiveActivityProgress
    {
        return DB::transaction(function () use ($user, $activity): InteractiveActivityProgress {
            [, $progress] = $this->lockedCurrentProgress($user, $activity);

            if ($progress->status !== 'completed') {
                $progress->status = 'in_progress';
                $progress->skipped_at = null;
                $progress->started_at ??= now();
                $progress->save();
            }

            return $progress;
        });
    }

    /** @return array<string, mixed> */
    public function practice(InteractiveActivity $activity): array
    {
        return $this->presenter->present($activity, null, true);
    }

    /** @return array{0: InteractiveActivity, 1: InteractiveActivityProgress} */
    private function lockedCurrentProgress(User $user, InteractiveActivity $activity): array
    {
        $lockedActivity = InteractiveActivity::query()
            ->whereKey($activity->id)
            ->lockForUpdate()
            ->firstOrFail();
        $handler = $this->registry->for($lockedActivity->activity_type);
        $configuration = $handler->normalize($lockedActivity->configuration, $lockedActivity->configuration);
        $progress = InteractiveActivityProgress::query()
            ->where('user_id', $user->id)
            ->where('interactive_activity_id', $lockedActivity->id)
            ->where('activity_revision', $lockedActivity->revision)
            ->lockForUpdate()
            ->first();

        if ($progress === null) {
            $progress = InteractiveActivityProgress::create([
                'user_id' => $user->id,
                'interactive_activity_id' => $lockedActivity->id,
                'activity_revision' => $lockedActivity->revision,
                'status' => 'in_progress',
                'working_state' => $handler->initialWorkingState($configuration, new Randomizer),
                'attempt_count' => 0,
                'started_at' => now(),
            ]);
        } elseif (! is_array($progress->working_state)) {
            $progress->working_state = $handler->initialWorkingState($configuration, new Randomizer);
            $progress->save();
        }

        return [$lockedActivity, $progress];
    }

    /** @param array<string, mixed> $answer @return array<string, mixed> */
    private function evaluatePractice(InteractiveActivity $activity, array $answer): array
    {
        $handler = $this->registry->for($activity->activity_type);
        $configuration = $handler->normalize($activity->configuration, $activity->configuration);
        $workingState = is_array($answer['_working_state'] ?? null)
            ? $answer['_working_state']
            : $handler->initialWorkingState($configuration, new Randomizer);
        unset($answer['_working_state']);
        $result = $handler->evaluate($configuration, $answer, $workingState);

        return [
            ...$result,
            'progress' => null,
            'status' => $result['is_complete'] === true ? 'practice_completed' : 'practice',
            'attempt_count' => 0,
            'payload' => $handler->learnerPayload($configuration, $result['working_state']),
            'explanation' => $result['is_complete'] === true ? $activity->explanation : null,
        ];
    }
}
