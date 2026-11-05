<div class="space-y-5">
    <div class="flex justify-between"><h1 class="text-2xl font-semibold">Internal reversal {{ $reversal->voucher->series }}/{{ $reversal->voucher->number }}</h1>
    @if (Route::has('reversals.pdf'))<a class="underline text-indigo-700" href="{{ route('reversals.pdf', $reversal->id) }}">Download PDF</a>@endif</div>
    <p class="rounded bg-amber-50 p-4 text-sm">Internal accounting document. Not a GST credit note. No GST filing or physical stock movement has been performed.</p>
    <section class="rounded-lg border bg-white p-5">
        <p>Original voucher: {{ $reversal->original_snapshot['number'] }} · {{ $reversal->original_snapshot['date'] }}</p>
        <p>Reversal date: {{ $reversal->date->format('d-m-Y') }}</p><p class="my-3 whitespace-pre-line">{{ $reversal->reason }}</p>
        <table class="w-full text-sm text-left"><thead class="border-b"><tr><th class="p-2">Ledger</th><th class="p-2 text-right">Debit INR</th><th class="p-2 text-right">Credit INR</th></tr></thead><tbody>
        @foreach ($reversal->entries_snapshot as $line)<tr class="border-b"><td class="p-2">{{ $line['ledger_name'] }} · {{ $line['ledger_code'] }}</td><td class="p-2 text-right">{{ $line['debit'] }}</td><td class="p-2 text-right">{{ $line['credit'] }}</td></tr>@endforeach
        </tbody></table>
    </section>
</div>

