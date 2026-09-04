@props(['type'])

@php
    $value = $type?->value ?? (string) $type;
    $label = $type?->label() ?? str($value)->headline()->toString();
    $classes = [
        'announcement' => 'border-purple-200 bg-purple-50 text-purple-700',
        'event' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'resource' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        'moderated_question' => 'border-amber-200 bg-amber-50 text-amber-800',
        'discussion_prompt' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
    ][$value] ?? 'border-gray-200 bg-gray-50 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold leading-none '.$classes]) }}>
    {{ $label }}
</span>
