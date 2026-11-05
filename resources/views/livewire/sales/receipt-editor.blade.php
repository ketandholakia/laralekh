<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $receiptId ? 'Edit receipt draft' : 'New customer receipt' }}</h1>
        <a href="{{ route('receipts.index') }}" class="text-sm text-slate-500 hover:underline">Back to list</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Customer</h2>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="partyQuery" placeholder="Search customer by name or GSTIN"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    @if (count($this->partyOptions) > 0)
                        <ul class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded shadow-lg max-h-60 overflow-auto">
                            @foreach ($this->partyOptions as $option)
                                <li><button type="button" wire:click="selectParty({{ $option['id'] }})"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                                        {{ $option['name'] }}
                                        @if ($option['gstin'])<span class="text-slate-400 font-mono text-xs"> · {{ $option['gstin'] }}</span>@endif
                                    </button></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($partyId)<p class="mt-2 text-sm text-green-700">Selected: <strong>{{ $partyName }}</strong></p>@endif
                @error('amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Payment</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Cash/bank ledger</label>
                        <select wire:model.live="ledgerId" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">— choose —</option>
                            @foreach ($this->cashLedgers as $ledger)
                                <option value="{{ $ledger->id }}" @selected($ledger->id === $ledgerId)>{{ $ledger->code }} · {{ $ledger->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Amount received (₹)</label>
                        <input type="text" wire:model.live="amount" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Receipt date</label>
                        <input type="date" wire:model.live="date" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Payment reference (UTR/cheque no.)</label>
                        <input type="text" wire:model="paymentReference" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-500">Narration</label>
                        <input type="text" wire:model="narration" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                </div>
                <div wire:loading class="text-xs text-slate-400 mt-2">Updating…</div>
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-1">Allocate to open invoices (optional)</h2>
                <p class="text-xs text-slate-500 mb-3">Funds not allocated stay with the customer as an unapplied advance and can be allocated later.</p>
                @if (count($this->eligibleInvoices) > 0)
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr><th class="py-1">Invoice</th><th class="py-1">Date</th>
                                <th class="py-1 text-right">Total</th><th class="py-1 text-right">Outstanding</th>
                                <th class="py-1 text-right">Allocate</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($this->eligibleInvoices as $invoice)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1.5 font-mono">{{ $invoice['label'] }}</td>
                                    <td class="py-1.5">{{ $invoice['date'] }}</td>
                                    <td class="py-1.5 text-right font-mono">{{ $invoice['total'] }}</td>
                                    <td class="py-1.5 text-right font-mono">{{ $invoice['outstanding'] }}</td>
                                    <td class="py-1.5 text-right">
                                        <input type="text" wire:model.live="allocations.{{ $invoice['id'] }}"
                                               class="w-28 rounded border border-slate-300 px-2 py-1 text-sm text-right">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-slate-400">No open invoices for this customer yet.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Posting preview</h2>
                @if ($this->preview['error'])
                    <p class="text-sm text-amber-700">{{ $this->preview['error'] }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">Debit</th><th class="py-1">Credit</th><th class="py-1">Ledger</th></tr></thead>
                        <tbody>
                            @foreach ($this->preview['entries'] as $entry)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1 font-mono text-right">{{ $entry['debit'] }}</td>
                                    <td class="py-1 font-mono text-right">{{ $entry['credit'] }}</td>
                                    <td class="py-1">{{ $entry['ledger'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <p class="mt-3 text-xs text-slate-500">Allocated so far: ₹{{ $this->allocatedPreview }} · Unapplied: ₹{{ \App\Domain\Shared\ValueObjects\Money::of(bcsub((string) ($this->amount !== '' && is_numeric($this->amount) ? $this->amount : '0'), $this->allocatedPreview, 2)) }}</p>
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4 space-y-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="w-full rounded border border-indigo-600 px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50 disabled:opacity-50">Save draft</button>
                <button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post"
                        wire:confirm="Post this receipt? The number is allocated and the entries become final."
                        class="w-full rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50">Post receipt</button>
                <p class="text-xs text-slate-400">Posting debits the cash/bank ledger and credits the customer, then applies the allocations.</p>
            </section>
        </aside>
    </div>
</div>
