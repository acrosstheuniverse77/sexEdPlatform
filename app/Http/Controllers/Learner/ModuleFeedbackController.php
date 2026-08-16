<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\StoreModuleFeedbackRequest;
use App\Models\Module;
use App\Services\ModuleFeedbackService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ModuleFeedbackController extends Controller
{
    public function __construct(
        private readonly ModuleFeedbackService $moduleFeedbackService,
    ) {
    }

    public function store(StoreModuleFeedbackRequest $request, Module $module): RedirectResponse
    {
        try {
            if ((string) $request->input('feedback_type') === 'instructor') {
                $this->moduleFeedbackService->storeInstructorFeedback(
                    $request->user(),
                    $module,
                    (int) $request->integer('rating'),
                    (string) $request->input('review_content', '')
                );
            } else {
                $this->moduleFeedbackService->upsertLearnerFeedback(
                    $request->user(),
                    $module,
                    (int) $request->integer('rating'),
                    (string) $request->input('review_content', '')
                );
            }
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Your feedback has been saved. Thank you.');
    }
}
