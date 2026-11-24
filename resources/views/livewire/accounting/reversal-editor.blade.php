<div class="space-y-6">
    <x-page-header
        title="Review full accounting reversal"
        eyebrow="Accounting · Reversal"
        :sub="'Original voucher ' . $original->series . '/' . $original->number . ' · ' . $original->date->format('d-m-Y')"
    />

    <x-alert type="warning">
        This posts equal and opposite entries. It is an internal accounting reversal, not a GST credit note or a GST return adjustment. The original remains immutable. Physical stock is unchanged.
    </x-alert>

    @error('reversal')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Entries that will be posted</h2>
        <x-table caption="Entries that will be posted">
            <x-slot name="head">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ledger</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Debit INR</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Credit INR</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @foreach ($original->lines as $line)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-3 py-2 text-sm text-slate-900 font-medium">
                            {{ $line->ledger->name }} · <span class="font-mono text-xs text-slate-500">{{ $line->ledger->code }}</span>
                        </td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-900"><x-money :value="$line->credit" /></td>
                        <td class="px-3 py-2 text-right font-mono text-sm text-slate-900"><x-money :value="$line->debit" /></td>
                    </tr>
                @endforeach
            </x-slot>
        </x-table>
    </x-card>

    <x-card class="space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-field label="Reversal date" name="date">
                <x-input type="date" wire:model="date" name="date" />
            </x-field>
        </div>

        <x-field label="Reason" name="reason">
            <x-textarea wire:model="reason" rows="3" maxlength="2000" placeholder="Provide the business and audit reason for this accounting reversal" />
        </x-field>

        <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
            <input type="checkbox" wire:model="confirmed" class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span>I confirm the displayed opposite entries and understand that this does not issue a GST credit note.</span>
        </label>

        <div>
            <x-button type="button" wire:click="post" wire:loading.attr="disabled" variant="danger">
                Post full reversal
            </x-button>
        </div>
    </x-card>
</div>
