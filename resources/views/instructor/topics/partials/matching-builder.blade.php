<fieldset class="space-y-4">
    <legend class="text-sm font-semibold text-gray-900">Matching pairs</legend>
    <p class="text-xs text-gray-500">Add 2–12 unique pairs. The server validates the final configuration.</p>
    <template x-for="(pair, index) in pairs" :key="pair.id || `pair-${index}`">
        <div class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-[1fr_1fr_auto]">
            <label class="text-xs font-semibold text-gray-700">Left item
                <input type="hidden" :name="`configuration[pairs][${index}][id]`" :value="pair.id || ''" :disabled="activityType !== 'matching'">
                <input type="hidden" :name="`configuration[pairs][${index}][left][id]`" :value="pair.left?.id || ''" :disabled="activityType !== 'matching'">
                <input type="hidden" :name="`configuration[pairs][${index}][left][kind]`" value="text" :disabled="activityType !== 'matching'">
                <input type="text" :name="`configuration[pairs][${index}][left][value]`" x-model="pair.left.value" maxlength="500" required :disabled="activityType !== 'matching'" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label class="text-xs font-semibold text-gray-700">Right item
                <input type="hidden" :name="`configuration[pairs][${index}][right][id]`" :value="pair.right?.id || ''" :disabled="activityType !== 'matching'">
                <input type="hidden" :name="`configuration[pairs][${index}][right][kind]`" value="text" :disabled="activityType !== 'matching'">
                <input type="text" :name="`configuration[pairs][${index}][right][value]`" x-model="pair.right.value" maxlength="500" required :disabled="activityType !== 'matching'" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <div class="flex items-end gap-1">
                <button type="button" @click="movePair(index, -1)" :disabled="index === 0" aria-label="Move pair up" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40">↑</button>
                <button type="button" @click="movePair(index, 1)" :disabled="index === pairs.length - 1" aria-label="Move pair down" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40">↓</button>
                <button type="button" @click="removePair(index)" :disabled="pairs.length <= 2" aria-label="Remove pair" class="rounded-lg border border-red-200 px-2 py-1.5 text-xs text-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove</button>
            </div>
        </div>
    </template>
    <button type="button" @click="addPair()" :disabled="pairs.length >= 12" class="rounded-lg border border-purple-200 px-3 py-2 text-xs font-semibold text-purple-700 disabled:cursor-not-allowed disabled:opacity-40">Add pair</button>
</fieldset>
