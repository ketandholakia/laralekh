@props(['icon' => 'invoice', 'title', 'message' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="rounded-full bg-slate-100 p-3 text-slate-400">
        <x-icon name="{{ $icon }}" class="h-6 w-6"/>
    </span>
    <p class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
