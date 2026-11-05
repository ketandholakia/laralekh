<!DOCTYPE html><html lang="en"><head>@include('pdf.correction-style')</head><body>
@if ($note->status !== 'posted')
<p class="banner">DRAFT PREVIEW - NO NUMBER ALLOCATED - NOT FOR ISSUE</p>
@endif
<h1>{{ strtoupper($note->label()) }}</h1>
<table><tr><td style="width:58%">
<strong>{{ $note->supplier_snapshot['name'] ?? '' }}</strong>
<p style="white-space:pre-line">{{ $note->supplier_snapshot['address'] ?? '' }}</p>
<p>GSTIN: {{ $note->supplier_snapshot['gstin'] ?? 'Unregistered' }} | State: {{ $note->supplier_snapshot['state_code'] ?? '' }}</p>
</td><td>
<p>Note: <strong>{{ $note->status === 'posted' ? $note->series.'/'.$note->number : 'DRAFT' }}</strong></p>
<p>Date: {{ $note->date->format('d-m-Y') }}</p>
<p>Original invoice: {{ $note->original_snapshot['number'] }}</p>
<p>Original date: {{ $note->original_snapshot['date'] }}</p>
</td></tr></table>
<table><tr><td style="width:58%">
<strong>Recipient: {{ $note->party_snapshot['name'] ?? '' }}</strong>
<p style="white-space:pre-line">{{ $note->party_snapshot['address'] ?? '' }}</p>
<p>GSTIN: {{ $note->party_snapshot['gstin'] ?? 'Unregistered' }} | State: {{ $note->party_snapshot['state_code'] ?? '' }}</p>
@if ($note->delivery_address)<p>Delivery address: {{ $note->delivery_address }}</p>@endif
</td><td>
<p>Place of supply: {{ $note->original_snapshot['place_of_supply'] }}</p>
<p>Supply: {{ strtoupper($note->original_snapshot['supply_type']) }}</p>
<p>Zero-rated mode: {{ $note->original_snapshot['zero_rated_mode'] }}</p>
<p>Reverse charge: {{ $note->original_snapshot['reverse_charge'] ? 'YES - recipient liability' : 'NO' }}</p>
</td></tr></table>
<p><strong>Reason:</strong> {{ $note->reason }}</p>
@if ($note->treatment === 'commercial')
<p class="banner">COMMERCIAL ADJUSTMENT - NO GST LIABILITY CHANGE</p>
@else
<p class="muted">GST {{ $note->kind }} note. Eligibility review recorded; return reporting is handled separately.</p>
@endif
<table><thead><tr>
<th style="width:27%">Description / HSN</th><th>Qty</th><th class="num">Base INR</th>
<th class="num">Rate %</th><th class="num">CGST</th><th class="num">SGST / UTGST</th><th class="num">IGST</th><th class="num">Total INR</th>
</tr></thead><tbody>
@foreach ($note->lines as $line)
<tr><td>{{ $line->description }}<div class="muted">HSN/SAC {{ $line->hsn_sac }}</div></td><td>{{ $line->quantity }}</td>
<td class="num">{{ $line->taxable_amount }}</td><td class="num">{{ $line->rate }}</td><td class="num">{{ $line->cgst }}</td>
<td class="num">{{ $line->sgst->add($line->utgst) }}</td><td class="num">{{ $line->igst }}</td><td class="num">{{ $line->total }}</td></tr>
@endforeach
</tbody></table>
<div class="summary">
<p class="num"><strong>Base INR {{ $note->taxable_amount }} | CGST {{ $note->cgst }} | SGST {{ $note->sgst }} | UTGST {{ $note->utgst }} | IGST {{ $note->igst }}</strong></p>
<p class="num"><strong>Total adjustment INR {{ $note->total }}</strong></p>
@if (!$note->recipient_tax->isZero())<p>Recipient reverse-charge tax adjustment INR {{ $note->recipient_tax }} (excluded from payable).</p>@endif
<p><strong>Amount in words:</strong> {{ $words }}</p>
<p class="muted">Values above are adjustment amounts, not a replacement invoice. No physical stock movement is recorded.</p>
@if ($note->treatment === 'commercial')<p class="muted">Rate identifies the original supply only; no GST is charged or reduced by this commercial note.</p>@endif
<p class="signature">For {{ $note->supplier_snapshot['name'] ?? '' }}<br><br>Authorised signatory</p>
</div></body></html>

