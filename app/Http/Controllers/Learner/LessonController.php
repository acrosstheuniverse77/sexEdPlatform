<?php

namespace App\Http\Controllers\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\InteractiveCheckpointProgress;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\QuizAttempt;
use App\Models\UserProgress;
use App\Services\GamificationService;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService,
    ) {}

    /**
     * Display a specific lesson
     */
    public function show(Lesson $lesson)
    {
        $user = Auth::user();
        $module = $lesson->module;

        // Security: Check if lesson is published.
        if (! $lesson->is_published) {
            abort(404);
        }

        if (! $module->isLearnerVisible()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module is currently deactivated. Lesson progression is temporarily unavailable.');
        }

        // Security: Check if user is enrolled
        $isEnrolled = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists();

        if (! $isEnrolled) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Please enroll in this module first.');
        }

        // Get all lessons for navigation (with topics + quiz eager-loaded for sidebar)
        $allLessons = $module->lessons()
            ->where('is_published', true)
            ->orderBy('order')
            ->with([
                'topics',
                'quiz' => fn ($q) => $q->where('is_active', true)->with('questions'),
            ])
            ->get();

        // Security: Check sequential access - must complete previous lessons first
        $currentIndex = $allLessons->search(fn ($l) => $l->id === $lesson->id);

        if ($currentIndex > 0) {
            // Check if all previous lessons are completed
            for ($i = 0; $i < $currentIndex; $i++) {
                $previousLesson = $allLessons[$i];
                $isCompleted = UserProgress::where('user_id', $user->id)
                    ->where('lesson_id', $previousLesson->id)
                    ->where('completed', true)
                    ->exists();

                if (! $isCompleted) {
                    return redirect()->route('learner.lessons.show', $previousLesson)
                        ->with('error', 'Please complete the previous lessons first.');
                }
            }
        }

        // Check if current lesson is completed
        $isLessonCompleted = UserProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('completed', true)
            ->exists();

        // Find previous and next lessons
        $previousLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        // Get completion status for all lessons (for sidebar)
        $completedLessonIds = UserProgress::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->toArray();

        $moduleCertificate = $user->certificates()
            ->where('module_id', $module->id)
            ->first();

        $allLessonsCompleted = $allLessons->count() > 0 && count($completedLessonIds) === $allLessons->count();

        $topicIds = $allLessons
            ->flatMap(fn ($moduleLesson) => $moduleLesson->topics->where('type', '!=', 'interactive_checkpoint')->pluck('id'))
            ->unique();
        $completedModuleTopicIds = LessonTopicProgress::where('user_id', $user->id)
            ->whereIn('lesson_topic_id', $topicIds)
            ->where('completed', true)
            ->pluck('lesson_topic_id')
            ->unique();
        $allTopicsCompleted = $topicIds->isEmpty() || $completedModuleTopicIds->count() === $topicIds->count();

        $lessonQuizIds = $allLessons
            ->pluck('quiz')
            ->filter()
            ->pluck('id')
            ->unique();
        $lessonQuizById = $allLessons
            ->pluck('quiz')
            ->filter()
            ->keyBy('id');
        $allLessonQuizzesCompleted = $lessonQuizIds->isEmpty() || $lessonQuizIds->every(function ($quizId) use ($user, $lessonQuizById) {
            $attemptCount = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $quizId)
                ->count();

            if ($attemptCount === 0) {
                return false;
            }

            $hasPassed = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $quizId)
                ->where('passed', true)
                ->exists();

            if ($hasPassed) {
                return true;
            }

            $attemptLimit = $lessonQuizById->get($quizId)?->attempt_limit;

            return $attemptLimit !== null && $attemptCount >= (int) $attemptLimit;
        });

        $finalQuizCompleted = true;
        if ($module->final_quiz_id) {
            $finalAttemptCount = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $module->final_quiz_id)
                ->count();

            if ($finalAttemptCount === 0) {
                $finalQuizCompleted = false;
            }

            $hasPassedFinalQuiz = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $module->final_quiz_id)
                ->where('passed', true)
                ->exists();

            $finalQuizAttemptLimit = $module->quizzes()
                ->where('id', $module->final_quiz_id)
                ->value('attempt_limit');

            $finalQuizCompleted = $hasPassedFinalQuiz
                || ($finalQuizAttemptLimit !== null && $finalAttemptCount >= (int) $finalQuizAttemptLimit);
        }

        $certificateEligible = $allLessonsCompleted
            && $allTopicsCompleted
            && $allLessonQuizzesCompleted
            && $finalQuizCompleted;

        // Get all completed topic IDs across the entire module (for sidebar display)
        $allModuleTopicIds = $allLessons->flatMap(fn ($l) => $l->topics->pluck('id'));
        $allCompletedTopicIds = [];
        if ($allModuleTopicIds->isNotEmpty()) {
            $allCompletedTopicIds = LessonTopicProgress::where('user_id', $user->id)
                ->whereIn('lesson_topic_id', $allModuleTopicIds)
                ->where('completed', true)
                ->pluck('lesson_topic_id')
                ->toArray();
        }

        // Get lesson quiz if exists — eager-load questions.options for the quiz wizard
        $lessonQuiz = $lesson->quiz()->where('is_active', true)->with('questions.options')->first();
        $quizAttempt = null;
        $quizAttempts = collect();
        $questionTypeCounts = collect();
        if ($lessonQuiz) {
            $quizAttempt = $user->quizAttempts()
                ->where('quiz_id', $lessonQuiz->id)
                ->orderByDesc('score')
                ->first();
            $quizAttempts = $user->quizAttempts()
                ->where('quiz_id', $lessonQuiz->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
            $questionTypeCounts = $lessonQuiz->questions
                ->groupBy('question_type')
                ->map->count();
        }

        // Get lesson topics with progress
        $lessonTopics = $lesson->topics()->ordered()->with('checkpointQuestions.options')->get();
        $checkpointQuestionIds = $lessonTopics->flatMap->checkpointQuestions->pluck('id');
        $checkpointProgress = InteractiveCheckpointProgress::where('user_id', $user->id)
            ->whereIn('quiz_question_id', $checkpointQuestionIds)
            ->get()
            ->keyBy('quiz_question_id');
        $completedTopicIds = [];
        if ($lessonTopics->count() > 0) {
            $completedTopicIds = LessonTopicProgress::where('user_id', $user->id)
                ->whereIn('lesson_topic_id', $lessonTopics->pluck('id'))
                ->where('completed', true)
                ->pluck('lesson_topic_id')
                ->toArray();
        }
        $resolvedCheckpointTopicIds = $checkpointProgress
            ->filter(fn (InteractiveCheckpointProgress $progress) => in_array($progress->status, ['correct', 'skipped'], true))
            ->filter(fn (InteractiveCheckpointProgress $progress) => $progress->checkpoint_block_uuid === null)
            ->pluck('lesson_topic_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $resolvedLearningItemIds = array_values(array_unique([
            ...array_map('intval', $completedTopicIds),
            ...$resolvedCheckpointTopicIds,
        ]));

        // Calculate locked topics based on prerequisite dependencies
        $lockedTopicIds = [];
        foreach ($lessonTopics as $index => $topic) {
            if ($topic->type === 'interactive_checkpoint') {
                continue;
            }

            if ($topic->is_prerequisite) {
                // Prerequisite topics must be completed sequentially
                // Lock if any previous prerequisite topics are not completed
                for ($i = 0; $i < $index; $i++) {
                    $previousTopic = $lessonTopics[$i];
                    if ($previousTopic->type === 'interactive_checkpoint') {
                        continue;
                    }

                    if ($previousTopic->is_prerequisite && ! in_array($previousTopic->id, $completedTopicIds)) {
                        $lockedTopicIds[] = $topic->id;
                        break;
                    }
                }
            }
            // Optional topics (is_prerequisite = false) are always accessible
        }

        // Determine current topic (allow navigation via URL parameter)
        $currentTopic = null;
        $currentTopicIndex = 0;

        if ($lessonTopics->count() > 0) {
            // Check if specific topic requested via URL
            $requestedTopicIndex = request()->query('topic');

            if ($requestedTopicIndex !== null && isset($lessonTopics[$requestedTopicIndex])) {
                $requestedTopic = $lessonTopics[$requestedTopicIndex];

                // Only allow access if topic is not locked
                if (! in_array($requestedTopic->id, $lockedTopicIds)) {
                    $currentTopic = $requestedTopic;
                    $currentTopicIndex = $requestedTopicIndex;
                } else {
                    // Redirect to first unlocked topic with error message
                    foreach ($lessonTopics as $index => $topic) {
                        if (! in_array($topic->id, $lockedTopicIds)) {
                            return redirect()->route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $index])
                                ->with('error', 'Please complete the required prerequisite topics first.');
                        }
                    }
                }
            }

            // If no topic specified or requested was locked, find appropriate topic
            if (! $currentTopic) {
                // Find first incomplete unlocked topic
                foreach ($lessonTopics as $index => $topic) {
                    if (! in_array($topic->id, $resolvedLearningItemIds) && ! in_array($topic->id, $lockedTopicIds)) {
                        $currentTopic = $topic;
                        $currentTopicIndex = $index;
                        break;
                    }
                }

                // If all unlocked topics are completed, show the last completed unlocked topic
                if (! $currentTopic) {
                    for ($i = $lessonTopics->count() - 1; $i >= 0; $i--) {
                        if (! in_array($lessonTopics[$i]->id, $lockedTopicIds)) {
                            $currentTopic = $lessonTopics[$i];
                            $currentTopicIndex = $i;
                            break;
                        }
                    }
                }

                // Fallback to first topic if nothing found
                if (! $currentTopic) {
                    $currentTopic = $lessonTopics->first();
                    $currentTopicIndex = 0;
                }
            }
        }

        return view('learner.lessons.show', compact(
            'lesson',
            'module',
            'previousLesson',
            'nextLesson',
            'allLessons',
            'isLessonCompleted',
            'completedLessonIds',
            'moduleCertificate',
            'allLessonsCompleted',
            'certificateEligible',
            'allCompletedTopicIds',
            'lessonQuiz',
            'quizAttempt',
            'quizAttempts',
            'questionTypeCounts',
            'lessonTopics',
            'checkpointProgress',
            'completedTopicIds',
            'resolvedCheckpointTopicIds',
            'lockedTopicIds',
            'currentTopic',
            'currentTopicIndex'
        ));
    }

    /**
     * Mark lesson as completed
     */
    public function complete(Lesson $lesson)
    {
        $user = Auth::user();
        $module = $lesson->module;

        // Security checks
        if (! $lesson->is_published) {
            abort(404);
        }

        if (! $module->isLearnerVisible()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module is currently deactivated. Lesson progression is temporarily unavailable.');
        }

        $isEnrolled = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists();

        if (! $isEnrolled) {
            return back()->with('error', 'You are not enrolled in this module.');
        }

        // Check if already completed
        $existingProgress = UserProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('completed', true)
            ->first();

        if ($existingProgress) {
            return back()->with('info', 'You have already completed this lesson.');
        }

        // Mark this specific lesson as completed
        UserProgress::create([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        // Award gamification points using dynamic policy values
        $lessonPoints = $this->gamificationService->awardConfiguredPoints($user, 'lesson_complete');
        $this->gamificationService->updateStreak($user);
        session()->flash('points_earned', $lessonPoints);

        return back()->with('success', "Lesson completed! You earned {$lessonPoints} points! 🎉");
    }

    /**
     * Mark a lesson topic as completed
     */
    public function completeTopic(LessonTopic $topic)
    {
        $user = Auth::user();
        $lesson = $topic->lesson;
        $module = $lesson->module;

        // Security checks
        if (! $lesson->is_published) {
            abort(404);
        }

        if (! $module->isLearnerVisible()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module is currently deactivated. Lesson progression is temporarily unavailable.');
        }

        $isEnrolled = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists();

        if (! $isEnrolled) {
            return back()->with('error', 'You are not enrolled in this module.');
        }

        if ($topic->type === 'interactive_checkpoint') {
            abort(404);
        }

        // Mark topic as completed
        $topic->markCompleted($user->id);

        // Award topic completion points and update streak
        $topicPoints = $this->gamificationService->awardConfiguredPoints($user, 'topic_complete');
        $this->gamificationService->updateStreak($user);
        session()->flash('points_earned', $topicPoints);

        // Check if all topics are completed to auto-complete lesson
        $allTopics = $lesson->topics()->instructional()->ordered()->get();
        $completedCount = LessonTopicProgress::where('user_id', $user->id)
            ->whereIn('lesson_topic_id', $allTopics->pluck('id'))
            ->where('completed', true)
            ->count();

        if ($completedCount === $allTopics->count()) {
            // Mark lesson as completed
            UserProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'module_id' => $module->id,
                    'completed' => true,
                    'progress_percentage' => 100,
                    'completed_at' => now(),
                ]
            );
            // Award lesson complete bonus using dynamic policy values
            $lessonCompletePoints = $this->gamificationService->awardConfiguredPoints($user, 'lesson_complete');
            session()->flash('points_earned', $lessonCompletePoints);
        }

        // Check if we should navigate to next topic
        $nextTopicIndex = request()->input('next_topic_index');
        if ($nextTopicIndex !== null) {
            return redirect()->route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $nextTopicIndex])
                ->with('success', "Topic completed! +{$topicPoints} points ✓");
        }

        return redirect()->route('learner.lessons.show', $lesson)
            ->with('success', "Topic completed! +{$topicPoints} points ✓");
    }

    /**
     * Mark a lesson topic as incomplete.
     */
    public function uncompleteTopic(LessonTopic $topic)
    {
        $user = Auth::user();
        $lesson = $topic->lesson;
        $module = $lesson->module;

        if (! $lesson->is_published) {
            abort(404);
        }

        if (! $module->isLearnerVisible()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module is currently deactivated. Lesson progression is temporarily unavailable.');
        }

        $isEnrolled = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists();

        if (! $isEnrolled) {
            return back()->with('error', 'You are not enrolled in this module.');
        }

        // Mark this topic incomplete
        LessonTopicProgress::where('user_id', $user->id)
            ->where('lesson_topic_id', $topic->id)
            ->update(['completed' => false, 'completed_at' => null]);

        // Revert lesson completion so progress is recalculated on next completion
        UserProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->update(['completed' => false, 'completed_at' => null, 'progress_percentage' => 0]);

        return redirect()->back()->with('info', 'Topic marked as incomplete.');
    }
}
