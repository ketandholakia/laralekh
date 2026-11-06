<?php

declare(strict_types=1);
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\ReceiptService;
use App\Domain\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, (string) $e);
    exit(1);
});
if (config('database.default') !== 'mysql' || ! str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('Dedicated MySQL test database required.');
}
CarbonImmutable::setTestNow('2026-11-06');
echo "READY\n";
$deadline = microtime(true) + 30;
// Barrier is always the last argument.
$barrier = $argv[count($argv) - 1];
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    }
    usleep(10000);
}
try {
    $workerArgs = $argv;
    app(TenantContext::class)->run((int) $argv[1], function () use ($workerArgs): void {
        match ($workerArgs[2]) {
            // Post a receipt draft and allocate it in the same transaction.
            'post' => app(ReceiptService::class)->post(
                Receipt::findOrFail((int) $workerArgs[3]), []),
            // Allocate funds from a posted receipt to an invoice. Mirrors the
            // AllocateReceipt action: the whole decision must be one transaction.
            'allocate' => (function () use ($workerArgs): void {
                DB::transaction(function () use ($workerArgs): void {
                    $service = app(ReceiptService::class);
                    $receipt = Receipt::findOrFail((int) $workerArgs[3]);
                    $canonical = $service->validateAllocations($receipt, [(int) $workerArgs[4] => (string) $workerArgs[5]]);
                    $service->applyAllocations($receipt, $canonical, null);
                }, 3);
            })(),
            // Undo one active allocation.
            'undo' => app(ReceiptService::class)->undoAllocations(
                Receipt::findOrFail((int) $workerArgs[3]), [(int) $workerArgs[4]], null),
            // Exact receipt reversal.
            'reverse' => app(ReceiptService::class)->reverse(
                Receipt::findOrFail((int) $workerArgs[3]), (int) $workerArgs[4], '2026-11-06',
                'Concurrent receipt reversal test', null),
            default => throw new RuntimeException('Unknown job kind'),
        };
    });
    echo "POSTED\n";
} catch (DomainException $e) {
    echo 'REJECTED: '.$e->getMessage()."\n";
    exit(3);
}
