@props(['label', 'name', 'required' => false, 'hint' => null, 'error' => null])

<div {{ $attributes->merge(['class' => 'block']) }}>
    <label for="{{ $name }}" class="mb-1.5 block text-xs font-medium text-slate-600">
        {{ $label }}@if ($required)<span class="text-red-500" aria-hidden="true"> *</span>@endif
    </label>
    {{ $slot }}
    @if (isset($hint) && ! $error)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if ($error)
        <p class="mt-1 text-xs text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
