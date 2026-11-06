<?php

// Renders sample invoices covering the Step 2 PDF surface into an output
// directory using an isolated SQLite database (never production data).
//
// Usage: php tools/render-sample-pdfs.php <output-directory>

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostInvoice;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Support\AmountInWords;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$out = $argv[1] ?? null;
if ($out === null || ! is_dir($out)) {
    fwrite(STDERR, "Usage: php tools/render-sample-pdfs.php <existing-output-directory>\n");
    exit(1);
}

config([
    'cache.default' => 'array',
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
    'session.driver' => 'array',
]);
DB::purge();
$migrator = $app->make('migrator');
if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}
$migrator->run(database_path('migrations'));

function render(string $name, Invoice $invoice, string $out, Company $company): void
{
    $isPosted = $invoice->status === 'posted';
    $zero = Money::of('0');
    $val = fn ($v) => $v ?? $zero;
    $supplier = $isPosted ? ($invoice->supplier_snapshot ?? []) : $company->only(['name', 'gstin', 'state_code', 'address', 'gst_regime']);
    $party = $isPosted ? ($invoice->party_snapshot ?? []) : [
        'name' => $invoice->party->name, 'gstin' => $invoice->party->gstin,
        'state_code' => $invoice->party->state_code, 'address' => $invoice->party->address,
        'registration_type' => $invoice->party->registration_type,
    ];
    $allNonTaxable = $invoice->items->count() > 0 && $invoice->items->every(fn ($i) => $i->tax_category !== 'taxable');
    $billOfSupply = ($supplier['gst_regime'] ?? 'regular') === 'composition' || $allNonTaxable;
    $interState = ! $val($invoice->igst)->isZero();
    $pdf = Pdf::loadView('pdf.invoice', [
        'invoice' => $invoice, 'supplier' => $supplier, 'party' => $party,
        'isPosted' => $isPosted, 'billOfSupply' => $billOfSupply,
        'placeOfSupply' => $invoice->place_of_supply, 'words' => AmountInWords::inr($val($invoice->total)),
        'paymentQr' => null, 'interState' => $interState,
    ])->setPaper('a4', 'portrait');

    // Same footer path as the HTTP controller: render first, then stamp.
    $dompdf = $pdf->getDomPDF();
    $dompdf->render();
    $font = $dompdf->getFontMetrics()->getFont('helvetica', 'normal');
    $label = $isPosted ? '' : 'DRAFT PREVIEW - no invoice number has been allocated  -  ';
    $dompdf->getCanvas()->page_text(24, 20, $label.'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.42, 0.45, 0.50]);
    file_put_contents($out.'/'.$name.'.pdf', $dompdf->output());
    $pages = $dompdf->getCanvas()->get_page_count();
    echo str_pad($name, 26), ' pages=', $pages, ' total=', (string) $val($invoice->total), PHP_EOL;
}

DB::table('states')->insertOrIgnore(['code' => '27', 'name' => 'Maharashtra']);
DB::table('states')->insertOrIgnore(['code' => '24', 'name' => 'Gujarat']);

$ctx = app(TenantContext::class);
$company = Company::create(['name' => 'Laravix Sample Traders', 'gstin' => '27AAAPL1234C1ZV',
    'state_code' => '27', 'address' => "14, Andheri East\nMumbai 400069\nMaharashtra", 'gst_regime' => 'regular']);

$ctx->run($company->id, function () use ($company, $out): void {
    (new ChartOfAccountsSeeder)->run();
    $year = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    session(['company_id' => $company->id, 'financial_year_id' => $year->id]);

    $receivables = LedgerGroup::where('code', 'RECEIVABLES')->sole();
    $customer = Ledger::create(['code' => 'CUSTOMER', 'name' => 'Sundry debtors control', 'ledger_group_id' => $receivables->id]);
    $party = Party::create(['ledger_id' => $customer->id, 'name' => 'Pune Retail Pvt Ltd',
        'gstin' => '27AABCU9603R1ZM', 'state_code' => '27', 'address' => "5, MG Road\nPune 411001", 'registration_type' => 'regular']);
    $item = Item::create(['sku' => 'WIDGET', 'name' => 'Precision Widget', 'hsn_sac' => '8471', 'sale_price' => '1250.00']);
    $service = Item::create(['sku' => 'MAINT', 'name' => 'Annual maintenance', 'hsn_sac' => '9987', 'sale_price' => '4000.00', 'kind' => 'service']);
    $sales = Ledger::where('code', 'SALES')->sole();
    $rate18 = TaxRate::create(['code' => 'GST18', 'rate' => '18', 'effective_from' => '2026-04-01', 'is_verified' => true,
        'legal_reference' => 'Sample only']);
    $rate5 = TaxRate::create(['code' => 'GST5', 'rate' => '5', 'effective_from' => '2026-04-01', 'is_verified' => true,
        'legal_reference' => 'Sample only']);

    $save = fn (array $input, array $lines) => app(SaveInvoiceDraft::class)->handle(null, $input, $lines);
    $line = fn (Item $item, string $qty, string $price, ?TaxRate $rate, string $discount = '0.00') => [
        'item_id' => $item->id, 'sales_ledger_id' => $sales->id, 'tax_rate_id' => $rate?->id,
        'description' => $item->name, 'hsn_sac' => $item->hsn_sac, 'quantity' => $qty,
        'unit_price' => $price, 'discount' => $discount, 'tax_category' => $rate ? 'taxable' : 'exempt',
    ];
    $input = fn (array $over = []) => array_merge([
        'party_id' => $party->id, 'date' => '2026-11-05', 'due_date' => '2026-12-05', 'series' => 'SI',
        'supply_type' => 'domestic', 'place_of_supply' => '27', 'zero_rated_mode' => 'none',
        'zero_rated_eligible' => false, 'sez_authorized' => false, 'reverse_charge' => false,
    ], $over);

    // 1. Normal intra-state invoice.
    $normal = $save($input(), [$line($item, '4', '1250.00', $rate18, '500.00'), $line($service, '1', '4000.00', $rate18)]);
    render('normal-tax-invoice', app(PostInvoice::class)->handle($normal->id)->fresh(['items', 'party', 'voucher']), $out, $company);

    // 2. Multi-page invoice with 60 lines.
    $lines = [];
    for ($i = 1; $i <= 60; $i++) {
        $lines[] = $line($item, '1', '1250.00', $rate18);
    }
    $multi = $save($input(), $lines);
    render('multi-page-invoice', app(PostInvoice::class)->handle($multi->id)->fresh(['items', 'party', 'voucher']), $out, $company);

    // 3. Inter-state invoice.
    $inter = $save($input(['place_of_supply' => '24']), [$line($item, '2', '1250.00', $rate18)]);
    render('interstate-invoice', app(PostInvoice::class)->handle($inter->id)->fresh(['items', 'party', 'voucher']), $out, $company);

    // 4. Reverse charge.
    $rc = $save($input(['reverse_charge' => true]), [$line($service, '1', '4000.00', $rate18)]);
    render('reverse-charge-invoice', app(PostInvoice::class)->handle($rc->id)->fresh(['items', 'party', 'voucher']), $out, $company);

    // 5. Export under LUT.
    $export = $save($input(['supply_type' => 'export', 'place_of_supply' => '99', 'zero_rated_mode' => 'lut',
        'zero_rated_eligible' => true]), [$line($item, '10', '1250.00', $rate18)]);
    render('export-lut-invoice', app(PostInvoice::class)->handle($export->id)->fresh(['items', 'party', 'voucher']), $out, $company);

    // 6. Composition bill of supply (company regime switched, no GST collected).
    $company->update(['gst_regime' => 'composition']);
    $bos = $save($input(), [$line($item, '3', '1250.00', $rate18)]);
    render('composition-bill-of-supply', app(PostInvoice::class)->handle($bos->id)->fresh(['items', 'party', 'voucher']), $out, $company);
    $company->update(['gst_regime' => 'regular']);

    // 7. Draft preview (no posting, no number).
    $draft = $save($input(), [$line($item, '2', '1250.00', $rate18)]);
    render('draft-preview', $draft->fresh(['items', 'party', 'voucher']), $out, $company);
});

echo "Sample PDFs written to: {$out}\n";
