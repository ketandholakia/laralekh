<!DOCTYPE html><html lang="en"><head>@include('pdf.correction-style')</head><body>
<h1>INTERNAL ACCOUNTING REVERSAL</h1>
<p class="banner">NOT A STATUTORY GST CREDIT NOTE - NOT FOR GST RETURN REPORTING</p>
<p><strong>{{ $reversal->original_snapshot['supplier']['name'] ?? 'Internal accounting document' }}</strong></p>
<p>Document: {{ $reversal->voucher->series }}/{{ $reversal->voucher->number }} | Date: {{ $reversal->date->format('d-m-Y') }}</p>
<p>Original voucher: {{ $reversal->original_snapshot['number'] }} | Original date: {{ $reversal->original_snapshot['date'] }}</p>
<p>Party: {{ $reversal->original_snapshot['party']['name'] ?? 'See ledger entries' }}</p>
<p><strong>Reason:</strong> {{ $reversal->reason }}</p>
<table><thead><tr><th>Ledger code / name</th><th class="num">Debit INR</th><th class="num">Credit INR</th></tr></thead><tbody>
@foreach ($reversal->entries_snapshot as $entry)
<tr><td>{{ $entry['ledger_code'] }} - {{ $entry['ledger_name'] }}</td><td class="num">{{ $entry['debit'] }}</td><td class="num">{{ $entry['credit'] }}</td></tr>
@endforeach
</tbody></table>
<div class="summary"><p class="num"><strong>Debit INR {{ $total }} = Credit INR {{ $total }}</strong></p>
<p>Reversed value in words: {{ $words }}</p>
<p>These entries exactly swap the original voucher's debit and credit amounts. The original remains unchanged.</p>
<p class="muted">This document does not establish eligibility to reduce GST liability. Resolve statutory reporting separately. No physical stock movement is recorded.</p>
<p class="signature">Prepared / reviewed by authorised accountant</p></div>
</body></html>

