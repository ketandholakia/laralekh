<div class="mx-auto max-w-6xl p-6">
    <div class="mb-6 flex justify-between"><h1 class="text-2xl font-semibold">Purchase bill {{ $bill->supplier_bill_number }}</h1>@if($bill->status === 'draft')<a class="rounded bg-slate-900 px-4 py-2 text-white" href="{{ route('purchases.edit', $bill) }}">Edit draft</a>@endif</div>
    <p>{{ $bill->party->name }} · {{ $bill->date->format('d-m-Y') }} · {{ ucfirst($bill->status) }}</p>
    <div class="mt-6 overflow-x-auto rounded border bg-white"><table class="w-full text-left"><thead><tr><th class="p-3">Description</th><th>Qty</th><th>Taxable</th><th>CGST</th><th>SGST/UTGST</th><th>IGST</th><th>Cess</th><th>Total</th><th>ITC</th></tr></thead><tbody>
    @foreach($bill->items as $line)<tr class="border-t"><td class="p-3">{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $line->taxable_amount }}</td><td>{{ $line->cgst }}</td><td>{{ $line->sgst->add($line->utgst) }}</td><td>{{ $line->igst }}</td><td>{{ $line->cess }}</td><td>{{ $line->total }}</td><td>{{ $line->itc_eligibility }}{{ $line->itc_reason ? ': '.$line->itc_reason : '' }}</td></tr>@endforeach
    </tbody></table></div><p class="mt-4 text-right font-semibold">Total: {{ $bill->total }}</p>
</div>
