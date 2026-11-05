<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-semibold">Customer statement · {{ $party->name }}</h1>
            <p class="text-sm text-slate-500">As-of statement reconciled to the receivable ledger.</p>
        </div>
        <a href="{{ route('customers.show', $party) }}" class="text-sm text-slate-500 hover:underline">Back to outstanding</a>
    </div>

    <div class="mb-4 bg-white rounded-lg border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500">From</label>
            <input type="date" wire:model.live="from" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">As of</label>
            <input type="date" wire:model.live="asOf" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            @error('asOf')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        @if ($statement)
        <div class="sm:justify-self-end">
            <a href="{{ route('customers.statement-pdf', ['party' => $party, 'from' => $statement['from'], 'as_of' => $statement['as_of']]) }}"
               class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Download PDF</a>
        </div>
        @endif
    </div>

    @if ($statement)
        <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Description</th>
                        <th class="px-4 py-2 text-right">Debit</th><th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2 text-right">Balance</th></tr>
                </thead>
                <tbody>
                    <tr class="border-t border-slate-100 bg-slate-50/50">
                        <td class="px-4 py-2 font-medium">{{ \Illuminate\Support\Carbon::parse($statement['from'])->subDay()->format('d-m-Y') }}</td>
                        <td class="px-4 py-2 font-medium">Opening balance</td>
                        <td class="px-4 py-2"></td><td class="px-4 py-2"></td>
                        <td class="px-4 py-2 text-right font-mono font-medium">{{ $statement['opening'] }}</td>
                    </tr>
                    @foreach ($statement['rows'] as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                            <td class="px-4 py-2">{{ $row['description'] }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ $row['debit'] !== '0.00' ? $row['debit'] : '' }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ $row['credit'] !== '0.00' ? $row['credit'] : '' }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ $row['balance'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t-2 border-slate-300 font-semibold">
                        <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($statement['as_of'])->format('d-m-Y') }}</td>
                        <td class="px-4 py-2">Closing balance</td>
                        <td class="px-4 py-2"></td><td class="px-4 py-2"></td>
                        <td class="px-4 py-2 text-right font-mono">{{ $statement['closing'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            Ledger balance up to {{ \Illuminate\Support\Carbon::parse($statement['as_of'])->format('d-m-Y') }} (authoritative): ₹{{ $statement['ledger_as_of'] }}
            @if ((string) $statement['closing'] === (string) $statement['ledger_as_of'])
                <span class="text-green-700">· reconciled</span>
            @else
                <span class="text-red-600">· MISMATCH</span>
            @endif
        </p>
    @else
        <p class="text-sm text-slate-400">Choose a from date to build the statement.</p>
    @endif
</div>
