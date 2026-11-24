<div class="space-y-6">
    <x-page-header
        :title="'Internal reversal ' . $reversal->voucher->series . '/' . $reversal->voucher->number"
        eyebrow="Accounting · Reversal"
        :sub="'Reversal date: ' . $reversal->date->format('d-m-Y')"
    >
        <x-badge variant="danger" size="md">Reversed</x-badge>

        @if (Route::has('reversals.pdf'))
            <x-slot name="actions">
                <x-button :href="route('reversals.pdf', $reversal->id)" variant="secondary" icon="file-down">Download PDF</x-button>
            </x-slot>
        @endif
    </x-page-header>

    <x-alert type="warning">
        Internal accounting document. Not a GST credit note. No GST filing or physical stock movement has been performed.
    </x-alert>

    <x-card class="space-y-4">
        <div class="rounded-md border border-slate-100 bg-slate-50 p-3 text-sm text-slate-700">
            <span class="font-medium text-slate-900">Original voucher:</span> {{ $reversal->original_snapshot['number'] }} · {{ $reversal->original_snapshot['date'] }}
        </div>

        @if ($reversal->reason)
            <div class="text-sm">
                <span class="block text-xs uppercase tracking-wider text-slate-500 font-semibold mb-1">Reason</span>
                <p class="whitespace-pre-line text-slate-800">{{ $reversal->reason }}</p>
            </div>
        @endif

        <div class="border-t border-slate-100 pt-4">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Reversal journal entries snapshot</h3>
            <x-table caption="Reversal journal entries snapshot">
                <x-slot name="head">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ledger</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Debit INR</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Credit INR</th>
                    </tr>
                </x-slot>
                <x-slot name="body">
                    @foreach ($reversal->entries_snapshot as $line)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-sm text-slate-900 font-medium">
                                {{ $line['ledger_name'] }} · <span class="font-mono text-xs text-slate-500">{{ $line['ledger_code'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-sm text-slate-900"><x-money :value="$line['debit']" /></td>
                            <td class="px-3 py-2 text-right font-mono text-sm text-slate-900"><x-money :value="$line['credit']" /></td>
                        </tr>
                    @endforeach
                </x-slot>
            </x-table>
        </div>
    </x-card>
</div>
