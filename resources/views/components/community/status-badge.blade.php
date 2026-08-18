@props(['status'])

@php
    $value = $status?->value ?? (string) $status;
    $label = $status?->label() ?? str($value)->headline()->toString();
    $classes = [
        'draft' => 'border-slate-200 bg-slate-50 text-slate-700',
        'pending_review' => 'border-amber-200 bg-amber-50 text-amber-800',
        'published' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'hidden' => 'border-slate-300 bg-slate-100 text-slate-700',
        'locked' => 'border-blue-200 bg-blue-50 text-blue-700',
        'removed' => 'border-rose-200 bg-rose-50 text-rose-700',
        'escalated' => 'border-rose-300 bg-rose-100 text-rose-800',
        'archived' => 'border-gray-200 bg-gray-100 text-gray-600',
    ][$value] ?? 'border-gray-200 bg-gray-50 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold leading-none '.$classes]) }}>
    {{ $label }}
</span>
