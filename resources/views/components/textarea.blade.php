@props(['name', 'label' => null, 'required' => false, 'hint' => null, 'error' => null, 'rows' => 3])

@php
    // Design-system textarea: label, hint and error presentation match
    // x-input/x-field. Error attribution is additive — callers pass
    // $error directly, or wrap in x-field with :error, same as the other
    // form controls. aria-describedby always points at whichever message
    // is visible.
    $id = $attributes->get('id') ?? $name;
    $errorId = $name.'-error';
    $hintId = $name.'-hint';
    $message = $error;
@endphp

<div class="block">
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-xs font-medium text-slate-600">
            {{ $label }}@if ($required)<span class="text-red-500" aria-hidden="true"> *</span>@endif
        </label>
    @endif
    <textarea
        id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        aria-invalid="{{ $message ? 'true' : 'false' }}"
        aria-describedby="{{ $message ? $errorId : ($hint ? $hintId : '') }}"
        {{ $attributes->merge(['class' => 'w-full rounded-md border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm
            focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400']) }}>{{ $slot }}</textarea>
    @if ($hint && ! $message)
        <p id="{{ $hintId }}" class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if ($message)
        <p id="{{ $errorId }}" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @endif
</div>
