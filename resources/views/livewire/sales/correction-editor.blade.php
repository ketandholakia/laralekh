<div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div><p class="text-sm text-slate-500">Sales correction · Original {{ $source->series }}/{{ $source->number }}</p>
            <h1 class="text-2xl font-semibold">{{ $noteId ? 'Edit correction draft' : 'New correction' }}</h1></div>
        <a class="text-sm text-indigo-700 underline" href="{{ route('invoices.show', $source->id) }}">Original invoice</a>
    </div>
    <p class="rounded border border-blue-200 bg-blue-50 p-3 text-sm">The original invoice stays unchanged. This document adjusts the financial accounts; it does not move stock.</p>
    @error('correction')<p role="alert" class="rounded bg-red-50 p-3 text-red-800">{{ $message }}</p>@enderror
    <section class="grid gap-4 rounded-lg border bg-white p-5 sm:grid-cols-3">
        <label class="text-sm">Document
            <select wire:model.live="form.kind" class="mt-1 w-full rounded border p-2"><option value="credit">Credit note · reduce receivable</option><option value="debit">Debit note · increase receivable</option></select>
        </label>
        <label class="text-sm">Tax treatment
            <select wire:model.live="form.treatment" class="mt-1 w-full rounded border p-2"><option value="commercial">Commercial · no GST adjustment</option><option value="gst">GST note · reviewed eligibility</option></select>
        </label>
        <label class="text-sm">Note date<input type="date" wire:model="form.date" class="mt-1 w-full rounded border p-2"></label>
        <label class="text-sm">Reason category<select wire:model="form.reason_code" class="mt-1 w-full rounded border p-2">
            <option value="commercial">Commercial concession / adjustment</option><option value="goods_return">Goods returned</option>
            <option value="overcharge">Original overcharge</option><option value="deficiency">Deficient goods / services</option><option value="undercharge">Original undercharge</option>
        </select></label>
        <label class="text-sm sm:col-span-2">Reason<textarea wire:model="form.reason" rows="2" maxlength="2000" class="mt-1 w-full rounded border p-2" placeholder="Explain the correction and supporting reference"></textarea></label>
    </section>
    @if (($form['treatment'] ?? '') === 'gst')
    <section class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm">
        <h2 class="font-semibold">GST eligibility review</h2>
        <p>Use the original supply's tax treatment. Confirm the statutory reason and recipient ITC/tax-incidence conditions. For credits, check reporting eligibility and the original year's annual-return status. Saving or posting here does not file a GST return.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <label>Planned reporting date<input type="date" wire:model="form.reporting_date" class="mt-1 w-full rounded border p-2"></label>
            <label>Original year's annual return filed on (blank only if not filed)<input type="date" wire:model="form.annual_return_filed_on" class="mt-1 w-full rounded border p-2"></label>
        </div>
        <label class="block">Review evidence / reference<textarea wire:model="form.eligibility_evidence" rows="2" class="mt-1 w-full rounded border p-2"></textarea></label>
        <label class="block">Recipient delivery address (required for unregistered recipients)<textarea wire:model="form.delivery_address" rows="2" class="mt-1 w-full rounded border p-2"></textarea></label>
        <label class="flex items-start gap-2"><input type="checkbox" wire:model="form.eligibility_confirmed" class="mt-1">I verified the applicable GST conditions and annual-return filing status, and recorded the evidence above.</label>
    </section>
    @endif
    <section class="overflow-x-auto rounded-lg border bg-white p-5">
        <h2 class="mb-1 font-semibold">Affected original lines</h2>
        <p class="mb-4 text-sm text-slate-500">Amounts are before tax. Use quantity 0 for value-only adjustments. Credit limits include previous commercial credits; debit notes do not expand these limits.</p>
        <table class="w-full text-left text-sm">
            <thead class="border-b text-slate-500"><tr><th class="p-2">Use</th><th class="p-2">Original line</th><th class="p-2">Credit available</th><th class="p-2">Affected quantity</th><th class="p-2">Adjustment INR</th></tr></thead>
            <tbody>
            @foreach ($lines as $index => $line)
                @php($item = $source->items->firstWhere('id', $line['invoice_item_id']))
                <tr wire:key="correction-line-{{ $index }}" class="border-b">
                    <td class="p-2"><input aria-label="Select line {{ $index + 1 }}" type="checkbox" wire:model.live="lines.{{ $index }}.selected"></td>
                    <td class="p-2">{{ $item?->description ?? 'Unknown line' }}<span class="block text-xs text-slate-500">HSN {{ $item?->hsn_sac }} · Original rate {{ $item?->rate }}%</span></td>
                    <td class="p-2 font-mono">{{ $capacity[$line['invoice_item_id']]['amount'] ?? '—' }}<span class="block text-xs">Qty {{ $capacity[$line['invoice_item_id']]['quantity'] ?? '—' }}</span></td>
                    <td class="p-2"><input aria-label="Affected quantity for line {{ $index + 1 }}" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" inputmode="decimal" class="w-28 rounded border p-2"></td>
                    <td class="p-2"><input aria-label="Adjustment amount for line {{ $index + 1 }}" wire:model.live.debounce.400ms="lines.{{ $index }}.amount" inputmode="decimal" class="w-36 rounded border p-2"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    @if ($previewError)<p role="alert" class="rounded bg-amber-50 p-3 text-amber-900">{{ $previewError }}</p>@endif
    @if ($preview)
    <section class="rounded-lg border bg-white p-5">
        <h2 class="mb-3 font-semibold">Financial preview</h2>
        <div class="flex flex-wrap gap-6 text-sm">
        @foreach ($preview['totals'] as $field => $value)
            @if ($field !== 'recipient_tax' || !$value->isZero())
            <div><span class="block text-slate-500">{{ $field === 'recipient_tax' ? 'Recipient RCM tax' : ucwords(str_replace('_', ' ', $field)) }}</span><span class="font-mono text-lg">{{ $value }}</span></div>
            @endif
        @endforeach
        </div>
        <p class="mt-3 text-sm">Party receivable {{ $form['kind'] === 'credit' ? 'decreases' : 'increases' }} by INR {{ $preview['totals']['total'] }}.</p>
        <p class="mt-2 text-sm">{{ $form['kind'] === 'credit' ? 'Debit sales and applicable output GST; credit party.' : 'Debit party; credit sales and applicable output GST.' }} Recipient reverse-charge tax is excluded from the voucher total.</p>
    </section>
    @endif
    <div class="flex flex-wrap gap-3">
        <button wire:click="save" wire:loading.attr="disabled" class="rounded border border-indigo-600 bg-white px-4 py-2 text-indigo-700 disabled:opacity-50">Save draft</button>
        @if ($noteId)
            @can('corrections.post')<button wire:click="post" wire:confirm="Post this correction? The posted document will be immutable." wire:loading.attr="disabled" class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50">Save and post correction</button>@endcan
            @if (Route::has('corrections.pdf'))<a href="{{ route('corrections.pdf', $noteId) }}" class="rounded border px-4 py-2">Draft PDF</a>@endif
        @endif
    </div>
</div>

