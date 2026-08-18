<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Parent\RejectEnrollmentRequest;
use App\Models\ModuleEnrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\Learner\ParentEnrollmentApprovedNotification;
use App\Notifications\Learner\ParentEnrollmentRejectedNotification;
use App\Notifications\Parent\ChildEnrollmentApprovedNotification;
use App\Notifications\Parent\ChildEnrollmentRejectedNotification;
use App\Services\ParentChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ParentController extends Controller
{
    public function __construct(private ParentChildService $service) {}

    public function show(User $child)
    {
        $this->authorize('view', $child);

        $parent = Auth::user();
        if (! $parent instanceof User) {
            abort(403);
        }

        $parentChildLink = $parent->children()
            ->where('users.id', $child->id)
            ->first();

        $canApproveContent = $parentChildLink?->pivot->can_approve_content ?? false;
        $canViewQuizAnswers = $parentChildLink?->pivot->can_view_quiz_answers ?? false;

        return view('parent.children.show', [
            'child'              => $child,
            'progress'           => $this->service->getProgress($child),
            'quizResults'        => $canViewQuizAnswers ? $this->service->getQuizResults($child) : collect(),
            'achievements'       => $this->service->getAchievements($child),
            'pendingEnrollments' => $canApproveContent ? $this->service->getPendingEnrollments($child) : collect(),
            'canViewQuizAnswers' => $canViewQuizAnswers,
            'canApproveContent'  => $canApproveContent,
            'parentChildLink' => $parentChildLink,
        ]);
    }

    public function showQuizAttempt(User $child, QuizAttempt $attempt)
    {
        $this->authorize('view', $child);

        $parent = Auth::user();
        if (! $parent instanceof User) {
            abort(403);
        }

        $parentChildLink = $parent->children()
            ->where('users.id', $child->id)
            ->first();

        $canViewQuizAnswers = $parentChildLink?->pivot->can_view_quiz_answers ?? false;

        abort_unless($canViewQuizAnswers, 403);

        if ((int) $attempt->user_id !== (int) $child->id) {
            abort(404);
        }

        $details = $this->service->getQuizAttemptDetails($child, $attempt);

        return view('parent.children.quiz-attempt-show', [
            'child' => $child,
            'attempt' => $details['attempt'],
            'questionResults' => $details['question_results'],
        ]);
    }

    public function showEnrollment(User $child, ModuleEnrollment $enrollment)
    {
        $this->authorize('view', $child);

        if ((int) $enrollment->user_id !== (int) $child->id) {
            abort(404);
        }

        $parent = Auth::user();
        if (! $parent instanceof User) {
            abort(403);
        }

        $parentChildLink = $parent->children()
            ->where('users.id', $child->id)
            ->first();

        $canApproveContent = (bool) ($parentChildLink?->pivot->can_approve_content ?? false);

        if ($enrollment->status === EnrollmentStatus::PendingParentApproval && ! $canApproveContent) {
            abort(403);
        }

        $enrollment->load([
            'module.creator.instructorProfile',
            'module.lessons' => function ($query) {
                $query->select([
                    'id',
                    'module_id',
                    'title',
                    'description',
                    'text_content',
                    'duration',
                    'order',
                    'is_published',
                ])->orderBy('order');
            },
            'module.lessons.topics' => function ($query) {
                $query->select([
                    'id',
                    'lesson_id',
                    'title',
                    'type',
                    'video_provider',
                    'video_id',
                    'video_file_path',
                    'file_path',
                    'worksheet_files',
                    'image_attachments',
                    'text_content',
                    'quiz_id',
                    'duration',
                    'order',
                ])->orderBy('order');
            },
            'module.lessons.topics.quiz' => function ($query) {
                $query->select([
                    'id',
                    'module_id',
                    'lesson_id',
                    'title',
                    'description',
                    'passing_score',
                    'time_limit',
                    'attempt_limit',
                    'is_active',
                ]);
            },
            'module.lessons.topics.quiz.questions' => function ($query) {
                $query->select([
                    'id',
                    'quiz_id',
                    'question_text',
                    'question_type',
                    'points',
                    'order',
                ])->orderBy('order');
            },
            'module.lessons.topics.quiz.questions.options' => function ($query) {
                $query->select([
                    'id',
                    'quiz_question_id',
                    'option_text',
                    'is_correct',
                    'order',
                ])->orderBy('order');
            },
            'module.lessons.quizzes' => function ($query) {
                $query->select([
                    'id',
                    'module_id',
                    'lesson_id',
                    'title',
                    'description',
                    'passing_score',
                    'time_limit',
                    'attempt_limit',
                    'is_active',
                ]);
            },
            'module.lessons.quizzes.questions' => function ($query) {
                $query->select([
                    'id',
                    'quiz_id',
                    'question_text',
                    'question_type',
                    'points',
                    'order',
                ])->orderBy('order');
            },
            'module.lessons.quizzes.questions.options' => function ($query) {
                $query->select([
                    'id',
                    'quiz_question_id',
                    'option_text',
                    'is_correct',
                    'order',
                ])->orderBy('order');
            },
            'module.quizzes' => function ($query) {
                $query->select([
                    'id',
                    'module_id',
                    'lesson_id',
                    'title',
                    'description',
                    'passing_score',
                    'time_limit',
                    'attempt_limit',
                    'is_active',
                ]);
            },
            'module.quizzes.questions' => function ($query) {
                $query->select([
                    'id',
                    'quiz_id',
                    'question_text',
                    'question_type',
                    'points',
                    'order',
                ])->orderBy('order');
            },
            'module.quizzes.questions.options' => function ($query) {
                $query->select([
                    'id',
                    'quiz_question_id',
                    'option_text',
                    'is_correct',
                    'order',
                ])->orderBy('order');
            },
        ]);

        return view('parent.children.enrollment-show', [
            'child' => $child,
            'enrollment' => $enrollment,
            'canApproveContent' => $canApproveContent,
            'openedFromNotification' => request()->query('from') === 'notification',
        ]);
    }

    public function approveEnrollment(User $child, ModuleEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('view', $child);

        $parent = Auth::user();
        if (! $parent instanceof User) {
            abort(403);
        }

        $parentChildLink = $parent->children()
            ->where('users.id', $child->id)
            ->first();

        $canApproveContent = (bool) ($parentChildLink?->pivot->can_approve_content ?? false);

        if (! $canApproveContent) {
            abort(403);
        }

        if ($enrollment->user_id !== $child->id || $enrollment->status !== EnrollmentStatus::PendingParentApproval) {
            abort(403);
        }

        $newStatus = $enrollment->module->enrollment_mode === 'manual' ? 'pending' : 'approved';

        if ($enrollment->module->access_type === 'paid') {
            // Paid modules require checkout completion before full access.
            $newStatus = EnrollmentStatus::Pending->value;
        }

        $enrollment->update([
            'status' => $newStatus,
            'enrolled_at' => $newStatus === EnrollmentStatus::Approved->value ? now() : null,
            'rejection_reason_code' => null,
            'rejection_reason_note' => null,
            'rejected_at' => null,
        ]);

        $freshEnrollment = $enrollment->fresh(['module']);
        $child->notify(new ParentEnrollmentApprovedNotification($freshEnrollment, $parent));
        $parent->notify(new ChildEnrollmentApprovedNotification($freshEnrollment, $child));

        return redirect()->route('parent.children.show', $child)
            ->with('success', 'Enrollment approved.');
    }

    public function rejectEnrollment(RejectEnrollmentRequest $request, User $child, ModuleEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('view', $child);

        $parent = Auth::user();
        if (! $parent instanceof User) {
            abort(403);
        }

        $parentChildLink = $parent->children()
            ->where('users.id', $child->id)
            ->first();

        $canApproveContent = (bool) ($parentChildLink?->pivot->can_approve_content ?? false);

        if (! $canApproveContent) {
            abort(403);
        }

        if ($enrollment->user_id !== $child->id || $enrollment->status !== EnrollmentStatus::PendingParentApproval) {
            abort(403);
        }

        $reasonCode = Str::lower(trim((string) $request->validated('reason_code')));
        $customReason = $this->normalizeReasonText($request->validated('custom_reason', null));

        $reasonFromCode = $reasonCode === 'others'
            ? $customReason
            : $this->reasonLabelFromCode($reasonCode);

        $normalizedReason = $reasonFromCode;

        $enrollment->update([
            'status' => EnrollmentStatus::Rejected->value,
            'enrolled_at' => null,
            'rejection_reason_code' => $reasonCode,
            'rejection_reason_note' => $normalizedReason,
            'rejected_at' => now(),
        ]);

        $freshEnrollment = $enrollment->fresh(['module']);
        $child->notify(new ParentEnrollmentRejectedNotification($freshEnrollment, $parent, $normalizedReason));
        $parent->notify(new ChildEnrollmentRejectedNotification($freshEnrollment, $child, $normalizedReason));

        return redirect()->route('parent.children.show', $child)
            ->with('info', 'Enrollment request rejected.');
    }

    private function reasonLabelFromCode(string $reasonCode): ?string
    {
        return match ($reasonCode) {
            'age_not_suitable' => 'Age suitability concerns for this module.',
            'not_ready_for_topic' => 'Not ready for this topic yet.',
            default => null,
        };
    }

    private function normalizeReasonText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, 500, '');
    }
}
