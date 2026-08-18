<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\LessonTopic;
use App\Models\LessonTopicProgress;
use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\RewardLog;
use App\Models\UserDailyShield;
use App\Models\UserProgress;
use App\Models\InstructorApplication;
use App\Models\ParentChildInvitation;
use App\Models\User;
use App\Services\Gamification\GamificationPolicyResolver;
use App\Services\SubscriptionService;
use App\Support\SubscriptionFeatureKeys;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly GamificationPolicyResolver $gamificationPolicyResolver,
    ) {
    }

    public function index()
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $learnerProfile = $user->learnerProfile;

        if (!$learnerProfile) {
            return redirect()->route('profile.complete')
                ->with('error', 'Please complete your profile to access your dashboard.');
        }

        // ── Enrolled modules with progress ──────────────────────────────
        $enrollments = ModuleEnrollment::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with(['module' => function ($query) {
                $query->withCount(['lessons' => function ($q) {
                    $q->where('is_published', true);
                }])->with(['creator.instructorProfile']);
            }])
            ->latest()
            ->get();

        $enrollmentData = $enrollments->map(function ($enrollment) use ($user) {
            $module = $enrollment->module;
            if (!$module) return null;

            $totalLessons = $module->lessons_count;

            // Lesson-level count for card display ("X/Y lessons completed")
            $completedLessons = UserProgress::where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->where('completed', true)
                ->count();

            // Topic-based progress — reflects partial lesson progress accurately
            $lessonIds = $module->lessons()->where('is_published', true)->pluck('id');
            $totalTopics = LessonTopic::whereIn('lesson_id', $lessonIds)->count();

            if ($totalTopics > 0) {
                $completedTopics = LessonTopicProgress::where('user_id', $user->id)
                    ->whereIn('lesson_topic_id', function ($q) use ($lessonIds) {
                        $q->select('id')->from('lesson_topics')->whereIn('lesson_id', $lessonIds);
                    })
                    ->where('completed', true)
                    ->count();
                $progressPercent = round(($completedTopics / $totalTopics) * 100);
            } else {
                // No topics: fall back to lesson-level completion
                $progressPercent = $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100)
                    : 0;
            }

            // Find the first incomplete lesson for "Continue Learning"
            $nextLesson = $module->lessons()
                ->where('is_published', true)
                ->whereDoesntHave('userProgress', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('completed', true);
                })
                ->orderBy('order')
                ->first();

            return [
                'enrollment'        => $enrollment,
                'module'            => $module,
                'total_lessons'     => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percent'  => $progressPercent,
                'next_lesson'       => $nextLesson,
            ];
        })->filter()->values();

        // ── Recommended modules ─────────────────────────────────────────
        $enrolledModuleIds = $enrollments->pluck('module_id')->toArray();
        $learnerAge = $learnerProfile->getAge();

        $recommendedModules = Module::published()
            ->forAge($learnerAge)
            ->whereNotIn('id', $enrolledModuleIds)
            ->with(['creator.instructorProfile'])
            ->withCount(['lessons' => fn($q) => $q->where('is_published', true)])
            ->orderBy('order')
            ->limit(6)
            ->get();

        // ── Stats ───────────────────────────────────────────────────────
        $totalEnrolled  = $enrollments->count();
        $totalCompleted = $enrollments->whereNotNull('completed_at')->count();
        $inProgress     = $totalEnrolled - $totalCompleted;

        // ── Gamification ────────────────────────────────────────────────
        $gamification = $user->gamification;
        $policy = $this->gamificationPolicyResolver->resolve();

        $currentScore = (int) ($gamification?->score ?? 0);
        $currentLevel = max(1, (int) ($gamification?->level ?? 1));
        [$xpInLevel, $xpToNext, $xpPercent, $xpLevelSpan] = $this->resolveXpProgress($currentScore, $currentLevel, $policy);

        $maxStreakSavers = max(0, (int) data_get($policy, 'streak_config.max_savers_held', 3));
        $streakSaverCost = max(0, (int) data_get($policy, 'streak_config.saver_purchase_cost_points', 75));
        $shieldCap = max(0, (int) data_get($policy, 'shield_config.max_shields_per_day_cap', 3));

        $singleShieldRefillCost = max(0, (int) data_get($policy, 'shield_config.refill_single_cost_points', 50));
        $fullShieldRefillCost = max(0, (int) data_get($policy, 'shield_config.refill_full_cost_points', 100));
        $fullShieldRefillTarget = max(0, (int) data_get($policy, 'shield_config.refill_full_target_shields', $shieldCap));

        // ── Shields today ───────────────────────────────────────────────
        $shieldsRemaining = UserDailyShield::getShields($user);

        // ── Streak data ─────────────────────────────────────────────────
        $streakActiveDays = LessonTopicProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->whereBetween('completed_at', [
                now()->startOfWeek(0),
                now()->endOfWeek(6),
            ])
            ->get()
            ->map(fn($p) => (int) $p->completed_at->dayOfWeek)
            ->unique()
            ->values()
            ->toArray();
        $longestStreak = $gamification?->longest_streak ?? 0;
        $streakSavers  = $gamification?->streak_savers ?? 0;

        // ── Recent achievements ─────────────────────────────────────────
        $recentAchievements = RewardLog::where('user_id', $user->id)
            ->with('achievement')
            ->orderByDesc('earned_at')
            ->take(3)
            ->get()
            ->pluck('achievement')
            ->filter();

        // ── Greeting ────────────────────────────────────────────────────
        $hour     = now('Asia/Manila')->hour;
        $greeting = match (true) {
            $hour < 12  => 'Good Morning',
            $hour < 17  => 'Good Afternoon',
            default     => 'Good Evening',
        };

        // ── Profile modal data ───────────────────────────────────────────
        $currentSubscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();

        $currentPlan = $currentSubscription && $currentSubscription->plan_id
            ? \App\Models\SubscriptionPlan::find($currentSubscription->plan_id)
            : null;

        $usernameCooldownDays = 0;
        $isPremium = $this->subscriptionService->isUserPremium($user);
        $hasUnlimitedUsernameChanges = $this->subscriptionService->hasFeature(
            $user,
            SubscriptionFeatureKeys::UNLIMITED_USERNAME_CHANGE
        );
        $hasUnlimitedQuizShields = $this->subscriptionService->hasFeature(
            $user,
            SubscriptionFeatureKeys::UNLIMITED_QUIZ_SHIELDS
        );

        if (!$hasUnlimitedUsernameChanges && $learnerProfile->username_changed_at) {
            $daysSince = now()->diffInDays($learnerProfile->username_changed_at);
            $usernameCooldownDays = $daysSince < 7 ? (7 - (int) $daysSince) : 0;
        }

        $profileEntitlementHints = [
            [
                'key' => 'username_change',
                'label' => 'Username changes',
                'value' => $hasUnlimitedUsernameChanges ? 'Unlimited' : 'Every 7 days',
                'description' => $hasUnlimitedUsernameChanges
                    ? 'Your current plan removes the username cooldown.'
                    : 'Free baseline applies a 7-day cooldown between username changes.',
                'is_enabled' => $hasUnlimitedUsernameChanges,
            ],
            [
                'key' => 'quiz_shields',
                'label' => 'Quiz shields',
                'value' => $hasUnlimitedQuizShields ? 'Unlimited' : '3 per day',
                'description' => $hasUnlimitedQuizShields
                    ? 'Retry quizzes without consuming daily shields.'
                    : 'Free baseline includes 3 shields each day, then resets at midnight.',
                'is_enabled' => $hasUnlimitedQuizShields,
            ],
        ];

        $latestInstructorApplication = InstructorApplication::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();
        $hasPendingInstructorApplication = $latestInstructorApplication?->status === 'pending';
        $canApplyAsInstructor = $user->isLearner()
            && ! $user->isParentRegistration()
            && ! $user->children()->exists()
            && ! $hasPendingInstructorApplication;

        $incomingParentInvitations = ParentChildInvitation::query()
            ->where('child_user_id', $user->id)
            ->where('status', 'pending')
            ->with(['inviterParent:id,name,email'])
            ->latest('id')
            ->take(5)
            ->get();

        $approvedParentLinks = $user->parentLinks()
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->with([
                'parent:id,name,email,birthdate',
                'parent.learnerProfile:id,user_id,avatar_path,birthdate',
            ])
            ->orderByDesc('relationship_verified_at')
            ->take(3)
            ->get()
            ->filter(fn ($link) => $link->parent)
            ->values();

        return view('learner.dashboard', compact(
            'learnerProfile',
            'enrollmentData',
            'totalEnrolled',
            'totalCompleted',
            'inProgress',
            'recommendedModules',
            'gamification',
            'xpInLevel',
            'xpToNext',
            'xpPercent',
            'xpLevelSpan',
            'shieldsRemaining',
            'shieldCap',
            'singleShieldRefillCost',
            'fullShieldRefillCost',
            'fullShieldRefillTarget',
            'streakActiveDays',
            'longestStreak',
            'streakSavers',
            'maxStreakSavers',
            'streakSaverCost',
            'recentAchievements',
            'greeting',
            'currentSubscription',
            'currentPlan',
            'isPremium',
            'usernameCooldownDays',
            'hasUnlimitedQuizShields',
            'profileEntitlementHints',
            'latestInstructorApplication',
            'hasPendingInstructorApplication',
            'canApplyAsInstructor',
            'incomingParentInvitations',
            'approvedParentLinks',
        ));
    }

    private function resolveXpProgress(int $score, int $level, array $policy): array
    {
        $currentLevelXp = $this->xpRequirementForLevel($level, $policy);
        $nextLevelXp = $this->xpRequirementForLevel($level + 1, $policy);
        $xpLevelSpan = max(1, $nextLevelXp - $currentLevelXp);
        $xpInLevel = max(0, $score - $currentLevelXp);
        $xpToNext = max(0, $nextLevelXp - $score);
        $xpPercent = (int) max(0, min(100, round(($xpInLevel / $xpLevelSpan) * 100)));

        return [$xpInLevel, $xpToNext, $xpPercent, $xpLevelSpan];
    }

    private function xpRequirementForLevel(int $level, array $policy): int
    {
        $level = max(1, $level);
        $levelingConfig = data_get($policy, 'leveling_config', []);
        $resolutionMode = (string) data_get($levelingConfig, 'threshold_resolution', 'explicit_then_formula');
        $thresholds = data_get($levelingConfig, 'explicit_thresholds', []);

        if (!is_array($thresholds)) {
            $thresholds = [];
        }

        $normalizedThresholds = [];
        foreach ($thresholds as $thresholdLevel => $xp) {
            $normalizedThresholds[(int) $thresholdLevel] = (int) $xp;
        }

        ksort($normalizedThresholds);

        $baseXpPerLevel = max(1, (int) data_get($levelingConfig, 'formula.base_xp_per_level', 100));
        $growthFactor = max(1, (int) data_get($levelingConfig, 'formula.growth_factor', 1));
        $effectiveStep = max(1, $baseXpPerLevel * $growthFactor);

        if (array_key_exists($level, $normalizedThresholds)) {
            return $normalizedThresholds[$level];
        }

        if ($resolutionMode === 'explicit_then_formula' && !empty($normalizedThresholds)) {
            $highestLevel = (int) max(array_keys($normalizedThresholds));
            $highestXp = (int) ($normalizedThresholds[$highestLevel] ?? 0);

            if ($level > $highestLevel) {
                return $highestXp + (($level - $highestLevel) * $effectiveStep);
            }
        }

        return ($level - 1) * $effectiveStep;
    }
}
