@props(['caption' => null])

{{-- Semantic, responsive table wrapper with design-system styling.
     Usage: <x-table caption="…"><thead>…</thead><tbody>…</tbody></x-table>
     The .x-table class hook styles thead/tbody in the components layer
     (see resources/css/app.css); callers keep control of their cells and
     may override any of it with their own utility classes. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-card']) }}>
    <table class="x-table min-w-full divide-y divide-slate-200 text-sm">
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        {{ $slot }}
    </table>
</div>
