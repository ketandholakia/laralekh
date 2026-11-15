@props(['label', 'icon' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white p-5 shadow-card']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="rounded-md bg-brand-50 p-1.5 text-brand-700">
                <x-icon name="{{ $icon }}" class="h-4 w-4"/>
            </span>
        @endif
    </div>
    <p class="mt-2 truncate text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ $value ?? $slot }}</p>
    @isset($sub)
        <p class="mt-1 text-xs text-slate-500">{{ $sub }}</p>
    @endisset
</div>
