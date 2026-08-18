<?php

namespace App\Providers;

use App\Events\PaymentSuccessful;
use App\Events\Chat\MessageSent as ChatMessageSent;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionExpired;
use App\Listeners\HandlePaymentSuccessful;
use App\Listeners\Chat\SendInAppChatMessageNotification;
use App\Listeners\HandleSubscriptionCreated;
use App\Listeners\HandleSubscriptionExpired;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\InstructorProfile;
use App\Models\InstructorApplication;
use App\Models\ContentReport;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\ParentChildAccount;
use App\Models\Quiz;
use App\Models\User;
use App\Models\AdminCreatorProfile;
use App\Observers\PaymentObserver;
use App\Policies\AdminCreatorProfilePolicy;
use App\Policies\LessonPolicy;
use App\Policies\InstructorProfilePolicy;
use App\Policies\ModulePolicy;
use App\Policies\ParentChildPolicy;
use App\Policies\QuizPolicy;
use App\Policies\TopicPolicy;
use App\Services\Instructor\InstructorPlanCapabilityService;
use App\Services\SubscriptionService;
use App\Support\ContentPanelContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ContentPanelContext::class, static function (): ContentPanelContext {
            return ContentPanelContext::fromRequest(request());
        });
    }

    public function boot(): void
    {
        Gate::before(function (?User $user, string $ability) {
            if ($user?->hasRole('admin')) {
                return true;
            }

            return null;
        });

        RateLimiter::for('chat-messages', function (Request $request) {
            $userId = (int) ($request->user()?->id ?? 0);
            $conversationRouteParam = $request->route('conversation');

            $conversationId = is_object($conversationRouteParam)
                ? (int) ($conversationRouteParam->id ?? 0)
                : (int) $conversationRouteParam;

            $key = "chat-message:{$userId}:{$conversationId}";

            return Limit::perSecond(10, 10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'You are sending messages too quickly. Please wait a moment.',
                    ], 429, $headers);
                });
        });

        Gate::policy(User::class, ParentChildPolicy::class);
        Gate::policy(AdminCreatorProfile::class, AdminCreatorProfilePolicy::class);
        Gate::policy(InstructorProfile::class, InstructorProfilePolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(LessonTopic::class, TopicPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);

        Payment::observe(PaymentObserver::class);

        Event::listen(PaymentSuccessful::class, HandlePaymentSuccessful::class);
        Event::listen(SubscriptionCreated::class, HandleSubscriptionCreated::class);
        Event::listen(SubscriptionExpired::class, HandleSubscriptionExpired::class);
        Event::listen(ChatMessageSent::class, SendInAppChatMessageNotification::class);

        View::composer('layouts.admin', function ($view): void {
            $moderationCounts = [
                'pending_instructor_applications' => InstructorApplication::query()->where('status', 'pending')->count(),
                'pending_module_reviews' => ModuleReviewRequest::query()->where('status', 'in_review')->count(),
                'pending_parent_verifications' => User::query()
                    ->where('is_parent_registration', true)
                    ->where(function ($query): void {
                        $query->where('parent_verification_status', 'pending')
                            ->orWhereNull('parent_verification_status');
                    })
                    ->count(),
                'pending_child_verifications' => ParentChildAccount::query()
                    ->whereNotNull('verification_document_path')
                    ->where('verification_status', 'pending')
                    ->count(),
                'pending_learner_reports' => ContentReport::query()->whereIn('status', ['submitted', 'under_review'])->count(),
            ];

            $operationalSignalItems = collect([
                [
                    'label' => 'Pending payments',
                    'value' => Payment::query()->whereIn('status', ['pending', 'processing'])->count(),
                    'href' => route('admin.payments.index', ['status' => 'pending']),
                    'tone' => 'amber',
                    'message' => 'Payments waiting for review or reconciliation.',
                ],
                [
                    'label' => 'Subscriptions expiring soon',
                    'value' => Subscription::query()->expiringSoon()->count(),
                    'href' => route('admin.subscribers.index'),
                    'tone' => 'blue',
                    'message' => 'Active subscribers nearing the end of their access window.',
                ],
                [
                    'label' => 'Inactive plans',
                    'value' => SubscriptionPlan::query()->notArchived()->where('is_active', false)->count(),
                    'href' => route('admin.subscription-plans.index'),
                    'tone' => 'rose',
                    'message' => 'Plans saved in admin but not currently visible to learners.',
                ],
            ]);

            /** @var User|null $adminUser */
            $adminUser = Auth::user();
            $adminRecentNotifications = collect();
            $adminDbUnreadCount = 0;

            if ($adminUser && $adminUser->hasRole('admin')) {
                $adminRecentNotifications = $adminUser->notifications()->latest()->limit(8)->get();
                $adminDbUnreadCount = $adminUser->unreadNotifications()->count();
            }

            $view->with('adminNotifications', [
                'items' => $adminRecentNotifications,
                'unread_count' => $adminDbUnreadCount,
            ]);
            $view->with('adminOperationalSignals', [
                'items' => $operationalSignalItems->all(),
                'unread_count' => (int) $operationalSignalItems->sum('value'),
            ]);
            $view->with('adminModerationCounts', $moderationCounts);
        });

        View::composer('layouts.instructor-app', function ($view): void {
            /** @var User|null $user */
            $user = Auth::user();

            $indicator = [
                'plan_name' => 'Free Plan',
                'is_premium' => false,
                'status_key' => 'free',
                'status_label' => 'Free',
                'ends_at' => null,
            ];

            if ($user && $user->hasRole('instructor')) {
                $subscriptionService = app(SubscriptionService::class);
                $capabilityService = app(InstructorPlanCapabilityService::class);

                $subscriptionService->reconcileLifecycleForUser($user);

                $eligibleSubscription = $subscriptionService->getEligibleSubscriptionForEntitlements($user);
                $latestInstructorSubscription = $user->subscriptions()
                    ->whereHas('plan', function ($query) {
                        $query->where('plan_audience', 'instructor');
                    })
                    ->with('plan')
                    ->latest('id')
                    ->first();

                if ($eligibleSubscription && (string) ($eligibleSubscription->plan?->plan_audience ?? '') === 'instructor') {
                    $latestInstructorSubscription = $eligibleSubscription->loadMissing('plan');
                }

                if ($latestInstructorSubscription) {
                    $statusKey = (string) ($latestInstructorSubscription->status->value ?? $latestInstructorSubscription->status);
                    $isPremium = (float) ($latestInstructorSubscription->plan?->price ?? 0) > 0
                        && in_array($statusKey, ['active', 'grace_period'], true);

                    $indicator['is_premium'] = $isPremium;
                    $indicator['plan_name'] = $isPremium
                        ? ($latestInstructorSubscription->plan?->name ?? 'Premium Plan')
                        : ($capabilityService->resolveBaselinePlan()?->name ?? 'Free Plan');
                    $indicator['status_key'] = $isPremium
                        ? 'active'
                        : (in_array($statusKey, ['expired', 'cancelled', 'pending'], true) ? $statusKey : 'free');
                    $indicator['status_label'] = match ($indicator['status_key']) {
                        'active' => 'Premium Active',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                        'pending' => 'Pending Payment',
                        default => 'Free',
                    };
                    $indicator['ends_at'] = $latestInstructorSubscription->ends_at ?? $latestInstructorSubscription->end_date;
                } else {
                    $indicator['plan_name'] = $capabilityService->resolveBaselinePlan()?->name ?? 'Free Plan';
                }
            }

            $view->with('instructorSubscriptionIndicator', $indicator);
        });

        View::composer([
            'instructor.modules.*',
            'instructor.lessons.*',
            'instructor.topics.*',
            'instructor.quizzes.*',
            'instructor.enrollments.*',
            'instructor.image-library.*',
        ], function ($view): void {
            $contentPanel = app(ContentPanelContext::class);

            $view->with('contentPanel', $contentPanel);
            $view->with('contentPanelLayout', $contentPanel->layout());
            $view->with('contentRoutePrefix', $contentPanel->routePrefix());
            $view->with('isContentAdminPanel', $contentPanel->isAdmin());
        });
    }
}
