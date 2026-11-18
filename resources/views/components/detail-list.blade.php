@props(['rows' => []])

@php
    // Semantic <dl> for read-only document details (label → value).
    // Rows render in a responsive 1/2-column grid; callers pass an array
    // of ['label' => string, 'value' => string|Stringable|null, 'mono' => bool?]
    // or use the $rows slot for custom markup.
@endphp

@if ($rows)
    <dl {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2']) }}>
        @foreach ($rows as $row)
            <div class="min-w-0 border-b border-slate-100 pb-2 last:border-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $row['label'] }}</dt>
                <dd class="mt-0.5 text-sm text-slate-900 {{ ($row['mono'] ?? false) ? 'tabular-nums' : '' }}">{{ $row['value'] ?? '—' }}</dd>
            </div>
        @endforeach
    </dl>
@else
    <dl {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2']) }}>{{ $slot }}</dl>
@endif
