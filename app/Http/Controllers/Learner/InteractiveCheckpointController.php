<?php

namespace App\Http\Controllers\Learner;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\InteractiveCheckpointProgress;
use App\Models\LessonTopicProgress;
use App\Models\QuizQuestion;
use App\Services\Learning\QuestionEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InteractiveCheckpointController extends Controller
{
    public function __construct(private QuestionEvaluator $questionEvaluator) {}

    public function submit(Request $request, QuizQuestion $question): JsonResponse
    {
        $question->load(['options', 'checkpointTopic.lesson.module']);
        $this->authorizeCheckpointAccess($question);

        $validated = $request->validate(['answer' => ['nullable']]);
        $result = $this->questionEvaluator->evaluate($question, $validated['answer'] ?? null);
        $status = $result['is_correct'] ? 'correct' : 'incorrect';

        $progress = InteractiveCheckpointProgress::firstOrNew([
            'user_id' => Auth::id(),
            'quiz_question_id' => $question->id,
        ]);

        $progress->fill([
            'lesson_topic_id' => $question->checkpoint_topic_id,
            'checkpoint_block_uuid' => $question->checkpoint_block_uuid,
            'status' => $status,
            'latest_answer' => $result,
            'is_correct' => $result['is_correct'],
            'attempt_count' => ((int) $progress->attempt_count) + 1,
            'answered_at' => now(),
            'completed_at' => now(),
        ])->save();

        $this->markBetweenTopicCheckpointComplete($question);

        return response()->json([
            'status' => $status,
            'is_correct' => $result['is_correct'],
            'result' => $result,
            'explanation' => $question->explanation,
        ]);
    }

    public function skip(QuizQuestion $question): JsonResponse
    {
        $question->load(['checkpointTopic.lesson.module']);
        $this->authorizeCheckpointAccess($question);

        InteractiveCheckpointProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'quiz_question_id' => $question->id],
            [
                'lesson_topic_id' => $question->checkpoint_topic_id,
                'checkpoint_block_uuid' => $question->checkpoint_block_uuid,
                'status' => 'skipped',
                'latest_answer' => null,
                'is_correct' => null,
                'skipped_at' => now(),
                'completed_at' => now(),
            ],
        );

        $this->markBetweenTopicCheckpointComplete($question);

        return response()->json([
            'status' => 'skipped',
            'is_correct' => null,
            'explanation' => null,
        ]);
    }

    private function authorizeCheckpointAccess(QuizQuestion $question): void
    {
        abort_unless($question->checkpoint_topic_id !== null, 404);

        $topic = $question->checkpointTopic;
        $lesson = $topic?->lesson;
        $module = $lesson?->module;

        abort_unless($topic && $lesson && $module, 404);
        abort_unless($lesson->is_published, 404);
        abort_unless($module->isLearnerVisible(), 403);

        $isEnrolled = Auth::user()->moduleEnrollments()
            ->where('module_id', $module->id)
            ->where('status', EnrollmentStatus::Approved)
            ->exists();

        abort_unless($isEnrolled, 403);
    }

    private function markBetweenTopicCheckpointComplete(QuizQuestion $question): void
    {
        $topic = $question->checkpointTopic;

        if ($topic?->type !== 'interactive_checkpoint' || $question->checkpoint_block_uuid !== null) {
            return;
        }

        LessonTopicProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'lesson_topic_id' => $topic->id],
            ['completed' => true, 'completed_at' => now()],
        );
    }
}
