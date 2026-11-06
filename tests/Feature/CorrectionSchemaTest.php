<?php

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

it('permits a correction in a later year with an explicit original year', function () {
    inSalesCompany(function () {
        $invoice = correctionSource();
        $year = FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        $note = SalesCorrection::create(['financial_year_id' => $year->id] + rawCorrectionData($invoice));
        expect($note->invoice->id)->toBe($invoice->id)->and($note->financial_year_id)->toBe($year->id);
    });
});

it('rejects a correction with the wrong source financial year', function () {
    inSalesCompany(function () {
        $invoice = correctionSource();
        $year = FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        expect(fn () => SalesCorrection::create(['original_financial_year_id' => $year->id] + rawCorrectionData($invoice)))
            ->toThrow(QueryException::class);
    });
});

it('rejects cross-company original references', function () {
    inSalesCompany(function () {
        $invoice = correctionSource();
        $other = companyFixture('Other');
        app(TenantContext::class)->run($other->id, function () use ($invoice) {
            $year = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
            expect(fn () => SalesCorrection::create(['financial_year_id' => $year->id] + rawCorrectionData($invoice)))
                ->toThrow(QueryException::class);
        });
    });
});
