<div
    x-show="isOpen"
    x-cloak
    role="dialog"
    aria-modal="true"
    aria-labelledby="interactive-preview-title"
    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
    @keydown.escape.window="if (isOpen) closePreview()"
>
    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-gray-100 shadow-2xl" @click.stop>
        <div class="flex items-center justify-between border-b border-gray-200 bg-white px-5 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-purple-600">Interactive Preview</p>
                <h2 id="interactive-preview-title" class="mt-1 text-lg font-semibold text-gray-900">Learner view</h2>
            </div>
            <button type="button" data-preview-close @click="closePreview()" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-5 py-3">
            <span class="mr-2 text-xs font-semibold text-gray-600">Viewport</span>
            <button type="button" @click="selectViewport('mobile')" :class="previewViewport === 'mobile' ? 'bg-purple-700 text-white' : 'border border-gray-300 text-gray-700'" class="rounded-lg px-3 py-1.5 text-xs font-semibold">Mobile · 375</button>
            <button type="button" @click="selectViewport('tablet')" :class="previewViewport === 'tablet' ? 'bg-purple-700 text-white' : 'border border-gray-300 text-gray-700'" class="rounded-lg px-3 py-1.5 text-xs font-semibold">Tablet · 768</button>
            <button type="button" @click="selectViewport('desktop')" :class="previewViewport === 'desktop' ? 'bg-purple-700 text-white' : 'border border-gray-300 text-gray-700'" class="rounded-lg px-3 py-1.5 text-xs font-semibold">Desktop · 1440</button>
            <span class="ml-auto text-xs text-gray-500">Preview only — no learner progress is saved.</span>
        </div>

        <div class="overflow-auto p-5">
            <div x-show="errorMessages().length" class="mx-auto mb-4 max-w-3xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p x-text="previewError"></p>
                <ul class="mt-1 list-inside list-disc">
                    <template x-for="message in errorMessages()" :key="message">
                        <li x-text="message"></li>
                    </template>
                </ul>
            </div>
            <div x-show="previewError && !errorMessages().length" class="mx-auto mb-4 max-w-3xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" x-text="previewError"></div>
            <div x-show="isLoading" class="py-16 text-center text-sm text-gray-600">Loading preview…</div>
            <div x-show="!isLoading && previewHtml" x-ref="previewMount" x-html="previewHtml" class="mx-auto bg-white shadow-sm transition-all" :style="`max-width: ${previewWidth()}px`"></div>
        </div>
    </div>
</div>
