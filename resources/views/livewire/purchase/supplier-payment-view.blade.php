<div>
    <x-page-header :eyebrow="'Supplier payment · '.$payment->party->name"
                   :title="$payment->number ? $payment->series.'/'.$payment->number : 'Draft payment'"
                   :sub="$payment->status === 'posted' ? 'Posted payment voucher' : 'Draft — not yet posted'">
        <x-badge :status="$payment->status"/>
    </x-page-header>

    @if ($reversal)
        <x-alert type="warning" class="mb-4">
            This payment has been reversed
            @if ($reversal->voucher) by voucher {{ $reversal->voucher->series }}/{{ $reversal->voucher->number }}@endif.
            Reason: {{ $reversal->reason }}
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold">Details</h2>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><dt class="text-xs font-medium text-slate-500">Supplier</dt><dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $payment->party->name }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Payment date</dt><dd class="mt-0.5 text-sm text-slate-700">{{ $payment->date->format('d-m-Y') }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Amount</dt><dd class="mt-0.5 text-sm"><x-money :value="$payment->amount" class="font-semibold"/></dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Paid from</dt><dd class="mt-0.5 text-sm text-slate-700">{{ $payment->ledger->code }} · {{ $payment->ledger->name }}</dd></div>
            </dl>

            <h2 class="mb-2 mt-6 text-sm font-semibold">Allocated bills</h2>
            @if ($payment->allocations->isEmpty())
                <p class="text-sm text-slate-500">No allocations recorded on this payment.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th scope="col" class="px-4 py-2 font-semibold">Bill</th><th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($payment->allocations as $allocation)
                                <tr class="border-t border-slate-100">
                                    <td class="px-4 py-2 font-mono text-xs">{{ $allocation->purchaseBill->supplier_bill_number }}</td>
                                    <td class="px-4 py-2 text-right"><x-money :value="$allocation->amount"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
