<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 12mm 18mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111827; }
    .doc-title { font-size: 14pt; font-weight: bold; text-align: right; }
    .draft-banner { border: 1.5pt solid #b45309; background: #fef3c7; color: #92400e; text-align: center;
        font-weight: bold; padding: 4pt; margin-bottom: 8pt; font-size: 10pt; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 0.5pt solid #9ca3af; padding: 3pt 4pt; vertical-align: top; }
    th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .muted { color: #6b7280; font-size: 8pt; }
    .totals td { border: none; padding: 1.5pt 4pt; }
    .totals .grand td { border-top: 0.75pt solid #111827; font-weight: bold; font-size: 10.5pt; white-space: nowrap; }
    .signature { margin-top: 14pt; text-align: right; }
    .note { font-size: 8pt; color: #374151; }
</style>
</head>
<body>
@if (!$posted)
    <div class="draft-banner">DRAFT PREVIEW - FOR REVIEW ONLY. NO RECEIPT NUMBER ALLOCATED. NOT A PAYMENT DOCUMENT.</div>
@endif
<table>
    <tr>
        <td style="width:62%; border:none;">
            <div style="font-size:12pt; font-weight:bold;">{{ $receipt->party_snapshot['name'] ?? $partyName }}</div>
            <div class="muted">Received with thanks from</div>
            <div style="font-weight:bold;">{{ $partyName }}</div>
            <div style="white-space:pre-line;">{{ $partyAddress }}</div>
        </td>
        <td style="width:38%; border:none;">
            <div class="doc-title">MONEY RECEIPT</div>
            <table style="margin-top:6pt;">
                <tr><td style="width:45%;">Receipt No.</td><td class="num">
                    @if ($posted){{ $receipt->series }}/{{ $receipt->number }}@else— (preview)@endif</td></tr>
                <tr><td>Receipt Date</td><td class="num">{{ $receipt->date->format('d-m-Y') }}</td></tr>
                <tr><td>Payment reference</td><td class="num">{{ $receipt->payment_reference ?? '—' }}</td></tr>
                <tr><td>Paid into</td><td class="num">{{ $receipt->ledger->code }} · {{ $receipt->ledger->name }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($original->count() > 0)
    <table style="margin-top:10pt;">
        <thead>
            <tr><th style="width:10%;">Invoice No.</th><th style="width:16%;">Invoice date</th>
                <th style="width:14%;" class="num">Invoice total (₹)</th><th style="width:16%;" class="num">Amount applied (₹)</th>
                <th>Remarks</th></tr>
        </thead>
        <tbody>
            @foreach ($original as $row)
                <tr>
                    <td>{{ $row->invoice_label }}</td>
                    <td>{{ $row->invoice_date }}</td>
                    <td class="num">{{ $row->invoice_total }}</td>
                    <td class="num">{{ $row->amount }}</td>
                    <td class="muted">Against invoice</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <div class="note" style="margin-top:10pt;">No invoices were applied when this receipt was posted; the amount is held as an unapplied customer advance.</div>
@endif

<table style="margin-top:12pt;">
    <tr>
        <td style="width:58%; border:none; vertical-align:top;">
            <div class="note"><strong>Amount in words:</strong> {{ $words }}</div>
            <div class="note" style="margin-top:8pt;">
                This is a commercial acknowledgement of money received. It is not a statutory
                GST receipt voucher and no GST is charged or certified by this document.
                @php
                    $applied = \App\Domain\Shared\ValueObjects\Money::of('0');
                    foreach ($original as $row) { $applied = $applied->add(\App\Domain\Shared\ValueObjects\Money::of($row->amount)); }
                @endphp
                @if ($receipt->amount->subtract($applied)->greaterThan(\App\Domain\Shared\ValueObjects\Money::of('0')))
                    Any unapplied balance is an advance against future supplies; advance-payment
                    GST treatment, where applicable, is handled outside this document.
                @endif
            </div>
            <div class="note" style="margin-top:4pt;">Allocations made after this receipt was issued are recorded in the receipt register, not on this document.</div>
        </td>
        <td style="width:42%; border:none; vertical-align:top;">
            <table class="totals">
                <tr class="grand"><td>Amount received</td><td class="num">₹&nbsp;{{ $receipt->amount }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="signature">
    <div class="muted">For <strong>{{ config('app.name') }}</strong></div>
    <div style="margin-top:18pt;" class="muted">Authorised signatory</div>
</div>
</body>
</html>
