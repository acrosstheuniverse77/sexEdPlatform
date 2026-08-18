<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\InstructorFeedback;
use App\Models\Module;
use App\Services\LearnerModuleCompletionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleReviewPageController extends Controller
{
    public function __construct(
        private readonly LearnerModuleCompletionService $completionService,
    ) {
    }

    public function index(Request $request, Module $module): View
    {
        $sort = (string) $request->string('sort', 'newest');
        $search = trim((string) $request->string('search'));
        $exactRating = $request->filled('rating') ? (int) $request->integer('rating') : null;
        $minRating = $request->filled('min_rating') ? (int) $request->integer('min_rating') : null;

        $reviewsQuery = $module->feedback()->with(['learner.learnerProfile']);

        if ($search !== '') {
            $reviewsQuery->where(function ($query) use ($search) {
                $query->where('review_html', 'like', '%' . $search . '%')
                    ->orWhereHas('learner', function ($learnerQuery) use ($search) {
                        $learnerQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($exactRating !== null && $exactRating >= 1 && $exactRating <= 5) {
            $reviewsQuery->where('rating', $exactRating);
        }

        if ($minRating !== null && $minRating >= 1 && $minRating <= 5) {
            $reviewsQuery->where('rating', '>=', $minRating);
        }

        if ($sort === 'highest') {
            $reviewsQuery->orderByDesc('rating')->orderByDesc('created_at');
        } elseif ($sort === 'lowest') {
            $reviewsQuery->orderBy('rating')->orderByDesc('created_at');
        } else {
            $reviewsQuery->latest('created_at');
            $sort = 'newest';
        }

        $reviews = $reviewsQuery->paginate(10)->withQueryString();

        $user = $request->user();
        $module->loadMissing('creator');
        $eligibility = $this->completionService->reviewEligibility($user, $module);
        $userFeedback = $module->feedback()->where('learner_id', $user->id)->first();
        $userInstructorFeedback = $module->creator
            ? InstructorFeedback::query()
                ->where('instructor_id', $module->creator->id)
                ->where('learner_id', $user->id)
                ->first()
            : null;

        $summary = [
            'average' => round((float) ($module->feedback()->avg('rating') ?? 0), 1),
            'count' => (int) $module->feedback()->count(),
            'distribution' => collect(range(1, 5))
                ->mapWithKeys(fn (int $rating) => [$rating => (int) $module->feedback()->where('rating', $rating)->count()]),
        ];

        $instructorReviews = $module->creator
            ? InstructorFeedback::query()
                ->where('instructor_id', $module->creator->id)
                ->with(['learner.learnerProfile', 'sourceModule:id,title'])
                ->latest('created_at')
                ->paginate(10, ['*'], 'instructor_page')
                ->withQueryString()
            : null;

        $instructorSummary = [
            'average' => $module->creator ? round((float) (InstructorFeedback::query()->where('instructor_id', $module->creator->id)->avg('rating') ?? 0), 1) : 0,
            'count' => $module->creator ? (int) InstructorFeedback::query()->where('instructor_id', $module->creator->id)->count() : 0,
        ];

        return view('learner.modules.reviews', [
            'module' => $module,
            'reviews' => $reviews,
            'sort' => $sort,
            'search' => $search,
            'exactRating' => $exactRating,
            'minRating' => $minRating,
            'summary' => $summary,
            'canSubmitReview' => (bool) $eligibility['eligible'],
            'reviewBlocker' => $eligibility['reason'],
            'userFeedback' => $userFeedback,
            'instructorReviews' => $instructorReviews,
            'instructorSummary' => $instructorSummary,
            'userInstructorFeedback' => $userInstructorFeedback,
        ]);
    }
}
