<div x-data>
    <x-page-header :title="$paymentId ? 'Edit payment draft' : 'New supplier payment'" eyebrow="Purchases"
                   :sub="'Settle posted purchase bills; posting creates the Dr supplier / Cr bank voucher'">
        <x-button :href="route('supplier-payments.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Supplier --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <h2 class="mb-3 text-sm font-semibold">Supplier</h2>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="partyQuery" placeholder="Search supplier by name or GSTIN"
                           class="w-full rounded-md border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                    @if (count($this->partyOptions) > 0)
                        <ul class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-pop">
                            @foreach ($this->partyOptions as $option)
                                <li><button type="button" wire:click="selectParty({{ $option['id'] }})"
                                        class="w-full px-3 py-2 text-left text-sm hover:bg-brand-50">
                                        {{ $option['name'] }}
                                        @if ($option['gstin'])<span class="font-mono text-xs text-slate-400"> · {{ $option['gstin'] }}</span>@endif
                                    </button></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @error('party')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @if ($partyId)<p class="mt-2 text-sm text-green-700">Selected: <strong>{{ $partyName }}</strong></p>@endif
            </section>

            {{-- Payment details --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <h2 class="mb-3 text-sm font-semibold">Payment</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-field label="Cash/bank ledger" name="ledger-select" :error="$errors->first('ledger')">
                        <x-select name="ledger-select" wire:model.live="ledgerId">
                            <option value="">— choose —</option>
                            @foreach ($this->cashLedgers as $ledger)
                                <option value="{{ $ledger->id }}" @selected($ledger->id === $ledgerId)>{{ $ledger->code }} · {{ $ledger->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Amount paid (₹)" name="amount" :error="$errors->first('amount')">
                        <x-input name="amount" type="text" wire:model.live="amount" placeholder="0.00"/>
                    </x-field>
                    <x-field label="Payment date" name="date" :error="$errors->first('date')">
                        <x-input name="date" type="date" wire:model.live="date"/>
                    </x-field>
                </div>
                <div wire:loading class="mt-2 text-xs text-slate-400">Updating…</div>
            </section>

            {{-- Allocations --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <h2 class="mb-3 text-sm font-semibold">Allocate to posted bills</h2>
                @if (count($this->eligibleBills) === 0)
                    <x-empty-state icon="cart" title="Nothing to settle"
                                   message="Select the supplier to see posted bills with outstanding balances, or the supplier has none outstanding."/>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-3 py-2 font-semibold">Bill</th>
                                <th scope="col" class="px-3 py-2 font-semibold">Date</th>
                                <th scope="col" class="px-3 py-2 text-right font-semibold">Payable</th>
                                <th scope="col" class="px-3 py-2 text-right font-semibold">Outstanding</th>
                                <th scope="col" class="px-3 py-2 text-right font-semibold">Pay now (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->eligibleBills as $bill)
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $bill['label'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $bill['date'] }}</td>
                                    <td class="px-3 py-2 text-right"><x-money :value="$bill['total']"/></td>
                                    <td class="px-3 py-2 text-right"><x-money :value="$bill['outstanding']" class="font-medium"/></td>
                                    <td class="px-3 py-2 text-right">
                                        <input type="text" wire:model.live="allocations.{{ $bill['id'] }}"
                                               class="w-28 rounded-md border-slate-300 text-right text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none"
                                               placeholder="0.00" aria-label="Pay now for bill {{ $bill['label'] }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-slate-500">
                        Allocated so far: <span class="font-medium tabular-nums">₹{{ $this->allocatedPreview }}</span>
                        — the payment amount must be fully allocated.
                    </p>
                @endif
            </section>
        </div>

        {{-- Preview + actions --}}
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <h2 class="mb-3 text-sm font-semibold">Posting preview</h2>
                @if ($this->preview['error'])
                    <p class="text-sm text-amber-700">{{ $this->preview['error'] }}</p>
                @elseif (count($this->preview['entries']) === 0)
                    <p class="text-sm text-slate-500">Select a supplier and cash/bank ledger to preview the voucher entries.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="pb-2">Ledger</th><th class="pb-2 text-right">Debit</th><th class="pb-2 text-right">Credit</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($this->preview['entries'] as $entry)
                                <tr class="border-t border-slate-100">
                                    <td class="py-2 text-slate-700">{{ $entry['ledger'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $entry['debit'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $entry['credit'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <div class="flex flex-col gap-2">
                    <x-button wire:click="save" variant="secondary" class="w-full">Save draft</x-button>
                    @can('purchases.post')
                        <x-button wire:click="post" variant="primary" class="w-full">Save &amp; post payment</x-button>
                    @else
                        <p class="text-xs text-slate-500">Posting requires the <span class="font-mono">purchases.post</span> permission; you can still save drafts.</p>
                    @endcan
                </div>
            </section>
        </div>
    </div>
</div>
