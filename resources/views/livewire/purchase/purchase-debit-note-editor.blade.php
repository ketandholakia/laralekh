<div class="space-y-6">
    <x-page-header
        :eyebrow="'Purchases · ' . ($bill->party?->name ?? 'Supplier')"
        :title="$debitNoteId ? 'Edit Purchase Debit Note' : 'Issue Purchase Debit Note'"
        :sub="'Against supplier bill ' . $bill->supplier_bill_number . ' (dated ' . $bill->date->format('d-m-Y') . ')'"
    >
        <x-button :href="route('purchases.show', $bill)" variant="ghost">Cancel</x-button>
    </x-page-header>

    @error('lines')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    @error('post')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Debit Note Date" name="date" required>
                        <x-input name="date" type="date" wire:model.live="date"/>
                    </x-field>

                    <x-field label="Reason for Debit Note" name="reason" required hint="e.g. Goods return, price dispute, deficiency">
                        <x-input name="reason" type="text" wire:model.live="reason" placeholder="Reason for return/correction"/>
                    </x-field>
                </div>
            </x-card>

            <x-card class="p-0 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">Select Items to Return</span>
                    <span class="text-xs text-slate-500">Quantities cannot exceed remaining bill item balance</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="w-10 px-4 py-3"></th>
                                <th class="px-4 py-3">Item / Description</th>
                                <th class="px-4 py-3 text-right">Bill Qty</th>
                                <th class="px-4 py-3 text-right">Prior Debits</th>
                                <th class="px-4 py-3 text-right">Available</th>
                                <th class="px-4 py-3 text-right w-32">Return Qty</th>
                                <th class="px-4 py-3 text-right">Taxable</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($lines as $id => $line)
                                <tr class="hover:bg-slate-50/60 {{ $line['selected'] ? 'bg-brand-50/20' : '' }}">
                                    <td class="px-4 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            wire:model.live="lines.{{ $id }}.selected"
                                            {{ bccomp($line['max_quantity'], '0', 3) <= 0 ? 'disabled' : '' }}
                                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-slate-900">{{ $line['description'] }}</span>
                                        <div class="text-xs text-slate-400">Rate: ₹{{ $line['unit_price'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-600">{{ $line['original_quantity'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-400">{{ $line['prior_debited_quantity'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-medium text-emerald-700">{{ $line['max_quantity'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            max="{{ $line['max_quantity'] }}"
                                            wire:model.live.debounce.300ms="lines.{{ $id }}.quantity"
                                            {{ !$line['selected'] ? 'disabled' : '' }}
                                            class="w-24 text-right rounded-md border-slate-300 text-sm py-1 px-2 focus:border-brand-600 focus:ring-brand-500 disabled:bg-slate-50"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono tabular-nums text-slate-700">₹{{ $line['taxable_amount'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold tabular-nums text-slate-900">₹{{ $line['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card>
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">Debit Note Totals</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <dt>Taxable Amount</dt>
                        <dd class="font-mono tabular-nums font-medium text-slate-900">₹{{ $totalTaxable }}</dd>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <dt>Tax Reversed (ITC)</dt>
                        <dd class="font-mono tabular-nums font-medium text-slate-900">₹{{ bcsub($totalAmount, $totalTaxable, 2) }}</dd>
                    </div>
                    <div class="flex justify-between text-base font-bold text-slate-900 border-t border-slate-200 pt-2">
                        <dt>Total Debit Amount</dt>
                        <dd class="font-mono tabular-nums text-brand-700">₹{{ $totalAmount }}</dd>
                    </div>
                </dl>

                <div class="mt-6 space-y-2">
                    @can('purchases.post')
                        <x-button type="button" wire:click="post" variant="primary" class="w-full">
                            Post Debit Note
                        </x-button>
                    @endcan
                    <x-button type="button" wire:click="saveDraft" variant="secondary" class="w-full">
                        Save as Draft
                    </x-button>
                </div>
            </x-card>

            <x-card class="bg-slate-50/50">
                <h4 class="text-xs font-semibold text-slate-700 mb-1">Accounting & Tax Treatment</h4>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Posting a purchase debit note debits the supplier's payable ledger, reduces the purchase expense, and reverses the corresponding Input Tax Credit (ITC). Tracked inventory is reduced accordingly.
                </p>
            </x-card>
        </div>
    </div>
</div>
