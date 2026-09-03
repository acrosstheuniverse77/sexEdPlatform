@props(['activity', 'continueUrl' => null])

<div class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
    <p>{{ $activity['message'] ?? 'This activity is temporarily unavailable.' }}</p>
    @if($continueUrl)
        <a href="{{ $continueUrl }}" class="mt-4 inline-flex rounded-xl bg-purple-700 px-4 py-2 font-semibold text-white">Continue</a>
    @endif
</div>
