@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus-visible:outline-2 focus-visible:outline-brand-600 disabled:opacity-50 disabled:pointer-events-none';
    $sizes = ['sm' => 'px-2.5 py-1.5 text-xs', 'md' => 'px-4 py-2 text-sm', 'lg' => 'px-5 py-2.5 text-sm'];
    $variants = [
        'primary' => 'bg-brand-700 text-white hover:bg-brand-800',
        'saffron' => 'bg-saffron text-navy hover:bg-saffron-600',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:border-brand-600 hover:text-brand-700',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
    ];
    $classes = $base.' '.$sizes[$size].' '.$variants[$variant];
@endphp

@if (isset($href))
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@elseif ($type === 'link')
    <a href="{{ $attributes->get('href') }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
