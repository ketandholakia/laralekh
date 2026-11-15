@props(['type' => 'info'])

@php
    $styles = [
        'info' => 'border-brand-200 bg-brand-50 text-brand-900',
        'success' => 'border-green-200 bg-green-50 text-green-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-red-200 bg-red-50 text-red-800',
    ];
    $icons = ['info' => 'info', 'success' => 'check-circle', 'warning' => 'alert', 'danger' => 'alert'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm '.$styles[$type]]) }} role="{{ $type === 'danger' ? 'alert' : 'status' }}">
    <x-icon name="{{ $icons[$type] }}" class="mt-0.5 h-4 w-4 shrink-0"/>
    <div class="min-w-0">{{ $slot }}</div>
</div>
