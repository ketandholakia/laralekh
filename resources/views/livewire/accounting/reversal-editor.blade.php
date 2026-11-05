<div class="space-y-5">
    <h1 class="text-2xl font-semibold">Review full accounting reversal</h1>
    <p class="text-slate-600">Original voucher {{ $original->series }}/{{ $original->number }} · {{ $original->date->format('d-m-Y') }}</p>
    <p class="rounded border border-amber-300 bg-amber-50 p-4 text-sm">This posts equal and opposite entries. It is an internal accounting reversal, not a GST credit note or a GST return adjustment. The original remains immutable. Physical stock is unchanged.</p>
    @error('reversal')<p role="alert" class="rounded bg-red-50 p-3 text-red-800">{{ $message }}</p>@enderror
    <section class="overflow-x-auto rounded-lg border bg-white p-5">
        <h2 class="mb-3 font-semibold">Entries that will be posted</h2>
        <table class="w-full text-sm text-left"><thead class="border-b"><tr><th class="p-2">Ledger</th><th class="p-2 text-right">Debit INR</th><th class="p-2 text-right">Credit INR</th></tr></thead><tbody>
        @foreach ($original->lines as $line)<tr class="border-b"><td class="p-2">{{ $line->ledger->name }} · {{ $line->ledger->code }}</td><td class="p-2 text-right font-mono">{{ $line->credit }}</td><td class="p-2 text-right font-mono">{{ $line->debit }}</td></tr>@endforeach
        </tbody></table>
    </section>
    <section class="space-y-3 rounded-lg border bg-white p-5">
        <label class="block text-sm">Reversal date<input type="date" wire:model="date" class="ml-3 rounded border p-2"></label>
        <label class="block text-sm">Reason<textarea wire:model="reason" rows="3" maxlength="2000" class="mt-1 w-full rounded border p-2"></textarea></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="confirmed" class="mt-1">I confirm the displayed opposite entries and understand that this does not issue a GST credit note.</label>
        <button wire:click="post" wire:loading.attr="disabled" class="rounded bg-red-700 px-4 py-2 text-white disabled:opacity-50">Post full reversal</button>
    </section>
</div>

