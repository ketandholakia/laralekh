<div class="space-y-6">
    <x-page-header
        :title="ucfirst($note->label()) . ($note->number ? ' ' . $note->series . '/' . $note->number : ' · Draft')"
        :eyebrow="'Sales · ' . ucfirst($note->label())"
        :sub="$note->date->format('d-m-Y') . ' · Original ' . $note->original_snapshot['number']"
    >
        <x-badge :variant="$note->status === 'posted' ? 'success' : 'neutral'" size="md">
            {{ ucfirst($note->status) }}
        </x-badge>

        <x-slot name="actions">
            <x-button :href="route('invoices.show', $note->invoice_id)" variant="ghost">Original invoice</x-button>
            @if (Route::has('corrections.pdf'))
                <x-button :href="route('corrections.pdf', $note->id)" variant="secondary" icon="file-down">Download PDF</x-button>
            @endif
            @if ($note->status === 'draft')
                @can('corrections.create')
                    <x-button :href="route('corrections.edit', $note->id)" variant="primary">Edit draft</x-button>
                @endcan
            @endif
        </x-slot>
    </x-page-header>

    <x-card class="space-y-2">
        <h2 class="text-base font-semibold text-slate-900">{{ $note->party_snapshot['name'] ?? '' }}</h2>
        <p class="whitespace-pre-line text-sm text-slate-700">{{ $note->reason }}</p>
        <p class="mt-2 text-xs text-slate-500">
            {{ $note->treatment === 'commercial' ? 'Commercial adjustment: no GST liability change.' : 'GST eligibility reviewed. Return reporting and acceptance are external to this document.' }} No physical stock adjustment.
        </p>
    </x-card>

    <x-card>
        <x-table caption="Correction line items">
            <x-slot name="head">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description / HSN</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Qty</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Base INR</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">CGST</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">SGST / UTGST</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">IGST</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Cess</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Total INR</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @foreach ($note->lines as $line)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-3 py-2">
                            <div class="font-medium text-slate-900">{{ $line->description }}</div>
                            <span class="block text-xs text-slate-500">HSN {{ $line->hsn_sac }} · {{ $line->rate }}%</span>
                        </td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-700">{{ $line->quantity }}</td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-700"><x-money :value="$line->taxable_amount" /></td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-700"><x-money :value="$line->cgst" /></td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-700"><x-money :value="$line->sgst->add($line->utgst)" /></td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-700"><x-money :value="$line->igst" /></td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-700"><x-money :value="$line->cess" /></td>
                        <td class="px-3 py-2 text-right font-mono text-sm font-semibold text-slate-900"><x-money :value="$line->total" /></td>
                    </tr>
                @endforeach
            </x-slot>
        </x-table>

        <div class="mt-6 border-t border-slate-100 pt-4 text-right">
            <p class="text-xl font-bold text-slate-900">Total INR {{ $note->total }}</p>
            @if (!$note->recipient_tax->isZero())
                <p class="mt-1 text-sm text-slate-500">Recipient RCM tax adjustment: INR {{ $note->recipient_tax }}</p>
            @endif
        </div>
    </x-card>
</div>
