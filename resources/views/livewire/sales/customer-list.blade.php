<div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <h1 class="text-xl font-semibold">Customers &amp; outstanding</h1>
    </div>
    <div class="mb-4 bg-white rounded-lg border border-slate-200 p-4">
        <label class="block text-xs font-medium text-slate-500">Search</label>
        <input type="text" wire:model.live.debounce.300ms="search" class="mt-1 w-full max-w-sm rounded border border-slate-300 px-2 py-1.5 text-sm">
    </div>
    <div class="bg-white rounded-lg border border-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-2">Customer</th><th class="px-4 py-2">GSTIN</th>
                    <th class="px-4 py-2 text-right">Receivable balance</th><th class="px-4 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $row)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $row['party']->name }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $row['party']->gstin ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-mono {{ $row['owed'] ? '' : 'text-green-700' }}">₹{{ $row['balance'] }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('customers.show', $row['party']) }}" class="text-indigo-600 hover:underline">Outstanding</a>
                            <a href="{{ route('customers.statement', $row['party']) }}" class="ml-2 text-indigo-600 hover:underline">Statement</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
