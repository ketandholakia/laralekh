<div class="space-y-6">
    <x-page-header
        :title="$noteId ? 'Edit correction draft' : 'New correction'"
        :eyebrow="'Sales correction · Original ' . $source->series . '/' . $source->number"
        sub="The original invoice stays unchanged. This document adjusts the financial accounts; it does not move stock."
    >
        <x-slot name="actions">
            <x-button :href="route('invoices.show', $source->id)" variant="ghost">Original invoice</x-button>
        </x-slot>
    </x-page-header>

    @error('correction')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-field label="Document" name="form.kind">
                <x-select wire:model.live="form.kind" name="form.kind">
                    <option value="credit">Credit note · reduce receivable</option>
                    <option value="debit">Debit note · increase receivable</option>
                </x-select>
            </x-field>

            <x-field label="Tax treatment" name="form.treatment">
                <x-select wire:model.live="form.treatment" name="form.treatment">
                    <option value="commercial">Commercial · no GST adjustment</option>
                    <option value="gst">GST note · reviewed eligibility</option>
                </x-select>
            </x-field>

            <x-field label="Note date" name="form.date">
                <x-input type="date" wire:model="form.date" name="form.date" />
            </x-field>

            <x-field label="Reason category" name="form.reason_code">
                <x-select wire:model="form.reason_code" name="form.reason_code">
                    <option value="commercial">Commercial concession / adjustment</option>
                    <option value="goods_return">Goods returned</option>
                    <option value="overcharge">Original overcharge</option>
                    <option value="deficiency">Deficient goods / services</option>
                    <option value="undercharge">Original undercharge</option>
                </x-select>
            </x-field>

            <x-field label="Reason" name="form.reason" class="sm:col-span-2">
                <x-textarea name="form.reason" wire:model="form.reason" rows="2" maxlength="2000" placeholder="Explain the correction and supporting reference" />
            </x-field>
        </div>
    </x-card>

    @if (($form['treatment'] ?? '') === 'gst')
        <x-card class="border-amber-200 bg-amber-50/50 space-y-4">
            <div class="border-b border-amber-200/60 pb-3">
                <h2 class="text-sm font-semibold text-amber-900">GST eligibility review</h2>
                <p class="mt-1 text-xs text-amber-800">
                    Use the original supply's tax treatment. Confirm the statutory reason and recipient ITC/tax-incidence conditions. For credits, check reporting eligibility and the original year's annual-return status. Saving or posting here does not file a GST return.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Planned reporting date" name="form.reporting_date">
                    <x-input name="form.reporting_date" type="date" wire:model="form.reporting_date" />
                </x-field>
                <x-field label="Original year's annual return filed on (blank only if not filed)" name="form.annual_return_filed_on">
                    <x-input name="form.annual_return_filed_on" type="date" wire:model="form.annual_return_filed_on" />
                </x-field>
            </div>

            <x-field label="Review evidence / reference" name="form.eligibility_evidence">
                <x-textarea name="form.eligibility_evidence" wire:model="form.eligibility_evidence" rows="2" />
            </x-field>

            <x-field label="Recipient delivery address (required for unregistered recipients)" name="form.delivery_address">
                <x-textarea name="form.delivery_address" wire:model="form.delivery_address" rows="2" />
            </x-field>

            <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" wire:model="form.eligibility_confirmed" class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span>I verified the applicable GST conditions and annual-return filing status, and recorded the evidence above.</span>
            </label>
        </x-card>
    @endif

    <x-card>
        <div class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900">Affected original lines</h2>
            <p class="mt-1 text-xs text-slate-500">Amounts are before tax. Use quantity 0 for value-only adjustments. Credit limits include previous commercial credits; debit notes do not expand these limits.</p>
        </div>

        <x-table caption="Affected original lines">
            <x-slot name="head">
                <tr>
                    <th class="w-12 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Use</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Original line</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Credit available</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Affected quantity</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Adjustment INR</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @foreach ($lines as $index => $line)
                    @php($item = $source->items->firstWhere('id', $line['invoice_item_id']))
                    <tr wire:key="correction-line-{{ $index }}" class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-3 py-2">
                            <input aria-label="Select line {{ $index + 1 }}" type="checkbox" wire:model.live="lines.{{ $index }}.selected" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        </td>
                        <td class="px-3 py-2">
                            <div class="font-medium text-slate-900">{{ $item?->description ?? 'Unknown line' }}</div>
                            <span class="block text-xs text-slate-500">HSN {{ $item?->hsn_sac }} · Original rate {{ $item?->rate }}%</span>
                        </td>
                        <td class="px-3 py-2 font-mono text-sm text-slate-700">
                            {{ $capacity[$line['invoice_item_id']]['amount'] ?? '—' }}
                            <span class="block text-xs text-slate-500">Qty {{ $capacity[$line['invoice_item_id']]['quantity'] ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <input aria-label="Affected quantity for line {{ $index + 1 }}" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" inputmode="decimal" class="w-28 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-mono focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                        </td>
                        <td class="px-3 py-2">
                            <input aria-label="Adjustment amount for line {{ $index + 1 }}" wire:model.live.debounce.400ms="lines.{{ $index }}.amount" inputmode="decimal" class="w-36 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-mono focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                        </td>
                    </tr>
                @endforeach
            </x-slot>
        </x-table>
    </x-card>

    @if ($previewError)
        <x-alert type="warning">{{ $previewError }}</x-alert>
    @endif

    @if ($preview)
        <x-card>
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Financial preview</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
                @foreach ($preview['totals'] as $field => $value)
                    @if ($field !== 'recipient_tax' || !$value->isZero())
                        <div class="rounded-md border border-slate-100 bg-slate-50 p-3">
                            <span class="block text-xs uppercase tracking-wider text-slate-500">{{ $field === 'recipient_tax' ? 'Recipient RCM tax' : ucwords(str_replace('_', ' ', $field)) }}</span>
                            <span class="mt-1 block font-mono text-lg font-semibold text-slate-900">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600 space-y-1">
                <p>Party receivable {{ $form['kind'] === 'credit' ? 'decreases' : 'increases' }} by INR {{ $preview['totals']['total'] }}.</p>
                <p>{{ $form['kind'] === 'credit' ? 'Debit sales and applicable output GST; credit party.' : 'Debit party; credit sales and applicable output GST.' }} Recipient reverse-charge tax is excluded from the voucher total.</p>
            </div>
        </x-card>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <x-button type="button" wire:click="save" wire:loading.attr="disabled" variant="secondary">Save draft</x-button>
        @if ($noteId)
            @can('corrections.post')
                <x-button type="button" wire:click="post" wire:confirm="Post this correction? The posted document will be immutable." wire:loading.attr="disabled" variant="primary">Save and post correction</x-button>
            @endcan
            @if (Route::has('corrections.pdf'))
                <x-button :href="route('corrections.pdf', $noteId)" variant="ghost" icon="file-down">Draft PDF</x-button>
            @endif
        @endif
    </div>
</div>
