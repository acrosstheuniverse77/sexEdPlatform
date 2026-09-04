<?php

namespace App\Http\Controllers\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\UserDailyShield;
use App\Models\User;
use App\Notifications\Instructor\QuizAttemptActivityNotification;
use App\Services\GamificationService;
use App\Services\LearnerModuleCompletionService;
use App\Services\Learning\QuestionEvaluator;
use App\Services\SubscriptionService;
use App\Support\SubscriptionFeatureKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService,
        private SubscriptionService $subscriptionService,
        private LearnerModuleCompletionService $completionService,
        private QuestionEvaluator $questionEvaluator,
    ) {}

    /**
     * Show quiz start page (redirects to start method)
     */
    public function show(Quiz $quiz)
    {
        // Redirect to start method which handles the quiz taking page
        return $this->start($quiz);
    }

    /**
     * Start quiz attempt
     */
    public function start(Quiz $quiz)
    {
        /** @var User $user */
        $user = Auth::user();

        // Check enrollment
        $moduleId = $quiz->module_id ?? $quiz->lesson?->module_id;
        if (!$user->moduleEnrollments()->where('module_id', $moduleId)->exists()) {
            return redirect()->route('learner.modules.show', $moduleId)
                ->with('error', 'You must enroll in the module first.');
        }

        $module = Module::find($moduleId);
        if ($module && !$module->isLearnerVisible()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module is currently deactivated. Quiz attempts are temporarily unavailable.');
        }

        if (!$this->canStartAttempt($quiz, $user->id)) {
            $latestAttempt = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            if ($latestAttempt) {
                return redirect()->route('quizzes.result', $latestAttempt)
                    ->with('info', 'You have reached the maximum attempt limit. Your result has been recorded as final.')
                    ->with('attempt_limit_reached', true);
            }

            return redirect()->route('learner.modules.show', $moduleId)
                ->with('error', 'You have reached the maximum number of attempts for this quiz.');
        }

        $hasUnlimitedShields = $this->subscriptionService->hasFeature(
            $user,
            SubscriptionFeatureKeys::UNLIMITED_QUIZ_SHIELDS
        );

        // Check shields for users without unlimited-shields entitlement.
        if (!$hasUnlimitedShields) {
            if (UserDailyShield::getShields($user) <= 0) {
                return redirect()->route('subscription.upgrade')
                    ->with('error', 'You are out of shields for today. Refill with points or upgrade to premium for unlimited attempts!');
            }
        }

        // Load quiz with questions and options
        $quiz->load(['questions.options']);

        session()->put($this->quizAttemptStartedAtSessionKey($quiz->id), now()->timestamp);

        return view('quizzes.take', compact('quiz'));
    }

    /**
     * Submit quiz attempt
     */
    public function submit(Request $request, Quiz $quiz)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$this->canStartAttempt($quiz, $user->id)) {
            $latestAttempt = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            if ($latestAttempt) {
                return redirect()->route('quizzes.result', $latestAttempt)
                    ->with('info', 'You have reached the maximum attempt limit. Your result has been recorded as final.')
                    ->with('attempt_limit_reached', true);
            }

            return redirect()->route('quizzes.start', $quiz)
                ->with('error', 'You have reached the maximum number of attempts for this quiz.');
        }

        $startedAt = $this->resolveAttemptStartedAt($request, $quiz->id);
        $expired = $quiz->time_limit !== null
            && now()->greaterThanOrEqualTo($startedAt->copy()->addSeconds((int) $quiz->time_limit));

        $request->validate([
            'answers' => $expired ? 'nullable|array' : 'required|array',
        ]);

        // Verify enrollment
        $moduleId = $quiz->module_id ?? $quiz->lesson?->module_id;
        if (!$user->moduleEnrollments()->where('module_id', $moduleId)->exists()) {
            abort(403);
        }

        $module = Module::find($moduleId);
        if ($module && !$module->isLearnerVisible()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module is currently deactivated. Quiz attempts are temporarily unavailable.');
        }

        $hasUnlimitedShields = $this->subscriptionService->hasFeature(
            $user,
            SubscriptionFeatureKeys::UNLIMITED_QUIZ_SHIELDS
        );

        // Re-check shields before submitting (race-condition guard for limited users)
        if (!$hasUnlimitedShields && UserDailyShield::getShields($user) <= 0) {
            return redirect()->route('subscription.upgrade')
                ->with('error', 'You are out of shields for today.');
        }

        DB::beginTransaction();

        try {
            // Calculate score
            $quiz->load(['questions.options']);
            $totalQuestions = $quiz->questions->count();
            $correctAnswers = 0;
            $userAnswers = [];

            foreach ($quiz->questions as $question) {
                $selectedAnswer = ($request->input('answers', []))[$question->id] ?? null;
                $result = $this->questionEvaluator->evaluate($question, $selectedAnswer);

                $userAnswers[$question->id] = $result;

                if ($result['is_correct']) {
                    $correctAnswers++;
                }
            }

            $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
            $passed = $score >= ($quiz->passing_score ?? 70);

            // Create quiz attempt
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'quiz_id' => $quiz->id,
                'score' => $score,
                'passed' => $passed,
                'answers' => $userAnswers,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]);

            $attempt->loadMissing(['quiz.module.creator', 'user']);
            $quizInstructor = $attempt->quiz?->module?->creator;
            if ($quizInstructor && (int) $quizInstructor->id !== (int) $user->id) {
                $quizInstructor->notify(new QuizAttemptActivityNotification($attempt));
            }

            // Pass Protection: deduct 1 shield on attempt; refund on pass (net 0 if passed)
            $shieldDelta = null; // null = premium (no cost)
            if (!$hasUnlimitedShields) {
                UserDailyShield::drainShield($user);
                if ($passed) {
                    UserDailyShield::refillOne($user); // refund — pass protects your shield
                    $shieldDelta = 0;
                } else {
                    $shieldDelta = -1;
                }
            }

            // Award points based on performance
            if ($passed) {
                $points = $this->gamificationService->awardConfiguredPoints($user, 'quiz_passed', ['score' => $score]);
                $message = "Congratulations! You passed and earned {$points} points!";
            } else {
                $points = $this->gamificationService->awardConfiguredPoints($user, 'quiz_attempted');
                $message = "You earned {$points} points for trying! Keep practicing!";
            }

            $this->gamificationService->updateStreak($user);

            $this->clearAttemptStartedAt($quiz->id);

            $attemptLimitReached = !$passed && $this->hasReachedAttemptLimit($quiz, $user->id);
            $timeExpiredAutoSubmitted = $expired || $request->boolean('auto_submit');
            $completedFinalQuizModule = $this->resolveCompletedFinalQuizModule($quiz, $user->id, $passed);

            DB::commit();

            $moduleCompletionPoints = 0;
            if ($completedFinalQuizModule && $this->completionService->isFullyCompleted($user, $completedFinalQuizModule)) {
                $moduleCompletionPoints = $this->completeModuleAndAwardCompletionPoints($user, $completedFinalQuizModule);
            }

            if ($completedFinalQuizModule) {
                $completionMessage = 'Congratulations! You have successfully completed this module.';
                if ($moduleCompletionPoints > 0) {
                    $completionMessage .= " Module completion bonus: +{$moduleCompletionPoints} points.";
                }

                $completionRedirect = redirect()->route('learner.modules.completion', $completedFinalQuizModule)
                    ->with('success', $completionMessage)
                    ->with('shield_delta', $shieldDelta)
                    ->with('xp_earned', $points)
                    ->with('module_completion_points_earned', $moduleCompletionPoints);

                if ($moduleCompletionPoints > 0) {
                    $completionRedirect->with('points_earned', $moduleCompletionPoints);
                }

                return $completionRedirect;
            }

            // Redirect: lesson quizzes return to the lesson viewer; standalone go to result page
            if ($quiz->lesson_id && !$attemptLimitReached && !$timeExpiredAutoSubmitted) {
                return redirect(route('learner.lessons.show', $quiz->lesson_id) . '?quiz=1')
                    ->with('success', $message)
                    ->with('quiz_result', true)
                    ->with('quiz_attempt_id', $attempt->id)
                    ->with('shield_delta', $shieldDelta)
                    ->with('xp_earned', $points)
                    ->with('points_earned', $points);
            }

            $resultRedirect = redirect()->route('quizzes.result', $attempt)
                ->with('success', $message)
                ->with('shield_delta', $shieldDelta)
                ->with('xp_earned', $points)
                ->with('points_earned', $points);

            if ($attemptLimitReached) {
                $resultRedirect->with('attempt_limit_reached', true)
                    ->with('info', 'You have reached the maximum attempt limit. Your result has been recorded as final.');
            }

            if ($timeExpiredAutoSubmitted) {
                $resultRedirect->with('quiz_time_expired', true);
            }

            return $resultRedirect;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Quiz submission transaction failed.', [
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to submit quiz. Please try again.');
        }
    }

    /**
     * Show quiz result
     */
    public function result(QuizAttempt $attempt)
    {
        // Verify attempt belongs to authenticated user
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }

        $attempt->load(['quiz.questions.options', 'quiz.lesson.module']);

        /** @var User $user */
        $user = Auth::user();
        $hasUnlimitedShields = $this->subscriptionService->hasFeature(
            $user,
            SubscriptionFeatureKeys::UNLIMITED_QUIZ_SHIELDS
        );
        $shieldsRemaining = $hasUnlimitedShields ? null : UserDailyShield::getShields($user);
        $attemptLimit = $attempt->quiz->attempt_limit !== null ? (int) $attempt->quiz->attempt_limit : null;
        $attemptsUsed = QuizAttempt::where('quiz_id', $attempt->quiz_id)
            ->where('user_id', $user->id)
            ->count();
        $attemptsRemaining = $attemptLimit !== null
            ? max($attemptLimit - $attemptsUsed, 0)
            : null;
        $hasReachedAttemptLimit = $attemptLimit !== null && $attemptsRemaining === 0;
        $canRetry = !$hasReachedAttemptLimit && ($hasUnlimitedShields || (($shieldsRemaining ?? 0) > 0));
        $remainingAttempts = $attemptsRemaining;
        $shieldDelta = session('shield_delta');
        $xpEarned = session('xp_earned');

        $nextLesson = null;
        $lessonModule = null;
        $showCompletionModal = false;
        $certificateClaimable = false;
        if ($attempt->quiz?->lesson && $attempt->quiz->lesson?->module) {
            $currentLesson = $attempt->quiz->lesson;
            $lessonModule = $currentLesson->module;
            $nextLesson = $currentLesson->module
                ->lessons()
                ->where('is_published', true)
                ->where('order', '>', $currentLesson->order)
                ->orderBy('order')
                ->first();

            $showCompletionModal = $attempt->passed && !$nextLesson;
            $certificateClaimable = $showCompletionModal
                && $this->completionService->isFullyCompleted($user, $lessonModule);
        }

        return view('quizzes.result', compact(
            'attempt',
            'shieldsRemaining',
            'remainingAttempts',
            'attemptLimit',
            'attemptsUsed',
            'attemptsRemaining',
            'hasReachedAttemptLimit',
            'canRetry',
            'nextLesson',
            'lessonModule',
            'showCompletionModal',
            'certificateClaimable',
            'shieldDelta',
            'xpEarned'
        ));
    }

    /**
     * Show quiz history for user
     */
    public function history()
    {
        /** @var User $user */
        $user = Auth::user();

        $attempts = $user->quizAttempts()
            ->with('quiz')
            ->latest()
            ->paginate(10);

        $hasUnlimitedShields = $this->subscriptionService->hasFeature(
            $user,
            SubscriptionFeatureKeys::UNLIMITED_QUIZ_SHIELDS
        );
        $shieldsRemaining = $hasUnlimitedShields ? null : UserDailyShield::getShields($user);

        return view('quizzes.history', compact('attempts', 'shieldsRemaining'));
    }

    /**
     * Check if lesson is completed
     */
    private function checkLessonCompletion($user, $lessonId)
    {
        // Update user progress for the lesson
        // This is a simplified version - you may want more complex logic
        $lesson = \App\Models\Lesson::find($lessonId);
        if ($lesson) {
            $progress = $user->progress()->where('module_id', $lesson->module_id)->first();
            if ($progress) {
                $progress->increment('completed_lessons');
            }
        }
    }

    /**
     * Check if module is completed
     */
    private function checkModuleCompletion($user, $moduleId)
    {
        $module = \App\Models\Module::find($moduleId);
        if (!$module) return;

        $progress = $user->progress()->where('module_id', $moduleId)->first();
        if (!$progress) return;

        // Check if all lessons and quizzes are completed
        $totalLessons = $module->lessons()->count();
        $completedLessons = $progress->completed_lessons ?? 0;

        if ($completedLessons >= $totalLessons) {
            $progress->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Award completion bonus from dynamic policy values
            $this->gamificationService->awardConfiguredPoints($user, 'module_completed');
        }
    }

    private function completeModuleAndAwardCompletionPoints(User $user, Module $module): int
    {
        $enrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved->value)
            ->first();

        if (!$enrollment || $enrollment->completed_at !== null) {
            return 0;
        }

        $enrollment->update([
            'completed_at' => now(),
            'completion_percentage' => 100,
        ]);

        return $this->gamificationService->awardConfiguredPoints($user, 'module_completed');
    }

    private function canStartAttempt(Quiz $quiz, int $userId): bool
    {
        if ($quiz->attempt_limit === null) {
            return true;
        }

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->count();

        return $attemptCount < (int) $quiz->attempt_limit;
    }

    private function hasReachedAttemptLimit(Quiz $quiz, int $userId): bool
    {
        if ($quiz->attempt_limit === null) {
            return false;
        }

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->count();

        return $attemptCount >= (int) $quiz->attempt_limit;
    }

    private function resolveAttemptStartedAt(Request $request, int $quizId): Carbon
    {
        $sessionStartedAt = session()->get($this->quizAttemptStartedAtSessionKey($quizId));

        if (is_numeric($sessionStartedAt) && (int) $sessionStartedAt > 0) {
            return Carbon::createFromTimestamp((int) $sessionStartedAt);
        }

        $requestStartedAt = $request->input('started_at');
        if (is_numeric($requestStartedAt) && (int) $requestStartedAt > 0) {
            return Carbon::createFromTimestamp((int) $requestStartedAt);
        }

        return now();
    }

    private function clearAttemptStartedAt(int $quizId): void
    {
        session()->forget($this->quizAttemptStartedAtSessionKey($quizId));
    }

    private function quizAttemptStartedAtSessionKey(int $quizId): string
    {
        return 'quiz.started_at.' . $quizId;
    }

    private function resolveCompletedFinalQuizModule(Quiz $quiz, int $userId, bool $passed): ?Module
    {
        if (!$passed) {
            return null;
        }

        $module = Module::query()
            ->where('final_quiz_id', $quiz->id)
            ->first();

        if (!$module) {
            return null;
        }

        $isApprovedEnrollment = $module->enrollments()
            ->where('user_id', $userId)
            ->where('status', \App\Enums\EnrollmentStatus::Approved)
            ->exists();

        return $isApprovedEnrollment ? $module : null;
    }
}
