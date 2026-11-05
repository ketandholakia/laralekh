<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{ $invoice->status === 'posted' ? 'Invoice '.$invoice->series.'/'.$invoice->number : 'Draft invoice' }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ $invoice->date->format('d-m-Y') }}
                @if ($invoice->status === 'posted') · Voucher {{ $invoice->voucher->series }}/{{ $invoice->voucher->number }}@endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invoices.pdf', $invoice) }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Download PDF</a>
            @if ($invoice->status === 'draft' && auth()->user()->can('invoices.create'))
                <a href="{{ route('invoices.edit', $invoice) }}" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Edit draft</a>
            @endif
            <a href="{{ route('invoices.index') }}" class="text-sm text-slate-500 self-center hover:underline">Back to list</a>
        </div>
    </div>

    @if ($invoice->status === 'draft')
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800">
            This invoice is a draft. It has no invoice number and no accounting effect until posted.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <h3 class="text-xs uppercase text-slate-500 mb-1">Supplier</h3>
                    @if ($invoice->status === 'posted' && $invoice->supplier_snapshot)
                        <p class="font-medium">{{ $invoice->supplier_snapshot['name'] }}</p>
                        <p class="whitespace-pre-line text-slate-600">{{ $invoice->supplier_snapshot['address'] }}</p>
                        <p class="text-slate-600">GSTIN: {{ $invoice->supplier_snapshot['gstin'] ?? '—' }} · State: {{ $invoice->supplier_snapshot['state_code'] }}</p>
                        <p class="text-slate-500 text-xs">{{ ucfirst($invoice->supplier_snapshot['gst_regime']) }} regime</p>
                    @else
                        <p class="text-slate-400 text-xs">Snapshotted at posting.</p>
                    @endif
                </div>
                <div>
                    <h3 class="text-xs uppercase text-slate-500 mb-1">Recipient</h3>
                    <p class="font-medium">{{ $invoice->party?->name }}</p>
                    @if ($invoice->status === 'posted' && $invoice->party_snapshot)
                        <p class="whitespace-pre-line text-slate-600">{{ $invoice->party_snapshot['address'] }}</p>
                        <p class="text-slate-600">GSTIN: {{ $invoice->party_snapshot['gstin'] ?? '—' }} · {{ ucfirst($invoice->party_snapshot['registration_type']) }}</p>
                    @endif
                </div>
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-2 pr-2">#</th><th class="py-2 pr-2">Description</th><th class="py-2 pr-2">HSN/SAC</th>
                            <th class="py-2 pr-2 text-right">Qty</th><th class="py-2 pr-2 text-right">Price</th>
                            <th class="py-2 pr-2 text-right">Discount</th><th class="py-2 pr-2 text-right">Taxable</th>
                            <th class="py-2 pr-2 text-right">Tax</th><th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-2">{{ $item->position }}</td>
                                <td class="py-2 pr-2">{{ $item->description }}</td>
                                <td class="py-2 pr-2 font-mono text-xs">{{ $item->hsn_sac }}</td>
                                <td class="py-2 pr-2 text-right font-mono">{{ $item->quantity }}</td>
                                <td class="py-2 pr-2 text-right font-mono">{{ $item->unit_price }}</td>
                                <td class="py-2 pr-2 text-right font-mono">{{ $item->discount }}</td>
                                <td class="py-2 pr-2 text-right font-mono">{{ $item->taxable_amount }}</td>
                                <td class="py-2 pr-2 text-right font-mono">
                                    {{ (string) $item->cgst->add($item->sgst)->add($item->utgst)->add($item->igst) }}
                                    @if ($item->tax_category !== 'taxable')<span class="text-xs text-slate-400">({{ str_replace('_',' ',$item->tax_category) }})</span>@endif
                                </td>
                                <td class="py-2 text-right font-mono">{{ $item->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4 text-sm">
                <h2 class="text-sm font-semibold mb-3">Totals</h2>
                <dl class="space-y-1">
                    <div class="flex justify-between"><dt>Taxable value</dt><dd class="font-mono">₹{{ $invoice->taxable_amount }}</dd></div>
                    @if (!$invoice->igst->isZero())<div class="flex justify-between"><dt>IGST</dt><dd class="font-mono">₹{{ $invoice->igst }}</dd></div>@endif
                    @if (!$invoice->cgst->isZero())<div class="flex justify-between"><dt>CGST</dt><dd class="font-mono">₹{{ $invoice->cgst }}</dd></div>@endif
                    @if (!$invoice->sgst->isZero())<div class="flex justify-between"><dt>SGST</dt><dd class="font-mono">₹{{ $invoice->sgst }}</dd></div>@endif
                    @if (!$invoice->utgst->isZero())<div class="flex justify-between"><dt>UTGST</dt><dd class="font-mono">₹{{ $invoice->utgst }}</dd></div>@endif
                    @if (!$invoice->recipient_tax->isZero())<div class="flex justify-between text-slate-500"><dt>RCM tax (payable by recipient)</dt><dd class="font-mono">₹{{ $invoice->recipient_tax }}</dd></div>@endif
                    <div class="flex justify-between border-t border-slate-200 pt-2 mt-2 font-semibold"><dt>Total</dt><dd class="font-mono">₹{{ $invoice->total }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">
                    Place of supply: {{ $invoice->place_of_supply }} · {{ ucfirst($invoice->supply_type) }}
                    @if ($invoice->reverse_charge) · Reverse charge @endif
                    @if ($invoice->zero_rated_mode !== 'none') · Zero-rated ({{ str_replace('_', ' ', $invoice->zero_rated_mode) }}) @endif
                </p>
            </section>
        </aside>
    </div>
</div>
