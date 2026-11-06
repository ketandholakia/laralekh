<?php

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Masters\Models\State;
use App\Domain\Shared\Tenancy\TenantContext;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\GstSlabSeeder;
use Database\Seeders\StateSeeder;

it('seeds all 38 codes while marking retired codes inactive', function () {
    (new StateSeeder)->run();
    (new StateSeeder)->run();
    expect(State::count())->toBe(38)->and(State::find('25')->is_active)->toBeFalse()
        ->and(State::find('28')->is_active)->toBeFalse()->and(State::find('38')->uses_utgst)->toBeTrue()
        ->and(State::find('34')->uses_utgst)->toBeFalse();
});

it('seeds per-company accounts and rates idempotently without overwriting customization', function () {
    $ctx = app(TenantContext::class);
    $a = companyFixture();
    $b = companyFixture();
    foreach ([$a, $b] as $company) {
        $ctx->run($company->id, function () {
            (new ChartOfAccountsSeeder)->run();
            (new GstSlabSeeder)->run();
            Ledger::where('code', 'SALES')->update(['name' => 'Custom sales']);
            (new ChartOfAccountsSeeder)->run();
            (new GstSlabSeeder)->run();
            expect(Ledger::count())->toBe(18)->and(TaxRate::count())->toBe(8)
                ->and(TaxRate::where('is_verified', true)->count())->toBe(0)
                ->and(Ledger::where('code', 'SALES')->sole()->name)->toBe('Custom sales');
        });
    }
});
