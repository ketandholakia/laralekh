<div>
    <x-page-header title="Trial balance" eyebrow="Reports" :sub="'Per-ledger balances from posted vouchers only'">
        <div class="flex items-end gap-2">
            <x-field label="From" name="tb-from">
                <x-input name="tb-from" type="date" wire:model.live="from"/>
            </x-field>
            <x-field label="Until" name="tb-until">
                <x-input name="tb-until" type="date" wire:model.live="until"/>
            </x-field>
        </div>
    </x-page-header>

    @if (count($report['rows']) === 0)
        <x-card>
            <x-empty-state icon="invoice" title="No posted movements yet"
                           message="The trial balance lists ledgers once documents are posted."/>
        </x-card>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-right text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left font-semibold">Ledger</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Opening debit</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Opening credit</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Period debit</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Period credit</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Closing debit</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Closing credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs text-slate-500">{{ $row['code'] }}</span>
                                    <span class="ml-1 text-slate-800">{{ $row['name'] }}</span>
                                    <span class="block text-xs text-slate-400">{{ $row['group'] }}</span>
                                </td>
                                <td class="px-5 py-3 text-right"><x-money :value="$row['opening_debit']"/></td>
                                <td class="px-5 py-3 text-right"><x-money :value="$row['opening_credit']"/></td>
                                <td class="px-5 py-3 text-right"><x-money :value="$row['debit']"/></td>
                                <td class="px-5 py-3 text-right"><x-money :value="$row['credit']"/></td>
                                <td class="px-5 py-3 text-right"><x-money :value="$row['closing_debit']"/></td>
                                <td class="px-5 py-3 text-right"><x-money :value="$row['closing_credit']"/></td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                            <td class="px-5 py-3 text-slate-900">Totals</td>
                            <td class="px-5 py-3 text-right"><x-money :value="$report['totals']['opening_debit']"/></td>
                            <td class="px-5 py-3 text-right"><x-money :value="$report['totals']['opening_credit']"/></td>
                            <td class="px-5 py-3 text-right"><x-money :value="$report['totals']['debit']"/></td>
                            <td class="px-5 py-3 text-right"><x-money :value="$report['totals']['credit']"/></td>
                            <td class="px-5 py-3 text-right"><x-money :value="$report['totals']['closing_debit']"/></td>
                            <td class="px-5 py-3 text-right"><x-money :value="$report['totals']['closing_credit']"/></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500">
            Balances reconcile: total debits equal total credits in the opening, movement and closing columns.
        </p>
    @endif
</div>
