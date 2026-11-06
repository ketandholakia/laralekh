<div class="mx-auto max-w-6xl p-6">
    <div class="mb-6 flex items-center justify-between"><h1 class="text-2xl font-semibold">Purchase bills</h1><a class="rounded bg-slate-900 px-4 py-2 text-white" href="{{ route('purchases.create') }}">New bill</a></div>
    <input class="mb-4 rounded border p-2" wire:model.live="search" placeholder="Supplier bill number">
    <div class="overflow-x-auto rounded border bg-white"><table class="w-full text-left"><thead><tr><th class="p-3">Date</th><th>Supplier</th><th>Supplier bill #</th><th>Status</th><th>Total</th></tr></thead><tbody>
    @forelse($bills as $bill)<tr class="border-t"><td class="p-3">{{ $bill->date->format('d-m-Y') }}</td><td>{{ $bill->party->name }}</td><td><a class="underline" href="{{ route('purchases.show', $bill) }}">{{ $bill->supplier_bill_number }}</a></td><td>{{ ucfirst($bill->status) }}</td><td>{{ $bill->total }}</td></tr>@empty<tr><td colspan="5" class="p-4">No purchase bills found.</td></tr>@endforelse
    </tbody></table></div>{{ $bills->links() }}
</div>
