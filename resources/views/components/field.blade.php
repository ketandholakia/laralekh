@props(['label', 'name', 'required' => false, 'hint' => null, 'error' => null])

@php
    // Label wrapper with required marker, hint and an accessible error
    // message. When an error is present the error is referenced by
    // #{$name}-error and hints are suppressed; callers associate the slot
    // control with the error via aria-describedby="<name>-error" and
    // aria-invalid — or simply render x-input inside and rely on this
    // naming contract. Error keys are unchanged from earlier versions.
    $id = $name.'-error';
    $hintId = $name.'-hint';
@endphp

<div {{ $attributes->merge(['class' => 'block']) }}>
    <label for="{{ $name }}" class="mb-1.5 block text-xs font-medium text-slate-600">
        {{ $label }}@if ($required)<span class="text-red-500" aria-hidden="true"> *</span>@endif
    </label>
    {{ $slot }}
    @if (isset($hint) && ! $error)
        <p id="{{ $hintId }}" class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}" class="mt-1 text-xs text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
