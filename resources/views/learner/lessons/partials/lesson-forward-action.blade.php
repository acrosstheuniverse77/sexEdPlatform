@php
    $__isLastTopic = $currentTopicIndex >= $lessonTopics->count() - 1;
    $__topicComplete = in_array($currentTopic->id, $completedTopicIds);
@endphp

@if($currentTopic->isOptionalInteraction())
    {{-- Optional interactions own their forward action. --}}
@elseif($__topicComplete)
    @if(!$__isLastTopic)
        <a href="{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $currentTopicIndex + 1]) }}" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-[0.98]" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Continue</a>
    @elseif($lessonQuiz)
        <a href="{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'quiz' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-[0.98]" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Take Lesson Quiz</a>
    @elseif($nextLesson)
        <a href="{{ route('learner.lessons.show', $nextLesson) }}" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-[0.98]" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Next Lesson</a>
    @else
        <a href="{{ route('learner.modules.show', $module) }}" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-[0.98]" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">Back to Module</a>
    @endif
@else
    <form action="{{ route('learner.topics.complete', $currentTopic) }}" method="POST" class="inline">
        @csrf
        @if(!$__isLastTopic)
            <input type="hidden" name="next_topic_index" value="{{ $currentTopicIndex + 1 }}">
        @endif
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-[0.98]" style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
            {{ !$__isLastTopic ? 'Mark Complete & Continue' : ($lessonQuiz ? 'Complete & Take Quiz' : 'Mark Complete') }}
        </button>
    </form>
@endif
