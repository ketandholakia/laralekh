<?php

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Shared\Models\Role;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

it('fails closed without a tenant context', function () {
    Ledger::query()->get();
})->throws(LogicException::class, 'company context');

it('isolates reads and restores nested contexts', function () {
    $a = companyFixture('A');
    $b = companyFixture('B');
    $ctx = app(TenantContext::class);
    $ctx->run($a->id, function () use ($ctx, $a, $b) {
        LedgerGroup::create(['code' => 'ASSET', 'name' => 'Assets A', 'nature' => 'asset']);
        $ctx->run($b->id, function () {
            expect(LedgerGroup::count())->toBe(0);
            LedgerGroup::create(['code' => 'ASSET', 'name' => 'Assets B', 'nature' => 'asset']);
        });
        expect($ctx->id())->toBe($a->id)->and(LedgerGroup::pluck('name')->all())->toBe(['Assets A']);
    });
    expect(fn () => $ctx->id())->toThrow(LogicException::class);
});

it('restores context after an exception', function () {
    $ctx = app(TenantContext::class);
    try {
        $ctx->run(1, fn () => throw new RuntimeException('stop'));
    } catch (RuntimeException) {
    }
    expect(fn () => $ctx->id())->toThrow(LogicException::class);
});

it('rejects cross-company model creation', function () {
    $a = companyFixture();
    $b = companyFixture();
    app(TenantContext::class)->run($a->id, fn () => LedgerGroup::create([
        'company_id' => $b->id, 'code' => 'A', 'name' => 'Assets', 'nature' => 'asset',
    ]));
})->throws(LogicException::class, 'Cross-company');

it('rejects cross-company foreign keys in the database', function () {
    $a = companyFixture();
    $b = companyFixture();
    $ctx = app(TenantContext::class);
    $group = $ctx->run($b->id, fn () => LedgerGroup::create(['code' => 'A', 'name' => 'Assets', 'nature' => 'asset']));
    $ctx->run($a->id, fn () => Ledger::create(['ledger_group_id' => $group->id, 'code' => 'CASH', 'name' => 'Cash']));
})->throws(QueryException::class);

it('requires unique financial-year names within a company', function () {
    $a = companyFixture();
    app(TenantContext::class)->run($a->id, function () {
        $data = ['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'];
        FinancialYear::create($data);
        FinancialYear::create($data);
    });
})->throws(QueryException::class);

it('rejects bulk changes to company ownership', function () {
    $a = companyFixture();
    $b = companyFixture();
    app(TenantContext::class)->run($a->id, fn () => LedgerGroup::query()->update(['company_id' => $b->id]));
})->throws(LogicException::class, 'ownership is immutable');

it('scopes Spatie roles to the active company', function () {
    $a = companyFixture();
    $b = companyFixture();
    $ctx = app(TenantContext::class);
    $ctx->run($a->id, fn () => Role::create(['name' => 'accountant', 'guard_name' => 'web']));
    $ctx->run($b->id, function () {
        expect(Role::count())->toBe(0);
        Role::create(['name' => 'accountant', 'guard_name' => 'web']);
    });
    expect($ctx->run($a->id, fn () => Role::count()))->toBe(1);
});
