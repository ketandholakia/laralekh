@props(['eyebrow' => null, 'title'])

<header {{ $attributes->merge(['class' => 'mb-6 border-b border-slate-200 pb-5']) }}>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            @if (isset($back))
                <a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">
                    <x-icon name="arrow-left" class="h-3.5 w-3.5"/> Back
                </a>
            @endif
            @if ($eyebrow)
                <p class="text-xs font-semibold uppercase tracking-[.18em] text-brand-700">{{ $eyebrow }}</p>
            @endif
            <h1 class="mt-1 truncate text-2xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
            @isset($sub)
                <p class="mt-1 text-sm text-slate-600">{{ $sub }}</p>
            @endisset
        </div>
        @if (isset($actions) || isset($slot))
            {{-- Callers may pass action buttons either as the default slot or
                 as an explicit x-slot:actions --}}
            <div class="flex flex-wrap items-center gap-2">{{ $actions ?? $slot }}</div>
        @endif
    </div>
</header>
