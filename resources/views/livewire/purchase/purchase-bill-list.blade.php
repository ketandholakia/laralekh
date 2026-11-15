<div>
    <x-page-header title="Purchase bills" eyebrow="Purchases" :sub="'Supplier bills recorded for input tax credit'">
        @can('purchases.create')
            <x-button :href="route('purchases.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New bill</x-button>
        @endcan
    </x-page-header>

    <div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-card">
        <x-field label="Search supplier bill number" name="purchase-search">
            <x-input name="purchase-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Supplier bill #" class="max-w-sm"/>
        </x-field>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Supplier</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Supplier bill #</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $bill->date->format('d-m-Y') }}</td>
                            <td class="max-w-[14rem] truncate px-5 py-3 text-slate-800">{{ $bill->party->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs">
                                <a href="{{ route('purchases.show', $bill) }}" class="text-brand-700 hover:text-brand-800 hover:underline">{{ $bill->supplier_bill_number }}</a>
                            </td>
                            <td class="px-5 py-3"><x-badge :status="$bill->status"/></td>
                            <td class="px-5 py-3 text-right"><x-money :value="$bill->total" class="font-medium"/></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state icon="cart" title="No purchase bills yet"
                                               message="Bills entered here feed input tax credit and the purchase register."/>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $bills->links() }}</div>
</div>
