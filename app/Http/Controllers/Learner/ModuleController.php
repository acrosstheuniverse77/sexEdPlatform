<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessModulePaymentRequest;
use App\Enums\EnrollmentStatus;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ModulePurchase;
use App\Models\ParentChildAccount;
use App\Models\QuizAttempt;
use App\Models\LessonTopicProgress;
use App\Models\ContentReport;
use App\Models\InstructorFeedback;
use App\Models\ModuleFeedback;
use App\Models\User;
use App\Models\UserDailyShield;
use App\Models\UserProgress;
use App\Notifications\Learner\ModulePurchaseResultNotification;
use App\Notifications\Parent\ChildEnrollmentApprovalRequestedNotification;
use App\Services\GamificationService;
use App\Services\Instructor\InstructorPlanCapabilityService;
use App\Services\ModulePurchaseService;
use App\Services\LearnerModuleCompletionService;
use App\Services\Content\AdminOwnershipDisplayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModulePurchaseService $modulePurchaseService,
        private readonly LearnerModuleCompletionService $completionService,
        private readonly GamificationService $gamificationService,
        private readonly AdminOwnershipDisplayService $ownershipDisplayService,
        private readonly InstructorPlanCapabilityService $instructorPlanCapabilityService,
    ) {
    }

    /**
     * Display all published modules filtered by learner's age
     */
    public function index()
    {
        $user = Auth::user();
        $learnerProfile = $user->learnerProfile;
        
        if (!$learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        // Get learner's age
        $learnerAge = $learnerProfile->getAge();
        
        $enrolledModuleIds = $user->moduleEnrollments()
            ->where('status', EnrollmentStatus::Approved)
            ->pluck('module_id');

        // Show active age-appropriate modules plus enrolled modules (including deactivated) for history visibility.
        $modules = Module::where(function ($query) use ($learnerAge, $enrolledModuleIds) {
                $query->where(function ($inner) use ($learnerAge) {
                    $inner->learnerVisible()->forAge($learnerAge);
                });

                if ($enrolledModuleIds->isNotEmpty()) {
                    $query->orWhereIn('id', $enrolledModuleIds);
                }
            })
            ->withCount([
                'lessons',
                'enrollments as approved_enrollments_count' => fn ($query) => $query->where('status', EnrollmentStatus::Approved),
            ])
            ->with([
                'creator.instructorProfile',
                'creator.adminCreatorProfile',
                'purchases' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', ModulePurchase::STATUS_COMPLETED),
            ])
            ->with(['lessons' => function ($query) {
                $query->where('is_published', true)->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        $modules->each(function (Module $module) {
            $module->applyPublishedSnapshot();
        });

        // Get user's enrollments
        $enrollments = $user->moduleEnrollments()->get()->keyBy('module_id');
        $enrolledModuleIds = $enrollments->keys()->toArray();

        // Calculate progress for each module
        $progress = [];
        foreach ($modules as $module) {
            $totalLessons = $module->lessons->count();
            $completedLessons = UserProgress::where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->where('completed', true)
                ->count();
            
            if ($totalLessons > 0) {
                $progressPercentage = ($completedLessons / $totalLessons) * 100;
            } else {
                $progressPercentage = 0;
            }
            
            $progress[$module->id] = (object)[
                'progress_percentage' => round($progressPercentage),
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
            ];
        }

        $ownershipDisplays = [];
        foreach ($modules as $module) {
            if ($module instanceof Module) {
                $ownershipDisplays[$module->id] = $this->ownershipDisplayService->forModule($module);
            }
        }

        return view('learner.modules.index', compact('modules', 'enrolledModuleIds', 'enrollments', 'progress', 'ownershipDisplays'));
    }

    /**
     * Display a specific module with its lessons
     */
    public function show(Module $module)
    {
        $user = Auth::user();
        $learnerProfile = $user->learnerProfile;

        if (!$learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        $enrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->first();

        $isApprovedEnrollment = $enrollment && $enrollment->status === EnrollmentStatus::Approved;

        // Deactivated modules remain viewable for enrolled learners only.
        if (!$module->isLearnerVisible() && !$isApprovedEnrollment) {
            abort(404);
        }

        $module->loadMissing('publishedRevision');
        $module->applyPublishedSnapshot();
        $module->loadMissing('creator.instructorProfile', 'creator.adminCreatorProfile');
        $creator = $module->creator;
        $ownershipDisplay = $this->ownershipDisplayService->forModule($module);

        // Security: Check age-based access for non-enrolled learners.
        $learnerAge = $learnerProfile->getAge();
        if (!$isApprovedEnrollment && !$this->canAccessModule($module, $learnerAge)) {
            return redirect()->route('learner.modules.index')
                ->with('error', 'This module is not available for your age group.');
        }

        // Get published lessons only
        $lessons = $module->lessons()
            ->where('is_published', true)
            ->orderBy('order')
            ->with([
                'topics' => fn($query) => $query->ordered(),
                'quiz' => fn($query) => $query->where('is_active', true)->with('questions'),
            ])
            ->get();

        // Check enrollment status
        $isEnrolled = $isApprovedEnrollment;
        $enrollmentStatus = $enrollment?->status?->value;

        $isPaidModule = $module->isPaidAccess();
        $isOwnedByCurrentUser = $module->isOwnedBy($user);
        $modulePurchase = $this->modulePurchaseService->getCompletedPurchase($user, $module);

        if ($isPaidModule && !$modulePurchase) {
            $pendingPayment = $user->payments()
                ->where('status', \App\Enums\PaymentStatus::Pending)
                ->where('payment_details->payment_scope', 'module_purchase')
                ->where('payment_details->module_id', $module->id)
                ->latest('id')
                ->first();

            if ($pendingPayment && $this->modulePurchaseService->verifyAndCompletePendingPayment($pendingPayment)) {
                $modulePurchase = $this->modulePurchaseService->getCompletedPurchase($user, $module);
            }
        }

        $hasPurchased = $modulePurchase !== null;

        $approvedEnrollmentsCount = ModuleEnrollment::query()
            ->approvedForModule($module->id)
            ->count();

        $effectiveEnrollmentLimit = $this->resolveEffectiveEnrollmentLimit($module);
        $isAtCapacity = $effectiveEnrollmentLimit !== null
            && $approvedEnrollmentsCount >= $effectiveEnrollmentLimit;

        $needsParentApproval = ParentChildAccount::query()
            ->where('child_user_id', $user->id)
            ->where('can_approve_content', true)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->exists();

        $isParentApprovedForPurchase = !$needsParentApproval
            || ($enrollment && in_array($enrollment->status, [EnrollmentStatus::Pending, EnrollmentStatus::Approved], true));

        $checkoutUnavailableReason = null;

        if ($isPaidModule && !$hasPurchased) {
            if (!$this->canReceivePaidEnrollments($module)) {
                $checkoutUnavailableReason = 'Paid enrollment is not enabled for this module yet.';
            } elseif ($isAtCapacity) {
                $checkoutUnavailableReason = 'Enrollment capacity has been reached for this module.';
            } elseif ($needsParentApproval && !$isParentApprovedForPurchase) {
                $checkoutUnavailableReason = 'Guardian approval is required before checkout.';
            } elseif ($enrollment && $enrollment->status === EnrollmentStatus::Rejected) {
                $checkoutUnavailableReason = 'Your enrollment request was rejected, so checkout is unavailable right now.';
            } elseif ($isOwnedByCurrentUser) {
                $checkoutUnavailableReason = 'You cannot purchase a module that you own.';
            }
        }

        $canPurchase = $isPaidModule
            && !$hasPurchased
            && !$isOwnedByCurrentUser
            && $this->canReceivePaidEnrollments($module)
            && !$isAtCapacity
            && $isParentApprovedForPurchase
            && (!$enrollment || $enrollment->status !== EnrollmentStatus::Rejected);

        if ($canPurchase) {
            $checkoutUnavailableReason = null;
        }

        // Calculate progress
        $totalLessons = $lessons->count();
        $completedLessons = UserProgress::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('completed', true)
            ->count();
        
        $progressPercentage = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
        
        $progress = (object)[
            'progress_percentage' => round($progressPercentage),
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
        ];

        // Get completed lesson IDs for UI
        $completedLessonIds = UserProgress::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->toArray();

        // Get quizzes for this module
        $moduleQuizzes = $module->quizzes()
            ->where('is_active', true)
            ->with('questions')
            ->get();
        
        // Get lesson quizzes
        $lessonQuizzes = [];
        foreach ($lessons as $lesson) {
            if ($lesson->quiz) {
                $lessonQuizzes[$lesson->id] = $lesson->quiz;
            }
        }
        
        // Get user's quiz attempts
        $quizAttempts = $user->quizAttempts()
            ->whereIn('quiz_id', $moduleQuizzes->pluck('id')->merge(collect($lessonQuizzes)->pluck('id')))
            ->get()
            ->groupBy('quiz_id');

        $moduleCertificate = $user->certificates()->where('module_id', $module->id)->first();

        $topicIds = $lessons->flatMap(fn ($lesson) => $lesson->topics->pluck('id'))->unique();
        $completedTopicIds = LessonTopicProgress::where('user_id', $user->id)
            ->whereIn('lesson_topic_id', $topicIds)
            ->where('completed', true)
            ->pluck('lesson_topic_id')
            ->unique();
        $allTopicsCompleted = $topicIds->isEmpty() || $completedTopicIds->count() === $topicIds->count();

        $lessonQuizIds = collect($lessonQuizzes)->pluck('id')->unique();
        $lessonQuizById = collect($lessonQuizzes)->keyBy('id');
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

            $finalQuizAttemptLimit = $moduleQuizzes
                ->firstWhere('id', $module->final_quiz_id)?->attempt_limit;

            $finalQuizCompleted = $hasPassedFinalQuiz
                || ($finalQuizAttemptLimit !== null && $finalAttemptCount >= (int) $finalQuizAttemptLimit);
        }

        $certificateEligible = $isEnrolled
            && $totalLessons > 0
            && count($completedLessonIds) === $totalLessons
            && $allTopicsCompleted
            && $allLessonQuizzesCompleted
            && $finalQuizCompleted;

        $reviewSummary = [
            'average' => round((float) (ModuleFeedback::query()->where('module_id', $module->id)->avg('rating') ?? 0), 1),
            'count' => (int) ModuleFeedback::query()->where('module_id', $module->id)->count(),
        ];

        $recentReviews = ModuleFeedback::query()
            ->where('module_id', $module->id)
            ->with(['learner.learnerProfile'])
            ->latest('created_at')
            ->limit(3)
            ->get();

        $instructorReviewSummary = [
            'average' => $creator ? round((float) (InstructorFeedback::query()->where('instructor_id', $creator->id)->avg('rating') ?? 0), 1) : 0,
            'count' => $creator ? (int) InstructorFeedback::query()->where('instructor_id', $creator->id)->count() : 0,
        ];

        $recentInstructorReviews = $creator
            ? InstructorFeedback::query()
                ->where('instructor_id', $creator->id)
                ->with(['learner.learnerProfile', 'sourceModule:id,title'])
                ->latest('created_at')
                ->limit(3)
                ->get()
            : collect();

        $reviewEligibility = $this->completionService->reviewEligibility($user, $module);
        $userFeedback = ModuleFeedback::query()
            ->where('module_id', $module->id)
            ->where('learner_id', $user->id)
            ->first();
        $userInstructorFeedback = $creator
            ? InstructorFeedback::query()
                ->where('instructor_id', $creator->id)
                ->where('learner_id', $user->id)
                ->first()
            : null;

        $activeModuleReport = ContentReport::query()
            ->activeForTarget($user->id, 'module', $module->id)
            ->latest('id')
            ->first();

        $activeInstructorReport = $creator
            ? ContentReport::query()
                ->activeForTarget($user->id, 'instructor', (int) $creator->id)
                ->latest('id')
                ->first()
            : null;

        $shieldsRemaining = UserDailyShield::getShields($user);

        return view('learner.modules.show', compact(
            'module', 
            'lessons', 
            'isEnrolled', 
            'enrollmentStatus',
            'isPaidModule',
            'modulePurchase',
            'hasPurchased',
            'isOwnedByCurrentUser',
            'approvedEnrollmentsCount',
            'isAtCapacity',
            'needsParentApproval',
            'isParentApprovedForPurchase',
            'checkoutUnavailableReason',
            'canPurchase',
            'progress', 
            'completedLessonIds',
            'moduleQuizzes',
            'lessonQuizzes',
            'quizAttempts',
            'moduleCertificate',
            'certificateEligible',
            'shieldsRemaining',
            'reviewSummary',
            'recentReviews',
            'instructorReviewSummary',
            'recentInstructorReviews',
            'reviewEligibility',
            'userFeedback',
            'userInstructorFeedback',
            'activeModuleReport',
            'activeInstructorReport',
            'ownershipDisplay'
        ));
    }

    /**
     * Enroll in a module
     */
    public function enroll(Module $module)
    {
        $user = Auth::user();

        $module->loadMissing('publishedRevision');
        $module->applyPublishedSnapshot();

        if (!$user->learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        if ($module->isPaidAccess() && !$this->canReceivePaidEnrollments($module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Paid enrollment is currently unavailable for this module.');
        }

        if ($module->isPaidAccess() && !$this->modulePurchaseService->hasCompletedPurchase($user, $module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'This module requires payment before enrollment.');
        }

        // Security checks
        if (!$module->is_published) {
            abort(404);
        }

        // Check age-based access
        $learnerAge = $user->learnerProfile->getAge();
        if (!$this->canAccessModule($module, $learnerAge)) {
            return back()->with('error', 'This module is not available for your age group.');
        }

        // Check if already enrolled or has pending request
        $existingEnrollment = $user->moduleEnrollments()->where('module_id', $module->id)->first();
        
        if ($existingEnrollment) {
            if ($existingEnrollment->status === EnrollmentStatus::Pending) {
                return back()->with('info', 'Your enrollment request is pending instructor approval.');
            }
            if ($existingEnrollment->status === EnrollmentStatus::Approved) {
                return back()->with('info', 'You are already enrolled in this module.');
            }
            if ($existingEnrollment->status === EnrollmentStatus::PendingParentApproval) {
                return back()->with('info', 'Your enrollment request is awaiting guardian approval.');
            }
            if ($existingEnrollment->status === EnrollmentStatus::Rejected) {
                return back()->with('error', 'Your enrollment request was rejected by the instructor.');
            }
        }

        // Check if parent approval is required
        $needsParentApproval = ParentChildAccount::where('child_user_id', $user->id)
            ->where('can_approve_content', true)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->exists();

        if ($needsParentApproval) {
            $enrollment = ModuleEnrollment::create([
                'user_id'     => $user->id,
                'module_id'   => $module->id,
                'status'      => EnrollmentStatus::PendingParentApproval,
                'enrolled_at' => null,
            ]);

            $enrollment->loadMissing('module');

            $parentApproverIds = ParentChildAccount::query()
                ->where('child_user_id', $user->id)
                ->where('can_approve_content', true)
                ->where('verification_status', 'approved')
                ->whereNotNull('relationship_verified_at')
                ->pluck('parent_user_id')
                ->unique()
                ->values();

            if ($parentApproverIds->isNotEmpty()) {
                User::query()
                    ->whereIn('id', $parentApproverIds)
                    ->get()
                    ->each(fn (User $parentUser) => $parentUser->notify(new ChildEnrollmentApprovalRequestedNotification($enrollment, $user)));
            }

            return redirect()->route('learner.modules.index')
                ->with('success', 'Your enrollment request has been submitted. Please wait for guardian approval.');
        }

        $effectiveEnrollmentLimit = $this->resolveEffectiveEnrollmentLimit($module);
        $isAtCapacity = $effectiveEnrollmentLimit !== null
            && ModuleEnrollment::query()
                ->approvedForModule($module->id)
                ->count() >= $effectiveEnrollmentLimit;

        if ($isAtCapacity) {
            ModuleEnrollment::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'status' => EnrollmentStatus::Pending,
                'enrolled_at' => null,
            ]);

            return redirect()->route('learner.modules.index')
                ->with('success', 'Module capacity has been reached. Your enrollment is queued for manual review.');
        }

        // Check enrollment mode
        if ($module->enrollment_mode === 'manual') {
            // Manual approval - create pending enrollment
            ModuleEnrollment::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'status' => EnrollmentStatus::Pending,
                'enrolled_at' => null,
            ]);

            return redirect()->route('learner.modules.index')
                ->with('success', 'Your enrollment request has been submitted. Please wait for instructor approval.');
        }

        // Auto approval - create approved enrollment
        ModuleEnrollment::create([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'status' => EnrollmentStatus::Approved,
            'enrolled_at' => now(),
        ]);

        return redirect()->route('learner.modules.show', $module)
            ->with('success', 'Successfully enrolled in module!');
    }

    public function purchase(Module $module)
    {
        $user = Auth::user();

        if (!$user->learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        if (!$module->isLearnerVisible()) {
            abort(404);
        }

        $module->loadMissing('publishedRevision');
        $module->applyPublishedSnapshot();

        if (!$module->isPaidAccess()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'This module does not require payment.');
        }

        if ($module->isOwnedBy($user)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'You cannot purchase a module that you own.');
        }

        if (!$this->canReceivePaidEnrollments($module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Paid enrollment is currently unavailable for this module.');
        }

        $learnerAge = $user->learnerProfile->getAge();
        if (!$this->canAccessModule($module, $learnerAge)) {
            return redirect()->route('learner.modules.index')
                ->with('error', 'This module is not available for your age group.');
        }

        if ($this->modulePurchaseService->hasCompletedPurchase($user, $module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'You have already purchased this module.');
        }

        $existingEnrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->first();

        $needsParentApproval = ParentChildAccount::query()
            ->where('child_user_id', $user->id)
            ->where('can_approve_content', true)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->exists();

        if ($needsParentApproval) {
            if ($existingEnrollment?->status === EnrollmentStatus::PendingParentApproval) {
                return redirect()->route('learner.modules.show', $module)
                    ->with('info', 'Waiting for guardian approval before payment.');
            }

            if ($existingEnrollment?->status === EnrollmentStatus::Rejected) {
                return redirect()->route('learner.modules.show', $module)
                    ->with('error', 'Guardian approval was not granted for this module.');
            }

            if (!$existingEnrollment) {
                $enrollment = ModuleEnrollment::query()->create([
                    'user_id' => $user->id,
                    'module_id' => $module->id,
                    'status' => EnrollmentStatus::PendingParentApproval,
                    'enrolled_at' => null,
                ]);

                $enrollment->loadMissing('module');

                $parentApproverIds = ParentChildAccount::query()
                    ->where('child_user_id', $user->id)
                    ->where('can_approve_content', true)
                    ->where('verification_status', 'approved')
                    ->whereNotNull('relationship_verified_at')
                    ->pluck('parent_user_id')
                    ->unique()
                    ->values();

                if ($parentApproverIds->isNotEmpty()) {
                    User::query()
                        ->whereIn('id', $parentApproverIds)
                        ->get()
                        ->each(fn (User $parentUser) => $parentUser->notify(new ChildEnrollmentApprovalRequestedNotification($enrollment, $user)));
                }

                return redirect()->route('learner.modules.show', $module)
                    ->with('info', 'Request sent for guardian approval. Complete payment after approval.');
            }
        }

        $effectiveEnrollmentLimit = $this->resolveEffectiveEnrollmentLimit($module);
        $isAtCapacity = $effectiveEnrollmentLimit !== null
            && ModuleEnrollment::query()->approvedForModule($module->id)->count() >= $effectiveEnrollmentLimit;

        if ($isAtCapacity) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Enrollment Closed: this module is already full.');
        }

        return redirect()->route('learner.modules.purchase.form', $module);
    }

    public function purchaseForm(Module $module)
    {
        $user = Auth::user();

        if (!$user->learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        if (!$module->isLearnerVisible()) {
            abort(404);
        }

        $module->loadMissing('publishedRevision');
        $module->applyPublishedSnapshot();

        if (!$module->isPaidAccess()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'This module does not require payment.');
        }

        if ($module->isOwnedBy($user)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'You cannot purchase a module that you own.');
        }

        if (!$this->canReceivePaidEnrollments($module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Paid enrollment is currently unavailable for this module.');
        }

        $learnerAge = $user->learnerProfile->getAge();
        if (!$this->canAccessModule($module, $learnerAge)) {
            return redirect()->route('learner.modules.index')
                ->with('error', 'This module is not available for your age group.');
        }

        if ($this->modulePurchaseService->hasCompletedPurchase($user, $module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'You have already purchased this module.');
        }

        $existingEnrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->first();

        $needsParentApproval = ParentChildAccount::query()
            ->where('child_user_id', $user->id)
            ->where('can_approve_content', true)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->exists();

        $isParentApprovedForPurchase = !$needsParentApproval
            || ($existingEnrollment && in_array($existingEnrollment->status, [EnrollmentStatus::Pending, EnrollmentStatus::Approved], true));

        if ($needsParentApproval && !$isParentApprovedForPurchase) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'Guardian approval is required before payment.');
        }

        $effectiveEnrollmentLimit = $this->resolveEffectiveEnrollmentLimit($module);
        $isAtCapacity = $effectiveEnrollmentLimit !== null
            && ModuleEnrollment::query()->approvedForModule($module->id)->count() >= $effectiveEnrollmentLimit;

        if ($isAtCapacity) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Enrollment Closed: this module is already full.');
        }

        $secretKey = (string) config('paymongo.secret_key', '');
        $paymongoMode = str_starts_with($secretKey, 'sk_test_')
            ? 'sandbox'
            : (str_starts_with($secretKey, 'sk_live_') ? 'live' : 'unknown');

        $amount = (float) ($module->price_amount ?? 0);

        $pendingPayment = $user->payments()
            ->where('status', \App\Enums\PaymentStatus::Pending)
            ->where('payment_details->payment_scope', 'module_purchase')
            ->where('payment_details->module_id', $module->id)
            ->latest('id')
            ->first();

        $preferredPaymentMethod = (string) ($pendingPayment?->method ?? data_get($pendingPayment?->payment_details, 'payment_method', 'gcash'));
        if (!in_array($preferredPaymentMethod, ['gcash', 'paymaya', 'grab_pay', 'card'], true)) {
            $preferredPaymentMethod = 'gcash';
        }

        $module->loadMissing('creator');

        return view('payments.checkout-summary', [
            'scope' => 'module_purchase',
            'module' => $module,
            'amount' => $amount,
            'paymongoMode' => $paymongoMode,
            'paymentMethod' => $preferredPaymentMethod,
            'submitUrl' => route('learner.modules.purchase.process', $module),
            'backUrl' => route('learner.modules.show', $module),
        ]);
    }

    public function processPurchase(ProcessModulePaymentRequest $request, Module $module)
    {
        $user = Auth::user();

        if (!$user->learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        if (!$module->isLearnerVisible()) {
            abort(404);
        }

        $module->loadMissing('publishedRevision');
        $module->applyPublishedSnapshot();

        if (!$module->isPaidAccess()) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'This module does not require payment.');
        }

        if ($module->isOwnedBy($user)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'You cannot purchase a module that you own.');
        }

        if (!$this->canReceivePaidEnrollments($module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Paid enrollment is currently unavailable for this module.');
        }

        $learnerAge = $user->learnerProfile->getAge();
        if (!$this->canAccessModule($module, $learnerAge)) {
            return redirect()->route('learner.modules.index')
                ->with('error', 'This module is not available for your age group.');
        }

        if ($this->modulePurchaseService->hasCompletedPurchase($user, $module)) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'You have already purchased this module.');
        }

        $existingEnrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->first();

        $needsParentApproval = ParentChildAccount::query()
            ->where('child_user_id', $user->id)
            ->where('can_approve_content', true)
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->exists();

        $isParentApprovedForPurchase = !$needsParentApproval
            || ($existingEnrollment && in_array($existingEnrollment->status, [EnrollmentStatus::Pending, EnrollmentStatus::Approved], true));

        if ($needsParentApproval && !$isParentApprovedForPurchase) {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', 'Guardian approval is required before payment.');
        }

        $effectiveEnrollmentLimit = $this->resolveEffectiveEnrollmentLimit($module);
        $isAtCapacity = $effectiveEnrollmentLimit !== null
            && ModuleEnrollment::query()->approvedForModule($module->id)->count() >= $effectiveEnrollmentLimit;

        if ($isAtCapacity) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Enrollment Closed: this module is already full.');
        }

        $selectedMethod = $request->filled('payment_method') ? (string) $request->input('payment_method') : null;
        if ($selectedMethod !== null && !in_array($selectedMethod, ['gcash', 'paymaya', 'grab_pay', 'card'], true)) {
            $selectedMethod = null;
        }

        $checkout = $this->modulePurchaseService->createCheckout(
            $user,
            $module,
            $selectedMethod,
            [
                'name' => (string) ($request->input('billing_name') ?: $user->name),
                'email' => (string) ($request->input('billing_email') ?: ($user->email ?? '')),
                'phone' => (string) ($request->input('billing_phone') ?: ''),
            ]
        );

        if (($checkout['status'] ?? null) !== 'checkout_created') {
            return redirect()->route('learner.modules.show', $module)
                ->with('info', $checkout['message'] ?? 'Unable to start checkout right now.');
        }

        $paymentId = (int) ($checkout['payment_id'] ?? 0);
        if ($paymentId > 0) {
            return redirect()->route('payment.pending', ['payment' => $paymentId])
                ->with('paymongo_checkout_url', (string) ($checkout['checkout_url'] ?? ''));
        }

        return redirect()->away((string) ($checkout['checkout_url'] ?? route('learner.modules.show', $module)));
    }

    public function purchaseSuccess(Module $module)
    {
        $user = Auth::user();

        $payment = $user->payments()
            ->where('status', \App\Enums\PaymentStatus::Pending)
            ->where('payment_details->payment_scope', 'module_purchase')
            ->where('payment_details->module_id', $module->id)
            ->latest('id')
            ->first();

        if (!$payment) {
            return redirect()->route('learner.modules.show', $module)
                ->with('success', 'Payment completed. Access will unlock once confirmation is received.');
        }

        $completed = $this->modulePurchaseService->verifyAndCompletePendingPayment($payment);

        if ($completed) {
            $user->notify(new ModulePurchaseResultNotification($module, 'success'));
        }

        return redirect()->route('learner.modules.show', $module)
            ->with($completed ? 'success' : 'info', $completed
                ? 'Payment confirmed. You now have access to this module.'
                : 'Payment is still being confirmed. Please refresh in a moment.');
    }

    public function purchaseFailed(Module $module)
    {
        Auth::user()->notify(new ModulePurchaseResultNotification($module, 'failed'));

        return redirect()->route('learner.modules.purchase.form', $module)
            ->with('error', 'Payment was cancelled or failed. Review your details and try again.');
    }

    /**
     * Show post-final-quiz completion page for an enrolled learner.
     */
    public function completion(Module $module)
    {
        $user = Auth::user();

        if (!$user->learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access modules.');
        }

        $enrollment = $user->moduleEnrollments()
            ->where('module_id', $module->id)
            ->first();

        if (!$enrollment || $enrollment->status !== EnrollmentStatus::Approved) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'You must be enrolled in this module to view completion details.');
        }

        if (!$module->final_quiz_id) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'This module does not have a final quiz completion flow.');
        }

        $hasPassedFinalQuiz = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $module->final_quiz_id)
            ->where('passed', true)
            ->exists();

        if (!$hasPassedFinalQuiz) {
            return redirect()->route('learner.modules.show', $module)
                ->with('error', 'Pass the final quiz first to unlock module completion.');
        }

        if ($enrollment->completed_at === null && $this->completionService->isFullyCompleted($user, $module)) {
            $enrollment->update([
                'completed_at' => now(),
                'completion_percentage' => 100,
            ]);

            $moduleCompletionPoints = $this->gamificationService->awardConfiguredPoints($user, 'module_completed');
            if ($moduleCompletionPoints > 0) {
                session()->flash('points_earned', $moduleCompletionPoints);
            }
        }

        $module->loadMissing('publishedRevision');
        $module->applyPublishedSnapshot();

        $certificate = $user->certificates()
            ->where('module_id', $module->id)
            ->first();

        return view('learner.modules.completion', compact('module', 'certificate'));
    }

    /**
     * Check if learner can access module based on age
     */
    private function canAccessModule(Module $module, int $learnerAge): bool
    {
        return $module->isAppropriateForAge($learnerAge);
    }

    private function canReceivePaidEnrollments(Module $module): bool
    {
        if (!$module->isPaidAccess()) {
            return true;
        }

        if ((string) ($module->content_owner_type ?? '') !== 'instructor') {
            return true;
        }

        $instructor = $module->creator;
        if (!$instructor) {
            return false;
        }

        return $this->instructorPlanCapabilityService->canReceivePaidEnrollments($instructor);
    }

    private function resolveEffectiveEnrollmentLimit(Module $module): ?int
    {
        $effectiveLimit = $module->enrollment_limit !== null ? (int) $module->enrollment_limit : null;

        if ((string) ($module->content_owner_type ?? '') !== 'instructor') {
            return $effectiveLimit;
        }

        $instructor = $module->creator;
        if (!$instructor) {
            return $effectiveLimit;
        }

        $planCap = $this->instructorPlanCapabilityService->getLearnerCapForModule(
            $instructor,
            $module->isPaidAccess() ? 'paid' : 'free'
        );
        if ($planCap === null) {
            return $effectiveLimit;
        }

        if ($effectiveLimit === null) {
            return $planCap;
        }

        return min($effectiveLimit, $planCap);
    }
}
