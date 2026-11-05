<?php
declare(strict_types=1);

use App\Domain\Accounting\Models\{Ledger, LedgerGroup, Voucher};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{Company, FinancialYear, Party};
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\TenantContext;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || !str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('Set DB_CONNECTION=mysql and use a dedicated laravix_test_ database.');
}
$migrator = $app->make('migrator');
if (!$migrator->repositoryExists()) { $migrator->getRepository()->createRepository(); }
$migrator->run(database_path('migrations'));
(new Database\Seeders\StateSeeder)->run();
$company = Company::create(['name' => 'Concurrency test '.bin2hex(random_bytes(6)), 'state_code' => '27']);
$ids = $app->make(TenantContext::class)->run($company->id, function (): array {
    (new Database\Seeders\ChartOfAccountsSeeder)->run();
    $year = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    $ledger = Ledger::create(['code' => 'CUSTOMER', 'name' => 'Customer', 'ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id]);
    $party = Party::create(['ledger_id' => $ledger->id, 'name' => 'Customer', 'state_code' => '27']);
    $item = Item::create(['sku' => 'TEST', 'name' => 'Test', 'hsn_sac' => '8471']);
    $rate = TaxRate::create(['code' => 'TEST', 'rate' => '18', 'effective_from' => '2026-04-01', 'is_verified' => true]);
    $ids = [];
    for ($index = 0; $index < 8; $index++) {
        $invoice = Invoice::create(['financial_year_id' => $year->id, 'party_id' => $party->id, 'date' => '2026-11-05', 'place_of_supply' => '27']);
        $invoice->items()->create(['financial_year_id' => $year->id, 'item_id' => $item->id, 'tax_rate_id' => $rate->id,
            'sales_ledger_id' => Ledger::where('code', 'SALES')->sole()->id, 'position' => 1, 'description' => 'Test',
            'hsn_sac' => '8471', 'quantity' => '1', 'unit_price' => '1000.00']);
        $ids[] = $invoice->id;
    }
    return $ids;
});
$barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravix-barrier-'.bin2hex(random_bytes(8));
$workers = [];
try {
    foreach ([...$ids, $ids[0], $ids[1]] as $id) {
        $worker = new Process([PHP_BINARY, base_path('tests/Support/post-worker.php'), (string) $company->id, (string) $id, $barrier], base_path(), timeout: 45);
        $worker->start();
        $workers[] = $worker;
    }
    $deadline = microtime(true) + 25;
    do {
        $ready = count(array_filter($workers, fn (Process $p) => str_contains($p->getOutput(), 'READY')));
        if (microtime(true) > $deadline) { throw new RuntimeException('Workers failed to reach barrier.'); }
        usleep(10000);
    } while ($ready !== count($workers));
    touch($barrier);
    $success = $duplicates = 0;
    foreach ($workers as $worker) {
        $code = $worker->wait();
        if ($code === 0) { $success++; }
        elseif ($code === 3) { $duplicates++; }
        else { throw new RuntimeException($worker->getErrorOutput().$worker->getOutput()); }
    }
    $numbers = $app->make(TenantContext::class)->run($company->id, fn () => Voucher::orderBy('number')->pluck('number')->all());
    if ($success !== 8 || $duplicates !== 2 || $numbers !== range(1, 8)) {
        throw new RuntimeException('Concurrent posting did not produce exactly eight consecutive numbers and two duplicate rejections.');
    }
    echo "PASS: 10 simultaneous workers; 8 posts numbered 1–8; 2 duplicate attempts rejected.\n";
} finally {
    foreach ($workers as $worker) { if ($worker->isRunning()) { $worker->stop(); } }
    if (is_file($barrier)) { unlink($barrier); }
}
