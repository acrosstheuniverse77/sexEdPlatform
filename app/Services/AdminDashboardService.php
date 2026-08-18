<?php

namespace App\Services;

use App\Models\InstructorApplication;
use App\Models\ContentReport;
use App\Models\MessageReport;
use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\ParentChildAccount;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function getCommandCenterPayload(): array
    {
        $analytics = $this->buildDashboardAnalytics();
        $learnerDemographics = $this->buildLearnerDemographics();

        $pendingParentVerifications = User::query()
            ->where('is_parent_registration', true)
            ->where(function ($query): void {
                $query->where('parent_verification_status', 'pending')
                    ->orWhereNull('parent_verification_status');
            })
            ->count();

        $pendingChildVerifications = ParentChildAccount::query()
            ->whereNotNull('verification_document_path')
            ->where(function ($query): void {
                $query->where('verification_status', 'pending')
                    ->orWhereNull('verification_status');
            })
            ->count();

        $combinedPendingVerifications = $pendingParentVerifications + $pendingChildVerifications;

        $pendingLearnerReports = ContentReport::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->count();
        $pendingMessageReports = MessageReport::query()
            ->whereIn('status', ['open', 'under_review'])
            ->count();

        return [
            'overview_cards' => $this->buildOverviewCards($analytics),
            'snapshot_metrics' => [
                [
                    'label' => 'Total Users',
                    'value' => User::query()->count(),
                    'description' => 'All accounts currently present in the platform.',
                    'accent' => 'sky',
                ],
                [
                    'label' => 'Total Instructors',
                    'value' => User::role('instructor')->count(),
                    'description' => 'Approved instructors with publishing privileges.',
                    'accent' => 'violet',
                ],
                [
                    'label' => 'Total Learners',
                    'value' => User::role('learner')->count(),
                    'description' => 'Learner accounts currently enrolled in the platform.',
                    'accent' => 'emerald',
                ],
                [
                    'label' => 'Total Modules',
                    'value' => Module::query()->count(),
                    'description' => 'All learning modules across instructor and admin ownership.',
                    'accent' => 'amber',
                ],
                [
                    'label' => 'Active Subscriptions',
                    'value' => Subscription::query()->where('status', 'active')->count(),
                    'description' => 'Subscribers with active billing and access entitlement.',
                    'accent' => 'fuchsia',
                ],
                [
                    'label' => 'Pending Instructor Applications',
                    'value' => InstructorApplication::query()->where('status', 'pending')->count(),
                    'description' => 'Learner applications waiting for instructor approval.',
                    'accent' => 'orange',
                ],
                [
                    'label' => 'Pending Module Reviews',
                    'value' => ModuleReviewRequest::query()->whereIn('status', ['submitted', 'in_review'])->count(),
                    'description' => 'Submitted modules currently queued for moderation.',
                    'accent' => 'indigo',
                ],
                [
                    'label' => 'Payments Needing Review',
                    'value' => Payment::query()->whereIn('status', ['pending', 'processing'])->count(),
                    'description' => 'Transactions pending reconciliation or completion.',
                    'accent' => 'rose',
                ],
            ],
            'moderation_queues' => [
                [
                    'label' => 'Instructor Applications',
                    'count' => InstructorApplication::query()->where('status', 'pending')->count(),
                    'cta_label' => 'Open Queue',
                    'cta_route' => route('admin.instructor-applications.index', ['status' => 'pending']),
                    'description' => 'Review learner requests to become instructors.',
                    'accent' => 'amber',
                ],
                [
                    'label' => 'Module Published Review',
                    'count' => ModuleReviewRequest::query()->whereIn('status', ['submitted', 'in_review'])->count(),
                    'cta_label' => 'Open Queue',
                    'cta_route' => route('admin.content-reviews.index'),
                    'description' => 'Moderate instructor module submissions before publish.',
                    'accent' => 'sky',
                ],
                [
                    'label' => 'Parent & Child Verifications',
                    'count' => $combinedPendingVerifications,
                    'cta_label' => 'Open Queue',
                    'cta_route' => route('admin.parent-verifications.index', [
                        'status' => 'pending',
                    ]),
                    'description' => 'Review pending guardian and child verification requests.',
                    'accent' => 'violet',
                ],
                [
                    'label' => 'Report Review Queue',
                    'count' => $pendingLearnerReports + $pendingMessageReports,
                    'cta_label' => 'Open Queue',
                    'cta_route' => route('admin.moderation-suspensions.index'),
                    'description' => 'Review learner and chat safety reports in the centralized moderation dashboard.',
                    'accent' => 'rose',
                ],
            ],
            'recent_activity' => $this->getRecentSystemActivity(),
            'analytics' => $analytics,
            'learner_demographics' => $learnerDemographics,
        ];
    }

    private function buildOverviewCards(array $analytics): array
    {
        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $previousMonthStart = now()->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->copy()->subMonth()->endOfMonth();

        $learnerCurrent = User::role('learner')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $learnerPrevious = User::role('learner')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $instructorCurrent = User::role('instructor')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $instructorPrevious = User::role('instructor')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $moduleCurrent = Module::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $modulePrevious = Module::query()
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $revenueTrend = $analytics['monthly_revenue_previous'] > 0
            ? (($analytics['monthly_revenue_current'] - $analytics['monthly_revenue_previous']) / $analytics['monthly_revenue_previous']) * 100
            : ($analytics['monthly_revenue_current'] > 0 ? 100.0 : 0.0);

        return [
            [
                'label' => 'Learners',
                'value' => User::role('learner')->count(),
                'delta_percent' => round($this->computeTrendPercent($learnerCurrent, $learnerPrevious), 2),
                'accent' => 'emerald',
            ],
            [
                'label' => 'Instructors',
                'value' => User::role('instructor')->count(),
                'delta_percent' => round($this->computeTrendPercent($instructorCurrent, $instructorPrevious), 2),
                'accent' => 'sky',
            ],
            [
                'label' => 'Total Modules',
                'value' => Module::query()->count(),
                'delta_percent' => round($this->computeTrendPercent($moduleCurrent, $modulePrevious), 2),
                'accent' => 'violet',
            ],
            [
                'label' => 'Total Revenue',
                'value' => (float) Payment::query()->where('status', 'completed')->sum('amount'),
                'delta_percent' => round($revenueTrend, 2),
                'accent' => 'amber',
                'is_currency' => true,
            ],
        ];
    }

    private function buildLearnerDemographics(): array
    {
        $learnerBaseQuery = User::role('learner');
        $totalLearners = max(1, (clone $learnerBaseQuery)->count());

        $kidsMaxBirthdate = now()->subYears(5)->toDateString();
        $kidsMinBirthdateExclusive = now()->subYears(13)->toDateString();
        $teensMaxBirthdate = now()->subYears(13)->toDateString();
        $teensMinBirthdateExclusive = now()->subYears(18)->toDateString();
        $adultsMaxBirthdate = now()->subYears(18)->toDateString();

        $rows = [
            [
                'label' => 'Kids (5-12)',
                'count' => (clone $learnerBaseQuery)
                    ->where(function ($query) use ($kidsMaxBirthdate, $kidsMinBirthdateExclusive): void {
                        $query->where('age_bracket_cached', 'kids')
                            ->orWhere(function ($fallback) use ($kidsMaxBirthdate, $kidsMinBirthdateExclusive): void {
                                $fallback->where(function ($missingCache): void {
                                    $missingCache->whereNull('age_bracket_cached')
                                        ->orWhere('age_bracket_cached', '');
                                })->whereHas('learnerProfile', function ($profileQuery) use ($kidsMaxBirthdate, $kidsMinBirthdateExclusive): void {
                                    $profileQuery
                                        ->whereNotNull('birthdate')
                                        ->whereDate('birthdate', '<=', $kidsMaxBirthdate)
                                        ->whereDate('birthdate', '>', $kidsMinBirthdateExclusive);
                                });
                            });
                    })
                    ->count(),
                'accent' => 'sky',
            ],
            [
                'label' => 'Teens (13-17)',
                'count' => (clone $learnerBaseQuery)
                    ->where(function ($query) use ($teensMaxBirthdate, $teensMinBirthdateExclusive): void {
                        $query->where('age_bracket_cached', 'teens')
                            ->orWhere(function ($fallback) use ($teensMaxBirthdate, $teensMinBirthdateExclusive): void {
                                $fallback->where(function ($missingCache): void {
                                    $missingCache->whereNull('age_bracket_cached')
                                        ->orWhere('age_bracket_cached', '');
                                })->whereHas('learnerProfile', function ($profileQuery) use ($teensMaxBirthdate, $teensMinBirthdateExclusive): void {
                                    $profileQuery
                                        ->whereNotNull('birthdate')
                                        ->whereDate('birthdate', '<=', $teensMaxBirthdate)
                                        ->whereDate('birthdate', '>', $teensMinBirthdateExclusive);
                                });
                            });
                    })
                    ->count(),
                'accent' => 'violet',
            ],
            [
                'label' => 'Adults (18+)',
                'count' => (clone $learnerBaseQuery)
                    ->where(function ($query) use ($adultsMaxBirthdate): void {
                        $query->where('age_bracket_cached', 'adults')
                            ->orWhere(function ($fallback) use ($adultsMaxBirthdate): void {
                                $fallback->where(function ($missingCache): void {
                                    $missingCache->whereNull('age_bracket_cached')
                                        ->orWhere('age_bracket_cached', '');
                                })->whereHas('learnerProfile', function ($profileQuery) use ($adultsMaxBirthdate): void {
                                    $profileQuery
                                        ->whereNotNull('birthdate')
                                        ->whereDate('birthdate', '<=', $adultsMaxBirthdate);
                                });
                            });
                    })
                    ->count(),
                'accent' => 'emerald',
            ],
        ];

        return array_map(function (array $row) use ($totalLearners): array {
            $row['percent'] = round(($row['count'] / $totalLearners) * 100, 2);

            return $row;
        }, $rows);
    }

    private function computeTrendPercent(int|float $current, int|float $previous): float
    {
        if ($previous > 0) {
            return (($current - $previous) / $previous) * 100;
        }

        return $current > 0 ? 100.0 : 0.0;
    }

    private function buildDashboardAnalytics(): array
    {
        $labels = [];
        $counts = [];

        for ($offset = 11; $offset >= 0; $offset--) {
            $month = now()->copy()->startOfMonth()->subMonths($offset);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $labels[] = $month->format('M');
            $counts[] = Subscription::query()
                ->where('status', 'active')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
        }

        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $previousMonthStart = now()->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->copy()->subMonth()->endOfMonth();

        $currentMonthSubscribers = Subscription::query()
            ->where('status', 'active')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();

        $previousMonthSubscribers = Subscription::query()
            ->where('status', 'active')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $subscriberTrendPercent = $previousMonthSubscribers > 0
            ? (($currentMonthSubscribers - $previousMonthSubscribers) / $previousMonthSubscribers) * 100
            : ($currentMonthSubscribers > 0 ? 100.0 : 0.0);

        $monthlyTarget = max(1, (int) config('billing.admin_dashboard_monthly_subscriber_target', 200));
        $targetPercent = min(100, round(($currentMonthSubscribers / $monthlyTarget) * 100, 2));

        $currentMonthRevenue = (float) Payment::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        $previousMonthRevenue = (float) Payment::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        return [
            'monthly_labels' => $labels,
            'monthly_subscriber_counts' => $counts,
            'active_subscribers_total' => Subscription::query()->where('status', 'active')->count(),
            'monthly_subscribers_current' => $currentMonthSubscribers,
            'monthly_subscribers_previous' => $previousMonthSubscribers,
            'monthly_subscriber_trend_percent' => round($subscriberTrendPercent, 2),
            'monthly_target' => $monthlyTarget,
            'monthly_target_percent' => $targetPercent,
            'monthly_revenue_current' => round($currentMonthRevenue, 2),
            'monthly_revenue_previous' => round($previousMonthRevenue, 2),
        ];
    }

    private function getRecentSystemActivity(): Collection
    {
        $applicationEvents = InstructorApplication::query()
            ->with('user')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (InstructorApplication $application): array {
                $status = (string) $application->status;
                $tone = $status === 'approved' ? 'emerald' : ($status === 'rejected' ? 'rose' : 'amber');

                return [
                    'type' => 'Instructor Application',
                    'title' => ($application->user?->name ?? 'Learner') . ' application is ' . str_replace('_', ' ', $status),
                    'meta' => 'Application #' . $application->id,
                    'occurred_at' => $application->updated_at,
                    'href' => route('admin.instructor-applications.index', [
                        'status' => $status,
                        'focus' => $application->id,
                    ]),
                    'tone' => $tone,
                ];
            })
            ->all();

        $moduleReviewEvents = ModuleReviewRequest::query()
            ->with('module')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (ModuleReviewRequest $reviewRequest): array {
                $status = (string) $reviewRequest->status;
                $tone = $status === 'approved' ? 'emerald' : ($status === 'rejected' ? 'rose' : 'sky');

                return [
                    'type' => 'Module Review',
                    'title' => ($reviewRequest->module_title ?: 'Module') . ' review is ' . str_replace('_', ' ', $status),
                    'meta' => 'Review #' . $reviewRequest->id,
                    'occurred_at' => $reviewRequest->updated_at,
                    'href' => route('admin.content-reviews.show', $reviewRequest),
                    'tone' => $tone,
                ];
            })
            ->all();

        $paymentEvents = Payment::query()
            ->with('user')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (Payment $payment): array {
                $status = is_object($payment->status) ? $payment->status->value : (string) $payment->status;
                $tone = $status === 'completed' ? 'emerald' : ($status === 'failed' ? 'rose' : 'amber');

                return [
                    'type' => 'Payment',
                    'title' => 'Payment #' . $payment->id . ' is ' . str_replace('_', ' ', $status),
                    'meta' => $payment->user?->name ?? 'Unknown user',
                    'occurred_at' => $payment->updated_at,
                    'href' => route('admin.payments.show', $payment),
                    'tone' => $tone,
                ];
            })
            ->all();

        return collect($applicationEvents)
            ->concat($moduleReviewEvents)
            ->concat($paymentEvents)
            ->sortByDesc(fn (array $event) => $event['occurred_at'])
            ->take(12)
            ->values();
    }
}
