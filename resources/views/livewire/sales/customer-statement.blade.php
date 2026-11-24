<div class="space-y-6">
    <x-page-header
        :title="'Customer statement · ' . $party->name"
        eyebrow="Sales · Customer statement"
        sub="As-of statement reconciled to the receivable ledger."
    >
        <x-slot name="actions">
            <x-button :href="route('customers.show', $party)" variant="ghost">Back to outstanding</x-button>
        </x-slot>
    </x-page-header>

    <x-card>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 items-end">
            <x-field label="From" name="from">
                <x-input name="from" type="date" wire:model.live="from" />
            </x-field>

            <x-field label="As of" name="asOf" :error="$errors->first('asOf')">
                <x-input name="asOf" type="date" wire:model.live="asOf" />
            </x-field>

            @if ($statement)
                <div class="sm:justify-self-end">
                    <x-button :href="route('customers.statement-pdf', ['party' => $party, 'from' => $statement['from'], 'as_of' => $statement['as_of']])" variant="secondary" icon="file-down">
                        Download PDF
                    </x-button>
                </div>
            @endif
        </div>
    </x-card>

    @if ($statement)
        <x-card>
            <x-table caption="Customer statement ledger transactions">
                <x-slot name="head">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Credit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                    </tr>
                </x-slot>
                <x-slot name="body">
                    <tr class="border-b border-slate-100 bg-slate-50/70">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">
                            {{ \Illuminate\Support\Carbon::parse($statement['from'])->subDay()->format('d-m-Y') }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">Opening balance</td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-slate-900">
                            <x-money :value="$statement['opening']" />
                        </td>
                    </tr>
                    @foreach ($statement['rows'] as $row)
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-m-Y') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $row['description'] }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                                @if ($row['debit'] !== '0.00')
                                    <x-money :value="$row['debit']" />
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                                @if ($row['credit'] !== '0.00')
                                    <x-money :value="$row['credit']" />
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-sm font-medium text-slate-900">
                                <x-money :value="$row['balance']" />
                            </td>
                        </tr>
                    @endforeach
                    <tr class="border-t-2 border-slate-300 bg-slate-50/50 font-semibold">
                        <td class="px-4 py-3 text-sm text-slate-900">
                            {{ \Illuminate\Support\Carbon::parse($statement['as_of'])->format('d-m-Y') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-900">Closing balance</td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900">
                            <x-money :value="$statement['closing']" />
                        </td>
                    </tr>
                </x-slot>
            </x-table>

            <div class="mt-4 border-t border-slate-100 pt-3">
                <p class="text-xs text-slate-500">
                    Ledger balance up to {{ \Illuminate\Support\Carbon::parse($statement['as_of'])->format('d-m-Y') }} (authoritative): <span class="font-mono font-semibold text-slate-900">₹{{ $statement['ledger_as_of'] }}</span>
                    @if ((string) $statement['closing'] === (string) $statement['ledger_as_of'])
                        <span class="font-medium text-emerald-700">· reconciled</span>
                    @else
                        <span class="font-medium text-red-600">· MISMATCH</span>
                    @endif
                </p>
            </div>
        </x-card>
    @else
        <x-alert type="info">Choose a from date to build the statement.</x-alert>
    @endif
</div>
