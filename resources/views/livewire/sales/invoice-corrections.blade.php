<section class="mb-6 rounded-lg border border-brand-200 bg-white p-5 shadow-card space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <h2 class="text-base font-semibold text-slate-900">Corrections and reversals</h2>
        @if (!$reversal && $source->status === 'posted')
            <div class="flex flex-wrap items-center gap-2">
                @can('corrections.create')
                    <x-button :href="route('corrections.create', ['invoice' => $source->id, 'kind' => 'credit'])" variant="secondary" size="sm">Create credit note</x-button>
                    <x-button :href="route('corrections.create', ['invoice' => $source->id, 'kind' => 'debit'])" variant="secondary" size="sm">Create debit note</x-button>
                @endcan
                @can('reversals.post')
                    <x-button :href="route('reversals.create', $source->voucher_id)" variant="danger" size="sm">Review full reversal</x-button>
                @endcan
            </div>
        @endif
    </div>

    @if ($reversal)
        <x-alert type="warning">
            The financial voucher has been reversed. The original invoice remains in the audit trail.
            <a class="font-medium underline ml-1" href="{{ route('reversals.show', $reversal->id) }}">View internal reversal</a>
        </x-alert>
    @else
        <details class="text-sm rounded-md border border-slate-200 bg-slate-50/50 p-3">
            <summary class="cursor-pointer font-medium text-slate-700 hover:text-slate-900">Remaining original amounts available for credit</summary>
            <ul class="mt-2.5 space-y-1.5 border-t border-slate-200/60 pt-2 text-xs text-slate-600">
                @foreach ($capacity as $line)
                    <li class="flex items-center justify-between">
                        <span>{{ $line['description'] }}</span>
                        <span class="font-mono">INR {{ $line['amount'] }} · Quantity {{ $line['quantity'] }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    <div class="space-y-2 pt-1">
        @forelse ($notes as $note)
            <div class="flex items-center justify-between rounded-md border border-slate-100 bg-slate-50/50 px-3 py-2 text-sm">
                <div class="flex items-center gap-2">
                    <a class="font-medium text-brand-700 hover:underline" href="{{ route('corrections.show', $note->id) }}">
                        {{ ucfirst($note->label()) }} {{ $note->number ? $note->series.'/'.$note->number : '#'.$note->id.' draft' }}
                    </a>
                    <span class="text-xs text-slate-500">· {{ $note->date->format('d-m-Y') }}</span>
                    <span class="font-mono text-xs font-semibold text-slate-800">· INR {{ $note->total }}</span>
                </div>
                <x-badge :variant="$note->status === 'posted' ? 'success' : 'neutral'" size="sm">{{ ucfirst($note->status) }}</x-badge>
            </div>
        @empty
            <p class="text-sm text-slate-500">No correction notes.</p>
        @endforelse
    </div>
</section>
