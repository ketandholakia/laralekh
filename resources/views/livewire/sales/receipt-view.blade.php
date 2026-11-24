<div>
    <x-page-header
        :eyebrow="'Receipts · ' . ($receipt->party_snapshot['name'] ?? $receipt->party?->name ?? 'Customer')"
        :title="$receipt->status === 'posted' ? 'Receipt '.$receipt->label() : 'Receipt draft'"
        :sub="$receipt->date->format('d-m-Y') . ($receipt->voucher ? ' · Voucher '.$receipt->voucher->series.'/'.$receipt->voucher->number : '')"
    >
        <x-badge :status="$receipt->status" class="mr-1" />
        @if ($receipt->status === 'posted')
            <x-button :href="route('receipts.pdf', $receipt)" variant="secondary">
                <x-icon name="file-down" class="h-4 w-4" /> Download PDF
            </x-button>
        @endif
        @if ($receipt->status === 'draft' && auth()->user()->can('receipts.create'))
            <x-button :href="route('receipts.edit', $receipt)" variant="primary">
                <x-icon name="receipt" class="h-4 w-4" /> Edit draft
            </x-button>
        @endif
        <x-button :href="route('receipts.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    @if ($receipt->status === 'draft')
        <x-alert type="warning" class="mb-6">
            Draft preview: no receipt number has been allocated and no entries posted.
        </x-alert>
    @endif
    @if ($receipt->reversals->count() > 0)
        <x-alert type="danger" class="mb-6">
            This receipt has been reversed
            @if ($receipt->reversals->first()?->voucher) by voucher {{ $receipt->reversals->first()->voucher->series }}/{{ $receipt->reversals->first()->voucher->number }}@endif.
            Reason: {{ $receipt->reversals->first()->reason }}
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Customer</h3>
                    <p class="font-medium text-slate-900">{{ $receipt->party_snapshot['name'] ?? $receipt->party?->name }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ ($receipt->status === 'posted' ? ($receipt->party_snapshot['address'] ?? '') : ($receipt->party->address ?? '')) }}</p>
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Payment details</h3>
                    <div class="space-y-1.5 text-sm">
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Paid to:</span> <span class="font-medium text-slate-800">{{ $receipt->ledger->code }} · {{ $receipt->ledger->name }}</span></p>
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Reference:</span> <span class="font-mono text-slate-800">{{ $receipt->payment_reference ?? '—' }}</span></p>
                        <p><span class="text-xs uppercase tracking-wide text-slate-500">Narration:</span> <span class="text-slate-800">{{ $receipt->narration ?? '—' }}</span></p>
                    </div>
                </div>
            </x-card>

            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900">Allocations</h2>
                @if ($receipt->allocations->count() === 0)
                    <p class="text-sm text-slate-400">Nothing allocated; the full amount is an unapplied customer advance.</p>
                @else
                    <x-table caption="Receipt allocations">
                        <thead>
                            <tr>
                                <th scope="col">Invoice</th>
                                <th scope="col">Invoice date</th>
                                <th scope="col" class="text-right">Amount</th>
                                <th scope="col">Status</th>
                                @can('reversals.post')<th scope="col" class="text-center">Undo</th>@endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($receipt->allocations as $allocation)
                                <tr>
                                    <td class="font-mono text-xs font-medium text-slate-900">{{ $allocation->invoice->series }}/{{ $allocation->invoice->number }}</td>
                                    <td class="text-sm text-slate-600">{{ $allocation->invoice->date->format('d-m-Y') }}</td>
                                    <td class="text-right font-mono tabular-nums text-sm text-slate-900"><x-money :value="$allocation->amount" /></td>
                                    <td>
                                        @if ($allocation->undoneBy)
                                            <x-badge status="inactive">undone</x-badge>
                                        @else
                                            <x-badge status="posted">active</x-badge>
                                        @endif
                                    </td>
                                    @can('reversals.post')
                                        <td class="text-center">
                                            @if (!$allocation->undoneBy && $receipt->status === 'posted' && $receipt->reversals->count() === 0)
                                                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                                                    <input type="checkbox" wire:model.live="undoSelection" value="{{ $allocation->id }}" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600/20">
                                                    <span>undo</span>
                                                </label>
                                            @endif
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                    @can('reversals.post')
                        @if (count($this->undoSelection) > 0 && $receipt->reversals->count() === 0)
                            <x-button type="button" wire:click="undoAllocations" wire:confirm="Undo the selected allocations? The invoice balances are restored; history is preserved."
                                      variant="danger" size="sm" class="mt-3">Undo selected allocations</x-button>
                        @endif
                    @endcan
                @endif
                @error('allocations')<x-alert type="danger" class="mt-3">{{ $message }}</x-alert>@enderror
            </x-card>

            @can('receipts.allocate')
                @if ($receipt->status === 'posted' && $receipt->reversals->count() === 0 && (float) $this->availableFunds > 0 && count($this->eligibleInvoices) > 0)
                    <x-card class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Allocate remaining funds (₹{{ $this->availableFunds }})</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Requires the allocation permission. Allocations never create accounting entries.</p>
                        </div>
                        <x-table caption="Eligible invoices for allocation">
                            <thead>
                                <tr>
                                    <th scope="col">Invoice</th>
                                    <th scope="col" class="text-right">Outstanding</th>
                                    <th scope="col" class="text-right">Allocate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($this->eligibleInvoices as $invoice)
                                    <tr>
                                        <td class="font-mono text-xs font-medium text-slate-900">{{ $invoice['label'] }}</td>
                                        <td class="text-right font-mono tabular-nums text-sm text-slate-700"><x-money :value="$invoice['outstanding']" /></td>
                                        <td class="text-right">
                                            <input type="text" wire:model.live="newAllocations.{{ $invoice['id'] }}"
                                                   class="w-28 rounded-md border-slate-300 px-2.5 py-1 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                                   placeholder="0.00" aria-label="Allocate for invoice {{ $invoice['label'] }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                        <x-button type="button" wire:click="allocate" wire:loading.attr="disabled" wire:target="allocate"
                                  variant="primary" size="sm">Allocate funds</x-button>
                    </x-card>
                @endif
            @endcan

            @can('reversals.post')
                @if ($receipt->status === 'posted' && $receipt->reversals->count() === 0)
                    <x-card class="space-y-3">
                        <div>
                            <h2 class="text-sm font-semibold text-red-900">Reverse receipt</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Exact reversal: the entries swap, every active allocation is undone, and history is preserved. The customer balance is restored.</p>
                        </div>
                        <textarea wire:model="reverseReason" rows="2" placeholder="Reason (5–2000 characters)"
                                  class="w-full rounded-md border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/20 focus:outline-none"></textarea>
                        <x-button type="button" wire:click="reverse" wire:loading.attr="disabled" wire:target="reverse"
                                  wire:confirm="Reverse receipt {{ $receipt->label() }}? The amount (₹{{ $receipt->amount }}) returns to the customer balance and active allocations are undone."
                                  variant="danger" size="sm">Reverse receipt</x-button>
                        @error('reverseReason')<x-alert type="danger" class="mt-2">{{ $message }}</x-alert>@enderror
                    </x-card>
                @endif
            @endcan
        </div>

        <aside class="space-y-6">
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Summary</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600"><dt>Received</dt><dd class="font-mono tabular-nums text-slate-900"><x-money :value="$receipt->amount" /></dd></div>
                    <div class="flex justify-between text-slate-600"><dt>Allocated</dt><dd class="font-mono tabular-nums text-slate-900"><x-money :value="\App\Domain\Shared\ValueObjects\Money::of(bcsub((string) $receipt->amount, $this->availableFunds, 2))" /></dd></div>
                    <div class="flex justify-between font-semibold border-t border-slate-200 pt-2.5 mt-2 text-slate-900"><dt>Unapplied advance</dt><dd class="font-mono tabular-nums text-brand-700 text-base"><x-money :value="$this->availableFunds" /></dd></div>
                </dl>
                @if ($receipt->status === 'posted')
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <a href="{{ route('customers.show', $receipt->party) }}" class="text-xs font-medium text-brand-700 hover:text-brand-900 hover:underline">Customer outstanding →</a>
                    </div>
                @endif
            </x-card>
        </aside>
    </div>
</div>
