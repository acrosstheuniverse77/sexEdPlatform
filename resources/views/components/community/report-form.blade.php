@props([
    'connector',
    'post',
    'comment' => null,
    'buttonLabel' => 'Report',
    'buttonClass' => 'inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2',
    'buttonTitle' => 'Report safety concern',
    'align' => 'right',
])

@php
    $reportReasons = config('community_feed.report_reasons', []);
    $editorId = 'community-report-other-'.($comment ? 'comment-'.$comment->id : 'post-'.$post->id).'-'.uniqid();
@endphp

<div
    class="relative"
    x-data="{ open: false, reason: '', requiresOther() { return this.reason === 'other'; } }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="{{ $buttonClass }}"
        title="{{ $buttonTitle }}"
        aria-label="{{ $buttonTitle }}"
        @click="open = !open"
    >
        @if(trim($buttonLabel) === '')
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
        @else
            {{ $buttonLabel }}
        @endif
    </button>

    <form
        method="POST"
        action="{{ route('connector.community.reports.store', [$connector, $post]) }}"
        class="absolute z-40 mt-2 w-80 rounded-2xl border border-gray-200 bg-white p-4 text-left shadow-xl {{ $align === 'right' ? 'right-0' : 'left-0' }}"
        x-show="open"
        x-cloak
        @click.away="open = false"
        data-confirm-submit
        data-confirm-title="{{ $comment ? 'Report this comment?' : 'Report this post?' }}"
        data-confirm-text="Send this report to connector moderation with your selected reason."
        data-confirm-icon="warning"
        data-confirm-button="Send Report"
        data-community-report-form
    >
        @csrf
        @if($comment)
            <input type="hidden" name="community_comment_id" value="{{ $comment->id }}">
        @endif

        <label class="text-xs font-bold uppercase tracking-wide text-gray-500" for="{{ $editorId }}_reason">Reason</label>
        <select
            id="{{ $editorId }}_reason"
            name="reason_code"
            x-model="reason"
            class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500"
            required
        >
            <option value="">Select a report reason</option>
            @foreach($reportReasons as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </select>

        <div class="mt-3" x-show="requiresOther()" x-cloak>
            <label class="text-xs font-bold uppercase tracking-wide text-gray-500" for="{{ $editorId }}">Other reason</label>
            <textarea
                id="{{ $editorId }}"
                name="details"
                rows="4"
                class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500"
                data-community-report-other-editor
                :required="requiresOther()"
                placeholder="Explain why this needs moderation review."
            ></textarea>
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50" @click="open = false">Cancel</button>
            <button class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white hover:bg-rose-700">Send Report</button>
        </div>
    </form>
</div>

@once
    @push('scripts')
        <script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const initCommunityReportEditors = () => {
                    if (typeof tinymce === 'undefined') {
                        return;
                    }

                    document.querySelectorAll('[data-community-report-other-editor]').forEach((textarea) => {
                        if (tinymce.get(textarea.id)) {
                            return;
                        }

                        tinymce.init({
                            selector: `#${textarea.id}`,
                            menubar: false,
                            branding: false,
                            height: 160,
                            plugins: 'lists link',
                            toolbar: 'bold italic bullist numlist link removeformat',
                        });
                    });
                };

                initCommunityReportEditors();

                document.querySelectorAll('[data-community-report-form]').forEach((form) => {
                    form.addEventListener('submit', () => {
                        if (typeof tinymce === 'undefined') {
                            return;
                        }

                        form.querySelectorAll('[data-community-report-other-editor]').forEach((textarea) => {
                            tinymce.get(textarea.id)?.save();
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce
