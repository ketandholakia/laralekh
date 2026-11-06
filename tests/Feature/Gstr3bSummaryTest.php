<?php

use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\Gstr3bSummaryBuilder;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Gst\Returns\ReturnLedgerTieOut;
use App\Domain\Masters\Models\Company;
use App\Domain\Purchase\Actions\PostPurchaseBill;

it('builds the 3B books reconciliation and ties output and ITC to tax ledgers', function () {
    inSalesCompany(function (Company $company) {
        $invoice = returnSalesFixture(['date' => '2026-11-06']);
        app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
        $tieOut = app(ReturnLedgerTieOut::class)->assert($dataset);
        expect($tieOut)->toHaveKeys(['output', 'itc'])
            ->and($tieOut['output']['cgst']['dataset'])->toBe('90.00')
            ->and($tieOut['output']['cgst']['ledger'])->toBe('90.00')
            ->and($tieOut['output']['sgst']['dataset'])->toBe('90.00')
            ->and($tieOut['output']['sgst']['ledger'])->toBe('90.00');
        $summary = app(Gstr3bSummaryBuilder::class)->build($dataset);
        expect($summary['basis'])->toContain('reconciliation only')
            ->and($summary['3_1']['taxable_outward']['tax']['cgst'])->toBe('90.00')
            ->and($summary['3_1']['taxable_outward']['taxable_value'])->toBe('1000.00')
            ->and($summary['net_payable_after_book_itc']['cgst'])->toBe('90.00')
            ->and($summary['net_payable_total_before_statutory_rounding'])->toBe('180.00')
            ->and($summary['net_payable_total_after_statutory_rounding'])->toBe('180.00');
    });
});

it('fails loudly when GSTR-1 output tax does not tie to OUTPUT ledgers', function () {
    inSalesCompany(function (Company $company) {
        $invoice = returnSalesFixture(['date' => '2026-11-06']);
        app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
        $dataset['b2cs'][0]['cgst'] = '91.00';
        expect(fn () => app(ReturnLedgerTieOut::class)->assert($dataset))->toThrow(DomainException::class, 'tie-out failed');
    });
});

it('ties GSTR-3B eligible ITC to INPUT ledger movements and rejects mismatches', function () {
    config(['gst_returns.rules.blocked_itc_rules.verified' => true]);
    inSalesCompany(function (Company $company) {
        $bill = returnPurchaseFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
        $tieOut = app(ReturnLedgerTieOut::class)->assert($dataset);
        expect($tieOut['itc']['cgst']['summary'])->toBe('90.00')
            ->and($tieOut['itc']['cgst']['ledger'])->toBe('90.00')
            ->and($tieOut['itc']['sgst']['summary'])->toBe('90.00')
            ->and($tieOut['itc']['sgst']['ledger'])->toBe('90.00');

        $dataset['purchases']['itc']['eligible']['cgst'] = '91.00';
        expect(fn () => app(ReturnLedgerTieOut::class)->assert($dataset))
            ->toThrow(DomainException::class, 'GSTR-3B ITC tie-out failed for cgst');
    });
});
