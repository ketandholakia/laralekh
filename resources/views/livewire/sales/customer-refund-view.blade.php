<div class="space-y-6">
    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif
    @error('error')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <x-page-header
        :title="$refund->label()"
        eyebrow="Sales · Customer Refunds"
        :sub="'Refund disbursement · ' . $refund->date->format('d-m-Y')"
    >
        <x-slot name="actions">
            @if ($refund->status === 'draft')
                @can('receipts.create')
                    <x-button :href="route('customer-refunds.edit', $refund->id)" variant="secondary">Edit Draft</x-button>
                @endcan
                @can('receipts.post')
                    <x-button wire:click="postRefund" variant="primary" icon="check">Post Refund</x-button>
                @endcan
            @elseif (! $isReversed)
                @can('receipts.reverse')
                    <x-button wire:click="$set('showReverseModal', true)" variant="danger">Reverse Refund</x-button>
                @endcan
            @endif
            <x-button :href="route('customers.show', $refund->party_id)" variant="ghost">Customer outstanding</x-button>
        </x-slot>
    </x-page-header>

    @if ($isReversed)
        @php $rev = $refund->reversals->first(); @endphp
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div class="font-bold">This customer refund has been reversed.</div>
            <p class="mt-1">
                Reversal Voucher: <strong>{{ $rev->voucher ? $rev->voucher->series.'/'.$rev->voucher->number : 'Posted' }}</strong>
                on <strong>{{ $rev->date->format('d-m-Y') }}</strong>.
                Reason: {{ $rev->reason }}
            </p>
            <p class="mt-1 text-xs text-red-700">The refunded advance / credit amounts have been restored to the customer's balance.</p>
        </div>
    @endif

    {{-- Details Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Customer</span>
            <span class="mt-1 block text-base font-bold text-slate-900">{{ $refund->party->name }}</span>
            <span class="block text-xs text-slate-500 font-mono">{{ $refund->party->gstin ?? 'Unregistered' }}</span>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Refund Amount</span>
            <span class="mt-1 block font-mono text-2xl font-bold text-slate-900">₹{{ $refund->amount }}</span>
            <span class="block text-xs text-slate-500">Status: <span class="font-semibold uppercase {{ $refund->status === 'posted' ? 'text-emerald-700' : 'text-slate-600' }}">{{ $isReversed ? 'REVERSED' : $refund->status }}</span></span>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Disbursement Account</span>
            <span class="mt-1 block text-sm font-semibold text-slate-900">{{ $refund->ledger->name }}</span>
            <span class="block text-xs text-slate-500 font-mono">{{ $refund->ledger->code }}</span>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Payment Reference</span>
            <span class="mt-1 block text-sm font-mono font-medium text-slate-900">{{ $refund->payment_reference ?? '—' }}</span>
            @if ($refund->voucher)
                <span class="block text-xs text-slate-500">Voucher: <span class="font-mono font-semibold">{{ $refund->voucher->series }}/{{ $refund->voucher->number }}</span></span>
            @endif
        </div>
    </div>

    @if ($refund->narration)
        <x-card class="p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Narration</span>
            <p class="mt-1 text-sm text-slate-700">{{ $refund->narration }}</p>
        </x-card>
    @endif

    {{-- Allocations Card --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-900 mb-3">Refund Allocations</h2>
        <x-table caption="Refunded credits and advances">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Source Document</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Document Date</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Document Total</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Refunded Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($refund->allocations as $alloc)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">
                            @if ($alloc->allocation_type === 'receipt' && $alloc->receipt)
                                Receipt {{ $alloc->receipt->label() }}
                            @elseif ($alloc->allocation_type === 'invoice' && $alloc->invoice)
                                Invoice {{ $alloc->invoice->series }}/{{ $alloc->invoice->number }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <x-badge :variant="$alloc->allocation_type === 'receipt' ? 'info' : 'warning'">
                                {{ $alloc->allocation_type === 'receipt' ? 'Unapplied Advance' : 'Excess Credit' }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            @if ($alloc->allocation_type === 'receipt' && $alloc->receipt)
                                {{ $alloc->receipt->date->format('d-m-Y') }}
                            @elseif ($alloc->allocation_type === 'invoice' && $alloc->invoice)
                                {{ $alloc->invoice->date->format('d-m-Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                            @if ($alloc->allocation_type === 'receipt' && $alloc->receipt)
                                ₹{{ $alloc->receipt->amount }}
                            @elseif ($alloc->allocation_type === 'invoice' && $alloc->invoice)
                                ₹{{ $alloc->invoice->total }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900">
                            ₹{{ $alloc->amount }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>

    {{-- Reversal Modal --}}
    @if ($showReverseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl space-y-4">
                <h3 class="text-lg font-bold text-slate-900">Reverse Customer Refund</h3>
                <p class="text-sm text-slate-600">
                    Reversing this refund will post a balanced reversal voucher (Dr. Cash/Bank, Cr. Customer Receivable)
                    and restore the refunded amounts to the customer's available advances and excess credits.
                </p>

                <div class="space-y-4">
                    <x-field label="Reversal date" name="reversalDate">
                        <x-input name="reversalDate" type="date" wire:model="reversalDate" />
                        @error('reversalDate')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Reversal reason (minimum 5 characters)" name="reversalReason">
                        <x-input name="reversalReason" type="text" wire:model="reversalReason" placeholder="State reason for reversal" />
                        @error('reversalReason')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <x-button wire:click="$set('showReverseModal', false)" variant="secondary">Cancel</x-button>
                    <x-button wire:click="reverseRefund" variant="danger">Confirm Reversal</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
