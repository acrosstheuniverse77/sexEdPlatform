@php($fieldActivity = $activity ?? null)
@php($eligibleActivityTopics = $lesson->topics->filter(fn ($topic) => ! $topic->isOptionalInteraction()))
@php($activityInsertAfterBlock = 0)
@if($fieldActivity?->placement === 'inside_topic')
    @foreach(($fieldActivity->lessonTopic?->content_blocks ?? []) as $blockIndex => $block)
        @if(is_array($block) && ($block['type'] ?? null) === 'interactive_activity' && (int) ($block['activity_id'] ?? 0) === (int) $fieldActivity->id)
            @php($activityInsertAfterBlock = max(0, $blockIndex - 1))
        @endif
    @endforeach
@endif
@php($activityBlockOptions = $eligibleActivityTopics->flatMap(function ($topic) use ($fieldActivity) {
    return collect($topic->content_blocks ?? [])
        ->filter(function ($block) use ($topic, $fieldActivity) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, ['checkpoint', 'interactive_activity'], true)) {
                return false;
            }

            return ! ($fieldActivity
                && $topic->is($fieldActivity->lessonTopic)
                && ($block['type'] ?? null) === 'interactive_activity'
                && (int) ($block['activity_id'] ?? 0) === (int) $fieldActivity->id);
        })
        ->keys()
        ->map(fn ($blockIndex) => [
            'topic_id' => (string) $topic->id,
            'block_index' => (int) $blockIndex,
            'label' => $topic->title.' - block '.((int) $blockIndex + 1),
        ]);
})->values())
@php($activityConfigurationHasErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'configuration') || str_starts_with($key, 'pairs') || str_starts_with($key, 'items')))
@php($activityConfigurationError = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'configuration') || str_starts_with($key, 'pairs') || str_starts_with($key, 'items'))->map(fn ($key) => $errors->first($key))->filter()->first() ?: 'Review the activity fields above and correct any highlighted values.')
<div id="interactiveActivityFields"
     x-data="interactiveActivityAuthoring({
         activityType: @js(old('activity_type', $fieldActivity?->activity_type?->value ?? 'matching')),
         placement: @js(old('placement', $fieldActivity?->placement ?? 'between_topics')),
         parentTopicId: @js(old('parent_topic_id', $fieldActivity?->placement === 'inside_topic' ? $fieldActivity->lesson_topic_id : '')),
         insertAfterBlock: @js((int) old('insert_after_block', $activityInsertAfterBlock)),
         blockOptions: @js($activityBlockOptions),
         validationErrors: @js($errors->getMessages()),
         pairs: @js(old('configuration.pairs', $fieldActivity?->activity_type?->value === 'matching' ? ($fieldActivity->configuration['pairs'] ?? []) : [])),
         items: @js(old('configuration.items', $fieldActivity?->activity_type?->value === 'sequencing' ? ($fieldActivity->configuration['items'] ?? []) : [])),
     })"
     @pointerup.window="dropItem(dragOverIndex)"
     @pointercancel.window="cancelItemDrag()"
     x-cloak>
    <input type="hidden" name="activity_type" x-model="activityType">

    <fieldset class="mb-6" aria-describedby="activity-type-error">
        <legend class="mb-3 text-sm font-semibold text-gray-900">Activity type</legend>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-gray-200 p-4 transition-colors"
                   :class="activityType === 'matching' && 'border-purple-400 bg-purple-50'">
                <input type="radio" name="activity_type_choice" value="matching" x-model="activityType" class="mt-1 text-purple-600 focus:ring-purple-500">
                <span><strong class="block text-sm text-gray-900">Matching</strong><span class="text-xs text-gray-500">Connect each item with its related pair.</span></span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-gray-200 p-4 transition-colors"
                   :class="activityType === 'sequencing' && 'border-purple-400 bg-purple-50'">
                <input type="radio" name="activity_type_choice" value="sequencing" x-model="activityType" class="mt-1 text-purple-600 focus:ring-purple-500">
                <span><strong class="block text-sm text-gray-900">Sequencing</strong><span class="text-xs text-gray-500">Arrange the items in their correct order.</span></span>
            </label>
        </div>
    </fieldset>
    @error('activity_type') <p id="activity-type-error" class="-mt-4 mb-6 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror

    <fieldset class="mb-6" aria-describedby="activity-placement-error">
        <legend class="mb-3 text-sm font-semibold text-gray-900">Activity placement</legend>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="cursor-pointer rounded-xl border-2 border-gray-200 p-4" :class="placement === 'between_topics' && 'border-purple-400 bg-purple-50'">
                <input type="radio" name="placement" value="between_topics" x-model="placement" class="text-purple-600 focus:ring-purple-500">
                <span class="ml-2 text-sm font-semibold text-gray-900">Between Topics</span>
                <span class="mt-1 block text-xs text-gray-500">Add a standalone optional activity to the Lesson flow.</span>
            </label>
            <label class="cursor-pointer rounded-xl border-2 border-gray-200 p-4" :class="placement === 'inside_topic' && 'border-purple-400 bg-purple-50'">
                <input type="radio" name="placement" value="inside_topic" x-model="placement" class="text-purple-600 focus:ring-purple-500">
                <span class="ml-2 text-sm font-semibold text-gray-900">Inside Topic</span>
                <span class="mt-1 block text-xs text-gray-500">Insert the activity into an instructional Topic body.</span>
            </label>
        </div>
    </fieldset>
    @error('placement') <p id="activity-placement-error" class="-mt-4 mb-6 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror

    <div x-show="placement === 'inside_topic'" class="mb-6 space-y-4">
        <label for="activity_parent_topic_id" class="block text-sm font-semibold text-gray-900">Containing Topic</label>
        <select id="activity_parent_topic_id" name="parent_topic_id" x-model="parentTopicId" @change="insertAfterBlock = 0" :disabled="placement !== 'inside_topic'" aria-describedby="activity-parent-topic-error" aria-invalid="{{ $errors->has('parent_topic_id') ? 'true' : 'false' }}" class="w-full rounded-xl border-gray-200 focus:border-purple-400 focus:ring-purple-300">
            <option value="">Select an instructional Topic</option>
            @foreach($eligibleActivityTopics as $lessonTopic)
                <option value="{{ $lessonTopic->id }}">{{ $lessonTopic->title }}</option>
            @endforeach
        </select>
        <label for="activity_insert_after_block" class="block text-sm font-semibold text-gray-900">Insert after block</label>
        <select id="activity_insert_after_block" name="insert_after_block" x-model.number="insertAfterBlock" :disabled="placement !== 'inside_topic'" aria-describedby="activity-insert-after-block-error" aria-invalid="{{ $errors->has('insert_after_block') ? 'true' : 'false' }}" class="w-full rounded-xl border-gray-200 focus:border-purple-400 focus:ring-purple-300">
            <option value="0">Topic body</option>
            <template x-for="option in blockOptions.filter(option => String(option.topic_id) === String(parentTopicId))" :key="`${option.topic_id}-${option.block_index}`">
                <option :value="option.block_index" x-text="option.label"></option>
            </template>
        </select>
        @error('parent_topic_id') <p id="activity-parent-topic-error" class="text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
        @error('insert_after_block') <p id="activity-insert-after-block-error" class="text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
    </div>

    @if($activityConfigurationHasErrors)
        <p id="activity-configuration-error" class="text-xs text-red-600" role="alert">{{ $activityConfigurationError }}</p>
    @endif

    <div x-show="activityType === 'matching'">
        @include('instructor.topics.partials.matching-builder')
    </div>
    <div x-show="activityType === 'sequencing'">
        @include('instructor.topics.partials.sequencing-builder')
    </div>
</div>
