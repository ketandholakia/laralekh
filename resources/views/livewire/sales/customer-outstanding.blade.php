<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $summary['party']->name }}</h1>
            <p class="text-sm text-slate-500">Customer outstanding · {{ $summary['party']->gstin ?? 'unregistered' }}</p>
        </div>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('customers.statement', $summary['party']) }}" class="rounded border border-slate-300 px-4 py-2 hover:bg-slate-50">Statement</a>
            <a href="{{ route('customers.index') }}" class="text-slate-500 self-center hover:underline">All customers</a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-lg border border-slate-200 p-4">
            <p class="text-xs uppercase text-slate-500">Open invoices balance</p>
            <p class="text-lg font-semibold font-mono">₹{{ $summary['invoices_balance'] }}</p>
        </div>
        <div class="bg-white rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase text-emerald-700">Unapplied advances</p>
            <p class="text-lg font-semibold font-mono">₹{{ $summary['advances'] }}</p>
            <p class="text-xs text-emerald-700 mt-1">Receipts held on account; allocation is a separate authorised action. Advance GST treatment is not computed here.</p>
        </div>
        <div class="bg-white rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase text-amber-700">Excess customer credits</p>
            <p class="text-lg font-semibold font-mono">₹{{ $summary['excess_credits'] }}</p>
            <p class="text-xs text-amber-700 mt-1">Credits exceed what was owed on those documents; refundable to the customer.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-2">Document</th><th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Outstanding</th></tr>
            </thead>
            <tbody>
                @forelse ($summary['documents'] as $document)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">
                            {{ $document['label'] }}
                            @if ($document['reversed'])<span class="text-xs text-slate-400">(reversed)</span>@endif
                        </td>
                        <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($document['date'])->format('d-m-Y') }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ $document['total'] }}</td>
                        <td class="px-4 py-2 text-right font-mono {{ str_starts_with($document['outstanding'], '-') ? 'text-amber-700' : '' }}">{{ $document['outstanding'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No settlement documents.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Receivable ledger balance (authoritative): ₹{{ $summary['ledger_balance'] }}</p>
</div>
