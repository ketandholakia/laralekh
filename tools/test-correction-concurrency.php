<?php

declare(strict_types=1);
use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
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
require __DIR__.'/../tests/Support/correction-helpers.php';
CarbonImmutable::setTestNow('2026-11-05');
(new StateSeeder)->run();
$company = companyFixture('Correction concurrency '.bin2hex(random_bytes(6)));
$company->update(['gstin' => '27AAAAA'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT).'A1Z5']);
app(TenantContext::class)->run($company->id, function () use ($company) {
    $race = function (array $jobs, int $expected) use ($company) {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravix-correction-'.bin2hex(random_bytes(8));
        $workers = [];
        try {
            foreach ($jobs as [$kind, $id, $year]) {
                $p = new Process([PHP_BINARY, base_path('tests/Support/correction-worker.php'), (string) $company->id,
                    $kind, (string) $id, (string) $year, $barrier], base_path(), timeout: 45);
                $p->start();
                $workers[] = $p;
            }
            $deadline = microtime(true) + 25;
            do {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Workers failed to reach barrier');
                }
                $ready = count(array_filter($workers, fn ($p) => str_contains($p->getOutput(), 'READY')));
                usleep(10000);
            } while ($ready !== count($workers));
            touch($barrier);
            $success = 0;
            foreach ($workers as $p) {
                $code = $p->wait();
                if ($code === 0 && str_contains($p->getOutput(), 'POSTED')) {
                    $success++;
                } elseif ($code !== 3) {
                    throw new RuntimeException($p->getErrorOutput().$p->getOutput());
                }
            }
            if ($success !== $expected) {
                throw new RuntimeException("Expected $expected successes; got $success");
            }
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
    $a = correctionSource();
    $n1 = saveCorrection($a, '700.00');
    $n2 = saveCorrection($a, '700.00');
    $race([['note', $n1->id, $a->financial_year_id], ['note', $n2->id, $a->financial_year_id]], 1);
    if (SalesCorrection::where('invoice_id', $a->id)->where('status', 'posted')->count() !== 1) {
        throw new RuntimeException('Over-credit occurred');
    }
    $b = correctionSource();
    $race([['reverse', $b->voucher_id, $b->financial_year_id], ['reverse', $b->voucher_id, $b->financial_year_id]], 1);
    if (VoucherReversal::where('original_voucher_id', $b->voucher_id)->count() !== 1) {
        throw new RuntimeException('Duplicate reversal');
    }
    $c = correctionSource();
    $n = saveCorrection($c);
    $race([['note', $n->id, $c->financial_year_id], ['reverse', $c->voucher_id, $c->financial_year_id]], 1);
    if (SalesCorrection::where('invoice_id', $c->id)->where('status', 'posted')->count() +
        VoucherReversal::where('original_voucher_id', $c->voucher_id)->count() !== 1) {
        throw new RuntimeException('Note/reversal exclusion failed');
    }
    $d = correctionSource();
    $n = saveCorrection($d, '100.00');
    $race([['note', $n->id, $d->financial_year_id], ['note', $n->id, $d->financial_year_id]], 1);
    $e = correctionSource();
    $jobs = [];
    for ($j = 0; $j < 4; $j++) {
        $n = saveCorrection($e, '100.00');
        $jobs[] = ['note', $n->id, $e->financial_year_id];
    }
    $race($jobs, 4);
    foreach (['CN', 'RV'] as $series) {
        $numbers = Voucher::where('series', $series)->orderBy('number')->pluck('number')->all();
        if ($numbers !== range(1, count($numbers)) || NumberSequence::where('series', $series)->sole()->last_number !== count($numbers)) {
            throw new RuntimeException('Sequence gap or duplicate');
        }
    }
    echo "PASS: over-credit, duplicate reversal, note-versus-reversal, duplicate post, four valid concurrent notes; gapless CN/RV sequences.\n";
});
