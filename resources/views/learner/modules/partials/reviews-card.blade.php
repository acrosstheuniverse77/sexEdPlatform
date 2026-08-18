<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 space-y-4">
    <div class="flex items-center justify-between gap-2">
        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Module Reviews</h4>
        @if(Route::has('learner.modules.reviews'))
            <a href="{{ route('learner.modules.reviews', $module) }}" class="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline">
                View All
            </a>
        @endif
    </div>

    <div class="flex items-end justify-between gap-3">
        <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((float) ($reviewSummary['average'] ?? 0), 1) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Content rating</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ (int) ($reviewSummary['count'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Module reviews</p>
        </div>
    </div>

    <div class="space-y-2">
        @forelse($recentReviews as $review)
            @php
                $reviewerName = $review->learner?->full_name ?: ($review->learner?->name ?? 'Learner');
                $reviewerAvatarPath = $review->learner?->learnerProfile?->avatar_path;
                $reviewerAvatar = $reviewerAvatarPath
                    ? asset('storage/' . ltrim($reviewerAvatarPath, '/'))
                    : null;
            @endphp
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        @if($reviewerAvatar)
                            <img src="{{ $reviewerAvatar }}" alt="{{ $reviewerName }}" class="h-7 w-7 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                        @else
                            <div class="h-7 w-7 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center text-[10px] font-bold dark:bg-purple-900/40 dark:text-purple-300">
                                {{ strtoupper(substr($reviewerName, 0, 1)) }}
                            </div>
                        @endif
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $reviewerName }}</p>
                    </div>
                    <x-reviews.heart-rating :rating="$review->rating" size-class="h-3.5 w-3.5" text-class="text-xs font-semibold text-gray-600 dark:text-gray-300" />
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ strip_tags($review->review_html) }}</p>
            </div>
        @empty
            <p class="text-xs text-gray-500 dark:text-gray-400">No module reviews yet. Leave feedback after completing this module.</p>
        @endforelse
    </div>

    <div class="border-t border-gray-100 pt-4 dark:border-gray-700">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Instructor Reviews</h4>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((float) ($instructorReviewSummary['average'] ?? 0), 1) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ (int) ($instructorReviewSummary['count'] ?? 0) }} instructor review{{ (int) ($instructorReviewSummary['count'] ?? 0) === 1 ? '' : 's' }}</p>
            </div>
        </div>

        <div class="mt-3 space-y-2">
            @forelse($recentInstructorReviews as $review)
                @php
                    $reviewerName = $review->learner?->full_name ?: ($review->learner?->name ?? 'Learner');
                @endphp
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $reviewerName }}</p>
                        <x-reviews.heart-rating :rating="$review->rating" size-class="h-3.5 w-3.5" text-class="text-xs font-semibold text-gray-600 dark:text-gray-300" />
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ strip_tags((string) $review->review_html) ?: 'No written feedback.' }}</p>
                </div>
            @empty
                <p class="text-xs text-gray-500 dark:text-gray-400">No instructor reviews yet.</p>
            @endforelse
        </div>
    </div>

    @if($canSubmitReview)
        <button
            type="button"
            @click="reviewModalOpen = true"
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 transition"
            style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
        >
            Give Feedback
        </button>
    @elseif($reviewBlocker)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
            {{ $reviewBlocker }}
        </div>
    @endif
</div>
