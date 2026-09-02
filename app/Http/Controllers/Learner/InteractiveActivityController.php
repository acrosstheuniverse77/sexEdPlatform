<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Services\Learning\InteractiveActivities\InteractiveActivityPresenter;
use App\Services\Learning\InteractiveActivities\InteractiveActivityProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InteractiveActivityController extends Controller
{
    public function __construct(
        private readonly InteractiveActivityProgressService $progress,
        private readonly InteractiveActivityPresenter $presenter,
    ) {}

    public function show(InteractiveActivity $interactiveActivity): JsonResponse
    {
        $this->authorizeActivity(request(), $interactiveActivity, false);
        $progress = null;

        try {
            $progress = $this->progress->stateFor(Auth::user(), $interactiveActivity);
        } catch (\Throwable) {
            // The presenter returns the safe unavailable fallback for malformed stored data.
        }

        return response()->json($this->presenter->present($interactiveActivity, $progress));
    }

    public function match(Request $request, InteractiveActivity $interactiveActivity): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer'],
            'left_id' => ['required', 'string'],
            'right_id' => ['required', 'string'],
            'practice' => ['sometimes', 'boolean'],
            'working_state' => ['sometimes', 'array'],
        ]);
        $this->authorizeActivity($request, $interactiveActivity);

        return $this->evaluationResponse($interactiveActivity, $this->progress->evaluate(
            Auth::user(),
            $interactiveActivity,
            [
                'left_id' => $validated['left_id'],
                'right_id' => $validated['right_id'],
                '_working_state' => $validated['working_state'] ?? null,
            ],
            (bool) ($validated['practice'] ?? false),
        ));
    }

    public function checkSequence(Request $request, InteractiveActivity $interactiveActivity): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer'],
            'item_order' => ['required', 'array'],
            'item_order.*' => ['string'],
            'practice' => ['sometimes', 'boolean'],
            'working_state' => ['sometimes', 'array'],
        ]);
        $this->authorizeActivity($request, $interactiveActivity);

        return $this->evaluationResponse($interactiveActivity, $this->progress->evaluate(
            Auth::user(),
            $interactiveActivity,
            [
                'item_order' => $validated['item_order'],
                '_working_state' => $validated['working_state'] ?? null,
            ],
            (bool) ($validated['practice'] ?? false),
        ));
    }

    public function saveState(Request $request, InteractiveActivity $interactiveActivity): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer'],
            'state' => ['required', 'array'],
        ]);
        $this->authorizeActivity($request, $interactiveActivity);
        $progress = $this->progress->saveWorkingState(Auth::user(), $interactiveActivity, $validated['state']);

        return response()->json($this->stateResponse($interactiveActivity, $progress));
    }

    public function skip(Request $request, InteractiveActivity $interactiveActivity): JsonResponse
    {
        $request->validate(['revision' => ['required', 'integer']]);
        $this->authorizeActivity($request, $interactiveActivity);
        $progress = $this->progress->skip(Auth::user(), $interactiveActivity);

        return response()->json($this->stateResponse($interactiveActivity, $progress));
    }

    public function resume(Request $request, InteractiveActivity $interactiveActivity): JsonResponse
    {
        $request->validate(['revision' => ['required', 'integer']]);
        $this->authorizeActivity($request, $interactiveActivity);
        $progress = $this->progress->resume(Auth::user(), $interactiveActivity);

        return response()->json($this->stateResponse($interactiveActivity, $progress));
    }

    public function practice(Request $request, InteractiveActivity $interactiveActivity): JsonResponse
    {
        $request->validate(['revision' => ['required', 'integer']]);
        $this->authorizeActivity($request, $interactiveActivity);

        return response()->json($this->progress->practice($interactiveActivity));
    }

    private function authorizeActivity(Request $request, InteractiveActivity $activity, bool $checkRevision = true): void
    {
        $activity->loadMissing('lessonTopic.lesson.module');
        $lesson = $activity->lessonTopic?->lesson;
        $module = $lesson?->module;

        abort_unless($activity->lessonTopic && $lesson && $module, 404);
        abort_unless($lesson->is_published, 404);
        abort_unless($module->isLearnerVisible(), 403);
        abort_unless(Auth::user()->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists(), 403);

        if ($checkRevision && (int) $request->input('revision') !== (int) $activity->revision) {
            abort(response()->json(['message' => 'Activity changed. Reload to continue.'], 409));
        }
    }

    /** @param array<string, mixed> $result */
    private function evaluationResponse(InteractiveActivity $activity, array $result): JsonResponse
    {
        $practice = $result['progress'] === null;
        $presentation = $practice
            ? null
            : $this->presenter->present($activity, $result['progress']);

        return response()->json([
            'available' => $presentation['available'] ?? true,
            'id' => $activity->id,
            'type' => $activity->activity_type->value,
            'revision' => (int) $activity->revision,
            'status' => $result['status'],
            'accepted' => $result['accepted'],
            'is_correct' => $result['is_correct'],
            'is_complete' => $result['is_complete'],
            'attempt_count' => $result['attempt_count'],
            'payload' => $result['payload'] ?? ($presentation['payload'] ?? null),
            'explanation' => $result['explanation'],
        ]);
    }

    /** @return array<string, mixed> */
    private function stateResponse(InteractiveActivity $activity, InteractiveActivityProgress $progress): array
    {
        $presentation = $this->presenter->present($activity, $progress);

        return [
            ...$presentation,
            'attempt_count' => (int) $progress->attempt_count,
        ];
    }
}
