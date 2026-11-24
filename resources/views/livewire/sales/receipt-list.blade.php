<div class="space-y-6">
    <x-page-header title="Customer receipts" eyebrow="Money in" :sub="'Receipts recorded against the session company'">
        @can('receipts.create')
            <x-button :href="route('receipts.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New receipt</x-button>
        @endcan
    </x-page-header>

    <x-card class="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
    </x-card>

    <x-table caption="Customer receipts register">
        <thead>
            <tr>
                <th scope="col">Number</th>
                <th scope="col">Date</th>
                <th scope="col">Customer</th>
                <th scope="col">Reference</th>
                <th scope="col" class="text-right">Amount</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($receipts as $receipt)
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="font-mono text-xs text-slate-700">{{ $receipt->number ? $receipt->series.'/'.$receipt->number : '—' }}</td>
                    <td class="whitespace-nowrap text-slate-600">{{ $receipt->date->format('d-m-Y') }}</td>
                    <td class="max-w-[12rem] truncate font-medium text-slate-900">{{ $receipt->party?->name }}</td>
                    <td class="max-w-[10rem] truncate text-slate-500">{{ $receipt->payment_reference }}</td>
                    <td class="text-right font-mono tabular-nums"><x-money :value="$receipt->amount" class="font-medium"/></td>
                    <td><x-badge :status="$receipt->status"/></td>
                    <td class="whitespace-nowrap text-right">
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
    </x-table>

    <div>{{ $receipts->links() }}</div>
</div>
