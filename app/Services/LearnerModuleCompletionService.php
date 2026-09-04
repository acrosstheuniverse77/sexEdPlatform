<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserProgress;

class LearnerModuleCompletionService
{
    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function reviewEligibility(User $user, Module $module): array
    {
        $reason = $this->completionBlockerReason($user, $module, requirePassingLessonQuizzes: false);

        return [
            'eligible' => $reason === null,
            'reason' => $reason,
        ];
    }

    public function isFullyCompleted(User $user, Module $module): bool
    {
        return $this->completionBlockerReason($user, $module, requirePassingLessonQuizzes: true) === null;
    }

    public function completionBlockerReason(User $user, Module $module, bool $requirePassingLessonQuizzes = true): ?string
    {
        $enrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->first();

        if (!$enrollment) {
            return 'You must be enrolled in this module.';
        }

        if ($enrollment->completed_at !== null || (int) $enrollment->completion_percentage >= 100) {
            return null;
        }

        $lessons = $module->lessons()
            ->where('is_published', true)
            ->with([
                'topics',
                'quiz' => fn ($query) => $query->where('is_active', true),
            ])
            ->get();

        if ($lessons->isEmpty()) {
            return 'No published lessons are available yet for this module.';
        }

        $completedLessonIds = UserProgress::query()
            ->where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->unique();

        if ($completedLessonIds->count() < $lessons->count()) {
            return 'Complete all lessons before submitting feedback.';
        }

        $topicIds = $lessons->flatMap(fn ($lesson) => $lesson->topics->pluck('id'))->unique();
        if ($topicIds->isNotEmpty()) {
            $completedTopicIds = LessonTopicProgress::query()
                ->where('user_id', $user->id)
                ->whereIn('lesson_topic_id', $topicIds)
                ->where('completed', true)
                ->pluck('lesson_topic_id')
                ->unique();

            if ($completedTopicIds->count() < $topicIds->count()) {
                return 'Complete all lesson topics before submitting feedback.';
            }
        }

        $lessonQuizIds = $lessons
            ->pluck('quiz')
            ->filter()
            ->pluck('id')
            ->unique();

        if ($lessonQuizIds->isNotEmpty()) {
            $lessonQuizById = $lessons
                ->pluck('quiz')
                ->filter()
                ->keyBy('id');

            $allLessonQuizzesCompleted = $lessonQuizIds->every(function ($quizId) use ($user, $lessonQuizById, $requirePassingLessonQuizzes) {
                $attemptCount = QuizAttempt::query()
                    ->where('user_id', $user->id)
                    ->where('quiz_id', $quizId)
                    ->whereNotNull('completed_at')
                    ->count();

                if ($attemptCount === 0) {
                    return false;
                }

                if (! $requirePassingLessonQuizzes) {
                    return true;
                }

                $hasPassed = QuizAttempt::query()
                    ->where('user_id', $user->id)
                    ->where('quiz_id', $quizId)
                    ->where('passed', true)
                    ->exists();

                if ($hasPassed) {
                    return true;
                }

                $attemptLimit = $lessonQuizById->get($quizId)?->attempt_limit;

                return $attemptLimit !== null && $attemptCount >= (int) $attemptLimit;
            });

            if (!$allLessonQuizzesCompleted) {
                return 'Complete all lesson quizzes before submitting feedback.';
            }
        }

        if ($module->final_quiz_id) {
            $finalAttemptCount = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('quiz_id', $module->final_quiz_id)
                ->count();

            $hasPassedFinalQuiz = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('quiz_id', $module->final_quiz_id)
                ->where('passed', true)
                ->exists();

            $finalQuizAttemptLimit = $module->quizzes()
                ->where('id', $module->final_quiz_id)
                ->value('attempt_limit');

            $isFinalQuizCompleted = $hasPassedFinalQuiz
                || ($finalQuizAttemptLimit !== null && $finalAttemptCount >= (int) $finalQuizAttemptLimit);

            if (!$isFinalQuizCompleted) {
                return 'Complete the final quiz before submitting feedback.';
            }
        }

        return null;
    }
}
