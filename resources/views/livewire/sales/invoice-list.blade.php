<div class="space-y-6">
    <x-page-header title="Sales invoices" eyebrow="Sales register" :sub="'Posted and draft invoices for the session company'">
        @can('invoices.create')
            <x-button :href="route('invoices.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New invoice</x-button>
        @endcan
    </x-page-header>

    <x-card class="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Search (number or party)" name="invoice-search">
                <x-input name="invoice-search" type="text" wire:model.live.debounce.300ms="search" placeholder="SI/1 or customer name"/>
            </x-field>
            <x-field label="Status" name="invoice-status">
                <x-select name="invoice-status" wire:model.live="status">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                </x-select>
            </x-field>
            <x-field label="From" name="invoice-from">
                <x-input name="invoice-from" type="date" wire:model.live="from"/>
            </x-field>
            <x-field label="To" name="invoice-to">
                <x-input name="invoice-to" type="date" wire:model.live="to"/>
            </x-field>
        </div>
    </x-card>

    <x-table caption="Sales invoices register">
        <thead>
            <tr>
                <th scope="col">Number</th>
                <th scope="col">Date</th>
                <th scope="col">Party</th>
                <th scope="col" class="text-right">Total</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="font-mono text-xs text-slate-700">{{ $invoice->number ? $invoice->series.'/'.$invoice->number : '—' }}</td>
                    <td class="whitespace-nowrap text-slate-600">{{ $invoice->date->format('d-m-Y') }}</td>
                    <td class="max-w-[14rem] truncate font-medium text-slate-900">{{ $invoice->party?->name }}</td>
                    <td class="text-right font-mono tabular-nums"><x-money :value="$invoice->total" class="font-medium"/></td>
                    <td><x-badge :status="$invoice->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ $invoice->status === 'draft' ? route('invoices.edit', $invoice) : route('invoices.show', $invoice) }}"
                           class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline">{{ $invoice->status === 'draft' ? 'Edit' : 'View' }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-0">
                        <x-empty-state icon="invoice" title="No invoices match the filters"
                                       message="Try clearing the search or date range, or create a new invoice."/>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div>{{ $invoices->links() }}</div>
</div>
