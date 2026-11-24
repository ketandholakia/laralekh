<div class="space-y-6">
    <x-page-header
        :title="$summary['party']->name"
        eyebrow="Sales · Customer outstanding"
        :sub="'Customer outstanding · ' . ($summary['party']->gstin ?? 'unregistered')"
    >
        <x-slot name="actions">
            @if ($summary['excess_credits']->isPositive() || $summary['advances']->isPositive())
                @can('receipts.create')
                    <x-button :href="route('customers.refunds.create', $summary['party'])" variant="primary" icon="wallet">Refund credit</x-button>
                @endcan
            @endif
            <x-button :href="route('customers.statement', $summary['party'])" variant="secondary" icon="swap">Statement</x-button>
            <x-button :href="route('customers.index')" variant="ghost">All customers</x-button>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Open invoices balance</span>
            <span class="mt-2 block font-mono text-2xl font-bold text-slate-900">₹{{ $summary['invoices_balance'] }}</span>
        </div>

        <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-5 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-800">Unapplied advances</span>
            <span class="mt-2 block font-mono text-2xl font-bold text-emerald-900">₹{{ $summary['advances'] }}</span>
            <p class="mt-2 text-xs text-emerald-700">Receipts held on account; allocation is a separate authorised action. Advance GST treatment is not computed here.</p>
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-5 shadow-card">
            <span class="block text-xs font-semibold uppercase tracking-wider text-amber-800">Excess customer credits</span>
            <span class="mt-2 block font-mono text-2xl font-bold text-amber-900">₹{{ $summary['excess_credits'] }}</span>
            <p class="mt-2 text-xs text-amber-700">Credits exceed what was owed on those documents; refundable to the customer.</p>
        </div>
    </div>

    <x-card>
        <x-table caption="Customer settlement documents">
            <x-slot name="head">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Document</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Outstanding</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @forelse ($summary['documents'] as $document)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">
                            {{ $document['label'] }}
                            @if ($document['reversed'])
                                <span class="ml-1 text-xs text-slate-400 font-normal">(reversed)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ \Illuminate\Support\Carbon::parse($document['date'])->format('d-m-Y') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                            {{ $document['total'] }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm font-medium {{ str_starts_with($document['outstanding'], '-') ? 'text-amber-700' : 'text-slate-900' }}">
                            {{ $document['outstanding'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No settlement documents.</td>
                    </tr>
                @endforelse
            </x-slot>
        </x-table>

        <div class="mt-4 border-t border-slate-100 pt-3">
            <p class="text-xs text-slate-500">
                Receivable ledger balance (authoritative): <span class="font-mono font-semibold text-slate-900">₹{{ $summary['ledger_balance'] }}</span>
            </p>
        </div>
    </x-card>
</div>
