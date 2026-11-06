<?php

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Services\DocumentCancellationService;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Masters\Models\Company;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Sales\Services\CorrectionService;
use Carbon\CarbonImmutable;

it('reverses and cancels posted sales invoices without reusing their numbers', function () {
    CarbonImmutable::setTestNow('2026-11-30 12:00:00');
    try {
        inSalesCompany(function (Company $company) {
            $first = returnSalesFixture(['date' => '2026-11-05']);
            app(PostingService::class)->postSalesInvoice($first->id);
            $originalNumber = $first->fresh()->number;
            $originalVoucher = $first->fresh()->voucher_id;
            $cancelled = app(DocumentCancellationService::class)->cancelInvoice($first->id, 'Entered in error', $first->financial_year_id, '2026-11-30');
            expect($cancelled->status)->toBe('cancelled')->and($cancelled->number)->toBe($originalNumber)
                ->and($cancelled->cancelled_at)->not->toBeNull()->and($cancelled->cancellation_reason)->toBe('Entered in error')
                ->and(Voucher::query()->where('reverses_voucher_id', $originalVoucher)->where('status', 'posted')->exists())->toBeTrue();
            $second = returnSalesFixture(['date' => '2026-11-06']);
            app(PostingService::class)->postSalesInvoice($second->id);
            expect($second->fresh()->number)->toBe($originalNumber + 1);
            $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
            expect($dataset['b2cs'])->toHaveCount(1)->and($dataset['b2cs'][0]['taxable_value'])->toBe('1000.00')
                ->and($dataset['documents_issued'][0]['total'])->toBe(2)->and($dataset['documents_issued'][0]['cancelled'])->toBe(1);
        });
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('cancels posted purchase bills through an accounting reversal and preserves the bill number', function () {
    CarbonImmutable::setTestNow('2026-11-30 12:00:00');
    try {
        inSalesCompany(function () {
            $bill = returnPurchaseFixture();
            app(PostPurchaseBill::class)->handle($bill->id);
            $number = $bill->fresh()->number;
            $voucherId = $bill->fresh()->voucher_id;
            $cancelled = app(DocumentCancellationService::class)->cancelPurchaseBill($bill->id, 'Supplier bill voided', $bill->financial_year_id, '2026-11-30');
            expect($cancelled->status)->toBe('cancelled')->and($cancelled->number)->toBe($number)
                ->and(Voucher::query()->where('reverses_voucher_id', $voucherId)->where('status', 'posted')->exists())->toBeTrue();
            $next = returnPurchaseFixture(['supplier_bill_number' => 'RET-PUR-2']);
            app(PostPurchaseBill::class)->handle($next->id);
            expect($next->fresh()->number)->toBe($number + 1);
        });
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('cancels drafts without allocating numbers and blocks cancelling an invoice with a posted correction', function () {
    CarbonImmutable::setTestNow('2026-11-30 12:00:00');
    try {
        inSalesCompany(function () {
            $draft = returnSalesFixture(['date' => '2026-11-05']);
            // This fixture is intentionally still a draft here.
            $cancelled = app(DocumentCancellationService::class)->cancelInvoice($draft->id, 'Duplicate draft');
            expect($cancelled->status)->toBe('cancelled')->and($cancelled->number)->toBeNull()->and($cancelled->voucher_id)->toBeNull();
            $source = correctionSource();
            $note = saveCorrection($source, '100.00', ['date' => '2026-11-10', 'reporting_date' => '2026-11-10']);
            app(CorrectionService::class)->post($note->id);
            expect(fn () => app(DocumentCancellationService::class)->cancelInvoice($source->id, 'Cannot void corrected invoice', $source->financial_year_id, '2026-11-30'))
                ->toThrow(DomainException::class, 'posted correction notes');
        });
    } finally {
        CarbonImmutable::setTestNow();
    }
});
