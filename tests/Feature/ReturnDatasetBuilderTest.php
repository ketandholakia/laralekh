<?php
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Accounting\Models\{Ledger,LedgerGroup};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{FinancialYear,Party};

function returnSalesFixture(array $invoiceOverrides=[],array $lineOverrides=[]):Invoice
{
    (new Database\Seeders\ChartOfAccountsSeeder)->run();
    $year=FinancialYear::firstOrCreate(['name'=>'2026-27'],['starts_on'=>'2026-04-01','ends_on'=>'2027-03-31']);
    $ledger=Ledger::firstOrCreate(['code'=>'RETURN_CUSTOMER'],['name'=>'Return customer','ledger_group_id'=>LedgerGroup::where('code','RECEIVABLES')->sole()->id]);
    $party=Party::firstOrCreate(['ledger_id'=>$ledger->id],['name'=>'Return customer','state_code'=>'27']);
    $item=Item::firstOrCreate(['sku'=>'RETURN_ITEM'],['name'=>'Return item','hsn_sac'=>'8471']);
    $rate=TaxRate::firstOrCreate(['code'=>'RETURN_18'],['rate'=>'18','effective_from'=>'2026-04-01','is_verified'=>true]);
    $invoice=Invoice::create(array_merge(['financial_year_id'=>$year->id,'party_id'=>$party->id,'date'=>'2026-11-06','place_of_supply'=>'27','series'=>'SI'],$invoiceOverrides));
    $invoice->items()->create(array_merge(['financial_year_id'=>$year->id,'item_id'=>$item->id,'sales_ledger_id'=>Ledger::where('code','SALES')->sole()->id,
        'tax_rate_id'=>$rate->id,'position'=>1,'description'=>'Return item','hsn_sac'=>'8471','quantity'=>'1','unit_price'=>'1000.00','discount'=>'0.00'],$lineOverrides));
    return $invoice;
}

it('classifies posted outward invoices into return dataset tables using period rules', function () {
    inSalesCompany(function (Company $company) {
        $registered = returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'27']);
        $registered->party->update(['registration_type'=>'regular','gstin'=>'27ABCDE1234F1Z5']);
        app(PostingService::class)->postSalesInvoice($registered->id);
        $registered->party->update(['registration_type'=>'unregistered','gstin'=>null]);
        $largeInterstate=returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'24'],['unit_price'=>'100000.01']);
        app(PostingService::class)->postSalesInvoice($largeInterstate->id);
        $out=app(ReturnDatasetBuilder::class)->build($company,'2026-11',500000000);
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
            fn()=>app(ReturnDatasetBuilder::class)->build($company,'2026-11',500000000)))
            ->toThrow(DomainException::class,'active tenant');
    });
});
