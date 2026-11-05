<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page {
        margin: 12mm 12mm 18mm 12mm;
    }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111827; }
    .page-footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #6b7280; }
    .doc-title { font-size: 14pt; font-weight: bold; text-align: right; }
    .draft-banner { border: 1.5pt solid #b45309; background: #fef3c7; color: #92400e; text-align: center;
        font-weight: bold; padding: 4pt; margin-bottom: 8pt; font-size: 10pt; }
    .draft-watermark { color: rgba(180, 83, 9, 0.14); font-size: 42pt; font-weight: bold;
        transform: rotate(-28deg); position: absolute; top: 90mm; left: 24mm; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 0.5pt solid #9ca3af; padding: 3pt 4pt; vertical-align: top; }
    th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .muted { color: #6b7280; font-size: 8pt; }
    .kv { margin: 0; }
    .totals td { border: none; padding: 1.5pt 4pt; }
    .totals .grand td { border-top: 0.75pt solid #111827; font-weight: bold; font-size: 10.5pt; white-space: nowrap; }
    .signature { margin-top: 14pt; text-align: right; }
    .declaration { font-size: 8pt; color: #374151; }
</style>
</head>
<body>
@php
    $zero = \App\Domain\Shared\ValueObjects\Money::of('0');
    $val = fn ($v) => $v ?? $zero;
@endphp
@if (!$isPosted)
    <div class="draft-watermark">DRAFT — NOT A TAX INVOICE</div>
@endif


@if (!$isPosted)
    <div class="draft-banner">DRAFT PREVIEW — FOR REVIEW ONLY. NO INVOICE NUMBER ALLOCATED. NOT A TAX INVOICE.</div>
@endif

{{-- Header: supplier + document meta --}}
<table>
    <tr>
        <td style="width:62%; border:none;">
            <div style="font-size:12pt; font-weight:bold;">{{ $supplier['name'] ?? '—' }}</div>
            <div style="white-space:pre-line;">{{ $supplier['address'] ?? '' }}</div>
            <div>GSTIN: <strong>{{ $supplier['gstin'] ?? 'Unregistered' }}</strong>
                · State: {{ $supplier['state_code'] ?? '' }}</div>
            <div class="muted">Regime: {{ ucfirst($supplier['gst_regime'] ?? 'regular') }}</div>
        </td>
        <td style="width:38%; border:none;">
            <div class="doc-title">
                @if (!$isPosted)
                    DRAFT PREVIEW
                @elseif ($billOfSupply)
                    BILL OF SUPPLY
                @elseif ($invoice->reverse_charge)
                    TAX INVOICE — REVERSE CHARGE
                @else
                    TAX INVOICE
                @endif
            </div>
            <table style="margin-top:6pt;">
                <tr><td style="width:45%;">Invoice No.</td><td class="num">
                    @if ($isPosted){{ $invoice->series }}/{{ $invoice->number }}@else— (preview)@endif</td></tr>
                <tr><td>Invoice Date</td><td class="num">{{ $invoice->date->format('d-m-Y') }}</td></tr>
                @if ($invoice->due_date)
                    <tr><td>Due Date</td><td class="num">{{ $invoice->due_date->format('d-m-Y') }}</td></tr>
                @endif
                <tr><td>Place of Supply</td><td class="num">{{ $placeOfSupply }}</td></tr>
                <tr><td>Supply Type</td><td class="num">{{ ucfirst($invoice->supply_type) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Party --}}
<table style="margin-top:8pt;">
    <tr>
        <td style="width:50%;">
            <div class="muted">BILLED TO</div>
            <div style="font-weight:bold;">{{ $party['name'] ?? '—' }}</div>
            <div style="white-space:pre-line;">{{ $party['address'] ?? '' }}</div>
            <div>GSTIN: {{ $party['gstin'] ?? '—' }}</div>
            <div class="muted">{{ ucfirst($party['registration_type'] ?? 'unregistered') }}
                @if (!empty($party['state_code'])) · State: {{ $party['state_code'] }}@endif</div>
        </td>
        <td style="width:50%;">
            <div class="muted">REVERSE CHARGE</div>
            <div>{{ $invoice->reverse_charge ? 'YES — tax payable by the recipient' : 'NO' }}</div>
            @if ($invoice->supply_type !== 'domestic')
                <div class="muted" style="margin-top:4pt;">
                    {{ $invoice->supply_type === 'export' ? 'EXPORT' : 'SEZ SUPPLY' }} —
                    {{ $invoice->zero_rated_mode === 'lut' ? 'under LUT, no IGST charged'
                        : ($invoice->zero_rated_mode === 'with_payment' ? 'with IGST payment' : 'zero-rated') }}
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Items: DomPDF repeats <thead> on every page --}}
<table style="margin-top:8pt;">
    <thead>
        <tr>
            <th style="width:4%;">#</th>
            <th style="width:22%;">Description</th>
            <th style="width:9%;">HSN/SAC</th>
            <th style="width:7%;" class="num">Qty</th>
            <th style="width:11%;" class="num">Rate (₹)</th>
            <th style="width:10%;" class="num">Discount (₹)</th>
            <th style="width:12%;" class="num">Taxable (₹)</th>
            @if ($interState)
                <th style="width:12%;" class="num">IGST</th>
            @else
                <th style="width:6%;" class="num">CGST</th>
                <th style="width:6%;" class="num">SGST/UTGST</th>
            @endif
            <th style="width:12%;" class="num">Total (₹)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->position }}</td>
                <td>{{ $item->description }}
                    @if ($item->tax_category !== 'taxable')<div class="muted">{{ strtoupper(str_replace('_', ' ', $item->tax_category)) }}</div>@endif
                </td>
                <td>{{ $item->hsn_sac }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">{{ $val($item->unit_price) }}</td>
                <td class="num">{{ $val($item->discount) }}</td>
                <td class="num">{{ $val($item->taxable_amount) }}</td>
                @if ($interState)
                    <td class="num">{{ $val($item->igst) }}<div class="muted">@ {{ $item->rate }}%</div></td>
                @else
                    <td class="num">{{ $val($item->cgst) }}<div class="muted">@ {{ $item->rate / 2 }}%</div></td>
                    <td class="num">{{ !$val($invoice->utgst)->isZero() ? $val($item->utgst) : $val($item->sgst) }}
                        <div class="muted">@ {{ $item->rate / 2 }}%</div></td>
                @endif
                <td class="num">{{ $val($item->total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Totals + words + QR --}}
<table style="margin-top:8pt;">
    <tr>
        <td style="width:58%; border:none; vertical-align:top;">
            <div class="declaration" style="margin-top:10pt;">
                <strong>Amount in words:</strong> {{ $words }}
            </div>
            <div class="declaration" style="margin-top:8pt;">
                @if ($billOfSupply)
                    This is a bill of supply. No tax is charged on this document
                    @if (($supplier['gst_regime'] ?? '') === 'composition')
                        — the supplier is registered under the GST composition scheme and is not
                        eligible to collect tax on outward supplies.
                    @else
                        — the supplies listed are non-taxable/exempt.
                    @endif
                @else
                    @if ($invoice->reverse_charge)
                        Tax is payable by the recipient on reverse charge basis; the supplier has
                        collected no tax on this invoice.
                    @endif
                    @if ($invoice->supply_type === 'export')
                        Supply meant for export
                        {{ $invoice->zero_rated_mode === 'lut' ? 'under Letter of Undertaking (LUT) without payment of IGST.'
                            : 'with payment of IGST.' }}
                    @elseif ($invoice->supply_type === 'sez')
                        Supply meant for a Special Economic Zone unit
                        {{ $invoice->zero_rated_mode === 'lut' ? 'under Letter of Undertaking (LUT) without payment of IGST.'
                            : 'with payment of IGST.' }}
                    @endif
                @endif
            </div>
        </td>
        <td style="width:42%; border:none; vertical-align:top;">
            <table class="totals">
                <tr><td>Taxable value</td><td class="num">{{ $val($invoice->taxable_amount) }}</td></tr>
                @if (!$val($invoice->igst)->isZero())
                    <tr><td>IGST</td><td class="num">{{ $val($invoice->igst) }}</td></tr>
                @endif
                @if (!$val($invoice->cgst)->isZero())
                    <tr><td>CGST</td><td class="num">{{ $val($invoice->cgst) }}</td></tr>
                @endif
                @if (!$val($invoice->sgst)->isZero())
                    <tr><td>SGST</td><td class="num">{{ $val($invoice->sgst) }}</td></tr>
                @endif
                @if (!$val($invoice->utgst)->isZero())
                    <tr><td>UTGST</td><td class="num">{{ $val($invoice->utgst) }}</td></tr>
                @endif
                @if (!$val($invoice->recipient_tax)->isZero())
                    <tr><td>Tax under RCM (recipient's liability)</td><td class="num">{{ $val($invoice->recipient_tax) }}</td></tr>
                @endif
                <tr class="grand"><td>Total</td><td class="num">₹&nbsp;{{ $val($invoice->total) }}</td></tr>
            </table>
            @if ($isPosted && $paymentQr)
                <div style="margin-top:6pt; text-align:center;">
                    {!! $paymentQr !!}
                    <div class="muted" style="margin-top:3pt;">Scan to pay ₹ {{ $val($invoice->total) }} via UPI
                        (supplier payment QR — not an IRP e-invoice QR)</div>
                </div>
            @endif
        </td>
    </tr>
</table>

<div class="signature">
    <div class="muted">For <strong>{{ $supplier['name'] ?? '—' }}</strong></div>
    <div style="margin-top:18pt;" class="muted">Authorised signatory</div>
</div>
</body>
</html>
