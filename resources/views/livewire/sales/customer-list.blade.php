<div>
    <x-page-header title="Customers &amp; outstanding" eyebrow="Parties" :sub="'Customer balances from posted invoices and receipts'"/>

    <div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-card">
        <x-field label="Search customers" name="customer-search">
            <x-input name="customer-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Name or GSTIN" class="max-w-sm"/>
        </x-field>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Customer</th>
                        <th scope="col" class="px-5 py-3 font-semibold">GSTIN</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Receivable balance</th>
                        <th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $row)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $row['party']->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $row['party']->gstin ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <x-money :value="$row['balance']" class="font-medium {{ $row['owed'] ? 'text-slate-900' : 'text-green-700' }}"/>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('customers.show', $row['party']) }}" class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline">Outstanding</a>
                                <a href="{{ route('customers.statement', $row['party']) }}" class="ml-3 text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline">Statement</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state icon="users" title="No customers yet"
                                               message="Customers appear here once parties are added to this company."/>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
