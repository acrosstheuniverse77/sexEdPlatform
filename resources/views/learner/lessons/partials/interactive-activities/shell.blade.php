@props(['activity', 'continueUrl' => null])

<section class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm" x-data="interactiveActivity(@js([
    'activityId' => $activity['id'] ?? null,
    'revision' => $activity['revision'] ?? 1,
    'initialStatus' => $activity['status'] ?? 'in_progress',
    'initialExplanation' => $activity['explanation'] ?? null,
    'continueUrl' => $continueUrl,
    'csrf' => csrf_token(),
]))">
    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-purple-600">INTERACTIVE ACTIVITY · Optional</p>
    <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ $activity['title'] ?? 'Interactive Activity' }}</h3>
    @if(!empty($activity['instructions']))
        <p class="mt-2 text-sm text-gray-600">{{ $activity['instructions'] }}</p>
    @endif

    @if(($activity['available'] ?? false) && ($activity['type'] ?? null) === 'matching')
        @include('learner.lessons.partials.interactive-activities.matching', ['activity' => $activity])
    @else
        <p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">This activity is temporarily unavailable.</p>
    @endif

    <div class="mt-5 flex flex-wrap gap-3">
        <button type="button" x-show="showSkip()" @click="skip()" :disabled="submitting" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-50">Skip</button>
        <button type="button" x-show="showResume()" @click="resume()" :disabled="submitting" class="rounded-xl border border-purple-300 px-4 py-2 text-sm font-semibold text-purple-700 disabled:opacity-50">Resume</button>
        <button type="button" x-show="showContinue()" @click="continueLearning()" class="rounded-xl bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Continue</button>
        <button type="button" x-show="status === 'completed'" @click="practice()" :disabled="submitting" class="rounded-xl border border-purple-300 px-4 py-2 text-sm font-semibold text-purple-700 disabled:opacity-50">Practice Again</button>
    </div>
    <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-red-600"></p>
</section>
