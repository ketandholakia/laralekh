<?php
declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never { fwrite(STDERR, (string) $e); exit(1); });
if (config('database.default') !== 'mysql' || !str_starts_with((string) config('database.connections.mysql.database'), 'laravix_test_')) {
    throw new RuntimeException('Dedicated MySQL test database required.');
}
Carbon\CarbonImmutable::setTestNow('2026-11-05');
echo "READY\n";
$deadline = microtime(true) + 30;
while (!is_file($argv[5])) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Barrier timeout'); }
    usleep(10000);
}
try {
    app(App\Domain\Shared\Tenancy\TenantContext::class)->run((int) $argv[1], function () use ($argv) {
        if ($argv[2] === 'note') { app(App\Domain\Sales\Services\CorrectionService::class)->post((int) $argv[3]); }
        else { app(App\Domain\Accounting\Services\ReversalService::class)->reverse((int) $argv[3], (int) $argv[4], '2026-11-05', 'Concurrent reversal test'); }
    });
    echo "POSTED\n";
} catch (DomainException $e) {
    echo "REJECTED: ".$e->getMessage()."\n";
    exit(3);
}

