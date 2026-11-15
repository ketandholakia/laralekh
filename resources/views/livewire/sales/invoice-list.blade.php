<div>
    <x-page-header title="Sales invoices" eyebrow="Sales register" :sub="'Posted and draft invoices for the session company'">
        @can('invoices.create')
            <x-button :href="route('invoices.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New invoice</x-button>
        @endcan
    </x-page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-card sm:grid-cols-2 lg:grid-cols-4">
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

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Number</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Party</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Total</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                            <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $invoice->number ? $invoice->series.'/'.$invoice->number : '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $invoice->date->format('d-m-Y') }}</td>
                            <td class="max-w-[14rem] truncate px-5 py-3 text-slate-800">{{ $invoice->party?->name }}</td>
                            <td class="px-5 py-3 text-right"><x-money :value="$invoice->total" class="font-medium"/></td>
                            <td class="px-5 py-3"><x-badge :status="$invoice->status"/></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
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
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</div>
