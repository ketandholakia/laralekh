@props(['name'])

<input id="{{ $name }}" name="{{ $name }}"
       {{ $attributes->merge(['class' => 'w-full rounded-md border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm
            focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400']) }}>
