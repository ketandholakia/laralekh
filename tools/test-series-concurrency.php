<?php

declare(strict_types=1);

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\TenantContext;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('Use a dedicated laravix_test_ MySQL database.');
}
try {
    DB::connection('mysql')->getPdo();
} catch (Throwable $error) {
    $hint = str_contains($error->getMessage(), 'Unknown database')
        ? ' Create the dedicated test database with: & "$env:LOCALAPPDATA/laravix/mysql-8.4.13-winx64/bin/mysql.exe" --no-defaults --host=127.0.0.1 --port=33360 --user=root -e "CREATE DATABASE IF NOT EXISTS laravix_test_suite".'
        : ' Start the instance from the repository root with: pwsh -File .\\local-mysql.ps1.';
    fwrite(STDERR, 'Cannot connect to MySQL at '.config('database.connections.mysql.host').':'.config('database.connections.mysql.port').'.'.$hint.' Then retry: pwsh -File .\\tools\\run-series-concurrency.ps1. Original error: '.$error->getMessage()."\n");
    exit(2);
}
$migrator = $app->make('migrator');
if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}
try {
    $migrator->run(database_path('migrations'));
} catch (Throwable $error) {
    fwrite(STDERR, 'Series concurrency test NOT RUN: the dedicated MySQL database could not be migrated. Resolve the migration failure before treating 0.2 as verified. Existing migrations are not changed by this harness. Original error: '.$error->getMessage()."\n");
    exit(3);
}
(new StateSeeder)->run();
$company = Company::create(['name' => 'Series concurrency '.bin2hex(random_bytes(5)), 'state_code' => '27']);
$ids = $app->make(TenantContext::class)->run($company->id, function () {
    (new ChartOfAccountsSeeder)->run();
    $year = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    $customerLedger = Ledger::create(['code' => 'RACE_CUSTOMER', 'name' => 'Race customer', 'ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id]);
    $customer = Party::create(['ledger_id' => $customerLedger->id, 'name' => 'Race customer', 'state_code' => '27']);
    $supplierLedger = Ledger::create(['code' => 'RACE_SUPPLIER', 'name' => 'Race supplier', 'ledger_group_id' => LedgerGroup::where('code', 'PAYABLES')->sole()->id]);
    $supplier = Party::create(['ledger_id' => $supplierLedger->id, 'name' => 'Race supplier', 'state_code' => '27']);
    $item = Item::create(['sku' => 'RACE_ITEM', 'name' => 'Race item', 'hsn_sac' => '1234']);
    $rate = TaxRate::create(['code' => 'RACE_18', 'rate' => '18', 'effective_from' => '2026-04-01', 'is_verified' => true]);
    $sales = [];
    $purchases = [];
    for ($i = 0; $i < 3; $i++) {
        $invoice = Invoice::create(['financial_year_id' => $year->id, 'party_id' => $customer->id, 'date' => '2026-11-06', 'place_of_supply' => '27', 'series' => 'SI']);
        $invoice->items()->create(['financial_year_id' => $year->id, 'item_id' => $item->id, 'tax_rate_id' => $rate->id, 'sales_ledger_id' => Ledger::where('code', 'SALES')->sole()->id, 'position' => 1, 'description' => 'Race item', 'hsn_sac' => '1234', 'quantity' => '1', 'unit_price' => '100.00']);
        $sales[] = $invoice->id;
        $bill = PurchaseBill::create(['financial_year_id' => $year->id, 'party_id' => $supplier->id, 'supplier_bill_number' => 'RACE-'.$i, 'supplier_bill_date' => '2026-11-06', 'date' => '2026-11-06', 'place_of_supply' => '27', 'series' => 'PB']);
        $bill->items()->create(['financial_year_id' => $year->id, 'item_id' => $item->id, 'purchase_ledger_id' => Ledger::where('code', 'PURCHASES')->sole()->id, 'tax_rate_id' => $rate->id, 'position' => 1, 'description' => 'Race item', 'hsn_sac' => '1234', 'quantity' => '1', 'unit_price' => '100.00']);
        $purchases[] = $bill->id;
    }

    return compact('sales', 'purchases');
});
$connection = DB::connection();
$runWorkers = function (string $type, array $ids, bool $expectFailure = false): array {
    $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravix-series-'.bin2hex(random_bytes(8));
    $workers = [];
    try {
        foreach ($ids as $id) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/series-post-worker.php'), (string) $GLOBALS['company']->id, $type, (string) $id, $barrier, $expectFailure ? '1' : '0'], base_path(), timeout: 45);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 25;
        do {
            $ready = count(array_filter($workers, fn (Process $p) => str_contains($p->getOutput(), 'READY')));
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach barrier.');
            }usleep(10000);
        } while ($ready !== count($workers));
        touch($barrier);
        $numbers = [];
        foreach ($workers as $worker) {
            $exit = $worker->wait();
            $output = $worker->getOutput();
            if ($expectFailure) {
                if ($exit !== 0 || ! str_contains($output, 'EXPECTED_FAILURE')) {
                    throw new RuntimeException($worker->getErrorOutput().$output);
                }

continue;
            }if ($exit !== 0) {
                throw new RuntimeException($worker->getErrorOutput().$output);
            }$jsonLine = array_values(array_filter(preg_split('/\R/', trim($output)), fn ($line) => str_starts_with($line, '{')))[0] ?? '';
            $result = json_decode($jsonLine, true, flags: JSON_THROW_ON_ERROR);
            $numbers[] = (int) $result['number'];
        }
        sort($numbers);

        return $numbers;
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }if (is_file($barrier)) {
            unlink($barrier);
        }
    }
};
$trigger = 'CREATE TRIGGER laravix_fail_after_allocate BEFORE INSERT ON vouchers FOR EACH ROW BEGIN IF NEW.number = 1 AND NEW.type IN (\'sales\', \'purchase\') THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'simulated post failure after allocation\'; END IF; END';
try {
    $connection->unprepared($trigger);
    $runWorkers('sales', [$ids['sales'][0]], true);
    $connection->unprepared('DROP TRIGGER laravix_fail_after_allocate');
    if ($runWorkers('sales', array_slice($ids['sales'], 1)) !== [1, 2]) {
        throw new RuntimeException('Sales series was not gap-free and sequential.');
    }
    $connection->unprepared($trigger);
    $runWorkers('purchase', [$ids['purchases'][0]], true);
    $connection->unprepared('DROP TRIGGER laravix_fail_after_allocate');
    if ($runWorkers('purchase', array_slice($ids['purchases'], 1)) !== [1, 2]) {
        throw new RuntimeException('Purchase series was not gap-free and sequential.');
    }
    $actual = $app->make(TenantContext::class)->run($company->id, fn () => Voucher::where('series', 'SI')->orderBy('number')->pluck('number')->all());
    if ($actual !== [1, 2]) {
        throw new RuntimeException('Sales vouchers have a sequence gap.');
    }
    $actual = $app->make(TenantContext::class)->run($company->id, fn () => Voucher::where('series', 'PB')->orderBy('number')->pluck('number')->all());
    if ($actual !== [1, 2]) {
        throw new RuntimeException('Purchase vouchers have a sequence gap.');
    }
    echo "PASS: parallel sales and purchase postings are sequential; failed posts rolled back allocation without gaps.\n";
} finally {
    try {
        $connection->unprepared('DROP TRIGGER IF EXISTS laravix_fail_after_allocate');
    } catch (Throwable) {
    }
}
