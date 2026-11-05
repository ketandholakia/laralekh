<?php
use App\Domain\Sales\Models\{Invoice, SalesCorrection};
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Masters\Models\FinancialYear;
use Illuminate\Support\Str;

function correctionSource(array $overrides = [], array $lineOverrides = []): Invoice
{
    (new \Database\Seeders\ChartOfAccountsSeeder)->run();
    $year = FinancialYear::firstOrCreate(['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    [$party, $item, $sales] = masters();
    $rate = verifiedRate();
    $invoice = Invoice::create($overrides + ['financial_year_id' => $year->id, 'party_id' => $party->id,
        'date' => '2026-11-01', 'place_of_supply' => '27', 'series' => 'SI']);
    $invoice->items()->create($lineOverrides + ['financial_year_id' => $year->id, 'item_id' => $item->id,
        'sales_ledger_id' => $sales->id, 'tax_rate_id' => $rate->id, 'position' => 1,
        'description' => 'Original widget', 'hsn_sac' => '8471', 'quantity' => '10', 'unit_price' => '100.00']);
    app(PostingService::class)->postSalesInvoice($invoice->id);
    return $invoice->fresh(['items', 'voucher']);
}

function rawCorrectionData(Invoice $invoice): array
{
    return ['financial_year_id' => $invoice->financial_year_id, 'original_financial_year_id' => $invoice->financial_year_id,
        'invoice_id' => $invoice->id, 'kind' => 'credit', 'treatment' => 'commercial', 'series' => 'CCN',
        'request_key' => (string) Str::uuid(), 'date' => '2026-11-05', 'reason_code' => 'commercial', 'reason' => 'Commercial concession',
        'supplier_snapshot' => $invoice->supplier_snapshot, 'party_snapshot' => $invoice->party_snapshot,
        'original_snapshot' => ['number' => $invoice->series.'/'.$invoice->number]];
}
