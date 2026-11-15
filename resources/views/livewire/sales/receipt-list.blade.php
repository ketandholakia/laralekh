<div>
    <x-page-header title="Customer receipts" eyebrow="Money in" :sub="'Receipts recorded against the session company'">
        @can('receipts.create')
            <x-button :href="route('receipts.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New receipt</x-button>
        @endcan
    </x-page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-card sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Search (number, reference or customer)" name="receipt-search">
            <x-input name="receipt-search" type="text" wire:model.live.debounce.300ms="search"/>
        </x-field>
        <x-field label="Status" name="receipt-status">
            <x-select name="receipt-status" wire:model.live="status">
                <option value="">All</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
            </x-select>
        </x-field>
        <x-field label="From" name="receipt-from">
            <x-input name="receipt-from" type="date" wire:model.live="from"/>
        </x-field>
        <x-field label="To" name="receipt-to">
            <x-input name="receipt-to" type="date" wire:model.live="to"/>
        </x-field>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Number</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Customer</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Reference</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Amount</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                            <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $receipt->number ? $receipt->series.'/'.$receipt->number : '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $receipt->date->format('d-m-Y') }}</td>
                            <td class="max-w-[12rem] truncate px-5 py-3 text-slate-800">{{ $receipt->party?->name }}</td>
                            <td class="max-w-[10rem] truncate px-5 py-3 text-slate-500">{{ $receipt->payment_reference }}</td>
                            <td class="px-5 py-3 text-right"><x-money :value="$receipt->amount" class="font-medium"/></td>
                            <td class="px-5 py-3"><x-badge :status="$receipt->status"/></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ $receipt->status === 'draft' ? route('receipts.edit', $receipt) : route('receipts.show', $receipt) }}"
                                   class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline">{{ $receipt->status === 'draft' ? 'Edit' : 'View' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state icon="receipt" title="No receipts match the filters"
                                               message="Try clearing the search or date range, or record a new receipt."/>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $receipts->links() }}</div>
</div>
