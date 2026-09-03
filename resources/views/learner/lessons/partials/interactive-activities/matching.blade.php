@props(['activity', 'preview' => false])

<div class="relative mt-6" x-data="matchingActivity(@js([
    'activityId' => $activity['id'] ?? null,
    'revision' => $activity['revision'] ?? 1,
    'matchUrl' => $activity['match_url'] ?? null,
    'preview' => $preview,
    'answerKey' => $preview ? ($activity['preview_answer_key'] ?? []) : null,
    'csrf' => csrf_token(),
    'initialStatus' => $activity['status'] ?? 'in_progress',
    'initialMatchedPairs' => $activity['matched_pairs'] ?? [],
    'leftItems' => $activity['payload']['left_items'] ?? [],
    'rightItems' => $activity['payload']['right_items'] ?? [],
]))"
    x-init="setupConnectors($el)"
    @interactive-activity-state.window="status = $event.detail.status"
    @interactive-activity-practice.window="resetPractice()">
    <svg aria-hidden="true" class="pointer-events-none absolute inset-0 z-0 hidden h-full w-full overflow-visible lg:block">
        <template x-for="(line, index) in connectorLines" :key="`connector-${index}`">
            <line :x1="line.x1" :y1="line.y1" :x2="line.x2" :y2="line.y2" stroke="currentColor" stroke-width="2" class="text-purple-300"></line>
        </template>
    </svg>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <h4 class="mb-2 text-sm font-semibold text-gray-700">Match each item</h4>
            <div class="space-y-2">
                @foreach(($activity['payload']['left_items'] ?? []) as $item)
                    <button type="button" data-match-left="{{ $item['id'] }}" @click="selectLeft(@js($item['id']))" :aria-pressed="ariaPressed('left', @js($item['id']))" :disabled="isLeftMatched(@js($item['id'])) || submitting" class="relative z-10 block w-full rounded-xl border border-gray-200 px-3 py-2 text-left text-sm hover:border-purple-400 disabled:cursor-not-allowed disabled:opacity-50">{{ $item['value'] }}</button>
                @endforeach
            </div>
        </div>
        <div>
            <h4 class="mb-2 text-sm font-semibold text-gray-700">Related item</h4>
            <div class="space-y-2">
                @foreach(($activity['payload']['right_items'] ?? []) as $item)
                    <button type="button" data-match-right="{{ $item['id'] }}" @click="selectRight(@js($item['id']))" :aria-pressed="ariaPressed('right', @js($item['id']))" :disabled="isRightMatched(@js($item['id'])) || submitting" class="relative z-10 block w-full rounded-xl border border-gray-200 px-3 py-2 text-left text-sm hover:border-purple-400 disabled:cursor-not-allowed disabled:opacity-50">{{ $item['value'] }}</button>
                @endforeach
            </div>
        </div>
    </div>
    <div class="mt-4 space-y-2 lg:hidden" x-show="matchedPairs.length" aria-label="Completed matches">
        <template x-for="pair in matchedPairs" :key="`${pair.left_id}-${pair.right_id}`">
            <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900">
                <span x-text="labelFor(leftItems, pair.left_id)"></span>
                <span aria-hidden="true"> ↔ </span>
                <span x-text="labelFor(rightItems, pair.right_id)"></span>
            </div>
        </template>
    </div>
    <button type="button" @click="submitMatch()" :disabled="submitting || leftId === null || rightId === null || status === 'completed'" class="mt-4 rounded-xl bg-purple-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Check match</button>
    <p x-show="feedback" x-text="feedback" role="status" aria-live="polite" class="mt-3 text-sm text-amber-700"></p>
</div>
