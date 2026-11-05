<div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <h1 class="text-xl font-semibold">Sales invoices</h1>
        @can('invoices.create')
        <a href="{{ route('invoices.create') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">New invoice</a>
        @endcan
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4 bg-white rounded-lg border border-slate-200 p-4">
        <div>
            <label class="block text-xs font-medium text-slate-500">Search (number or party)</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="SI/1 or customer name"
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
                    <th class="px-4 py-2">Party</th>
                    <th class="px-4 py-2 text-right">Total</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 font-mono">{{ $invoice->number ? $invoice->series.'/'.$invoice->number : '—' }}</td>
                        <td class="px-4 py-2">{{ $invoice->date->format('d-m-Y') }}</td>
                        <td class="px-4 py-2">{{ $invoice->party?->name }}</td>
                        <td class="px-4 py-2 text-right font-mono">₹{{ $invoice->total }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $invoice->status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ $invoice->status === 'draft' ? route('invoices.edit', $invoice) : route('invoices.show', $invoice) }}"
                               class="text-indigo-600 hover:underline">{{ $invoice->status === 'draft' ? 'Edit' : 'View' }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No invoices match the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</div>
