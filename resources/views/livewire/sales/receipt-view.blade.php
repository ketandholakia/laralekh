<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{ $receipt->status === 'posted' ? 'Receipt '.$receipt->label() : 'Receipt draft' }}
            </h1>
            <p class="text-sm text-slate-500">{{ $receipt->date->format('d-m-Y') }}
                @if ($receipt->voucher) · Voucher {{ $receipt->voucher->series }}/{{ $receipt->voucher->number }}@endif</p>
        </div>
        <div class="flex gap-2">
            @if ($receipt->status === 'posted')
                <a href="{{ route('receipts.pdf', $receipt) }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Download PDF</a>
            @endif
            @if ($receipt->status === 'draft' && auth()->user()->can('receipts.create'))
                <a href="{{ route('receipts.edit', $receipt) }}" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Edit draft</a>
            @endif
            <a href="{{ route('receipts.index') }}" class="text-sm text-slate-500 self-center hover:underline">Back to list</a>
        </div>
    </div>

    @if ($receipt->status === 'draft')
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800">
            Draft preview: no receipt number has been allocated and no entries posted.
        </div>
    @endif
    @if ($receipt->reversals->count() > 0)
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800">
            This receipt has been reversed
            @if ($receipt->reversals->first()?->voucher) by voucher {{ $receipt->reversals->first()->voucher->series }}/{{ $receipt->reversals->first()->voucher->number }}@endif.
            Reason: {{ $receipt->reversals->first()->reason }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4 text-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><span class="text-xs uppercase text-slate-500">Customer</span>
                        <p class="font-medium">{{ $receipt->party_snapshot['name'] ?? $receipt->party?->name }}</p>
                        <p class="whitespace-pre-line text-slate-600">{{ ($receipt->status === 'posted' ? ($receipt->party_snapshot['address'] ?? '') : ($receipt->party->address ?? '')) }}</p></div>
                    <div>
                        <p><span class="text-xs uppercase text-slate-500">Paid to</span> {{ $receipt->ledger->code }} · {{ $receipt->ledger->name }}</p>
                        <p><span class="text-xs uppercase text-slate-500">Reference</span> {{ $receipt->payment_reference ?? '—' }}</p>
                        <p><span class="text-xs uppercase text-slate-500">Narration</span> {{ $receipt->narration ?? '—' }}</p>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-2">Allocations</h2>
                @if ($receipt->allocations->count() === 0)
                    <p class="text-sm text-slate-400">Nothing allocated; the full amount is an unapplied customer advance.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr><th class="py-1">Invoice</th><th class="py-1">Invoice date</th>
                                <th class="py-1 text-right">Amount</th><th class="py-1">Status</th>
                                @can('reversals.post')<th class="py-1">Undo</th>@endcan</tr>
                        </thead>
                        <tbody>
                            @foreach ($receipt->allocations as $allocation)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1.5 font-mono">{{ $allocation->invoice->series }}/{{ $allocation->invoice->number }}</td>
                                    <td class="py-1.5">{{ $allocation->invoice->date->format('d-m-Y') }}</td>
                                    <td class="py-1.5 text-right font-mono">{{ $allocation->amount }}</td>
                                    <td class="py-1.5">
                                        @if ($allocation->undoneBy)
                                            <span class="text-xs text-slate-400">undone</span>
                                        @else
                                            <span class="text-xs text-green-700">active</span>
                                        @endif
                                    </td>
                                    @can('reversals.post')
                                    <td class="py-1.5">
                                        @if (!$allocation->undoneBy && $receipt->status === 'posted' && $receipt->reversals->count() === 0)
                                            <label class="flex items-center gap-1 text-xs">
                                                <input type="checkbox" wire:model.live="undoSelection" value="{{ $allocation->id }}" class="rounded border-slate-300"> undo
                                            </label>
                                        @endif
                                    </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @can('reversals.post')
                    @if (count($this->undoSelection) > 0 && $receipt->reversals->count() === 0)
                        <button type="button" wire:click="undoAllocations" wire:confirm="Undo the selected allocations? The invoice balances are restored; history is preserved."
                                class="mt-3 rounded border border-red-500 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">Undo selected allocations</button>
                    @endif
                    @endcan
                @endif
                @error('allocations')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </section>

            @can('receipts.allocate')
            @if ($receipt->status === 'posted' && $receipt->reversals->count() === 0 && (float) $this->availableFunds > 0 && count($this->eligibleInvoices) > 0)
                <section class="bg-white rounded-lg border border-slate-200 p-4">
                    <h2 class="text-sm font-semibold mb-1">Allocate remaining funds (₹{{ $this->availableFunds }})</h2>
                    <p class="text-xs text-slate-500 mb-3">Requires the allocation permission. Allocations never create accounting entries.</p>
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr><th class="py-1">Invoice</th><th class="py-1 text-right">Outstanding</th><th class="py-1 text-right">Allocate</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($this->eligibleInvoices as $invoice)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1.5 font-mono">{{ $invoice['label'] }}</td>
                                    <td class="py-1.5 text-right font-mono">{{ $invoice['outstanding'] }}</td>
                                    <td class="py-1.5 text-right">
                                        <input type="text" wire:model.live="newAllocations.{{ $invoice['id'] }}"
                                               class="w-28 rounded border border-slate-300 px-2 py-1 text-sm text-right">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" wire:click="allocate" wire:loading.attr="disabled" wire:target="allocate"
                            class="mt-3 rounded bg-indigo-600 px-4 py-1.5 text-sm text-white hover:bg-indigo-700 disabled:opacity-50">Allocate funds</button>
                </section>
            @endif
            @endcan

            @can('reversals.post')
            @if ($receipt->status === 'posted' && $receipt->reversals->count() === 0)
                <section class="bg-white rounded-lg border border-slate-200 p-4">
                    <h2 class="text-sm font-semibold mb-1">Reverse receipt</h2>
                    <p class="text-xs text-slate-500 mb-3">Exact reversal: the entries swap, every active allocation is undone, and history is preserved. The customer balance is restored.</p>
                    <textarea wire:model="reverseReason" rows="2" placeholder="Reason (5–2000 characters)"
                              class="w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <button type="button" wire:click="reverse" wire:loading.attr="disabled" wire:target="reverse"
                            wire:confirm="Reverse receipt {{ $receipt->label() }}? The amount (₹{{ $receipt->amount }}) returns to the customer balance and active allocations are undone."
                            class="mt-2 rounded border border-red-500 px-4 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50">Reverse receipt</button>
                    @error('reverseReason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </section>
            @endif
            @endcan
        </div>

        <aside class="space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4 text-sm">
                <h2 class="text-sm font-semibold mb-3">Summary</h2>
                <dl class="space-y-1">
                    <div class="flex justify-between"><dt>Received</dt><dd class="font-mono">₹{{ $receipt->amount }}</dd></div>
                    <div class="flex justify-between"><dt>Allocated</dt><dd class="font-mono">₹{{ \App\Domain\Shared\ValueObjects\Money::of(bcsub((string) $receipt->amount, $this->availableFunds, 2)) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Unapplied advance</dt><dd class="font-mono">₹{{ $this->availableFunds }}</dd></div>
                </dl>
                @if ($receipt->status === 'posted')
                    <a href="{{ route('customers.show', $receipt->party) }}" class="mt-3 inline-block text-xs text-indigo-600 hover:underline">Customer outstanding →</a>
                @endif
            </section>
        </aside>
    </div>
</div>
