<?php

namespace App\Http\Controllers\Instructor;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ModuleEnrollment;
use App\Models\ParentChildAccount;
use App\Models\QuizAttempt;
use App\Models\UserProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of learners enrolled in instructor-owned modules.
     */
    public function index(Request $request)
    {
        $instructorId = Auth::id();

        $users = User::role('learner')
            ->whereHas('moduleEnrollments.module', function ($query) use ($instructorId) {
                $query->where('created_by', $instructorId);
            })
            ->with([
                'learnerProfile',
                'moduleEnrollments' => function ($query) use ($instructorId) {
                    $query->whereHas('module', function ($moduleQuery) use ($instructorId) {
                        $moduleQuery->where('created_by', $instructorId);
                    })->with('module:id,title,created_by');
                },
                'parentLinks.parent:id,name,email,status',
                'childLinks:id,parent_user_id,relationship_verified_at',
            ])
            ->withCount([
                'moduleEnrollments as instructor_modules_enrolled_count' => function ($query) use ($instructorId) {
                    $query->whereHas('module', function ($moduleQuery) use ($instructorId) {
                        $moduleQuery->where('created_by', $instructorId);
                    });
                },
                'childLinks as verified_child_links_count' => function ($query) {
                    $query->whereNotNull('relationship_verified_at');
                },
            ])
            ->latest()
            ->get();

        $users->each(function (User $user): void {
            $user->setAttribute('learner_category_label', $this->resolveLearnerCategory($user));
            $user->setAttribute('avatar_url', $this->resolveLearnerAvatarUrl($user));
        });

        return view('instructor.users.index', compact('users'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $instructorId = Auth::id();
        $hasInstructorEnrollment = $user->moduleEnrollments()
            ->whereHas('module', fn ($query) => $query->where('created_by', $instructorId))
            ->exists();

        abort_unless($hasInstructorEnrollment, 403);

        $user->load([
            'learnerProfile.city',
            'learnerProfile.barangay',
            'gamification',
            'achievements',
            'moduleEnrollments' => fn ($query) => $query
                ->whereHas('module', fn ($moduleQuery) => $moduleQuery->where('created_by', $instructorId))
                ->with(['module' => fn ($moduleQuery) => $moduleQuery->with('lessons:id,module_id')]),
            'certificates' => fn ($query) => $query
                ->whereHas('module', fn ($moduleQuery) => $moduleQuery->where('created_by', $instructorId))
                ->with('module:id,title'),
            'parentLinks.parent:id,name,email,status,birthdate',
            'parentLinks.parent.learnerProfile:id,user_id,avatar_path,birthdate',
            'childLinks:id,parent_user_id,child_user_id,relationship_verified_at,verification_status',
            'childLinks.child:id,name,first_name,last_name,email,birthdate,status',
            'childLinks.child.learnerProfile:id,user_id,avatar_path,birthdate',
        ]);

        $moduleIds = $user->moduleEnrollments->pluck('module_id')->filter()->values();

        $moduleProgress = $user->moduleEnrollments->map(function ($enrollment) use ($user) {
            $totalLessons = (int) ($enrollment->module?->lessons?->count() ?? 0);
            $completedLessons = UserProgress::query()
                ->where('user_id', $user->id)
                ->where('module_id', $enrollment->module_id)
                ->where('completed', true)
                ->count();

            $quizAttemptsCount = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->whereHas('quiz', function ($quizQuery) use ($enrollment) {
                    $quizQuery->where('module_id', $enrollment->module_id)
                        ->orWhereHas('lesson', fn ($lessonQuery) => $lessonQuery->where('module_id', $enrollment->module_id));
                })
                ->count();

            $quizPassedCount = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('passed', true)
                ->whereHas('quiz', function ($quizQuery) use ($enrollment) {
                    $quizQuery->where('module_id', $enrollment->module_id)
                        ->orWhereHas('lesson', fn ($lessonQuery) => $lessonQuery->where('module_id', $enrollment->module_id));
                })
                ->count();

            return [
                'module' => $enrollment->module,
                'enrollment' => $enrollment,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percentage' => (int) ($enrollment->completion_percentage ?? 0),
                'quiz_attempts_count' => $quizAttemptsCount,
                'quiz_passed_count' => $quizPassedCount,
            ];
        });

        $lastLessonCompleted = UserProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('module_id', $moduleIds)
            ->where('completed', true)
            ->latest('completed_at')
            ->with('lesson:id,title,module_id')
            ->first();

        $recentProgressTimeline = UserProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('module_id', $moduleIds)
            ->orderByDesc('updated_at')
            ->with('lesson:id,title,module_id')
            ->limit(8)
            ->get();

        $quizPerformanceSummary = [
            'attempts' => QuizAttempt::query()
                ->where('user_id', $user->id)
                ->whereHas('quiz', function ($quizQuery) use ($moduleIds) {
                    $quizQuery->whereIn('module_id', $moduleIds)
                        ->orWhereHas('lesson', fn ($lessonQuery) => $lessonQuery->whereIn('module_id', $moduleIds));
                })
                ->count(),
            'passed' => QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('passed', true)
                ->whereHas('quiz', function ($quizQuery) use ($moduleIds) {
                    $quizQuery->whereIn('module_id', $moduleIds)
                        ->orWhereHas('lesson', fn ($lessonQuery) => $lessonQuery->whereIn('module_id', $moduleIds));
                })
                ->count(),
            'average_score' => (float) QuizAttempt::query()
                ->where('user_id', $user->id)
                ->whereHas('quiz', function ($quizQuery) use ($moduleIds) {
                    $quizQuery->whereIn('module_id', $moduleIds)
                        ->orWhereHas('lesson', fn ($lessonQuery) => $lessonQuery->whereIn('module_id', $moduleIds));
                })
                ->avg('score'),
        ];

        $parentLinks = ParentChildAccount::query()
            ->where('child_user_id', $user->id)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->with([
                'parent:id,name,email,status,birthdate',
                'parent.learnerProfile:id,user_id,avatar_path,birthdate',
            ])
            ->latest('id')
            ->get();

        $linkedChildren = ParentChildAccount::query()
            ->where('parent_user_id', $user->id)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->with([
                'child:id,name,first_name,last_name,email,birthdate,status',
                'child.learnerProfile:id,user_id,avatar_path,birthdate',
            ])
            ->latest('id')
            ->get();

        $engagementStatus = 'Low';
        $lastActivityAt = $recentProgressTimeline->first()?->updated_at;
        if ($lastActivityAt && now()->diffInDays($lastActivityAt) <= 3) {
            $engagementStatus = 'Active';
        } elseif ($lastActivityAt && now()->diffInDays($lastActivityAt) <= 14) {
            $engagementStatus = 'Moderate';
        }

        $learnerCategoryLabel = $this->resolveLearnerCategory($user);
        $avatarUrl = $this->resolveLearnerAvatarUrl($user);

        return view('instructor.users.show', compact(
            'user',
            'moduleProgress',
            'lastLessonCompleted',
            'recentProgressTimeline',
            'quizPerformanceSummary',
            'parentLinks',
            'linkedChildren',
            'engagementStatus',
            'learnerCategoryLabel',
            'avatarUrl',
        ));
    }

    public function archive(User $user)
    {
        $instructorId = Auth::id();

        $hasInstructorEnrollment = $user->moduleEnrollments()
            ->whereHas('module', fn ($query) => $query->where('created_by', $instructorId))
            ->exists();

        abort_unless($hasInstructorEnrollment, 403);

        ModuleEnrollment::query()
            ->where('user_id', $user->id)
            ->whereHas('module', fn ($query) => $query->where('created_by', $instructorId))
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Approved->value])
            ->update([
                'status' => EnrollmentStatus::Rejected->value,
                'rejection_reason_code' => 'other',
                'rejection_reason_note' => 'Archived by instructor from learner list.',
                'rejected_by_instructor_id' => $instructorId,
                'rejected_at' => now(),
            ]);

        return redirect()->route('instructor.users.index')
            ->with('success', 'Learner archived from your active roster.');
    }

    public function remove(User $user)
    {
        $instructorId = Auth::id();

        $moduleIds = $user->moduleEnrollments()
            ->whereHas('module', fn ($query) => $query->where('created_by', $instructorId))
            ->pluck('module_id');

        abort_if($moduleIds->isEmpty(), 403);

        ModuleEnrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('module_id', $moduleIds)
            ->delete();

        UserProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('module_id', $moduleIds)
            ->delete();

        return redirect()->route('instructor.users.index')
            ->with('success', 'Learner removed from your module roster.');
    }

    private function resolveLearnerCategory(User $user): string
    {
        $age = $user->age;

        if (is_null($age) && $user->birthdate) {
            $age = now()->diffInYears($user->birthdate);
        }

        if (is_null($age) && $user->learnerProfile?->birthdate) {
            $age = now()->diffInYears($user->learnerProfile->birthdate);
        }

        if (is_null($age)) {
            return 'Not Available';
        }

        if ($age <= 12) {
            return 'Child';
        }

        if ($age <= 17) {
            return 'Teen';
        }

        if ($this->hasVerifiedChildLink($user)) {
            return 'Adult (Guardian)';
        }

        return 'Adult';
    }

    private function hasVerifiedChildLink(User $user): bool
    {
        $count = $user->getAttribute('verified_child_links_count');
        if (!is_null($count)) {
            return (int) $count > 0;
        }

        if ($user->relationLoaded('childLinks')) {
            return $user->childLinks->contains(fn ($link) => !is_null($link->relationship_verified_at));
        }

        return $user->childLinks()
            ->whereNotNull('relationship_verified_at')
            ->exists();
    }

    private function resolveLearnerAvatarUrl(User $user): ?string
    {
        $path = $user->learnerProfile?->avatar_path;
        if (!$path) {
            return null;
        }

        $normalized = ltrim((string) $path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        return Storage::url($normalized);
    }
}
