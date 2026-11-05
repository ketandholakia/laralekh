<div class="space-y-5">
    <div class="flex justify-between gap-3">
        <div><h1 class="text-2xl font-semibold">{{ ucfirst($note->label()) }} {{ $note->number ? $note->series.'/'.$note->number : '· Draft' }}</h1>
        <p class="text-sm text-slate-500">{{ $note->date->format('d-m-Y') }} · {{ ucfirst($note->status) }} · Original {{ $note->original_snapshot['number'] }}</p></div>
        <div class="flex gap-3 text-sm">
            <a href="{{ route('invoices.show', $note->invoice_id) }}" class="underline">Original invoice</a>
            @if (Route::has('corrections.pdf'))<a href="{{ route('corrections.pdf', $note->id) }}" class="underline">Download PDF</a>@endif
            @if ($note->status === 'draft')
                @can('corrections.create')
                    <a href="{{ route('corrections.edit', $note->id) }}" class="underline">Edit draft</a>
                @endcan
            @endif
        </div>
    </div>
    <section class="rounded-lg border bg-white p-5">
        <p class="font-medium">{{ $note->party_snapshot['name'] ?? '' }}</p>
        <p class="mt-2 whitespace-pre-line">{{ $note->reason }}</p>
        <p class="mt-3 text-sm text-slate-500">{{ $note->treatment === 'commercial' ? 'Commercial adjustment: no GST liability change.' : 'GST eligibility reviewed. Return reporting and acceptance are external to this document.' }} No physical stock adjustment.</p>
    </section>
    <section class="overflow-x-auto rounded-lg border bg-white p-5">
        <table class="w-full text-sm text-left"><thead class="border-b"><tr><th class="p-2">Description / HSN</th><th class="p-2">Qty</th><th class="p-2">Base INR</th><th class="p-2">CGST</th><th class="p-2">SGST / UTGST</th><th class="p-2">IGST</th><th class="p-2">Total INR</th></tr></thead>
        <tbody>@foreach ($note->lines as $line)<tr class="border-b"><td class="p-2">{{ $line->description }}<span class="block text-xs">{{ $line->hsn_sac }} · {{ $line->rate }}%</span></td><td class="p-2">{{ $line->quantity }}</td><td class="p-2">{{ $line->taxable_amount }}</td><td class="p-2">{{ $line->cgst }}</td><td class="p-2">{{ $line->sgst->add($line->utgst) }}</td><td class="p-2">{{ $line->igst }}</td><td class="p-2">{{ $line->total }}</td></tr>@endforeach</tbody></table>
        <p class="mt-4 text-right text-xl font-semibold">Total INR {{ $note->total }}</p>
        @if (!$note->recipient_tax->isZero())<p class="mt-2 text-right text-sm">Recipient RCM tax adjustment: INR {{ $note->recipient_tax }}</p>@endif
    </section>
</div>

