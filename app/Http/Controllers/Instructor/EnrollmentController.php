<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\RejectEnrollmentRequest;
use App\Enums\EnrollmentStatus;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\UserProgress;
use App\Notifications\Learner\EnrollmentApprovedNotification;
use App\Notifications\Learner\EnrollmentRejectedNotification;
use App\Services\Content\ContentOwnershipGuard;
use App\Support\ContentPanelContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    public function __construct(private readonly ContentOwnershipGuard $ownershipGuard)
    {
    }

    /**
     * Show pending enrollment requests for instructor's modules
     */
    public function index(Request $request)
    {
        $statusFilter = (string) $request->string('status')->toString();
        if ($statusFilter === '') {
            $statusFilter = 'all';
        }
        $search = trim((string) $request->string('search')->toString());
        $moduleFilter = (int) $request->integer('module_id', 0);

        $scopeConstraint = function ($query): void {
            if ($this->panelContext()->isInstructor()) {
                $query->whereHas('module', fn ($moduleQuery) => $moduleQuery->where('created_by', Auth::id()));
            }
        };

        $baseScope = ModuleEnrollment::query();
        $scopeConstraint($baseScope);

        $statusCounts = [
            'all' => (clone $baseScope)->count(),
            'pending' => (clone $baseScope)->whereIn('status', [
                EnrollmentStatus::Pending,
                EnrollmentStatus::PendingParentApproval,
            ])->count(),
            'approved' => (clone $baseScope)->where('status', EnrollmentStatus::Approved)->count(),
            'rejected' => (clone $baseScope)
                ->where('status', EnrollmentStatus::Rejected)
                ->where(function ($query): void {
                    $query->whereNull('rejection_reason_code')
                        ->orWhere('rejection_reason_code', '!=', 'archived_enrollment');
                })
                ->count(),
        ];

        $modulesForFilter = Module::query()
            ->select('id', 'title')
            ->when(
                $this->panelContext()->isInstructor(),
                fn ($query) => $query->where('created_by', Auth::id())
            )
            ->orderBy('title')
            ->get();

        $enrollments = ModuleEnrollment::query()
            ->with([
                'user.learnerProfile.city',
                'user.learnerProfile.barangay',
                'module' => fn ($query) => $query->withCount('lessons')->with('creator:id,role'),
            ])
            ->when(
                $this->panelContext()->isInstructor(),
                fn ($query) => $query->whereHas('module', fn ($moduleQuery) => $moduleQuery->where('created_by', Auth::id()))
            )
            ->when($moduleFilter > 0, fn ($query) => $query->where('module_id', $moduleFilter))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->whereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    })->orWhereHas('module', fn ($moduleQuery) => $moduleQuery->where('title', 'like', '%' . $search . '%'));
                });
            })
            ->when($statusFilter !== 'all', function ($query) use ($statusFilter): void {
                if ($statusFilter === 'pending') {
                    $query->whereIn('status', [EnrollmentStatus::Pending, EnrollmentStatus::PendingParentApproval]);

                    return;
                }

                if ($statusFilter === 'approved') {
                    $query->where('status', EnrollmentStatus::Approved);

                    return;
                }

                if ($statusFilter === 'archived') {
                    $query->where('status', EnrollmentStatus::Rejected)
                        ->where('rejection_reason_code', 'archived_enrollment');

                    return;
                }

                if ($statusFilter === 'rejected') {
                    $query->where('status', EnrollmentStatus::Rejected)
                        ->where(function ($rejectedQuery): void {
                            $rejectedQuery->whereNull('rejection_reason_code')
                                ->orWhere('rejection_reason_code', '!=', 'archived_enrollment');
                        });
                }
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('instructor.enrollments.index', [
            'enrollments' => $enrollments,
            'statusCounts' => $statusCounts,
            'modulesForFilter' => $modulesForFilter,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'moduleFilter' => $moduleFilter,
        ]);
    }

    /**
     * Show learner details for review before approval
     */
    public function show(ModuleEnrollment $enrollment)
    {
        $this->ensureEnrollmentAccessible($enrollment);

        // Load relationships
        $enrollment->load([
            'user.learnerProfile.city',
            'user.learnerProfile.barangay',
            'user.parentLinks.parent',
            'user.moduleEnrollments.module',
            'module.lessons:id,module_id',
        ]);

        // Calculate learner statistics
        $totalEnrollments = $enrollment->user->moduleEnrollments()->approved()->count();
        $completedModules = $enrollment->user->moduleEnrollments()
            ->approved()
            ->whereNotNull('completed_at')
            ->count();

        // Calculate completion rate
        $completionRate = $totalEnrollments > 0 
            ? round(($completedModules / $totalEnrollments) * 100) 
            : 0;

        // Get recent enrollments
        $recentEnrollments = $enrollment->user->moduleEnrollments()
            ->with('module')
            ->approved()
            ->latest()
            ->take(5)
            ->get();

        $moduleLessonCount = (int) ($enrollment->module?->lessons?->count() ?? 0);
        $completedLessonCount = UserProgress::query()
            ->where('user_id', $enrollment->user_id)
            ->where('module_id', $enrollment->module_id)
            ->whereNotNull('lesson_id')
            ->where('completed', true)
            ->distinct('lesson_id')
            ->count('lesson_id');

        $moduleProgressPercent = $moduleLessonCount > 0
            ? min(100, (int) round(($completedLessonCount / $moduleLessonCount) * 100))
            : (int) ($enrollment->completion_percentage ?? 0);

        $parentConnections = $enrollment->user->parentLinks
            ->filter(fn ($link) => $link->parent !== null)
            ->values()
            ->map(function ($link): array {
                $parentName = trim((string) ($link->parent?->full_name ?? $link->parent?->name ?? 'Guardian'));

                return [
                    'parent_name' => $parentName !== '' ? $parentName : 'Guardian',
                    'verification_status' => (string) ($link->verification_status ?? 'pending'),
                    'can_view_progress' => (bool) ($link->can_view_progress ?? false),
                    'can_view_quiz_answers' => (bool) ($link->can_view_quiz_answers ?? false),
                    'can_approve_content' => (bool) ($link->can_approve_content ?? false),
                    'relationship_verified_at' => $link->relationship_verified_at,
                ];
            })
            ->all();

        return view('instructor.enrollments.show', compact(
            'enrollment',
            'totalEnrollments',
            'completedModules',
            'completionRate',
            'recentEnrollments',
            'moduleLessonCount',
            'completedLessonCount',
            'moduleProgressPercent',
            'parentConnections'
        ));
    }

    /**
     * Approve an enrollment request
     */
    public function approve(Request $request, ModuleEnrollment $enrollment)
    {
        $this->ensureEnrollmentAccessible($enrollment, true);

        if ($enrollment->status !== EnrollmentStatus::Pending) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This enrollment request is not pending.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'This enrollment request is not pending.');
        }

        $enrollment->loadMissing('module', 'user');

        // Update enrollment to approved
        $enrollment->update([
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
            'rejection_reason_code' => null,
            'rejection_reason_note' => null,
            'rejected_by_instructor_id' => null,
            'rejected_at' => null,
        ]);

        $enrollment->user->notify(new EnrollmentApprovedNotification($enrollment));

        // Note: UserProgress records are created per-lesson as learners progress,
        // not at enrollment time. The user_progress table tracks lesson-level completion.

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Enrollment request approved successfully!',
                'status' => EnrollmentStatus::Approved->value,
            ]);
        }

        return redirect()->back()->with('success', 'Enrollment request approved successfully!');
    }

    /**
     * Reject an enrollment request
     */
    public function reject(RejectEnrollmentRequest $request, ModuleEnrollment $enrollment)
    {
        $this->ensureEnrollmentAccessible($enrollment, true);

        if ($enrollment->status !== EnrollmentStatus::Pending) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This enrollment request is not pending.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'This enrollment request is not pending.');
        }

        $validated = $request->validated();

        $enrollment->loadMissing('module', 'user');

        $instructor = Auth::user();
        $instructorName = trim((string) ($instructor?->full_name ?? $instructor?->name ?? 'your instructor'));

        $reasonCode = (string) $validated['rejection_reason_code'];
        $reasonNote = $validated['rejection_reason_note'] ?? null;

        // Update enrollment to rejected
        $enrollment->update([
            'status' => EnrollmentStatus::Rejected,
            'rejection_reason_code' => $reasonCode,
            'rejection_reason_note' => $reasonNote,
            'rejected_by_instructor_id' => $instructor?->id,
            'rejected_at' => now(),
        ]);

        $enrollment->user->notify(new EnrollmentRejectedNotification($enrollment, $instructorName));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Enrollment request rejected.',
                'status' => EnrollmentStatus::Rejected->value,
            ]);
        }

        return redirect()->back()->with('success', 'Enrollment request rejected.');
    }

    /**
     * Archive an enrollment record from operational queues.
     */
    public function archive(Request $request, ModuleEnrollment $enrollment)
    {
        $this->ensureEnrollmentAccessible($enrollment, true);

        if (
            $enrollment->status === EnrollmentStatus::Rejected
            && (string) $enrollment->rejection_reason_code === 'archived_enrollment'
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Enrollment is already archived.']);
            }

            return redirect()->back()->with('info', 'Enrollment is already archived.');
        }

        $enrollment->update([
            'status' => EnrollmentStatus::Rejected,
            'rejection_reason_code' => 'archived_enrollment',
            'rejection_reason_note' => 'Archived from enrollment management.',
            'rejected_by_instructor_id' => Auth::id(),
            'rejected_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Enrollment archived successfully.']);
        }

        return redirect()->back()->with('success', 'Enrollment archived successfully.');
    }

    /**
     * Show all enrollments for a specific module
     */
    public function moduleEnrollments(Module $module)
    {
        if ($this->panelContext()->isInstructor()) {
            abort_unless((int) $module->created_by === (int) Auth::id(), 403);
        }

        $enrollments = $module->enrollments()
            ->with(['user.learnerProfile'])
            ->latest()
            ->paginate(20);

        return view('instructor.enrollments.module', compact('module', 'enrollments'));
    }

    /**
     * Remove an enrollment record owned by the instructor.
     */
    public function destroy(Request $request, ModuleEnrollment $enrollment)
    {
        $this->ensureEnrollmentAccessible($enrollment, true);

        $learnerName = $enrollment->user?->name ?? 'Learner';
        $moduleTitle = $enrollment->module?->title ?? 'module';

        $enrollment->delete();

        $message = sprintf('%s was removed from %s enrollment records.', $learnerName, $moduleTitle);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    private function ensureEnrollmentAccessible(ModuleEnrollment $enrollment, bool $forMutation = false): void
    {
        $enrollment->loadMissing('module.creator');

        if ($this->panelContext()->isAdmin()) {
            if (!$forMutation) {
                return;
            }

            $ownerType = $this->ownershipGuard->ownerTypeForModule($enrollment->module);

            abort_unless(
                $this->ownershipGuard->canAdminMutateOwnerType($ownerType),
                403,
                'Admins can only modify enrollments for platform-owned learning content.',
            );

            return;
        }

        abort_unless((int) ($enrollment->module?->created_by ?? 0) === (int) Auth::id(), 403);
    }

    private function panelContext(): ContentPanelContext
    {
        return app(ContentPanelContext::class);
    }
}
