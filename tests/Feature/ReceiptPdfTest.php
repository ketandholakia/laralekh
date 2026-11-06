<?php

use App\Domain\Sales\Actions\AllocateReceipt;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\CustomerStatement;
use App\Domain\Sales\Services\ReceiptDocuments;
use App\Domain\Shared\Tenancy\TenantContext;

it('renders a posted receipt PDF with number, customer, reference, words and original allocations', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $one = postInvoiceFor($party, '1000.00', '2026-11-01');
        $two = postInvoiceFor($party, '500.00', '2026-11-02');
        $receipt = app(PostReceipt::class)->handle(
            receiptDraft($party, '700.00', ['payment_reference' => 'UTR-9001'])->id,
            [$one->id => '500.00', $two->id => '200.00'],
        );
        $bytes = app(ReceiptDocuments::class)->receipt($receipt->fresh())->output();
        expect(pdfHas($bytes, 'MONEY RECEIPT'))->toBeTrue()
            ->and(pdfHas($bytes, 'SR/1'))->toBeTrue()
            ->and(pdfHas($bytes, 'UTR-9001'))->toBeTrue()
            ->and(pdfHas($bytes, 'Customer'))->toBeTrue()
            ->and(pdfHas($bytes, 'Rupees Seven Hundred Only'))->toBeTrue()
            // Original allocations at posting time, with invoice references.
            ->and(pdfHas($bytes, $one->series.'/'.$one->number))->toBeTrue()
            ->and(pdfHas($bytes, '500.00'))->toBeTrue()
            ->and(pdfHas($bytes, '200.00'))->toBeTrue()
            // Legal positioning statements.
            ->and(pdfHas($bytes, 'not a statutory'))->toBeTrue()
            ->and(pdfHas($bytes, 'Page 1 of 1'))->toBeTrue();
    });
});

it('marks draft receipt previews clearly and allocates nothing', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $draft = receiptDraft($party, '250.00');
        $bytes = app(ReceiptDocuments::class)->receipt($draft->fresh())->output();
        expect(pdfHas($bytes, 'DRAFT PREVIEW'))->toBeTrue()
            ->and(pdfHas($bytes, 'NO RECEIPT NUMBER ALLOCATED'))->toBeTrue()
            ->and($draft->fresh()->number)->toBeNull();
    });
});

it('distinguishes later allocation history from the original document', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00', '2026-11-01');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '300.00')->id);
        $before = app(ReceiptDocuments::class)->receipt($receipt->fresh())->output();
        app(AllocateReceipt::class)->handle($receipt->id, [$invoice->id => '300.00']);
        $after = app(ReceiptDocuments::class)->receipt($receipt->fresh())->output();
        // The original document never shows the later allocation...
        expect(pdfHas($before, $invoice->series.'/'.$invoice->number))->toBeFalse()
            ->and(pdfHas($after, $invoice->series.'/'.$invoice->number))->toBeFalse()
            // ...and states that later allocations live in the register.
            ->and(pdfHas($after, 'receipt register'))->toBeTrue()
            ->and(Receipt::find($receipt->id)->allocations()->count())->toBe(1);
    });
});

it('renders a reconciled multi-page statement with opening and closing balances', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    $bytes = null;
    inTenantContext($company, function () use (&$bytes): void {
        [$party] = masters();
        $today = now()->toDateString();
        // Enough invoices to spill onto a second page.
        for ($i = 0; $i < 40; $i++) {
            postInvoiceFor($party, '100.00', $today);
        }
        app(PostReceipt::class)->handle(receiptDraft($party, '2500.00', ['date' => $today])->id);
        $statement = app(CustomerStatement::class)
            ->build($party->id, '2026-04-01', $today);
        expect((string) $statement['closing'])->toBe((string) $statement['ledger_as_of'])
            // 40 invoices x 100 debit - 2500 credit = 1500 receivable
            ->and((string) $statement['closing'])->toBe('1500.00')
            ->and(count($statement['rows']))->toBe(41);
        $bytes = app(ReceiptDocuments::class)->statement($statement)->output();
    });
    expect(pdfHas($bytes, 'STATEMENT OF ACCOUNT'))->toBeTrue()
        ->and(pdfHas($bytes, 'Opening balance'))->toBeTrue()
        ->and(pdfHas($bytes, 'Closing balance'))->toBeTrue()
        ->and(pdfHas($bytes, '1500.00'))->toBeTrue()
        ->and(pdfPageCount($bytes))->toBeGreaterThan(1)
        // Repeated headers on every page.
        ->and(substr_count(pdfText($bytes), 'OpeningbalanceOpeningbalance'))->toBe(0)
        ->and(substr_count(pdfText($bytes), 'DESCRIPTION'))->toBeGreaterThanOrEqual(2);
});

it('withholds the receipts.view verdict from non-granted members', function () {
    [$user, $company] = receiptSession();
    $noView = webUser('noview@example.test');
    addMembership($noView, $company);
    selectSessionCompany($noView, $company, salesYear($company));
    $this->actingAs($noView);
    // can() checks run under the entered company team so the verdict is company-scoped.
    // The PDF route's access check keys off this verdict; without the
    // permission the member cannot reach receipt or statement documents.
    // Permission checks run under the entered company team (company-scoped roles).
    $verdicts = app(TenantContext::class)
        ->run($company->id, fn (): array => [
            auth()->user()->can('receipts.view'), auth()->user()->can('receipts.create'),
        ]);
    expect($verdicts[0])->toBeFalse()->and($verdicts[1])->toBeFalse();
});
