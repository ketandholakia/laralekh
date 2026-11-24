<div>
    <x-page-header
        :eyebrow="'Purchases · ' . ($bill->party?->name ?? 'Supplier')"
        :title="'Purchase bill ' . $bill->supplier_bill_number"
        :sub="$bill->date->format('d-m-Y') . ($bill->voucher ? ' · Voucher '.$bill->voucher->series.'/'.$bill->voucher->number : '')"
    >
        <x-badge :status="$bill->status" class="mr-1" />
        @if ($bill->status === 'draft' && auth()->user()->can('purchases.create'))
            <x-button :href="route('purchases.edit', $bill)" variant="primary">
                <x-icon name="cart" class="h-4 w-4" /> Edit draft
            </x-button>
        @endif
        <x-button :href="route('purchases.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    @if ($bill->status === 'draft')
        <x-alert type="warning" class="mb-6">
            Draft purchase bill: this bill has not been posted and has no accounting effect.
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Supplier</h3>
                    <p class="font-medium text-slate-900">{{ $bill->party->name }}</p>
                    @if ($bill->party->address)
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $bill->party->address }}</p>
                    @endif
                    @if ($bill->party->gstin)
                        <p class="mt-1 text-sm text-slate-600">GSTIN: <span class="font-mono">{{ $bill->party->gstin }}</span></p>
                    @endif
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Bill details</h3>
                    <div class="space-y-1.5 text-sm">
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Supplier bill no:</span> <span class="font-mono font-medium text-slate-900">{{ $bill->supplier_bill_number }}</span></p>
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Supplier bill date:</span> <span class="text-slate-800">{{ $bill->supplier_bill_date?->format('d-m-Y') ?? '—' }}</span></p>
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Posting date:</span> <span class="text-slate-800">{{ $bill->date->format('d-m-Y') }}</span></p>
                        @if ($bill->reverse_charge)
                            <p class="text-xs font-medium text-amber-700">· Reverse charge applicable</p>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-table caption="Purchase bill line items">
                <thead>
                    <tr>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-right">Qty</th>
                        <th scope="col" class="text-right">Taxable</th>
                        <th scope="col" class="text-right">CGST</th>
                        <th scope="col" class="text-right">SGST/UTGST</th>
                        <th scope="col" class="text-right">IGST</th>
                        <th scope="col" class="text-right">Cess</th>
                        <th scope="col" class="text-right">Total</th>
                        <th scope="col">ITC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($bill->items as $line)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $line->description }}</td>
                            <td class="text-right font-mono tabular-nums text-slate-700">{{ $line->quantity }}</td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->taxable_amount" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->cgst" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->sgst->add($line->utgst)" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->igst" /></td>
                            <td class="text-right font-mono tabular-nums text-slate-700"><x-money :value="$line->cess" /></td>
                            <td class="text-right font-mono tabular-nums font-medium text-slate-900"><x-money :value="$line->total" /></td>
                            <td>
                                @if ($line->itc_eligibility === 'eligible')
                                    <x-badge status="posted">Eligible</x-badge>
                                @elseif ($line->itc_eligibility === 'blocked')
                                    <x-badge status="reversed">Blocked</x-badge>
                                @else
                                    <x-badge status="pending">{{ ucfirst(str_replace('_', ' ', $line->itc_eligibility)) }}</x-badge>
                                @endif
                                @if ($line->itc_reason)
                                    <span class="block text-xs text-slate-500 mt-0.5">{{ $line->itc_reason }}</span>
                                @endif
                            </td>
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
                        <dt>Taxable amount</dt>
                        <dd class="font-mono tabular-nums text-slate-900">
                            <x-money :value="$bill->items->reduce(fn ($carry, $item) => $carry->add($item->taxable_amount), \App\Domain\Shared\ValueObjects\Money::of('0'))" />
                        </dd>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <dt>Total tax</dt>
                        <dd class="font-mono tabular-nums text-slate-900">
                            <x-money :value="$bill->total->subtract($bill->items->reduce(fn ($carry, $item) => $carry->add($item->taxable_amount), \App\Domain\Shared\ValueObjects\Money::of('0')))" />
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2.5 mt-2 font-semibold text-slate-900">
                        <dt>Total payable</dt>
                        <dd class="font-mono tabular-nums text-brand-700 text-base"><x-money :value="$bill->total" /></dd>
                    </div>
                </dl>
            </x-card>
        </aside>
    </div>
</div>
