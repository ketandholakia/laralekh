@php
    // Status semantics shared by every register screen.
    $tones = [
        'posted' => 'bg-green-100 text-green-800 ring-green-600/20',
        'filed' => 'bg-green-100 text-green-800 ring-green-600/20',
        'final' => 'bg-brand-100 text-brand-800 ring-brand-700/20',
        'draft' => 'bg-amber-100 text-amber-900 ring-amber-600/20',
        'pending' => 'bg-amber-100 text-amber-900 ring-amber-600/20',
        'reversed' => 'bg-red-100 text-red-800 ring-red-600/20',
        'cancelled' => 'bg-red-100 text-red-800 ring-red-600/20',
        'inactive' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $tone = $tones[strtolower((string) ($status ?? ''))] ?? $tones[strtolower((string) ($color ?? ''))] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$tone]) }}>
    {{ $slot ?? ucwords((string) ($status ?? '')) }}
</span>
