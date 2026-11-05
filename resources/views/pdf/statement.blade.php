<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 12mm 18mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #111827; }
    .doc-title { font-size: 14pt; font-weight: bold; text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 0.5pt solid #9ca3af; padding: 2.5pt 4pt; vertical-align: top; }
    th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .muted { color: #6b7280; font-size: 8pt; }
    .opening td { background: #f9fafb; font-weight: bold; }
    .closing td { border-top: 1pt solid #111827; font-weight: bold; }
    .note { font-size: 8pt; color: #374151; margin-top: 8pt; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width:62%; border:none;">
            <div style="font-size:12pt; font-weight:bold;">{{ config('app.name') }}</div>
            <div class="muted">Customer statement</div>
        </td>
        <td style="width:38%; border:none;">
            <div class="doc-title">STATEMENT OF ACCOUNT</div>
            <table style="margin-top:6pt;">
                <tr><td style="width:45%;">Customer</td><td>{{ $party->name }}</td></tr>
                <tr><td>GSTIN</td><td>{{ $party->gstin ?? '—' }}</td></tr>
                <tr><td>Period</td><td>{{ \Illuminate\Support\Carbon::parse($statement['from'])->format('d-m-Y') }} to {{ \Illuminate\Support\Carbon::parse($statement['as_of'])->format('d-m-Y') }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table style="margin-top:10pt;">
    <thead>
        <tr><th style="width:13%;">Date</th><th>Description</th>
            <th style="width:14%;" class="num">Debit (₹)</th><th style="width:14%;" class="num">Credit (₹)</th>
            <th style="width:16%;" class="num">Balance (₹)</th></tr>
    </thead>
    <tbody>
        <tr class="opening">
            <td>{{ \Illuminate\Support\Carbon::parse($statement['from'])->subDay()->format('d-m-Y') }}</td>
            <td>Opening balance</td><td class="num"></td><td class="num"></td>
            <td class="num">{{ $statement['opening'] }}</td>
        </tr>
        @foreach ($statement['rows'] as $row)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="num">{{ $row['debit'] !== '0.00' ? $row['debit'] : '' }}</td>
                <td class="num">{{ $row['credit'] !== '0.00' ? $row['credit'] : '' }}</td>
                <td class="num">{{ $row['balance'] }}</td>
            </tr>
        @endforeach
        <tr class="closing">
            <td>{{ \Illuminate\Support\Carbon::parse($statement['as_of'])->format('d-m-Y') }}</td>
            <td>Closing balance</td><td class="num"></td><td class="num"></td>
            <td class="num">{{ $statement['closing'] }}</td>
        </tr>
    </tbody>
</table>

<div class="note">
    Opening and closing balances are taken from the customer's receivable ledger account
    @if ((string) $statement['closing'] === (string) $statement['ledger_as_of']) and reconcile exactly with it.
    @else <strong>and currently DO NOT reconcile (ledger: ₹{{ $statement['ledger_as_of'] }}); please investigate.</strong>
    @endif
    Entries appear on their document dates; documents issued later but dated inside the
    window are included. Receipts appear once at full value — allocations between receipts
    and invoices are settlement links and are never shown as separate rows. Amounts are in INR.
</div>
</body>
</html>
