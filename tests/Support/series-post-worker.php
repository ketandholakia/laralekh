<?php
declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || !str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('A dedicated MySQL test database is required.');
}
[$script, $companyId, $type, $documentId, $barrier, $expectFailure] = $argv + [null, null, null, null, null, '0'];
echo "READY\n";
$deadline = microtime(true) + 30;
while (!is_file($barrier)) {
    if (microtime(true) > $deadline) throw new RuntimeException('Worker barrier timed out.');
    usleep(10000);
}
try {
    $number = $app->make(App\Domain\Shared\Tenancy\TenantContext::class)->run((int)$companyId, function () use ($app,$type,$documentId) {
        return match ($type) {
            'sales' => $app->make(App\Domain\Accounting\Services\PostingService::class)->postSalesInvoice((int)$documentId)->number,
            'purchase' => $app->make(App\Domain\Purchase\Actions\PostPurchaseBill::class)->handle((int)$documentId)->number,
            default => throw new InvalidArgumentException('Unknown document type.'),
        };
    });
    if ($expectFailure === '1') throw new RuntimeException('The expected failing post unexpectedly succeeded.');
    echo json_encode(['number'=>(int)$number], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if ($expectFailure !== '1' || !str_contains($error->getMessage(), 'simulated post failure')) {
        fwrite(STDERR, (string)$error); exit(1);
    }
    echo "EXPECTED_FAILURE\n";
}
