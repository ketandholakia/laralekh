<?php
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Gst\Returns\{ReturnDatasetBuilder,ReturnLedgerTieOut};
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Services\CorrectionService;

it('uses a strict B2CL boundary and routes the boundary invoice to B2CS',function(){
    inSalesCompany(function(Company $company){
        $zero=TaxRate::create(['code'=>'RETURN_ZERO','rate'=>'0','effective_from'=>'2026-04-01','is_verified'=>true]);
        $at=returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'24'],['tax_rate_id'=>$zero->id,'unit_price'=>'100000.00']);
        $over=returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'24'],['tax_rate_id'=>$zero->id,'unit_price'=>'100000.01']);
        app(PostingService::class)->postSalesInvoice($at->id);app(PostingService::class)->postSalesInvoice($over->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        expect($dataset['b2cl'])->toHaveCount(1)->and($dataset['b2cl'][0]['value'])->toBe('100000.01')
            ->and($dataset['b2cs'])->toHaveCount(1)->and($dataset['b2cs'][0]['taxable_value'])->toBe('100000.00');
    });
});

it('classifies purchase reverse-charge bills and eligible input tax by head',function(){
    inSalesCompany(function(Company $company){
        $bill=returnPurchaseFixture(['reverse_charge'=>true]);app(PostPurchaseBill::class)->handle($bill->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        expect(app(ReturnLedgerTieOut::class)->assert($dataset))->toHaveKeys(['output','itc']);
        expect($dataset['purchases']['rcm'])->toHaveCount(1)
            ->and($dataset['purchases']['itc']['eligible']['cgst'])->toBe('90.00')
            ->and($dataset['purchases']['itc']['eligible']['sgst'])->toBe('90.00');
    });
});

it('classifies exports with and without payment and SEZ supplies separately',function(){
    inSalesCompany(function(Company $company){
        $with=returnSalesFixture(['date'=>'2026-11-06','supply_type'=>'export','place_of_supply'=>'99','zero_rated_mode'=>'with_payment','zero_rated_eligible'=>true]);
        $without=returnSalesFixture(['date'=>'2026-11-06','supply_type'=>'export','place_of_supply'=>'99','zero_rated_mode'=>'lut','zero_rated_eligible'=>true]);
        $sez=returnSalesFixture(['date'=>'2026-11-06','supply_type'=>'sez','place_of_supply'=>'27','zero_rated_mode'=>'lut','zero_rated_eligible'=>true,'sez_authorized'=>true]);
        foreach([$with,$without,$sez] as $invoice)app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        expect($dataset['exports']['with_payment'])->toHaveCount(1)->and($dataset['exports']['without_payment'])->toHaveCount(1)->and($dataset['sez'])->toHaveCount(1);
    });
});

it('reports a correction in its later reporting period instead of the source invoice period',function(){
    Carbon\CarbonImmutable::setTestNow('2026-12-10');
    try {
    inSalesCompany(function(Company $company){
        $invoice=correctionSource();$note=saveCorrection($invoice,'100.00',['date'=>'2026-11-25','reporting_date'=>'2026-12-01']);
        app(CorrectionService::class)->post($note->id);
        $builder=app(ReturnDatasetBuilder::class);
        $november=$builder->build($company,'2026-11',50000000);$december=$builder->build($company,'2026-12',50000000);
        expect(app(ReturnLedgerTieOut::class)->assert($december))->toHaveKeys(['output','itc']);
        expect($november['notes']['unregistered'])->toBeEmpty()->and($december['notes']['unregistered'])->toHaveCount(1)
            ->and($december['notes']['unregistered'][0]['date'])->toBe('2026-12-01');
    });
    } finally { Carbon\CarbonImmutable::setTestNow(); }
});
