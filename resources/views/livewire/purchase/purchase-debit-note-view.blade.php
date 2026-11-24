<div class="space-y-6">
    <x-page-header
        :eyebrow="'Purchases · ' . ($debitNote->bill->party?->name ?? 'Supplier')"
        :title="$debitNote->status === 'posted' ? 'Purchase Debit Note PDN/' . $debitNote->number : 'Draft Purchase Debit Note'"
        :sub="'Date: ' . $debitNote->date->format('d-m-Y') . ' · Against bill: ' . $debitNote->bill->supplier_bill_number"
    >
        <x-badge :status="$debitNote->status" class="mr-1"/>
        @if ($debitNote->status === 'draft')
            @can('purchases.create')
                <x-button :href="route('purchase-debit-notes.edit', $debitNote)" variant="secondary">Edit Draft</x-button>
            @endcan
            @can('purchases.post')
                <x-button type="button" wire:click="post" variant="primary">Post Debit Note</x-button>
            @endcan
        @endif
        <x-button :href="route('purchases.show', $debitNote->bill)" variant="ghost">View Original Bill</x-button>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="danger">{{ session('error') }}</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Supplier</h3>
                    <p class="font-medium text-slate-900">{{ $debitNote->bill->party->name }}</p>
                    @if ($debitNote->bill->party->address)
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $debitNote->bill->party->address }}</p>
                    @endif
                    @if ($debitNote->bill->party->gstin)
                        <p class="mt-1 text-sm text-slate-600">GSTIN: <span class="font-mono">{{ $debitNote->bill->party->gstin }}</span></p>
                    @endif
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Document Particulars</h3>
                    <div class="space-y-1.5 text-sm">
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Debit note date:</span> <span class="text-slate-800">{{ $debitNote->date->format('d-m-Y') }}</span></p>
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Original bill no:</span> <a href="{{ route('purchases.show', $debitNote->bill) }}" class="font-mono text-brand-700 hover:underline">{{ $debitNote->bill->supplier_bill_number }}</a></p>
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Reason:</span> <span class="text-slate-800">{{ $debitNote->reason }}</span></p>
                        @if ($debitNote->voucher)
                            <p><span class="text-xs uppercase tracking-wide text-slate-500">Accounting voucher:</span> <span class="font-mono text-slate-800">{{ $debitNote->voucher->series }}/{{ $debitNote->voucher->number }}</span></p>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-table caption="Debit note items">
                <thead>
                    <tr>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-right">Qty Returned</th>
                        <th scope="col" class="text-right">Taxable</th>
                        <th scope="col" class="text-right">CGST</th>
                        <th scope="col" class="text-right">SGST/UTGST</th>
                        <th scope="col" class="text-right">IGST</th>
                        <th scope="col" class="text-right">Cess</th>
                        <th scope="col" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($debitNote->lines as $line)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $line->description }}</td>
                            <td class="text-right font-mono tabular-nums text-slate-700">{{ $line->quantity }}</td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->taxable_amount"/></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->cgst"/></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="(string) $line->sgst->add($line->utgst)"/></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->igst"/></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->cess"/></td>
                            <td class="text-right font-mono tabular-nums font-semibold text-slate-900"><x-money :value="$line->total"/></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            @if ($debitNote->voucher)
                <x-card>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                        Accounting Voucher Entries (Voucher {{ $debitNote->voucher->series }}/{{ $debitNote->voucher->number }})
                    </h3>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-500 border-b border-slate-100">
                                <th class="text-left py-2">Ledger</th>
                                <th class="text-right py-2">Debit (₹)</th>
                                <th class="text-right py-2">Credit (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach ($debitNote->voucher->lines as $vl)
                                <tr>
                                    <td class="py-2 text-slate-800 font-sans">{{ $vl->ledger->name }} ({{ $vl->ledger->code }})</td>
                                    <td class="py-2 text-right text-slate-700">{{ $vl->debit->isZero() ? '—' : $vl->debit }}</td>
                                    <td class="py-2 text-right text-slate-700">{{ $vl->credit->isZero() ? '—' : $vl->credit }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card>
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">Debit Note Totals</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <dt>Taxable Amount</dt>
                        <dd class="font-mono tabular-nums font-medium text-slate-900"><x-money :value="$debitNote->taxable_amount"/></dd>
                    </div>
                    @if (!$debitNote->cgst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>CGST Reversed</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$debitNote->cgst"/></dd>
                        </div>
                    @endif
                    @if (!$debitNote->sgst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>SGST Reversed</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$debitNote->sgst"/></dd>
                        </div>
                    @endif
                    @if (!$debitNote->igst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>IGST Reversed</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$debitNote->igst"/></dd>
                        </div>
                    @endif
                    @if (!$debitNote->cess->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>Cess Reversed</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$debitNote->cess"/></dd>
                        </div>
                    @endif
                    <div class="flex justify-between text-base font-bold text-slate-900 border-t border-slate-200 pt-2">
                        <dt>Total Debit to Supplier</dt>
                        <dd class="font-mono tabular-nums text-brand-700"><x-money :value="$debitNote->total"/></dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</div>
