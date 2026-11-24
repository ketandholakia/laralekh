<div class="space-y-6">
    <x-page-header title="Purchase bills" eyebrow="Purchases" :sub="'Supplier bills recorded for input tax credit'">
        @can('purchases.create')
            <x-button :href="route('purchases.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New bill</x-button>
        @endcan
    </x-page-header>

    <x-card class="p-4">
        <x-field label="Search supplier bill number" name="purchase-search">
            <x-input name="purchase-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Supplier bill #" class="max-w-sm"/>
        </x-field>
    </x-card>

    <x-table caption="Purchase bills register">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Supplier</th>
                <th scope="col">Supplier bill #</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bills as $bill)
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="whitespace-nowrap text-slate-600">{{ $bill->date->format('d-m-Y') }}</td>
                    <td class="max-w-[14rem] truncate font-medium text-slate-900">{{ $bill->party->name }}</td>
                    <td class="font-mono text-xs">
                        <a href="{{ route('purchases.show', $bill) }}" class="font-medium text-brand-700 hover:text-brand-800 hover:underline">{{ $bill->supplier_bill_number }}</a>
                    </td>
                    <td><x-badge :status="$bill->status"/></td>
                    <td class="text-right font-mono tabular-nums"><x-money :value="$bill->total" class="font-medium"/></td>
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
    </x-table>

    <div>{{ $bills->links() }}</div>
</div>
