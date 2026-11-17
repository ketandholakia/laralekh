<div>
    <x-page-header title="Supplier payments" eyebrow="Purchases" :sub="'Payments settling posted purchase bills'">
        @can('purchases.create')
            <x-button :href="route('supplier-payments.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New payment</x-button>
        @endcan
    </x-page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-card sm:grid-cols-2">
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

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Number</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Supplier</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Amount</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                            <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $payment->number ? $payment->series.'/'.$payment->number : '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $payment->date->format('d-m-Y') }}</td>
                            <td class="max-w-[14rem] truncate px-5 py-3 text-slate-800">{{ $payment->party?->name }}</td>
                            <td class="px-5 py-3 text-right"><x-money :value="$payment->amount" class="font-medium"/></td>
                            <td class="px-5 py-3"><x-badge :status="$payment->status"/></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
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
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</div>
