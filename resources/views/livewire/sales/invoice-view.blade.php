<div>
    @if ($invoice->status === 'posted')
        @can('corrections.view')
            <livewire:sales.invoice-corrections :invoice-id="$invoice->id" />
        @endcan
    @endif

    <x-page-header
        :eyebrow="'Sales · ' . ($invoice->party?->name ?? 'Invoice')"
        :title="$invoice->status === 'posted' ? 'Invoice '.$invoice->series.'/'.$invoice->number : 'Draft invoice'"
        :sub="$invoice->date->format('d-m-Y') . ($invoice->status === 'posted' && $invoice->voucher ? ' · Voucher '.$invoice->voucher->series.'/'.$invoice->voucher->number : '')"
    >
        <x-badge :status="$invoice->status" class="mr-1" />
        <x-button :href="route('invoices.pdf', $invoice)" variant="secondary">
            <x-icon name="file-down" class="h-4 w-4" /> Download PDF
        </x-button>
        @if ($invoice->status === 'draft' && auth()->user()->can('invoices.create'))
            <x-button :href="route('invoices.edit', $invoice)" variant="primary">
                <x-icon name="invoice" class="h-4 w-4" /> Edit draft
            </x-button>
        @endif
        <x-button :href="route('invoices.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    @if ($invoice->status === 'draft')
        <x-alert type="warning" class="mb-6">
            This invoice is a draft. It has no invoice number and no accounting effect until posted.
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Supplier</h3>
                    @if ($invoice->status === 'posted' && $invoice->supplier_snapshot)
                        <p class="font-medium text-slate-900">{{ $invoice->supplier_snapshot['name'] }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $invoice->supplier_snapshot['address'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">GSTIN: <span class="font-mono">{{ $invoice->supplier_snapshot['gstin'] ?? '—' }}</span> · State: {{ $invoice->supplier_snapshot['state_code'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ ucfirst($invoice->supplier_snapshot['gst_regime']) }} regime</p>
                    @else
                        <p class="text-xs text-slate-400">Snapshotted at posting.</p>
                    @endif
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Recipient</h3>
                    <p class="font-medium text-slate-900">{{ $invoice->party?->name }}</p>
                    @if ($invoice->status === 'posted' && $invoice->party_snapshot)
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $invoice->party_snapshot['address'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">GSTIN: <span class="font-mono">{{ $invoice->party_snapshot['gstin'] ?? '—' }}</span> · {{ ucfirst($invoice->party_snapshot['registration_type']) }}</p>
                    @endif
                </div>
            </x-card>

            <x-table caption="Invoice line items">
                <thead>
                    <tr>
                        <th scope="col" class="w-12">#</th>
                        <th scope="col">Description</th>
                        <th scope="col">HSN/SAC</th>
                        <th scope="col" class="text-right">Qty</th>
                        <th scope="col" class="text-right">Price</th>
                        <th scope="col" class="text-right">Discount</th>
                        <th scope="col" class="text-right">Taxable</th>
                        <th scope="col" class="text-right">Tax</th>
                        <th scope="col" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="text-xs text-slate-500">{{ $item->position }}</td>
                            <td class="font-medium text-slate-900">{{ $item->description }}</td>
                            <td class="font-mono text-xs text-slate-600">{{ $item->hsn_sac }}</td>
                            <td class="text-right font-mono tabular-nums text-slate-700">{{ $item->quantity }}</td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$item->unit_price" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$item->discount" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$item->taxable_amount" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700">
                                <x-money :value="(string) $item->cgst->add($item->sgst)->add($item->utgst)->add($item->igst)->add($item->cess)" />
                                @if ($item->tax_category !== 'taxable')
                                    <span class="block text-xs font-sans text-slate-400">({{ str_replace('_', ' ', $item->tax_category) }})</span>
                                @endif
                            </td>
                            <td class="text-right font-mono tabular-nums font-medium text-slate-900"><x-money :value="$item->total" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>

        <aside class="space-y-6">
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Totals</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <dt>Taxable value</dt>
                        <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$invoice->taxable_amount" /></dd>
                    </div>
                    @if (!$invoice->igst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>IGST</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$invoice->igst" /></dd>
                        </div>
                    @endif
                    @if (!$invoice->cgst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>CGST</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$invoice->cgst" /></dd>
                        </div>
                    @endif
                    @if (!$invoice->sgst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>SGST</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$invoice->sgst" /></dd>
                        </div>
                    @endif
                    @if (!$invoice->utgst->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>UTGST</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$invoice->utgst" /></dd>
                        </div>
                    @endif
                    @if (!$invoice->cess->isZero())
                        <div class="flex justify-between text-slate-600">
                            <dt>Compensation cess</dt>
                            <dd class="font-mono tabular-nums text-slate-900"><x-money :value="$invoice->cess" /></dd>
                        </div>
                    @endif
                    @if (!$invoice->recipient_tax->isZero())
                        <div class="flex justify-between text-slate-500">
                            <dt>RCM tax (payable by recipient)</dt>
                            <dd class="font-mono tabular-nums text-slate-500"><x-money :value="$invoice->recipient_tax" /></dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-slate-200 pt-2.5 mt-2 font-semibold text-slate-900">
                        <dt>Total</dt>
                        <dd class="font-mono tabular-nums text-brand-700 text-base"><x-money :value="$invoice->total" /></dd>
                    </div>
                </dl>
                <div class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500 space-y-1">
                    <p>Place of supply: <span class="font-medium text-slate-700">{{ $invoice->place_of_supply }}</span> · {{ ucfirst($invoice->supply_type) }}</p>
                    @if ($invoice->reverse_charge)
                        <p class="text-amber-700 font-medium">· Reverse charge applicable</p>
                    @endif
                    @if ($invoice->zero_rated_mode !== 'none')
                        <p class="text-slate-600">· Zero-rated ({{ str_replace('_', ' ', $invoice->zero_rated_mode) }})</p>
                    @endif
                </div>
            </x-card>
        </aside>
    </div>
</div>
