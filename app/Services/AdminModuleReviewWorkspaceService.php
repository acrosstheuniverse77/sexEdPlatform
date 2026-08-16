<?php

namespace App\Services;

use App\Enums\ModuleReviewRejectionReason;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ModuleReviewRequest;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminModuleReviewWorkspaceService
{
    public function compose(ModuleReviewRequest $reviewRequest): array
    {
        $reviewRequest->loadMissing([
            'module',
            'revision',
            'reviewer',
            'revision.submitter',
            'module.publisher',
            'module.lessons.topics',
            'module.quizzes.questions.options',
            'module.finalQuiz.questions.options',
        ]);

        $snapshot = $reviewRequest->revision?->snapshot_payload ?? [];
        $module = $reviewRequest->module;

        $moduleData = is_array($snapshot['module'] ?? null)
            ? $snapshot['module']
            : [];

        $lessons = collect($this->extractLessons($snapshot, $module))
            ->map(function (array $lesson): array {
                $requirementType = $this->lessonRequirementType($lesson);

                return [
                    ...$lesson,
                    'requirement_type' => $requirementType,
                    'requirement_label' => $requirementType === 'required' ? 'Required Lesson' : 'Optional Lesson',
                ];
            })
            ->values()
            ->all();

        $quizzes = $this->extractQuizzes($snapshot, $module);

        $thumbnailUrl = data_get($moduleData, 'thumbnail_url');
        if (!is_string($thumbnailUrl) || trim($thumbnailUrl) === '') {
            $thumbnailUrl = $module?->thumbnail_url
                ?? $this->resolveMediaUrl((string) data_get($moduleData, 'thumbnail', ''), 'modules');
        }

        $topicCount = collect($lessons)
            ->sum(fn (array $lesson) => count((array) data_get($lesson, 'topics', [])));

        $quizzesByLesson = collect($quizzes)
            ->groupBy(fn (array $quiz) => (int) data_get($quiz, 'attributes.lesson_id', 0));

        $finalQuizzes = $quizzesByLesson->get(0, collect())->values()->all();

        $instructor = $this->resolveInstructor($reviewRequest);
        if ($instructor) {
            $instructor->loadMissing([
                'profile',
                'learnerProfile.city',
                'learnerProfile.barangayLocation',
                'instructorProfile',
                'adminCreatorProfile',
            ]);
        }

        $moduleOwnerType = strtolower((string) data_get($moduleData, 'content_owner_type', $module?->content_owner_type ?? ''));
        if (!in_array($moduleOwnerType, ['admin', 'platform', 'instructor'], true)) {
            $moduleOwnerType = strtolower((string) ($instructor?->role ?? '')) === 'admin'
                ? 'admin'
                : 'instructor';
        }

        $isPlatformOwned = in_array($moduleOwnerType, ['admin', 'platform'], true);
        $creatorAffiliation = $isPlatformOwned
            ? (string) data_get($instructor?->adminCreatorProfile, 'affiliation', 'Conscious Connections Team')
            : 'Independent Instructor';

        $moderationProfile = $instructor?->moderationProfile()->first();
        $recentViolations = $instructor
            ? $instructor->violationHistories()->latest('id')->take(20)->get()
            : collect();

        $instructorPreview = $this->buildInstructorPreview($instructor, $recentViolations, $moderationProfile);

        return [
            'module' => [
                ...$moduleData,
                'id' => data_get($moduleData, 'id', $reviewRequest->module_id),
                'title' => data_get($moduleData, 'title', $reviewRequest->module_title),
                'content_owner_type' => $moduleOwnerType,
                'thumbnail_url' => $thumbnailUrl,
                'age_group' => $module ? implode(' / ', $module->learnerCategoryLabels()) : $this->ageGroupLabel(
                    data_get($moduleData, 'min_age', $module?->min_age),
                    data_get($moduleData, 'max_age', $module?->max_age),
                ),
                'enrollment_mode' => $this->humanizeLabel((string) data_get($moduleData, 'enrollment_mode', $module?->enrollment_mode), true),
                'access_type' => $this->humanizeLabel((string) data_get($moduleData, 'access_type', $module?->access_type), true),
                'enrollment_limit' => data_get($moduleData, 'enrollment_limit', $module?->enrollment_limit),
                'price_amount' => data_get($moduleData, 'price_amount', $module?->price_amount),
                'price_currency' => data_get($moduleData, 'price_currency', $module?->price_currency),
                'submission_date' => optional($reviewRequest->submitted_at)?->toDateTimeString(),
                'status' => $reviewRequest->status,
                'status_label' => $this->humanizeLabel($reviewRequest->status, true),
            ],
            'hierarchy' => [
                'lessons' => $lessons,
                'quizzes' => $quizzes,
                'quizzes_by_lesson' => $quizzesByLesson->map(fn ($group) => $group->values()->all())->all(),
                'final_quizzes' => $finalQuizzes,
                'lesson_count' => count($lessons),
                'lesson_topic_count' => $topicCount,
                'quiz_count' => count($quizzes),
                'stepper' => [
                    ...collect($lessons)->map(fn (array $lesson) => [
                        'type' => 'lesson',
                        'id' => (int) data_get($lesson, 'attributes.id', 0),
                        'title' => data_get($lesson, 'attributes.title', 'Untitled Lesson'),
                    ])->values()->all(),
                    ...collect($finalQuizzes)->map(fn (array $quiz) => [
                        'type' => 'final_quiz',
                        'id' => (int) data_get($quiz, 'attributes.id', 0),
                        'title' => data_get($quiz, 'attributes.title', 'Final Quiz'),
                    ])->values()->all(),
                ],
            ],
            'instructor' => [
                'id' => $instructor?->id,
                'name' => $instructor?->name ?? $reviewRequest->revision?->submitter?->name ?? 'Unknown Instructor',
                'role' => strtolower((string) ($instructor?->role ?? ($isPlatformOwned ? 'admin' : 'instructor'))),
                'is_platform_owner' => $isPlatformOwned,
                'affiliation' => $creatorAffiliation,
                'avatar' => $this->resolveMediaUrl(
                    (string) (
                        data_get($instructor?->instructorProfile, 'profile_photo_path')
                        ?? data_get($instructor?->learnerProfile, 'avatar_path')
                        ?? data_get($instructor?->profile, 'avatar')
                        ?? ''
                    ),
                    'avatars',
                ),
            ],
            'moderation' => [
                'warning_count' => $moderationProfile?->warning_count ?? 0,
                'current_restriction_status' => $moderationProfile?->current_restriction_status,
                'current_restriction_label' => $this->humanizeLabel((string) ($moderationProfile?->current_restriction_status ?? 'none'), true),
                'restriction_ends_at' => optional($moderationProfile?->restriction_ends_at)?->toDateTimeString(),
                'last_violation_at' => optional($moderationProfile?->last_violation_at)?->toDateTimeString(),
                'recent_violations' => $recentViolations->take(5)->map(fn ($violation) => [
                    'id' => $violation->id,
                    'reason_code' => $violation->reason_code,
                    'reason_label' => ModuleReviewRejectionReason::tryFrom((string) $violation->reason_code)?->label() ?? $this->humanizeLabel((string) $violation->reason_code, true),
                    'guidance_note' => $violation->guidance_note,
                    'violation_sequence' => $violation->violation_sequence,
                    'suggested_penalty_action' => $violation->suggested_penalty_action,
                    'suggested_penalty_label' => $this->humanizeLabel((string) $violation->suggested_penalty_action, true),
                    'confirmed_penalty_action' => $violation->confirmed_penalty_action,
                    'confirmed_penalty_label' => $this->humanizeLabel((string) $violation->confirmed_penalty_action, true),
                    'created_at' => optional($violation->created_at)?->toDateTimeString(),
                ])->all(),
            ],
            'instructor_preview' => $instructorPreview,
        ];
    }

    private function resolveInstructor(ModuleReviewRequest $reviewRequest): ?User
    {
        $moduleInstructorId = $reviewRequest->module?->created_by;
        if ($moduleInstructorId) {
            return User::query()->find($moduleInstructorId);
        }

        return $reviewRequest->revision?->submitter;
    }

    public function resolvePreviewNode(ModuleReviewRequest $reviewRequest, string $nodeType, int $nodeId): ?array
    {
        $snapshot = $reviewRequest->revision?->snapshot_payload ?? [];

        if ($nodeType === 'topic') {
            foreach ((array) ($snapshot['lessons'] ?? []) as $lesson) {
                foreach ((array) data_get($lesson, 'topics', []) as $topic) {
                    if ((int) data_get($topic, 'id') !== $nodeId) {
                        continue;
                    }

                    return [
                        'type' => 'topic',
                        'id' => $nodeId,
                        'title' => data_get($topic, 'title'),
                        'topic_type' => data_get($topic, 'type'),
                        'topic_type_label' => $this->humanizeLabel((string) data_get($topic, 'type'), true),
                        'text_content' => $this->sanitizeRichContent((string) data_get($topic, 'text_content', '')),
                        'file_path' => data_get($topic, 'file_path'),
                        'file_url' => $this->resolveMediaUrl((string) data_get($topic, 'file_path', '')),
                        'video_id' => data_get($topic, 'video_id'),
                        'video_provider' => data_get($topic, 'video_provider'),
                        'video_url' => $this->buildVideoUrl(
                            (string) data_get($topic, 'video_provider', ''),
                            (string) data_get($topic, 'video_id', ''),
                        ),
                        'video_file_url' => $this->resolveMediaUrl((string) data_get($topic, 'video_file_path', ''), 'lesson-videos'),
                        'image_attachments' => array_values((array) data_get($topic, 'image_attachments', [])),
                        'image_attachment_urls' => $this->extractAttachmentUrls((array) data_get($topic, 'image_attachments', [])),
                        'slideshow_data' => array_values((array) data_get($topic, 'slideshow_data', [])),
                    ];
                }
            }

            $topic = $reviewRequest->module?->lessons
                ?->flatMap(fn (Lesson $lesson) => $lesson->topics)
                ?->firstWhere('id', $nodeId);

            if (!$topic) {
                return null;
            }

            return [
                'type' => 'topic',
                'id' => $nodeId,
                'title' => $topic->title,
                'topic_type' => $topic->type,
                'topic_type_label' => $this->humanizeLabel((string) $topic->type, true),
                'text_content' => $this->sanitizeRichContent((string) ($topic->text_content ?? '')),
                'file_path' => $topic->file_path,
                'file_url' => $this->resolveMediaUrl((string) ($topic->file_path ?? '')),
                'video_id' => $topic->video_id,
                'video_provider' => $topic->video_provider,
                'video_url' => $this->buildVideoUrl((string) ($topic->video_provider ?? ''), (string) ($topic->video_id ?? '')),
                'video_file_url' => $this->resolveMediaUrl((string) ($topic->video_file_path ?? ''), 'lesson-videos'),
                'image_attachments' => array_values((array) ($topic->image_attachments ?? [])),
                'image_attachment_urls' => $this->extractAttachmentUrls((array) ($topic->image_attachments ?? [])),
                'slideshow_data' => array_values((array) ($topic->slideshow_data ?? [])),
            ];
        }

        if ($nodeType === 'quiz') {
            foreach ((array) ($snapshot['quizzes'] ?? []) as $quiz) {
                if ((int) data_get($quiz, 'attributes.id') !== $nodeId) {
                    continue;
                }

                return [
                    'type' => 'quiz',
                    'id' => $nodeId,
                    'title' => data_get($quiz, 'attributes.title'),
                    'description' => data_get($quiz, 'attributes.description'),
                    'passing_score' => data_get($quiz, 'attributes.passing_score'),
                    'time_limit' => data_get($quiz, 'attributes.time_limit'),
                    'attempt_limit' => data_get($quiz, 'attributes.attempt_limit'),
                    'questions' => array_values((array) data_get($quiz, 'questions', [])),
                ];
            }

            $quiz = $reviewRequest->module?->quizzes?->firstWhere('id', $nodeId);
            if (!$quiz) {
                return null;
            }

            return [
                'type' => 'quiz',
                'id' => $nodeId,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'passing_score' => $quiz->passing_score,
                'time_limit' => $quiz->time_limit,
                'attempt_limit' => $quiz->attempt_limit,
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
            ];
        }

        return null;
    }

    private function sanitizeRichContent(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $content) ?? '';

        return strip_tags($content, '<p><br><strong><em><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><iframe><video><source>');
    }

    private function humanizeLabel(string $value, bool $titleCase = false): string
    {
        $normalized = trim(str_replace(['-', '_'], ' ', $value));
        if ($normalized === '') {
            return 'N/A';
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $titleCase
            ? Str::title($normalized)
            : $normalized;
    }

    private function ageGroupLabel(mixed $minAge, mixed $maxAge): string
    {
        if ($minAge === null || $maxAge === null || $minAge === '' || $maxAge === '') {
            return 'Not specified';
        }

        return (string) $minAge . ' - ' . (string) $maxAge;
    }

    private function resolveMediaUrl(string $path, string $defaultDirectory = ''): ?string
    {
        if (trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if (!str_contains($normalized, '/') && $defaultDirectory !== '') {
            $normalized = trim($defaultDirectory, '/') . '/' . $normalized;
        }

        return Storage::url($normalized);
    }

    private function buildVideoUrl(string $provider, string $videoId): ?string
    {
        $provider = strtolower(trim($provider));
        $videoId = trim($videoId);

        if ($provider === '' || $videoId === '') {
            return null;
        }

        return match ($provider) {
            'youtube' => 'https://www.youtube.com/embed/' . $videoId,
            'vimeo' => 'https://player.vimeo.com/video/' . $videoId,
            default => null,
        };
    }

    /**
     * @param array<int, mixed> $attachments
     * @return array<int, string>
     */
    private function extractAttachmentUrls(array $attachments): array
    {
        return collect($attachments)
            ->map(function ($attachment) {
                if (is_string($attachment)) {
                    return $this->resolveMediaUrl($attachment, 'topics');
                }

                if (is_array($attachment)) {
                    $path = (string) ($attachment['url'] ?? $attachment['path'] ?? '');

                    return $this->resolveMediaUrl($path, 'topics');
                }

                return null;
            })
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractLessons(array $snapshot, ?Module $module): array
    {
        $snapshotLessons = collect($snapshot['lessons'] ?? [])
            ->filter(fn ($lesson) => is_array($lesson))
            ->sortBy(fn ($lesson) => (int) data_get($lesson, 'attributes.order', 0))
            ->values();

        if ($snapshotLessons->isNotEmpty()) {
            return $snapshotLessons->all();
        }

        if (!$module) {
            return [];
        }

        return $module->lessons
            ->sortBy(fn (Lesson $lesson) => (int) ($lesson->order ?? 0))
            ->map(fn (Lesson $lesson) => [
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
                'topics' => $lesson->topics->map(fn ($topic) => $topic->only([
                    'id',
                    'lesson_id',
                    'title',
                    'type',
                    'video_provider',
                    'video_id',
                    'video_file_path',
                    'text_content',
                    'file_path',
                    'quiz_id',
                    'interactive_config',
                    'image_attachments',
                    'slideshow_data',
                    'duration',
                    'is_prerequisite',
                    'order',
                ]))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractQuizzes(array $snapshot, ?Module $module): array
    {
        $snapshotQuizzes = collect($snapshot['quizzes'] ?? [])
            ->filter(fn ($quiz) => is_array($quiz))
            ->sortBy(fn ($quiz) => (int) data_get($quiz, 'attributes.id', 0))
            ->values();

        if ($snapshotQuizzes->isNotEmpty()) {
            return $snapshotQuizzes->all();
        }

        if (!$module) {
            return [];
        }

        return $module->quizzes
            ->sortBy(fn (Quiz $quiz) => (int) ($quiz->id ?? 0))
            ->map(fn (Quiz $quiz) => $this->mapQuizModelToSnapshot($quiz))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapQuizModelToSnapshot(Quiz $quiz): array
    {
        return [
            'attributes' => $quiz->only([
                'id',
                'module_id',
                'lesson_id',
                'title',
                'description',
                'passing_score',
                'time_limit',
                'is_active',
                'attempt_limit',
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
        ];
    }

    private function lessonRequirementType(array $lesson): string
    {
        $hasRequiredTopic = collect((array) data_get($lesson, 'topics', []))
            ->contains(fn ($topic) => (bool) data_get($topic, 'is_prerequisite', false));

        return $hasRequiredTopic ? 'required' : 'optional';
    }

    private function buildInstructorPreview(?User $instructor, $recentViolations, $moderationProfile): array
    {
        if (!$instructor) {
            return [
                'profile' => [
                    'user_id' => null,
                    'profile_picture' => null,
                    'full_name' => 'Unknown Instructor',
                    'role' => 'instructor',
                    'role_badge' => 'Instructor',
                    'affiliation' => 'N/A',
                    'username' => 'N/A',
                    'location' => 'N/A',
                    'educational_background' => 'N/A',
                    'professional_expertise' => 'N/A',
                ],
                'indicators' => [
                    'total_modules_created' => 0,
                    'total_published_modules' => 0,
                    'total_enrolled_learners' => 0,
                    'certificates_earned' => 0,
                ],
                'moderation' => [
                    'warning_count' => 0,
                    'violation_history' => [],
                    'suspension_records' => [],
                ],
                'module_portfolio' => [],
            ];
        }

        $location = collect([
            data_get($instructor->learnerProfile, 'city.name'),
            data_get($instructor->learnerProfile, 'barangayLocation.name'),
            data_get($instructor->profile, 'location'),
        ])->filter(fn ($item) => is_string($item) && trim($item) !== '')->implode(', ');

        $totalModulesCreated = $instructor->authoredModules()->count();
        $totalPublishedModules = $instructor->authoredModules()->where('is_published', true)->count();
        $totalEnrolledLearners = ModuleEnrollment::query()
            ->whereHas('module', fn ($query) => $query->where('created_by', $instructor->id))
            ->approved()
            ->count();
        $certificatesEarned = $instructor->certificates()->count();

        $portfolio = $instructor->authoredModules()
            ->withCount([
                'enrollments as enrolled_learners_count' => fn ($query) => $query->approved(),
            ])
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(fn (Module $module) => [
                'title' => $module->title,
                'status' => $this->moduleStatusLabel($module),
                'enrolled_learners_count' => (int) $module->enrolled_learners_count,
                'created_at' => optional($module->created_at)?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $violationHistory = $recentViolations->map(fn ($violation) => [
            'reason_label' => ModuleReviewRejectionReason::tryFrom((string) $violation->reason_code)?->label() ?? $this->humanizeLabel((string) $violation->reason_code, true),
            'guidance_note' => $violation->guidance_note,
            'suggested_penalty_label' => $this->humanizeLabel((string) $violation->suggested_penalty_action, true),
            'confirmed_penalty_label' => $this->humanizeLabel((string) $violation->confirmed_penalty_action, true),
            'created_at' => optional($violation->created_at)?->toDateTimeString(),
        ])->all();

        $suspensionRecords = $recentViolations
            ->filter(fn ($violation) => in_array((string) $violation->confirmed_penalty_action, ['restrict_14_days', 'suspension_review'], true))
            ->map(fn ($violation) => [
                'action' => $this->humanizeLabel((string) $violation->confirmed_penalty_action, true),
                'reason_label' => ModuleReviewRejectionReason::tryFrom((string) $violation->reason_code)?->label() ?? $this->humanizeLabel((string) $violation->reason_code, true),
                'created_at' => optional($violation->created_at)?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'profile' => [
                'user_id' => $instructor->id,
                'profile_picture' => $this->resolveMediaUrl(
                    (string) (
                        data_get($instructor->instructorProfile, 'profile_photo_path')
                        ?? data_get($instructor->learnerProfile, 'avatar_path')
                        ?? data_get($instructor->profile, 'avatar')
                        ?? ''
                    ),
                    'avatars',
                ),
                'full_name' => $instructor->name,
                'role' => strtolower((string) ($instructor->role ?? 'instructor')),
                'role_badge' => strtolower((string) ($instructor->role ?? '')) === 'admin' ? 'Platform Developer' : 'Instructor',
                'affiliation' => strtolower((string) ($instructor->role ?? '')) === 'admin'
                    ? (string) data_get($instructor->adminCreatorProfile, 'affiliation', 'Conscious Connections Team')
                    : 'Independent Instructor',
                'username' => data_get($instructor->learnerProfile, 'username', 'N/A'),
                'location' => $location !== '' ? $location : 'N/A',
                'educational_background' => data_get($instructor->instructorProfile, 'educational_background', 'N/A'),
                'professional_expertise' => data_get($instructor->instructorProfile, 'primary_expertise')
                    ?? data_get($instructor->instructorProfile, 'professional_background')
                    ?? data_get($instructor->instructorProfile, 'specialization')
                    ?? 'N/A',
            ],
            'indicators' => [
                'total_modules_created' => $totalModulesCreated,
                'total_published_modules' => $totalPublishedModules,
                'total_enrolled_learners' => $totalEnrolledLearners,
                'certificates_earned' => $certificatesEarned,
            ],
            'moderation' => [
                'warning_count' => $moderationProfile?->warning_count ?? 0,
                'violation_history' => $violationHistory,
                'suspension_records' => $suspensionRecords,
            ],
            'module_portfolio' => $portfolio,
        ];
    }

    private function moduleStatusLabel(Module $module): string
    {
        if ($module->is_published) {
            return 'Published';
        }

        $status = (string) ($module->current_review_status ?? 'draft');

        return match ($status) {
            'submitted' => 'Submitted',
            'in_review' => 'Under Review',
            'needs_revision' => 'Rejected / Needs Revision',
            'approved' => 'Approved',
            default => 'Draft',
        };
    }
}
