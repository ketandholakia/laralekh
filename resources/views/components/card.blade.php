@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white shadow-card'.($padded ? ' p-5' : '')]) }}>
    {{ $slot }}
</div>
