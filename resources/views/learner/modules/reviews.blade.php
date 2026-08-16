@extends('layouts.learner-app')

@section('title', $module->title . ' Reviews')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('learner.modules.show', $module) }}" class="text-xs font-semibold text-purple-600 hover:text-purple-700">← Back to Module</a>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $module->title }} Reviews</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Module content reviews are shown separately from instructor reviews.</p>
        </div>
        <div class="text-right">
            <p class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($summary['average'], 1) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Module average from {{ $summary['count'] }} review{{ $summary['count'] === 1 ? '' : 's' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-4">
            <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search review text or learner name" class="md:col-span-2 rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm">
                    <select name="sort" class="rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm">
                        <option value="newest" @selected($sort === 'newest')>Newest</option>
                        <option value="highest" @selected($sort === 'highest')>Highest Rating</option>
                        <option value="lowest" @selected($sort === 'lowest')>Lowest Rating</option>
                    </select>
                    <select name="rating" class="rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Any Rating</option>
                        @foreach(range(5, 1) as $ratingOption)
                            <option value="{{ $ratingOption }}" @selected((int) $exactRating === $ratingOption)>{{ $ratingOption }} hearts</option>
                        @endforeach
                    </select>
                    <select name="min_rating" class="rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Min Rating</option>
                        @foreach(range(5, 1) as $ratingOption)
                            <option value="{{ $ratingOption }}" @selected((int) $minRating === $ratingOption)>{{ $ratingOption }}+ hearts</option>
                        @endforeach
                    </select>
                    <div class="md:col-span-5 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 transition" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Apply Filters</button>
                        <a href="{{ route('learner.modules.reviews', $module) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Reset</a>
                    </div>
                </form>
            </div>

            <div class="space-y-3">
                @forelse($reviews as $review)
                    @php
                        $reviewerName = $review->learner?->full_name ?: ($review->learner?->name ?? 'Learner');
                        $reviewerAvatarPath = $review->learner?->learnerProfile?->avatar_path;
                        $reviewerAvatar = $reviewerAvatarPath
                            ? asset('storage/' . ltrim($reviewerAvatarPath, '/'))
                            : null;
                    @endphp
                    <article class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                @if($reviewerAvatar)
                                    <img src="{{ $reviewerAvatar }}" alt="{{ $reviewerName }}" class="h-10 w-10 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-purple-100 text-purple-700 font-bold flex items-center justify-center dark:bg-purple-900/40 dark:text-purple-300">
                                        {{ strtoupper(substr($reviewerName, 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $reviewerName }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $review->created_at?->format('M d, Y') }}</p>
                                </div>
                            </div>
                            <x-reviews.heart-rating :rating="$review->rating" size-class="h-4 w-4" text-class="text-sm font-semibold text-gray-600 dark:text-gray-300" />
                        </div>
                        <div class="mt-3 prose prose-sm max-w-none dark:prose-invert">
                            {!! $review->review_html !!}
                        </div>
                        @if($review->instructor_reply_html)
                            <div class="mt-3 rounded-xl border border-purple-200 bg-purple-50 px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-purple-700">Instructor Reply</p>
                                <div class="mt-1 text-sm text-purple-900 prose prose-sm max-w-none">
                                    {!! $review->instructor_reply_html !!}
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                        No reviews found for the current filters.
                    </div>
                @endforelse
            </div>

            <div>{{ $reviews->links() }}</div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Rating Breakdown</h2>
                <div class="mt-3 space-y-2">
                    @foreach(range(5, 1) as $rating)
                        @php
                            $count = (int) ($summary['distribution'][$rating] ?? 0);
                            $percent = $summary['count'] > 0 ? round(($count / $summary['count']) * 100) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
                                <span>{{ $rating }} hearts</span>
                                <span>{{ $count }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full rounded-full" style="width: {{ $percent }}%; background: linear-gradient(135deg, #A30EB2, #3B0CB1);"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Your Module Feedback</h2>
                @if($canSubmitReview)
                    <form method="POST" action="{{ route('learner.modules.feedback.store', $module) }}" class="mt-3 space-y-3" data-review-form="true" x-data="{ selectedRating: {{ (int) old('rating', $userFeedback?->rating ?? 0) }} }">
                        @csrf
                        <input type="hidden" name="feedback_type" value="module">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Rating</label>
                            <input type="hidden" name="rating" :value="selectedRating">
                            <div class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                @foreach(range(1, 5) as $ratingOption)
                                    <button
                                        type="button"
                                        aria-label="Select {{ $ratingOption }} hearts"
                                        @click="selectedRating = {{ $ratingOption }}"
                                        class="transition-transform duration-150 hover:scale-110 focus:outline-none"
                                    >
                                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" :class="selectedRating >= {{ $ratingOption }} ? 'text-rose-500' : 'text-gray-300 dark:text-gray-600'">
                                            <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                        </svg>
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="selectedRating > 0 ? `${selectedRating} heart${selectedRating > 1 ? 's' : ''} selected` : 'Select a rating to continue.'"></p>
                            @error('rating')
                                <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Tell us about your experience with this module</label>
                            <textarea id="review_content" name="review_content" rows="6" class="js-learner-rich-editor w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm" data-review-content="true">{!! old('review_content', $userFeedback?->review_html) !!}</textarea>
                            @error('review_content')
                                <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-rose-600 hidden" data-review-content-error="true">Please enter your review before submitting.</p>
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 transition" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            {{ $userFeedback ? 'Module Feedback Submitted' : 'Submit Module Feedback' }}
                        </button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $reviewBlocker }}</p>
                @endif
            </div>

            <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Your Instructor Feedback</h2>
                @if($canSubmitReview)
                    <form method="POST" action="{{ route('learner.modules.feedback.store', $module) }}" class="mt-3 space-y-3" x-data="{ selectedRating: {{ (int) old('rating', $userInstructorFeedback?->rating ?? 0) }} }">
                        @csrf
                        <input type="hidden" name="feedback_type" value="instructor">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Overall Instructor Rating</label>
                            <input type="hidden" name="rating" :value="selectedRating">
                            <div class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                @foreach(range(1, 5) as $ratingOption)
                                    <button type="button" aria-label="Select {{ $ratingOption }} hearts" @click="selectedRating = {{ $ratingOption }}" class="transition-transform duration-150 hover:scale-110 focus:outline-none">
                                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" :class="selectedRating >= {{ $ratingOption }} ? 'text-rose-500' : 'text-gray-300 dark:text-gray-600'">
                                            <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                        </svg>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Tell us about your experience with this instructor</label>
                            <textarea name="review_content" rows="5" class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm">{{ old('review_content', $userInstructorFeedback?->review_html) }}</textarea>
                        </div>
                        <button type="submit" @disabled($userInstructorFeedback) class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-xl hover:opacity-90 transition disabled:cursor-not-allowed disabled:opacity-50" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            {{ $userInstructorFeedback ? 'Instructor Feedback Submitted' : 'Submit Instructor Feedback' }}
                        </button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $reviewBlocker }}</p>
                @endif
            </div>
        </aside>
    </div>

    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Instructor Reviews</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Reviews about the instructor across their modules. Average: {{ number_format((float) ($instructorSummary['average'] ?? 0), 1) }} from {{ (int) ($instructorSummary['count'] ?? 0) }} review{{ (int) ($instructorSummary['count'] ?? 0) === 1 ? '' : 's' }}.</p>
        </div>
        @if($instructorReviews && $instructorReviews->count() > 0)
            @foreach($instructorReviews as $review)
                <article class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $review->learner?->full_name ?: ($review->learner?->name ?? 'Learner') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $review->created_at?->format('M d, Y') }}{{ $review->sourceModule ? ' / From ' . $review->sourceModule->title : '' }}</p>
                        </div>
                        <x-reviews.heart-rating :rating="$review->rating" size-class="h-4 w-4" text-class="text-sm font-semibold text-gray-600 dark:text-gray-300" />
                    </div>
                    <div class="mt-3 prose prose-sm max-w-none dark:prose-invert">
                        {!! $review->review_html ?: '<p>No written feedback.</p>' !!}
                    </div>
                </article>
            @endforeach
            <div>{{ $instructorReviews->links() }}</div>
        @else
            <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                No instructor reviews yet.
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reviewForm = document.querySelector('[data-review-form="true"]');
        if (reviewForm) {
            reviewForm.addEventListener('submit', function (event) {
                if (typeof tinymce !== 'undefined') {
                    tinymce.triggerSave();
                }
            });
        }

        if (typeof tinymce === 'undefined') {
            return;
        }

        tinymce.remove('textarea.js-learner-rich-editor');
        tinymce.init({
            selector: 'textarea.js-learner-rich-editor',
            license_key: 'gpl',
            height: 180,
            menubar: false,
            branding: false,
            plugins: 'lists link',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
        });
    });
</script>
@endpush
