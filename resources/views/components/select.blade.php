@props(['name', 'error' => null])

@php
    // Design-system select. Optional :error prop wires aria-invalid and
    // aria-describedby to the error message rendered by x-field (or
    // stand-alone with the same {name}-error id convention).
@endphp

<select id="{{ $name }}" name="{{ $name }}"
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        @if ($error) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border-slate-300 text-sm text-slate-900 shadow-sm
            focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none disabled:bg-slate-50']) }}>
    {{ $slot }}
</select>
