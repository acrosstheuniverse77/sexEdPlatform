<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleFeedback;
use App\Models\InstructorFeedback;
use App\Models\User;
use App\Notifications\Instructor\ModuleFeedbackSubmittedNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ModuleFeedbackService
{
    public function __construct(
        private readonly LearnerModuleCompletionService $completionService,
    ) {
    }

    public function upsertLearnerFeedback(User $learner, Module $module, int $rating, string $reviewHtml): ModuleFeedback
    {
        $eligibility = $this->completionService->reviewEligibility($learner, $module);
        if (!$eligibility['eligible']) {
            throw new RuntimeException((string) ($eligibility['reason'] ?? 'You are not allowed to submit feedback yet.'));
        }

        $sanitizedReview = $this->sanitizeOptionalHtml($reviewHtml);

        return DB::transaction(function () use ($learner, $module, $rating, $sanitizedReview) {
            $feedback = ModuleFeedback::query()->where([
                'module_id' => $module->id,
                'learner_id' => $learner->id,
            ])->first();

            $isNew = $feedback === null;
            $feedback ??= new ModuleFeedback([
                'module_id' => $module->id,
                'learner_id' => $learner->id,
            ]);

            $feedback->fill([
                'rating' => $rating,
                'review_html' => $sanitizedReview,
                'submitted_at' => $feedback->submitted_at ?? now(),
                'last_edited_at' => $isNew ? null : now(),
            ]);
            $feedback->save();

            $feedback->loadMissing(['module.creator', 'learner']);
            $moduleOwner = $feedback->module?->creator;
            if ($isNew && $moduleOwner && (int) $moduleOwner->id !== (int) $learner->id) {
                $moduleOwner->notify(new ModuleFeedbackSubmittedNotification($feedback));
            }

            return $feedback->fresh(['learner']);
        });
    }

    public function storeInstructorFeedback(User $learner, Module $module, int $rating, string $reviewHtml): InstructorFeedback
    {
        $eligibility = $this->completionService->reviewEligibility($learner, $module);
        if (!$eligibility['eligible']) {
            throw new RuntimeException((string) ($eligibility['reason'] ?? 'You are not allowed to submit feedback yet.'));
        }

        $module->loadMissing('creator');
        $instructor = $module->creator;

        if (!$instructor) {
            throw new RuntimeException('This module does not have an instructor to review.');
        }

        if ((int) $instructor->id === (int) $learner->id) {
            throw new RuntimeException('You cannot submit instructor feedback for yourself.');
        }

        $sanitizedReview = $this->sanitizeOptionalHtml($reviewHtml);

        return DB::transaction(function () use ($learner, $module, $instructor, $rating, $sanitizedReview) {
            $exists = InstructorFeedback::query()
                ->where('instructor_id', $instructor->id)
                ->where('learner_id', $learner->id)
                ->exists();

            if ($exists) {
                throw new RuntimeException('You have already submitted instructor feedback for this instructor.');
            }

            return InstructorFeedback::query()->create([
                'instructor_id' => $instructor->id,
                'learner_id' => $learner->id,
                'source_module_id' => $module->id,
                'rating' => $rating,
                'review_html' => $sanitizedReview,
                'submitted_at' => now(),
            ]);
        });
    }

    public function upsertInstructorReply(User $instructor, ModuleFeedback $feedback, string $replyHtml): ModuleFeedback
    {
        $feedback->loadMissing('module');
        $module = $feedback->module;

        if (!$module || (int) $module->created_by !== (int) $instructor->id) {
            throw new RuntimeException('You are not allowed to reply to this review.');
        }

        $sanitizedReply = $this->sanitizeHtml($replyHtml);
        if (trim(strip_tags($sanitizedReply)) === '') {
            throw new RuntimeException('Reply content cannot be empty.');
        }

        $feedback->update([
            'instructor_reply_html' => $sanitizedReply,
        ]);

        return $feedback->fresh(['learner']);
    }

    public function sanitizeHtml(string $html): string
    {
        return trim((string) strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote>'));
    }

    public function sanitizeOptionalHtml(string $html): ?string
    {
        $sanitized = $this->sanitizeHtml($html);

        return trim(strip_tags($sanitized)) === '' ? null : $sanitized;
    }
}
