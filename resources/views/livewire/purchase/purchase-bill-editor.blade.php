<div class="mx-auto max-w-5xl p-6">
    <h1 class="mb-6 text-2xl font-semibold">{{ $billId ? 'Edit purchase bill draft' : 'New purchase bill' }}</h1>
    @if (session('status'))<p class="mb-4 text-green-700">{{ session('status') }}</p>@endif
    <div class="grid gap-4 md:grid-cols-2">
        <label>Supplier<select class="mt-1 w-full rounded border p-2" wire:model="data.party_id"><option value="">Select supplier</option>@foreach($parties as $party)<option value="{{ $party->id }}">{{ $party->name }}</option>@endforeach</select></label>
        <label>Supplier bill number<input class="mt-1 w-full rounded border p-2" wire:model="data.supplier_bill_number"></label>
        <label>Supplier bill date<input type="date" class="mt-1 w-full rounded border p-2" wire:model="data.supplier_bill_date"></label>
        <label>Posting/tax date<input type="date" class="mt-1 w-full rounded border p-2" wire:model="data.date"></label>
        <label class="flex gap-2"><input type="checkbox" wire:model="data.reverse_charge"> Reverse charge (professional review required)</label>
    </div>
    <h2 class="mb-2 mt-8 text-lg font-semibold">Lines</h2>
    @foreach($lines as $index => $line)<div class="mb-4 grid gap-2 rounded border p-3 md:grid-cols-4">
        <input class="rounded border p-2" placeholder="Description" wire:model="lines.{{ $index }}.description">
        <input class="rounded border p-2" placeholder="HSN/SAC" wire:model="lines.{{ $index }}.hsn_sac">
        <input class="rounded border p-2" placeholder="Quantity" wire:model="lines.{{ $index }}.quantity">
        <input class="rounded border p-2" placeholder="Unit price" wire:model="lines.{{ $index }}.unit_price">
        <select class="rounded border p-2" wire:model="lines.{{ $index }}.purchase_ledger_id"><option value="">Cost ledger</option>@foreach($ledgers as $ledger)<option value="{{ $ledger->id }}">{{ $ledger->name }}</option>@endforeach</select>
        <select class="rounded border p-2" wire:model="lines.{{ $index }}.tax_rate_id"><option value="">GST rate</option>@foreach($rates as $rate)<option value="{{ $rate->id }}">{{ $rate->code }} ({{ $rate->rate }}%){{ $rate->is_verified ? '' : ' — UNVERIFIED' }}</option>@endforeach</select>
        <select class="rounded border p-2" wire:model="lines.{{ $index }}.itc_eligibility"><option value="eligible">ITC eligible</option><option value="blocked">Blocked ITC</option><option value="ineligible_reversal_review">Review/reversal</option></select>
        <input class="rounded border p-2" placeholder="ITC reason" wire:model="lines.{{ $index }}.itc_reason">
    </div>@endforeach
    <button class="rounded border px-3 py-2" wire:click="addLine">Add line</button>
    <div class="mt-6 flex gap-3"><button class="rounded border px-4 py-2" wire:click="save">Save draft</button>@can('purchases.post')<button class="rounded bg-slate-900 px-4 py-2 text-white" wire:click="post">Save and post</button>@endcan</div>
</div>
