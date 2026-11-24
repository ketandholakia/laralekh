<div class="space-y-6">
    <x-page-header title="Supplier payments" eyebrow="Purchases" :sub="'Payments settling posted purchase bills'">
        @can('purchases.create')
            <x-button :href="route('supplier-payments.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New payment</x-button>
        @endcan
    </x-page-header>

    <x-card class="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-field label="Search (number or supplier)" name="payment-search">
                <x-input name="payment-search" type="text" wire:model.live.debounce.300ms="search"/>
            </x-field>
            <x-field label="Status" name="payment-status">
                <x-select name="payment-status" wire:model.live="status">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                </x-select>
            </x-field>
        </div>
    </x-card>

    <x-table caption="Supplier payments register">
        <thead>
            <tr>
                <th scope="col">Number</th>
                <th scope="col">Date</th>
                <th scope="col">Supplier</th>
                <th scope="col" class="text-right">Amount</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="font-mono text-xs text-slate-700">{{ $payment->number ? $payment->series.'/'.$payment->number : '—' }}</td>
                    <td class="whitespace-nowrap text-slate-600">{{ $payment->date->format('d-m-Y') }}</td>
                    <td class="max-w-[14rem] truncate font-medium text-slate-900">{{ $payment->party?->name }}</td>
                    <td class="text-right font-mono tabular-nums"><x-money :value="$payment->amount" class="font-medium"/></td>
                    <td><x-badge :status="$payment->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ $payment->status === 'draft' ? route('supplier-payments.create') : route('supplier-payments.show', $payment) }}"
                           class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline">{{ $payment->status === 'draft' ? 'Edit' : 'View' }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-0">
                        <x-empty-state icon="wallet" title="No supplier payments yet"
                                       message="Payments recorded here settle posted purchase bills."/>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div>{{ $payments->links() }}</div>
</div>
