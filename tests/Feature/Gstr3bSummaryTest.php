<?php
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\{Gstr3bSummaryBuilder, ReturnDatasetBuilder, ReturnLedgerTieOut};
use App\Domain\Masters\Models\Company;

it('builds the 3B books reconciliation and ties output and ITC to tax ledgers', function () {
    inSalesCompany(function(Company $company){
        $invoice=returnSalesFixture(['date'=>'2026-11-06']);app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        expect(app(ReturnLedgerTieOut::class)->assert($dataset))->toHaveKeys(['output','itc']);
        $summary=app(Gstr3bSummaryBuilder::class)->build($dataset);
        expect($summary['basis'])->toContain('reconciliation only')
            ->and($summary['3_1']['taxable_outward']['tax']['cgst'])->toBe('90.00')
            ->and($summary['3_1']['taxable_outward']['taxable_value'])->toBe('1000.00')
            ->and($summary['net_payable_after_book_itc']['cgst'])->toBe('90.00')
            ->and($summary['net_payable_total_before_statutory_rounding'])->toBe('180.00')
            ->and($summary['net_payable_total_after_statutory_rounding'])->toBe('180.00');
    });
});

it('fails loudly when GSTR-1 output tax does not tie to OUTPUT ledgers', function () {
    inSalesCompany(function(Company $company){
        $invoice=returnSalesFixture(['date'=>'2026-11-06']);app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        $dataset['b2cs'][0]['cgst']='91.00';
        expect(fn()=>app(ReturnLedgerTieOut::class)->assert($dataset))->toThrow(DomainException::class,'tie-out failed');
    });
});
