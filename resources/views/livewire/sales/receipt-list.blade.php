<div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <h1 class="text-xl font-semibold">Customer receipts</h1>
        @can('receipts.create')
        <a href="{{ route('receipts.create') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">New receipt</a>
        @endcan
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4 bg-white rounded-lg border border-slate-200 p-4">
        <div>
            <label class="block text-xs font-medium text-slate-500">Search (number, reference or customer)</label>
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Status</label>
            <select wire:model.live="status" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">All</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">From</label>
            <input type="date" wire:model.live="from" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">To</label>
            <input type="date" wire:model.live="to" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-2">Number</th>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Customer</th>
                    <th class="px-4 py-2">Reference</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipts as $receipt)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 font-mono">{{ $receipt->number ? $receipt->series.'/'.$receipt->number : '—' }}</td>
                        <td class="px-4 py-2">{{ $receipt->date->format('d-m-Y') }}</td>
                        <td class="px-4 py-2">{{ $receipt->party?->name }}</td>
                        <td class="px-4 py-2">{{ $receipt->payment_reference }}</td>
                        <td class="px-4 py-2 text-right font-mono">₹{{ $receipt->amount }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $receipt->status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ ucfirst($receipt->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ $receipt->status === 'draft' ? route('receipts.edit', $receipt) : route('receipts.show', $receipt) }}"
                               class="text-indigo-600 hover:underline">{{ $receipt->status === 'draft' ? 'Edit' : 'View' }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No receipts match the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $receipts->links() }}</div>
</div>
