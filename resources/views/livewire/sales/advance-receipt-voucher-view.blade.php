<div class="space-y-6">
    <x-page-header
        :title="'Advance Receipt Voucher '.$voucher->label()"
        eyebrow="Sales · Statutory Receipt Voucher"
        sub="Statutory receipt voucher issued under CGST Act s. 31(3)(d) & Rule 50"
    >
        <x-button :href="route('customers.show', $voucher->party_id)" variant="ghost">Customer outstanding</x-button>
        @if ($voucher->receipt_id)
            <x-button :href="route('receipts.show', $voucher->receipt_id)" variant="secondary">View Money Receipt</x-button>
        @endif
    </x-page-header>

    {{-- Top Overview Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Customer</span>
            <span class="mt-1 block text-base font-bold text-slate-900">{{ $voucher->party_snapshot['name'] ?? $voucher->party->name }}</span>
            <span class="block text-xs text-slate-500 font-mono">GSTIN: {{ $voucher->party_snapshot['gstin'] ?? ($voucher->party->gstin ?? 'URP') }}</span>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Gross Advance</span>
            <span class="mt-1 block font-mono text-2xl font-bold text-slate-900">₹{{ $voucher->gross_amount }}</span>
            <span class="block text-xs text-slate-500">Status: <span class="font-semibold uppercase text-emerald-700">{{ $voucher->status }}</span></span>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Taxable Value</span>
            <span class="mt-1 block font-mono text-xl font-bold text-slate-900">₹{{ $voucher->taxable_amount }}</span>
            <span class="block text-xs text-slate-500 font-mono">Rate: {{ $voucher->tax_rate }}%</span>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Place of Supply</span>
            <span class="mt-1 block text-base font-semibold text-slate-900">State code: {{ $voucher->place_of_supply }}</span>
            <span class="block text-xs text-slate-500">Date: {{ $voucher->date->format('d-m-Y') }}</span>
        </div>
    </div>

    {{-- Description & Rule 50 details --}}
    <x-card class="space-y-4">
        <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Statutory Rule 50 Particulars</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Description of Supply</span>
                <p class="mt-1 text-sm text-slate-800 font-medium">{{ $voucher->description }}</p>
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Supply Type</span>
                <p class="mt-1 text-sm text-slate-800 font-medium uppercase">{{ $voucher->supply_type }}</p>
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Rate Determinability</span>
                <p class="mt-1 text-sm text-slate-800">
                    @if ($voucher->is_rate_determinable)
                        <span class="inline-flex items-center text-emerald-700 font-medium">Determinable ({{ $voucher->tax_rate }}%)</span>
                    @else
                        <span class="inline-flex items-center text-amber-700 font-medium">Undetermined · 18% statutory rate applied (Rule 50 proviso (i))</span>
                    @endif
                </p>
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Nature of Supply</span>
                <p class="mt-1 text-sm text-slate-800">
                    @if ($voucher->is_interstate_determinable)
                        <span class="inline-flex items-center text-emerald-700 font-medium">Determinable (POS {{ $voucher->place_of_supply }})</span>
                    @else
                        <span class="inline-flex items-center text-amber-700 font-medium">Undetermined · Inter-state IGST applied (Rule 50 proviso (ii))</span>
                    @endif
                </p>
            </div>
        </div>

        @if ($voucher->payment_reference || $voucher->narration)
            <div class="pt-3 border-t border-slate-100 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @if ($voucher->payment_reference)
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Payment Reference</span>
                        <span class="mt-1 block text-sm font-mono text-slate-800">{{ $voucher->payment_reference }}</span>
                    </div>
                @endif
                @if ($voucher->narration)
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Narration</span>
                        <span class="mt-1 block text-sm text-slate-800">{{ $voucher->narration }}</span>
                    </div>
                @endif
            </div>
        @endif
    </x-card>

    {{-- Statutory Tax Table --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-900 mb-3">Statutory Tax Breakdown</h2>
        <x-table caption="Statutory advance tax breakdown">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Gross Advance</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Taxable Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">CGST</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">SGST / UTGST</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">IGST</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tax</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr class="hover:bg-slate-50/50">
                    <td class="px-4 py-3 text-sm font-mono text-slate-900">
                        {{ $voucher->tax_rate }}%
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900">
                        ₹{{ $voucher->gross_amount }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-slate-800">
                        ₹{{ $voucher->taxable_amount }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                        ₹{{ $voucher->cgst }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                        ₹{{ ((float) $voucher->utgst > 0) ? $voucher->utgst : $voucher->sgst }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                        ₹{{ $voucher->igst }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-700">
                        ₹{{ $voucher->cgst->add($voucher->sgst)->add($voucher->utgst)->add($voucher->igst) }}
                    </td>
                </tr>
            </tbody>
        </x-table>
    </x-card>
</div>
