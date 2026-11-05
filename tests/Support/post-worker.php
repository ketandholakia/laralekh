<?php
declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || !str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('A dedicated MySQL test database is required.');
}
echo "READY\n";
$deadline = microtime(true) + 30;
while (!is_file($argv[3])) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Worker barrier timed out.'); }
    usleep(10000);
}
try {
    $voucher = $app->make(App\Domain\Shared\Tenancy\TenantContext::class)->run((int) $argv[1],
        fn () => $app->make(App\Domain\Accounting\Services\PostingService::class)->postSalesInvoice((int) $argv[2]));
    echo json_encode(['number' => $voucher->number], JSON_THROW_ON_ERROR)."\n";
} catch (DomainException $error) {
    if (!str_contains($error->getMessage(), 'already posted')) { throw $error; }
    echo "DUPLICATE_REJECTED\n";
    exit(3);
}
