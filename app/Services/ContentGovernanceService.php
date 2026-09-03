<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\ModuleRevision;
use App\Models\User;
use App\Notifications\Admin\NewModuleSubmissionNotification;
use App\Notifications\InstructorModuleReviewDecisionNotification;
use App\Notifications\InstructorModuleReviewStatusNotification;
use App\Services\Content\ContentAuthoringService;
use App\Services\Instructor\InstructorPlanCapabilityService;
use App\Services\Moderation\SourceAdapters\ModuleReviewModerationAdapter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContentGovernanceService
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public function __construct(
        private readonly AdminActivityLogService $adminActivityLogService,
        private readonly InstructorModerationPenaltyService $instructorModerationPenaltyService,
        private readonly ContentAuthoringService $contentAuthoringService,
        private readonly InstructorPlanCapabilityService $instructorPlanCapabilityService,
        private readonly ModuleReviewModerationAdapter $moduleReviewModerationAdapter,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createAdminOwnedModuleFromValidated(array $validated, User $admin): Module
    {
        return $this->createAdminOwnedModule(
            $this->contentAuthoringService->toAdminPayload($validated),
            $admin,
        );
    }

    public function submitForReview(Module $module, User $actor): ModuleReviewRequest
    {
        return DB::transaction(function () use ($module, $actor) {
            $hasActiveSubmission = ModuleReviewRequest::query()
                ->where('module_id', $module->id)
                ->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_IN_REVIEW])
                ->exists();

            if ($hasActiveSubmission) {
                throw new InvalidArgumentException('This module already has an active submission under review.');
            }

            $revision = $this->createRevisionSnapshot($module, $actor);

            $reviewRequest = ModuleReviewRequest::query()->create([
                'module_id' => $module->id,
                'module_revision_id' => $revision->id,
                'status' => self::STATUS_SUBMITTED,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            $this->moduleReviewModerationAdapter->syncSubmission($reviewRequest, $actor);

            $module->forceFill([
                'current_review_status' => self::STATUS_SUBMITTED,
            ])->save();

            User::query()
                ->role('admin')
                ->get()
                ->each(fn (User $admin) => $admin->notify(new NewModuleSubmissionNotification($reviewRequest->loadMissing('module'))));

            return $reviewRequest;
        });
    }

    public function startReview(ModuleReviewRequest $reviewRequest, User $admin): ModuleReviewRequest
    {
        return DB::transaction(function () use ($reviewRequest, $admin) {
            if (! in_array($reviewRequest->status, [self::STATUS_SUBMITTED, self::STATUS_IN_REVIEW], true)) {
                throw new InvalidArgumentException('Only submitted module requests can be moved to under review.');
            }

            $reviewRequest->loadMissing('module', 'revision');

            if ($reviewRequest->status === self::STATUS_IN_REVIEW) {
                return $reviewRequest;
            }

            $reviewRequest->forceFill([
                'status' => self::STATUS_IN_REVIEW,
            ])->save();

            $this->moduleReviewModerationAdapter->syncSubmission($reviewRequest, $admin);

            $reviewRequest->revision?->forceFill([
                'status' => self::STATUS_IN_REVIEW,
            ])->save();

            $reviewRequest->module?->forceFill([
                'current_review_status' => self::STATUS_IN_REVIEW,
            ])->save();

            $instructor = $reviewRequest->module?->created_by
                ? User::query()->find($reviewRequest->module->created_by)
                : null;

            if ($instructor) {
                $instructor->notify(new InstructorModuleReviewStatusNotification(
                    reviewRequest: $reviewRequest,
                    status: self::STATUS_IN_REVIEW,
                    title: 'Module Under Review',
                    message: 'An admin has started reviewing your submitted module.',
                ));
            }

            $this->adminActivityLogService->logModelMutation(
                action: 'content_reviews.start_review',
                entity: $reviewRequest,
                after: [
                    'module_id' => $reviewRequest->module_id,
                    'module_revision_id' => $reviewRequest->module_revision_id,
                    'status' => self::STATUS_IN_REVIEW,
                ],
                adminUserId: $admin->id,
            );

            return $reviewRequest->fresh();
        });
    }

    public function withdrawSubmission(Module $module, User $actor): ModuleReviewRequest
    {
        return DB::transaction(function () use ($module, $actor) {
            $reviewRequest = ModuleReviewRequest::query()
                ->where('module_id', $module->id)
                ->latest('id')
                ->first();

            if (! $reviewRequest) {
                throw new InvalidArgumentException('No submission found to withdraw.');
            }

            if ($reviewRequest->status !== self::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('This submission can no longer be withdrawn because review has already started.');
            }

            $reviewRequest->forceFill([
                'status' => self::STATUS_WITHDRAWN,
                'reviewed_at' => now(),
                'feedback' => 'Withdrawn by instructor before review started.',
            ])->save();

            $this->moduleReviewModerationAdapter->syncSubmission($reviewRequest, $actor);

            $reviewRequest->revision?->forceFill([
                'status' => self::STATUS_WITHDRAWN,
                'review_feedback' => 'Withdrawn by instructor before review started.',
                'reviewed_at' => now(),
            ])->save();

            $module->forceFill([
                'current_review_status' => 'draft',
            ])->save();

            User::query()
                ->role('admin')
                ->get()
                ->each(fn (User $admin) => $admin->notify(new InstructorModuleReviewStatusNotification(
                    reviewRequest: $reviewRequest,
                    status: self::STATUS_WITHDRAWN,
                    title: 'Module Submission Withdrawn',
                    message: 'An instructor withdrew a module submission before review started.',
                    actionUrl: route('admin.content-reviews.index'),
                )));

            return $reviewRequest->fresh();
        });
    }

    public function createAdminOwnedModule(array $attributes, User $admin): Module
    {
        return DB::transaction(function () use ($attributes, $admin) {
            $module = Module::query()->create([
                ...$attributes,
                'created_by' => $admin->id,
                'content_owner_type' => 'admin',
                'current_review_status' => 'approved',
                'is_published' => true,
            ]);

            $revision = ModuleRevision::query()->create([
                'module_id' => $module->id,
                'revision_number' => 1,
                'snapshot_payload' => $this->buildSnapshotPayload($module->fresh()),
                'submitted_by' => $admin->id,
                'status' => 'approved',
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            $module->forceFill([
                'published_revision_id' => $revision->id,
                'published_by_admin_id' => $admin->id,
            ])->save();

            $this->adminActivityLogService->logModelMutation(
                action: 'admin_modules.publish',
                entity: $module,
                after: $module->fresh()->only([
                    'id',
                    'title',
                    'created_by',
                    'content_owner_type',
                    'published_revision_id',
                    'published_by_admin_id',
                ]),
                adminUserId: $admin->id,
            );

            return $module->fresh();
        });
    }

    public function approveReview(ModuleReviewRequest $reviewRequest, User $admin, ?string $notes = null): ModuleRevision
    {
        return DB::transaction(function () use ($reviewRequest, $admin, $notes) {
            if ($reviewRequest->status !== self::STATUS_IN_REVIEW) {
                throw new InvalidArgumentException('Only pending review submissions can be approved.');
            }

            $reviewRequest->loadMissing('module', 'revision');

            $revision = $reviewRequest->revision;
            $module = $reviewRequest->module;

            $this->assertInstructorPublishQuotaAvailable($module);

            $revision->forceFill([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'review_feedback' => $notes,
            ])->save();

            $reviewRequest->forceFill([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'feedback' => $notes,
            ])->save();

            $this->moduleReviewModerationAdapter->syncSubmission($reviewRequest, $admin);

            $module->forceFill([
                'is_published' => true,
                'published_revision_id' => $revision->id,
                'published_by_admin_id' => $module->content_owner_type === 'instructor' ? $admin->id : null,
                'current_review_status' => 'approved',
            ])->save();

            $instructor = $module->created_by
                ? User::query()->find($module->created_by)
                : null;

            if ($instructor) {
                $instructor->notify(new InstructorModuleReviewDecisionNotification(
                    status: 'approved',
                    reviewRequest: $reviewRequest,
                    guidanceNote: $notes,
                ));
            }

            $this->adminActivityLogService->logModelMutation(
                action: 'content_reviews.approve',
                entity: $reviewRequest,
                after: [
                    'module_id' => $module->id,
                    'module_revision_id' => $revision->id,
                    'status' => 'approved',
                ],
                meta: [
                    'notes' => $notes,
                ],
                adminUserId: $admin->id,
            );

            return $revision->fresh();
        });
    }

    private function assertInstructorPublishQuotaAvailable(Module $module): void
    {
        if ((string) ($module->content_owner_type ?? '') !== 'instructor') {
            return;
        }

        $instructor = User::query()->find($module->created_by);
        if (! $instructor) {
            return;
        }

        $publishedLimit = $this->instructorPlanCapabilityService->getPublishedModuleLimit($instructor);
        if ($publishedLimit === null) {
            return;
        }

        $publishedCount = Module::query()
            ->where('created_by', $instructor->id)
            ->where('content_owner_type', 'instructor')
            ->where('is_published', true)
            ->where('id', '!=', $module->id)
            ->count();

        if ($publishedCount >= $publishedLimit && $this->instructorPlanCapabilityService->isStrictRolloutMode()) {
            throw new InvalidArgumentException(
                "This instructor has reached the published module limit ({$publishedLimit}) for the current plan."
            );
        }
    }

    public function rejectReview(
        ModuleReviewRequest $reviewRequest,
        User $admin,
        string $feedback,
        ?string $reasonCode = null,
        ?string $guidanceNote = null,
        bool $issueWarning = false,
        ?string $moderationNotes = null,
    ): ModuleRevision {
        if (trim($feedback) === '') {
            throw new InvalidArgumentException('Review feedback is required when rejecting a module submission.');
        }

        return DB::transaction(function () use ($reviewRequest, $admin, $feedback, $reasonCode, $guidanceNote, $issueWarning, $moderationNotes) {
            if ($reviewRequest->status !== self::STATUS_IN_REVIEW) {
                throw new InvalidArgumentException('Only pending review submissions can be rejected.');
            }

            $reviewRequest->loadMissing('module', 'revision');

            $revision = $reviewRequest->revision;
            $module = $reviewRequest->module;

            $revision->forceFill([
                'status' => 'needs_revision',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'review_feedback' => $feedback,
            ])->save();

            $reviewRequest->forceFill([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'feedback' => $feedback,
            ])->save();

            $this->moduleReviewModerationAdapter->syncSubmission($reviewRequest, $admin);

            $module->forceFill([
                'current_review_status' => 'needs_revision',
            ])->save();

            $instructor = User::query()->find($module->created_by);
            $notificationGuidance = trim((string) ($moderationNotes ?? '')) !== ''
                ? $moderationNotes
                : (trim($guidanceNote ?? '') !== '' ? $guidanceNote : $feedback);

            $violation = null;
            if ($instructor && $issueWarning) {
                $violation = $this->instructorModerationPenaltyService->recordViolation(
                    $instructor,
                    $module,
                    $reviewRequest,
                    $reasonCode ?? 'other',
                    trim($guidanceNote ?? '') !== '' ? $guidanceNote : strip_tags((string) $notificationGuidance),
                );

                $instructor->notify(new InstructorModuleReviewDecisionNotification(
                    status: 'rejected',
                    reviewRequest: $reviewRequest,
                    reasonCode: $reasonCode,
                    guidanceNote: $notificationGuidance,
                    penaltySummary: $violation?->suggested_penalty_action,
                ));
            } elseif ($instructor) {
                $instructor->notify(new InstructorModuleReviewDecisionNotification(
                    status: 'rejected',
                    reviewRequest: $reviewRequest,
                    reasonCode: $reasonCode,
                    guidanceNote: $notificationGuidance,
                ));
            }

            $this->adminActivityLogService->logModelMutation(
                action: 'content_reviews.reject',
                entity: $reviewRequest,
                after: [
                    'module_id' => $module->id,
                    'module_revision_id' => $revision->id,
                    'status' => 'rejected',
                ],
                meta: [
                    'feedback' => $feedback,
                    'reason_code' => $reasonCode,
                    'guidance_note' => $guidanceNote,
                    'moderation_notes' => $moderationNotes,
                    'issue_warning' => $issueWarning,
                    'suggested_penalty_action' => $violation?->suggested_penalty_action,
                ],
                adminUserId: $admin->id,
            );

            return $revision->fresh();
        });
    }

    public function archiveReview(ModuleReviewRequest $reviewRequest, User $admin, ?string $notes = null): ModuleReviewRequest
    {
        return DB::transaction(function () use ($reviewRequest, $admin, $notes) {
            if (! in_array($reviewRequest->status, [self::STATUS_SUBMITTED, self::STATUS_IN_REVIEW], true)) {
                throw new InvalidArgumentException('Only pending review submissions can be archived.');
            }

            $reviewRequest->loadMissing('module', 'revision');

            $reviewRequest->forceFill([
                'status' => 'archived',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'feedback' => $notes,
            ])->save();

            $reviewRequest->revision?->forceFill([
                'status' => 'needs_revision',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'review_feedback' => $notes,
            ])->save();

            $reviewRequest->module?->forceFill([
                'current_review_status' => 'needs_revision',
            ])->save();

            $this->adminActivityLogService->logModelMutation(
                action: 'content_reviews.archive',
                entity: $reviewRequest,
                after: [
                    'module_id' => $reviewRequest->module_id,
                    'module_revision_id' => $reviewRequest->module_revision_id,
                    'status' => 'archived',
                ],
                meta: [
                    'notes' => $notes,
                ],
                adminUserId: $admin->id,
            );

            return $reviewRequest->fresh();
        });
    }

    public function createRevisionSnapshot(Module $module, User $actor): ModuleRevision
    {
        $module->loadMissing([
            'lessons.topics.interactiveActivities',
            'quizzes.questions.options',
            'finalQuiz.questions.options',
        ]);

        $nextRevisionNumber = ((int) $module->revisions()->max('revision_number')) + 1;

        $revision = ModuleRevision::query()->create([
            'module_id' => $module->id,
            'revision_number' => $nextRevisionNumber,
            'snapshot_payload' => $this->buildSnapshotPayload($module),
            'submitted_by' => $actor->id,
            'status' => self::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $module->forceFill([
            'current_review_status' => self::STATUS_SUBMITTED,
        ])->save();

        return $revision;
    }

    private function buildSnapshotPayload(Module $module): array
    {
        return [
            'module' => $module->only([
                'id',
                'title',
                'description',
                'thumbnail',
                'min_age',
                'max_age',
                'age_specific_content',
                'order',
                'duration_minutes',
                'is_published',
                'is_premium',
                'access_type',
                'price_amount',
                'price_currency',
                'enrollment_limit',
                'enrollment_mode',
                'final_quiz_id',
                'certificate_pass_score',
                'created_by',
                'content_owner_type',
            ]),
            'lessons' => $module->lessons->map(fn ($lesson) => [
                'attributes' => $lesson->only([
                    'id',
                    'module_id',
                    'title',
                    'description',
                    'order',
                    'duration',
                    'is_published',
                    'text_content',
                ]),
                'topics' => $lesson->topics->map(fn ($topic) => [
                    ...$topic->only([
                        'id',
                        'lesson_id',
                        'title',
                        'type',
                        'video_provider',
                        'video_id',
                        'video_file_path',
                        'text_content',
                        'content_blocks',
                        'file_path',
                        'quiz_id',
                        'interactive_config',
                        'image_attachments',
                        'slideshow_data',
                        'duration',
                        'is_prerequisite',
                        'order',
                    ]),
                    'interactive_activities' => $topic->interactiveActivities->map(fn ($activity) => $activity->only([
                        'id',
                        'lesson_topic_id',
                        'placement',
                        'block_uuid',
                        'activity_type',
                        'title',
                        'instructions',
                        'explanation',
                        'configuration',
                        'revision',
                    ]))->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
            'quizzes' => $module->quizzes->map(fn ($quiz) => [
                'attributes' => $quiz->only([
                    'id',
                    'module_id',
                    'lesson_id',
                    'title',
                    'description',
                    'passing_score',
                    'time_limit',
                    'is_active',
                ]),
                'questions' => $quiz->questions->map(fn ($question) => [
                    'attributes' => $question->only([
                        'id',
                        'quiz_id',
                        'question_text',
                        'question_type',
                        'points',
                        'order',
                        'acceptable_answers',
                        'case_sensitive',
                        'word_bank',
                        'image_path',
                    ]),
                    'options' => $question->options->map(fn ($option) => $option->only([
                        'id',
                        'quiz_question_id',
                        'option_text',
                        'is_correct',
                        'order',
                    ]))->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
