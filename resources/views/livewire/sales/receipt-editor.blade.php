<div x-data>
    <x-page-header
        :title="$receiptId ? 'Edit receipt draft' : 'New customer receipt'"
        eyebrow="Sales · Receipts"
        :sub="'Record payment from customer; posting creates the Dr bank / Cr customer voucher'"
    >
        <x-button :href="route('receipts.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Customer --}}
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Customer</h2>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="partyQuery" placeholder="Search customer by name or GSTIN"
                           class="w-full rounded-md border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                    @if (count($this->partyOptions) > 0)
                        <ul class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-pop">
                            @foreach ($this->partyOptions as $option)
                                <li>
                                    <button type="button" wire:click="selectParty({{ $option['id'] }})"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-50">
                                        <span class="font-medium text-slate-900">{{ $option['name'] }}</span>
                                        @if ($option['gstin'])<span class="font-mono text-xs text-slate-500">{{ $option['gstin'] }}</span>@endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($partyId)
                    <div class="mt-2.5 flex items-center justify-between rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900">
                        <span>Selected: <strong>{{ $partyName }}</strong></span>
                        <button type="button" wire:click="$set('partyId', null); $set('partyQuery', '')" class="text-xs text-slate-500 hover:text-slate-800 hover:underline">(change)</button>
                    </div>
                @endif
                @error('amount')<x-alert type="danger" class="mt-3">{{ $message }}</x-alert>@enderror
            </x-card>

            {{-- Payment --}}
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Payment</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Cash/bank ledger" name="ledger-select">
                        <x-select name="ledger-select" wire:model.live="ledgerId">
                            <option value="">— choose —</option>
                            @foreach ($this->cashLedgers as $ledger)
                                <option value="{{ $ledger->id }}" @selected($ledger->id === $ledgerId)>{{ $ledger->code }} · {{ $ledger->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Amount received (₹)" name="amount">
                        <x-input name="amount" type="text" wire:model.live="amount" placeholder="0.00" />
                    </x-field>
                    <x-field label="Receipt date" name="date">
                        <x-input name="date" type="date" wire:model.live="date" />
                    </x-field>
                    <x-field label="Payment reference (UTR/cheque no.)" name="payment-ref">
                        <x-input name="payment-ref" type="text" wire:model="paymentReference" placeholder="UTR or cheque number" />
                    </x-field>
                    <div class="sm:col-span-2">
                        <x-field label="Narration" name="narration">
                            <x-input name="narration" type="text" wire:model="narration" placeholder="Optional payment narration" />
                        </x-field>
                    </div>
                </div>
                <div wire:loading wire:target="ledgerId,amount,date" class="text-xs text-slate-400">Updating…</div>
            </x-card>

            {{-- Allocations --}}
            <x-card class="space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Allocate to open invoices (optional)</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Funds not allocated stay with the customer as an unapplied advance and can be allocated later.</p>
                </div>
                @if (count($this->eligibleInvoices) > 0)
                    <x-table caption="Eligible invoices for allocation">
                        <thead>
                            <tr>
                                <th scope="col">Invoice</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col" class="text-right">Outstanding</th>
                                <th scope="col" class="text-right">Allocate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->eligibleInvoices as $invoice)
                                <tr>
                                    <td class="font-mono text-xs font-medium text-slate-900">{{ $invoice['label'] }}</td>
                                    <td class="text-sm text-slate-600">{{ $invoice['date'] }}</td>
                                    <td class="text-right font-mono tabular-nums text-sm text-slate-700"><x-money :value="$invoice['total']" /></td>
                                    <td class="text-right font-mono tabular-nums text-sm font-medium text-slate-900"><x-money :value="$invoice['outstanding']" /></td>
                                    <td class="text-right">
                                        <input type="text" wire:model.live="allocations.{{ $invoice['id'] }}"
                                               class="w-28 rounded-md border-slate-300 px-2.5 py-1 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                               placeholder="0.00" aria-label="Allocate for invoice {{ $invoice['label'] }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @else
                    <p class="text-sm text-slate-400">No open invoices for this customer yet.</p>
                @endif
            </x-card>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-6">
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Posting preview</h2>
                @if ($this->preview['error'])
                    <x-alert type="warning">{{ $this->preview['error'] }}</x-alert>
                @elseif (count($this->preview['entries']) === 0)
                    <p class="text-sm text-slate-400">Select customer and ledger to preview entries.</p>
                @else
                    <x-table caption="Posting journal entries">
                        <thead>
                            <tr>
                                <th scope="col" class="text-right">Debit</th>
                                <th scope="col" class="text-right">Credit</th>
                                <th scope="col">Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->preview['entries'] as $entry)
                                <tr>
                                    <td class="text-right font-mono tabular-nums text-sm text-slate-900">₹{{ $entry['debit'] }}</td>
                                    <td class="text-right font-mono tabular-nums text-sm text-slate-900">₹{{ $entry['credit'] }}</td>
                                    <td class="text-sm text-slate-700">{{ $entry['ledger'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
                <div class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-600 space-y-1">
                    <div class="flex justify-between"><span>Allocated so far:</span><span class="font-mono tabular-nums font-medium text-slate-900">₹{{ $this->allocatedPreview }}</span></div>
                    <div class="flex justify-between"><span>Unapplied advance:</span><span class="font-mono tabular-nums font-medium text-brand-700">₹{{ \App\Domain\Shared\ValueObjects\Money::of(bcsub((string) ($this->amount !== '' && is_numeric($this->amount) ? $this->amount : '0'), $this->allocatedPreview, 2)) }}</span></div>
                </div>
            </x-card>

            <x-card class="space-y-3">
                <x-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                          variant="secondary" class="w-full">
                    Save draft
                </x-button>
                @can('receipts.post')
                    <x-button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post"
                              wire:confirm="Post this receipt? The number is allocated and the entries become final."
                              variant="primary" class="w-full">
                        Post receipt
                    </x-button>
                @endcan
                <p class="text-xs text-slate-400 text-center">Posting debits the cash/bank ledger and credits the customer, then applies the allocations.</p>
            </x-card>
        </aside>
    </div>
</div>
