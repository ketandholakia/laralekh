<?php
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\TenantContext;

it('classifies posted outward invoices into return dataset tables using period rules', function () {
    inSalesCompany(function (Company $company) {
        $registered = returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'27']);
        $registered->party->update(['registration_type'=>'regular','gstin'=>'27ABCDE1234F1Z5']);
        app(PostingService::class)->postSalesInvoice($registered->id);
        $registered->party->update(['registration_type'=>'unregistered','gstin'=>null]);
        $largeInterstate=returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'24'],['unit_price'=>'100000.01']);
        app(PostingService::class)->postSalesInvoice($largeInterstate->id);
        $out=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        expect($out['b2b'])->toHaveCount(1)->and($out['b2cl'])->toHaveCount(1)
            ->and($out['b2cs'])->toBeEmpty()->and($out['validation']['valid'])->toBeTrue()
            ->and($out['meta']['b2cl_threshold_rupees'])->toBe(100000);
    });
});

it('fails closed if turnover is missing for HSN validation and refuses composition returns', function () {
    inSalesCompany(function (Company $company) {
        $invoice=returnSalesFixture(['date'=>'2026-11-06']); app(PostingService::class)->postSalesInvoice($invoice->id);
        $out=app(ReturnDatasetBuilder::class)->build($company,'2026-11');
        expect($out['validation']['valid'])->toBeFalse()
            ->and(collect($out['validation']['errors'])->pluck('code'))->toContain('missing_or_invalid_hsn');
        $company->update(['gst_regime'=>'composition']);
        expect(fn()=>app(ReturnDatasetBuilder::class)->build($company,'2026-11'))->toThrow(DomainException::class,'Composition');
    });
});

it('keeps return dataset access tenant scoped', function () {
    inSalesCompany(function (Company $company) {
        expect(fn()=>app(TenantContext::class)->run(companyFixture('Other tenant')->id,
            fn()=>app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000)))
            ->toThrow(DomainException::class,'active tenant');
    });
});
