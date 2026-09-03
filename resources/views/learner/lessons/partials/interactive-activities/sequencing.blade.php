@props(['activity', 'preview' => false])

<div class="mt-6" x-data="sequencingActivity(@js([
    'activityId' => $activity['id'] ?? null,
    'revision' => $activity['revision'] ?? 1,
    'checkUrl' => $activity['check_sequence_url'] ?? null,
    'stateUrl' => $activity['state_url'] ?? null,
    'preview' => $preview,
    'answerKey' => $preview ? ($activity['preview_answer_key'] ?? []) : null,
    'csrf' => csrf_token(),
    'initialStatus' => $activity['status'] ?? 'in_progress',
    'items' => $activity['payload']['items'] ?? [],
    'initialOrder' => collect($activity['payload']['items'] ?? [])->pluck('id')->values()->all(),
]))"
     @interactive-activity-state.window="status = $event.detail.status"
     @interactive-activity-practice.window="resetPractice()"
     @pointerup.window="dropItem(dragOverIndex)"
     @pointercancel.window="cancelItemDrag()">
    <ol class="space-y-2" aria-label="Sequence items">
        <template x-for="(itemId, index) in order" :key="itemId">
            <li class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3" role="listitem"
                @pointerenter="if (dragIndex !== null) dragOverIndex = index"
                @pointermove="if (dragIndex !== null) dragOverIndex = index">
                <span class="min-w-12 text-xs font-semibold text-gray-500" aria-live="polite" x-text="positionLabel(index)"></span>
                <span class="flex-1 text-sm text-gray-800" x-text="itemFor(itemId).value"></span>
                <span class="cursor-grab text-lg" aria-hidden="true" @pointerdown.prevent="startItemDrag(index, $event)">⠿</span>
                <button type="button" @click="move(index, -1)" :disabled="isLocked() || index === 0" :aria-label="`Move ${itemFor(itemId).value} up`" class="rounded-lg border border-gray-300 px-2 py-1 text-xs disabled:opacity-40">↑</button>
                <button type="button" @click="move(index, 1)" :disabled="isLocked() || index === order.length - 1" :aria-label="`Move ${itemFor(itemId).value} down`" class="rounded-lg border border-gray-300 px-2 py-1 text-xs disabled:opacity-40">↓</button>
            </li>
        </template>
    </ol>
    <button type="button" @click="checkAnswer()" :disabled="isLocked()" class="mt-4 rounded-xl bg-purple-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Check answer</button>
    <p x-show="feedback" x-text="feedback" role="status" aria-live="polite" class="mt-3 text-sm text-amber-700"></p>
    <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-red-600"></p>
</div>
