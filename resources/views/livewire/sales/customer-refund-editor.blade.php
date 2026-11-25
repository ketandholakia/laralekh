<div class="space-y-6">
    <x-page-header
        :title="$refundId ? 'Edit customer refund draft' : 'Issue customer credit refund'"
        eyebrow="Sales · Customer Settlements"
        sub="Disburse excess customer credits or unapplied advances; posting creates the Dr customer / Cr bank voucher"
    >
        @if ($partyId)
            <x-button :href="route('customers.show', $partyId)" variant="ghost">Customer outstanding</x-button>
        @endif
    </x-page-header>

    @error('allocations')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Customer Card --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Customer</span>
                        <span class="mt-1 block text-base font-bold text-slate-900">{{ $partyName }}</span>
                    </div>
                    @if ($partyId)
                        <x-button :href="route('customers.statement', $partyId)" variant="secondary" size="sm">Statement</x-button>
                    @endif
                </div>
            </x-card>

            {{-- Payment Details --}}
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Refund Disbursement Details</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Disbursement account (Cash/Bank)" name="ledger-select">
                        <x-select name="ledger-select" wire:model.live="ledgerId">
                            <option value="0">— choose account —</option>
                            @foreach ($cashLedgers as $ledger)
                                <option value="{{ $ledger->id }}" @selected($ledger->id === $ledgerId)>{{ $ledger->code }} · {{ $ledger->name }}</option>
                            @endforeach
                        </x-select>
                        @error('ledgerId')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Refund date" name="date">
                        <x-input name="date" type="date" wire:model.live="date" />
                        @error('date')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Payment reference (UTR / Cheque no.)" name="payment-ref">
                        <x-input name="payment-ref" type="text" wire:model="paymentReference" placeholder="e.g. UTR12345678" />
                        @error('paymentReference')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Narration / Reason" name="narration">
                        <x-input name="narration" type="text" wire:model="narration" placeholder="Reason for refund" />
                        @error('narration')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>
                </div>
            </x-card>

            {{-- Refundable Credits Selection --}}
            <x-card class="space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Select Available Credits to Refund</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Specify the amount to refund against unapplied receipt advances or negative invoice balances.</p>
                </div>

                @if (count($refundableItems) > 0)
                    <x-table caption="Refundable customer credit sources">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Document</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Available Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 w-44">Refund Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($refundableItems as $item)
                                <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                                    <td class="px-4 py-3 text-sm font-medium text-slate-900">
                                        {{ $item['label'] }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <x-badge :variant="$item['type'] === 'receipt' ? 'info' : 'warning'">
                                            {{ $item['tag'] }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ $item['date'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-emerald-700">
                                        ₹{{ $item['available'] }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="allocations.{{ $item['key'] }}"
                                            placeholder="0.00"
                                            class="w-full rounded border-slate-300 text-right font-mono text-sm py-1.5 px-2 focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @else
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        This customer currently has no unapplied receipt advances or negative invoice excess credits available to refund.
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Sidebar Summary & Actions --}}
        <div class="space-y-6">
            <x-card class="space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Refund Summary</h3>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Customer:</span>
                        <span class="font-medium text-slate-900">{{ $partyName }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Date:</span>
                        <span class="font-medium text-slate-900">{{ $date }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Disbursement:</span>
                        <span class="font-medium text-slate-900">
                            {{ collect($cashLedgers)->firstWhere('id', $ledgerId)?->name ?? 'None' }}
                        </span>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-3">
                    <span class="block text-xs uppercase tracking-wider text-slate-500 font-semibold">Total Refund Amount</span>
                    <span class="mt-1 block font-mono text-3xl font-bold text-slate-900">₹{{ $totalRefund }}</span>
                </div>

                <div class="pt-2 space-y-2">
                    <x-button wire:click="postRefund" variant="primary" class="w-full justify-center" icon="check">
                        Post Refund
                    </x-button>
                    <x-button wire:click="saveDraft" variant="secondary" class="w-full justify-center">
                        Save Draft
                    </x-button>
                    @if ($partyId)
                        <x-button :href="route('customers.show', $partyId)" variant="ghost" class="w-full justify-center">
                            Cancel
                        </x-button>
                    @endif
                </div>

                <div class="mt-3 rounded border border-slate-100 bg-slate-50 p-3 text-xs text-slate-500">
                    <p class="font-semibold text-slate-700 mb-1">Double-Entry Accounting Effect:</p>
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li><strong>Dr.</strong> Customer Receivable (Sundry Debtors)</li>
                        <li><strong>Cr.</strong> Cash / Bank Account</li>
                    </ul>
                    <p class="mt-1">Reduces customer credit and available advances without altering historical documents.</p>
                </div>
            </x-card>
        </div>
    </div>
</div>
