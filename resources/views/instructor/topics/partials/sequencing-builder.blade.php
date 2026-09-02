<fieldset class="space-y-4">
    <legend class="text-sm font-semibold text-gray-900">Sequence items</legend>
    <p class="text-xs text-gray-500">Add 3-12 unique items. The displayed order becomes the correct sequence.</p>
    <div role="list" aria-label="Sequence items">
    <template x-for="(item, index) in items" :key="item.id || `item-${index}`">
        <div class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-[auto_1fr_auto]" role="listitem"
             @pointerenter="if (dragIndex !== null) dragOverIndex = index">
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500">
                <span class="cursor-grab text-lg" aria-hidden="true"
                      @pointerdown.prevent="startItemDrag(index)">⠿</span>
                <span aria-live="polite" x-text="`${index + 1} of ${items.length}`"></span>
                <span class="sr-only" aria-live="polite" x-text="dragIndex === index ? `Dragging item ${index + 1} of ${items.length}` : ''"></span>
            </div>
            <label class="text-xs font-semibold text-gray-700">Item text
                <input type="hidden" :name="`configuration[items][${index}][id]`" :value="item.id || ''" :disabled="activityType !== 'sequencing'">
                <input type="hidden" :name="`configuration[items][${index}][kind]`" value="text" :disabled="activityType !== 'sequencing'">
                <input type="text" :name="`configuration[items][${index}][value]`" x-model="item.value" maxlength="500" required :disabled="activityType !== 'sequencing'" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <div class="flex items-end gap-1">
                <button type="button" @click="moveItem(index, -1)" :disabled="index === 0" aria-label="Move item up" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40">↑</button>
                <button type="button" @click="moveItem(index, 1)" :disabled="index === items.length - 1" aria-label="Move item down" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40">↓</button>
                <button type="button" @click="removeItem(index)" :disabled="items.length <= 3" aria-label="Remove item" class="rounded-lg border border-red-200 px-2 py-1.5 text-xs text-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove</button>
            </div>
        </div>
    </template>
    </div>
    <button type="button" @click="addItem()" :disabled="items.length >= 12" class="rounded-lg border border-purple-200 px-3 py-2 text-xs font-semibold text-purple-700 disabled:cursor-not-allowed disabled:opacity-40">Add item</button>
</fieldset>
