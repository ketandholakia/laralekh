<?php

// Renders receipt and statement PDF samples covering the Step 4 surface into an
// output directory using an isolated in-memory SQLite database.
//
// Usage: php tools/render-receipt-samples.php <existing-output-directory>

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\AllocateReceipt;
use App\Domain\Sales\Actions\PostInvoice;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Actions\SaveReceiptDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\CustomerStatement;
use App\Domain\Sales\Services\ReceiptDocuments;
use App\Domain\Shared\Tenancy\TenantContext;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$out = $argv[1] ?? null;
if ($out === null || ! is_dir($out)) {
    fwrite(STDERR, "Usage: php tools/render-receipt-samples.php <existing-output-directory>\n");
    exit(1);
}

config(['cache.default' => 'array', 'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
DB::purge();
$migrator = $app->make('migrator');
if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}
$migrator->run(database_path('migrations'));

DB::table('states')->insertOrIgnore(['code' => '27', 'name' => 'Maharashtra']);
$company = Company::create(['name' => 'LaraLekh Sample Traders', 'gstin' => '27AAAPL1234C1ZV',
    'state_code' => '27', 'address' => "14, Andheri East\nMumbai 400069", 'gst_regime' => 'regular']);

app(TenantContext::class)->run($company->id, function () use ($company, $out): void {
    (new ChartOfAccountsSeeder)->run();
    $year = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    session(['company_id' => $company->id, 'financial_year_id' => $year->id]);
    $receivables = LedgerGroup::where('code', 'RECEIVABLES')->sole();
    $customer = Ledger::create(['code' => 'CUSTOMER', 'name' => 'Sundry debtors control', 'ledger_group_id' => $receivables->id]);
    $party = Party::create(['ledger_id' => $customer->id, 'name' => 'Pune Retail Pvt Ltd',
        'gstin' => '27AABCU9603R1ZM', 'state_code' => '27', 'address' => "5, MG Road\nPune 411001",
        'registration_type' => 'regular']);
    $sales = Ledger::where('code', 'SALES')->sole();
    $cash = Ledger::where('code', 'CASH')->sole();
    $bank = Ledger::where('code', 'BANK')->sole();
    $item = Item::create(['sku' => 'WIDGET', 'name' => 'Precision Widget',
        'hsn_sac' => '8471', 'sale_price' => '1250.00']);
    $today = now()->toDateString();

    $invoice = function (string $amount, string $date) use ($party, $item, $sales): Invoice {
        $draft = app(SaveInvoiceDraft::class)->handle(null, [
            'party_id' => $party->id, 'date' => $date, 'place_of_supply' => '27'], [
                ['item_id' => $item->id, 'sales_ledger_id' => $sales->id, 'tax_rate_id' => null,
                    'description' => 'Precision Widget', 'hsn_sac' => '8471', 'quantity' => '1',
                    'unit_price' => $amount, 'discount' => '0.00', 'tax_category' => 'exempt'],
            ]);

        return app(PostInvoice::class)->handle($draft->id)->fresh(['items', 'party', 'voucher']);
    };

    $draft = fn (string $amount, Ledger $ledger, string $date, ?string $ref): Receipt => app(SaveReceiptDraft::class)
        ->handle(null, ['party_id' => $party->id, 'ledger_id' => $ledger->id, 'date' => $date,
            'payment_reference' => $ref, 'narration' => 'On account', 'amount' => $amount]);

    // 1. Normal receipt, fully allocated to one invoice at posting.
    $inv1 = $invoice('11800.00', $today);
    $r1 = app(PostReceipt::class)->handle($draft('11800.00', $bank, $today, 'UTR-2026-1001')->id, [$inv1->id => '11800.00']);
    file_put_contents($out.'/receipt-normal.pdf', app(ReceiptDocuments::class)->receipt($r1->fresh())->output());

    // 2. Partial payment across two invoices; funds remain unapplied.
    $inv2 = $invoice('9000.00', $today);
    $inv3 = $invoice('6000.00', $today);
    $r2 = app(PostReceipt::class)->handle($draft('8000.00', $cash, $today, 'CHQ-4410')->id,
        [$inv2->id => '6000.00', $inv3->id => '1000.00']);
    file_put_contents($out.'/receipt-partial.pdf', app(ReceiptDocuments::class)->receipt($r2->fresh())->output());
    // Later allocation against the same receipt (not on the original PDF).
    app(AllocateReceipt::class)->handle($r2->id, [$inv3->id => '1000.00']);

    // 3. Unapplied advance.
    $r3 = app(PostReceipt::class)->handle($draft('15000.00', $bank, $today, 'UTR-2026-1042')->id);
    file_put_contents($out.'/receipt-advance.pdf', app(ReceiptDocuments::class)->receipt($r3->fresh())->output());

    // 4. Draft preview (no number, no posting).
    $draftReceipt = $draft('5000.00', $bank, $today, 'UTR-DRAFT')->fresh();
    file_put_contents($out.'/receipt-draft-preview.pdf', app(ReceiptDocuments::class)->receipt($draftReceipt)->output());

    // 5. Multi-page statement: 45 invoices plus a receipt.
    for ($i = 0; $i < 45; $i++) {
        $invoice('1000.00', $today);
    }
    app(PostReceipt::class)->handle($draft('12000.00', $bank, $today, 'UTR-2026-1100')->id);
    $statement = app(CustomerStatement::class)->build($party->id, '2026-04-01', $today);
    file_put_contents($out.'/customer-statement.pdf', app(ReceiptDocuments::class)->statement($statement)->output());

    echo 'closing=', (string) $statement['closing'], ' ledger=', (string) $statement['ledger_as_of'], PHP_EOL;
});
echo "Receipt samples written to: {$out}\n";
