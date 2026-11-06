<?php

declare(strict_types=1);
use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostInvoice;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Actions\SaveReceiptDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptAllocation;
use App\Domain\Sales\Models\ReceiptAllocationReversal;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, (string) $e);
    exit(1);
});
if (config('database.default') !== 'mysql' || ! str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('Dedicated MySQL test database required.');
}
require __DIR__.'/../tests/Support/web-helpers.php';
CarbonImmutable::setTestNow('2026-11-06');
(new StateSeeder)->run();
$company = companyFixture('Receipt concurrency '.bin2hex(random_bytes(6)));
app(TenantContext::class)->run($company->id, function () use ($company) {
    (new ChartOfAccountsSeeder)->run();
    $year = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    session(['company_id' => $company->id, 'financial_year_id' => $year->id]);
    $receivables = LedgerGroup::where('code', 'RECEIVABLES')->sole();
    $customerLedger = Ledger::create(['code' => 'CUSTOMER', 'name' => 'Customer', 'ledger_group_id' => $receivables->id]);
    $party = Party::create(['name' => 'Race customer', 'state_code' => '27', 'ledger_id' => $customerLedger->id]);
    $cash = Ledger::where('code', 'CASH')->sole();
    $draft = fn (string $amount): Receipt => app(SaveReceiptDraft::class)->handle(null, [
        'party_id' => $party->id, 'ledger_id' => $cash->id, 'date' => '2026-11-06', 'amount' => $amount,
        'request_key' => 'race-'.bin2hex(random_bytes(8)),
    ]);
    $postInvoice = function () use ($party): Invoice {
        $item = Item::firstOrCreate(['sku' => 'W'], ['name' => 'W', 'hsn_sac' => '8471']);
        $draft = app(SaveInvoiceDraft::class)->handle(null, [
            'party_id' => $party->id, 'date' => '2026-11-06', 'place_of_supply' => '27',
        ], [['item_id' => $item->id, 'sales_ledger_id' => Ledger::where('code', 'SALES')->sole()->id,
            'tax_rate_id' => null, 'description' => 'W', 'hsn_sac' => '8471', 'quantity' => '1',
            'unit_price' => '1000.00', 'discount' => '0.00', 'tax_category' => 'exempt']]);

        return app(PostInvoice::class)->handle($draft->id)->fresh();
    };

    $race = function (array $jobs, int $expectedSuccess, int $expectedReject) use ($company): array {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravix-receipt-'.bin2hex(random_bytes(8));
        $workers = [];
        try {
            foreach ($jobs as $job) {
                $p = new Process([PHP_BINARY, base_path('tests/Support/receipt-worker.php'), (string) $company->id,
                    ...$job, $barrier], base_path(), timeout: 60);
                $p->start();
                $workers[] = $p;
            }
            $deadline = microtime(true) + 30;
            do {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Workers failed to reach barrier');
                }
                $ready = count(array_filter($workers, fn ($p) => str_contains($p->getOutput(), 'READY')));
                usleep(10000);
            } while ($ready !== count($workers));
            touch($barrier);
            $success = $rejected = 0;
            foreach ($workers as $p) {
                $code = $p->wait();
                $out = trim(preg_replace('/\s+/', ' ', $p->getOutput()));
                if ($code === 0 && str_contains($out, 'POSTED')) {
                    $success++;
                    echo '  ok: '.$out.PHP_EOL;
                } elseif ($code === 3) {
                    $rejected++;
                    echo '  rejected: '.$out.PHP_EOL;
                } else {
                    throw new RuntimeException($p->getErrorOutput().$out);
                }
            }
            if (($expectedSuccess >= 0 && $success !== $expectedSuccess)
                || ($expectedReject >= 0 && $rejected !== $expectedReject)) {
                throw new RuntimeException("Expected $expectedSuccess successes / $expectedReject rejections; got $success / $rejected");
            }

            return [$success, $rejected];
        } finally {
            foreach ($workers as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
        }
    };

    // 1. Duplicate post of the same receipt: exactly one voucher, one number.
    $receipt = $draft('300.00');
    $race([['post', (string) $receipt->id], ['post', (string) $receipt->id]], 1, 1);
    if (Voucher::where('type', 'receipt')->count() !== 1 || Receipt::sole()->number !== 1) {
        throw new RuntimeException('Duplicate receipt post');
    }

    // 2. Concurrent allocation of one receipt across two invoices: both fit, funds exact.
    $one = $postInvoice();
    $two = $postInvoice();
    $receipt = app(PostReceipt::class)->handle($draft('600.00')->id);
    $race([['allocate', (string) $receipt->id, (string) $one->id, '300.00'],
        ['allocate', (string) $receipt->id, (string) $two->id, '300.00']], 2, 0);
    if (ReceiptAllocation::count() !== 2) {
        throw new RuntimeException('Concurrent allocation lost a row');
    }

    // 3. Over-allocation race: two invoices, one receipt with funds for one.
    $three = $postInvoice();
    $four = $postInvoice();
    $receipt = app(PostReceipt::class)->handle($draft('500.00')->id);
    echo '  scenario3 receipt status=', $receipt->fresh()->status, ' number=', $receipt->fresh()->number ?? 'null', PHP_EOL;
    $race([['allocate', (string) $receipt->id, (string) $three->id, '500.00'],
        ['allocate', (string) $receipt->id, (string) $four->id, '500.00']], 1, 1);

    // 4. Concurrent payment of the same invoice from two receipts: sums stay exact.
    $five = $postInvoice();
    $r1 = app(PostReceipt::class)->handle($draft('400.00')->id);
    $r2 = app(PostReceipt::class)->handle($draft('600.00')->id);
    $race([['allocate', (string) $r1->id, (string) $five->id, '400.00'],
        ['allocate', (string) $r2->id, (string) $five->id, '600.00']], 2, 0);

    // 5. Allocation versus receipt-reversal race. A reversal is allowed to run
    // after an allocation: it atomically undoes the active allocation, so both
    // may succeed as long as the end state is exactly reversed.
    $six = $postInvoice();
    $receipt = app(PostReceipt::class)->handle($draft('500.00')->id);
    $balanceBefore = (string) app(SettlementService::class)->ledgerBalance($customerLedger->id);
    [$success] = $race([['allocate', (string) $receipt->id, (string) $six->id, '500.00'],
        ['reverse', (string) $receipt->id, (string) $year->id]], -1, -1);
    $reversal = ReceiptReversal::where('original_receipt_id', $receipt->id)->sole();
    if ($success === 2) {
        // Allocation won the lock: the reversal then undid it atomically.
        if (ReceiptAllocation::where('receipt_id', $receipt->id)->count() !== 1
            || ReceiptAllocationReversal::count() !== 1
            || count($reversal->undone_allocations ?? []) !== 1) {
            throw new RuntimeException('Allocation-then-reversal end state inconsistent');
        }
    } else {
        // Reversal won the lock: the allocation was refused outright.
        if (ReceiptAllocation::where('receipt_id', $receipt->id)->count() !== 0) {
            throw new RuntimeException('Reversal-then-allocation left an allocation behind');
        }
    }
    // Allocations never touch the ledger; the exact reversal posts one
    // swapping voucher, so the receivable must rise by exactly the receipt
    // amount under either ordering.
    if ((string) app(SettlementService::class)->ledgerBalance($customerLedger->id)
        !== bcadd($balanceBefore, '500.00', 2)) {
        throw new RuntimeException('Race left the customer ledger inconsistent');
    }

    // 6. Concurrent undo of the same allocation: exactly one succeeds.
    $seven = $postInvoice();
    $receipt = app(PostReceipt::class)->handle($draft('250.00')->id, [$seven->id => '250.00']);
    $allocationId = ReceiptAllocation::where('receipt_id', $receipt->id)->sole()->id;
    $race([['undo', (string) $receipt->id, (string) $allocationId],
        ['undo', (string) $receipt->id, (string) $allocationId]], 1, 1);
    if (ReceiptAllocationReversal::where('allocation_id', $allocationId)->count() !== 1) {
        throw new RuntimeException('Double undo');
    }

    // 7. Four simultaneous valid receipts: gapless SR numbers.
    $jobs = [];
    foreach (['100.00', '200.00', '300.00', '400.00'] as $amount) {
        $jobs[] = ['post', (string) $draft($amount)->id];
    }
    $race($jobs, 4, 0);
    $numbers = Voucher::where('type', 'receipt')->orderBy('number')->pluck('number')->all();
    if ($numbers !== range(1, count($numbers)) || NumberSequence::where('series', 'SR')->sole()->last_number !== count($numbers)) {
        throw new RuntimeException('SR sequence gap or duplicate');
    }
    echo 'PASS: duplicate post, concurrent allocation, over-allocation, same-invoice payments, '.
        "allocation-versus-reversal, double undo rejected, gapless SR numbering.\n";
});
