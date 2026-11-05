<?php
use App\Domain\Accounting\Models\{Ledger, LedgerGroup, NumberSequence, Voucher, VoucherLine};
use App\Domain\Accounting\Services\ReversalService;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\{AllocateReceipt, PostInvoice, PostReceipt, SaveInvoiceDraft, SaveReceiptDraft, UndoReceiptAllocations};
use App\Domain\Sales\Actions\ReverseReceipt as ReverseReceiptAction;
use App\Domain\Sales\Models\{Invoice, Receipt, ReceiptAllocation, ReceiptAllocationReversal, ReceiptReversal};
use App\Domain\Sales\Services\{ReceiptService, SettlementService};
use App\Domain\Shared\Models\Activity;
use App\Domain\Shared\Tenancy\CompanySession;
use DomainException;
use Illuminate\Validation\ValidationException;

function cashLedger(): Ledger
{
    return Ledger::where('code', 'CASH')->sole();
}

// Posts an invoice whose total equals the given amount exactly (exempt line, no tax).
function postInvoiceFor(Party $party, string $amount = '1000.00', ?TaxRate $rate = null, ?string $date = null): Invoice
{
    $item = \App\Domain\Inventory\Models\Item::firstOrCreate(['sku' => 'WIDGET'], ['name' => 'Widget', 'hsn_sac' => '8471']);
    $sales = Ledger::where('code', 'SALES')->sole();
    $draft = app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id, [
        'date' => $date ?? '2026-11-06',
    ]), [linePayload($item->id, $sales->id, null, ['unit_price' => $amount, 'tax_category' => 'exempt'])]);
    return app(PostInvoice::class)->handle($draft->id)->fresh(['items', 'party', 'voucher']);
}

function receiptDraft(Party $party, string $amount, array $overrides = []): Receipt
{
    return app(SaveReceiptDraft::class)->handle(null, array_merge([
        'party_id' => $party->id, 'ledger_id' => cashLedger()->id, 'date' => '2026-11-06',
        'payment_reference' => 'UTR-1', 'narration' => 'On account', 'amount' => $amount,
    ], $overrides));
}

it('posts a receipt with exact Dr cash / Cr customer entries and a series number', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party);
        $receipt = receiptDraft($party, '400.00');
        $posted = app(PostReceipt::class)->handle($receipt->id);
        $entries = $posted->voucher->lines->mapWithKeys(fn (VoucherLine $l) => [
            $l->ledger->code => [(string) $l->debit, (string) $l->credit],
        ])->all();
        expect($entries)->toBe(['CASH' => ['400.00', '0.00'], 'CUSTOMER' => ['0.00', '400.00']])
            ->and($posted->status)->toBe('posted')->and($posted->number)->toBe(1)
            ->and($posted->voucher->type)->toBe('receipt')
            ->and($posted->party_snapshot['name'])->toBe('Customer')
            ->and(NumberSequence::where('series', 'SR')->sole()->last_number)->toBe(1)
            ->and(Activity::where('description', 'Customer receipt posted')->count())->toBe(1);
    });
});

it('supports partial payments, multiple receipts and multi-invoice allocation', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $one = postInvoiceFor($party, '1000.00');
        $two = postInvoiceFor($party, '500.00');
        // One receipt allocated across two invoices.
        $receipt = receiptDraft($party, '1200.00');
        $posted = app(PostReceipt::class)->handle($receipt->id, [
            $one->id => '900.00', $two->id => '300.00',
        ]);
        expect(ReceiptAllocation::count())->toBe(2)
            ->and((string) app(SettlementService::class)->documentOutstanding($one->fresh()))->toBe('100.00')
            ->and((string) app(SettlementService::class)->documentOutstanding($two->fresh()))->toBe('200.00');
        // A second receipt later settles the remainder of the first invoice.
        $later = app(PostReceipt::class)->handle(receiptDraft($party, '100.00')->id, [$one->id => '100.00']);
        expect((string) app(SettlementService::class)->documentOutstanding($one->fresh()))->toBe('0.00')
            ->and((string) app(SettlementService::class)->availableReceiptFunds($posted->fresh()))->toBe('0.00')
            ->and((string) app(SettlementService::class)->availableReceiptFunds($later->fresh()))->toBe('0.00');
    });
});

it('treats unallocated receipt funds as customer advances without touching invoices', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '750.00')->id);
        expect(ReceiptAllocation::count())->toBe(0)
            ->and((string) app(SettlementService::class)->availableReceiptFunds($receipt->fresh()))->toBe('750.00')
            ->and((string) app(SettlementService::class)->ledgerBalance($party->ledger_id))->toBe('-750.00');
    });
});

it('allocates previously posted receipts in a later authorized action across years', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function () use ($company, $user): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1000.00', ['date' => '2026-11-06'])->id);
        // An invoice in the NEXT financial year of the same company.
        $nextYear = \App\Domain\Masters\Models\FinancialYear::create([
            'name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        app(CompanySession::class)->select($user, $company->id, $nextYear->id);
        $item = \App\Domain\Inventory\Models\Item::firstOrCreate(['sku' => 'WIDGET'], ['name' => 'Widget', 'hsn_sac' => '8471']);
        $sales = Ledger::where('code', 'SALES')->sole();
        $draft = app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id, ['date' => '2027-04-10']),
            [linePayload($item->id, $sales->id, null, ['unit_price' => '1000.00', 'tax_category' => 'exempt'])]);
        $invoice = app(PostInvoice::class)->handle($draft->id);
        app(CompanySession::class)->select($user, $company->id, salesYear($company)->id);

        expect(inTenantContext($company, fn () => $receipt->fresh()->financial_year_id))
            ->toBe(salesYear($company)->id)
            ->and($invoice->financial_year_id)->toBe($nextYear->id);
        app(AllocateReceipt::class)->handle($receipt->id, [$invoice->id => '1000.00']);
        expect((string) inTenantContext($company, fn (): string => (string) app(SettlementService::class)->documentOutstanding($invoice->fresh())))->toBe('0.00')
            ->and(inTenantContext($company, fn (): int => ReceiptAllocation::sole()->invoice_financial_year_id))->toBe($nextYear->id);
    });
});

it('rejects over-allocation, foreign customers and non-posted invoices', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party, '1000.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '400.00')->id);
        expect(fn () => app(AllocateReceipt::class)->handle($receipt->id, [Invoice::sole()->id => '500.00']))
            ->toThrow(ValidationException::class);
        // A different customer's invoice can never be allocated.
        $other = Party::create(['name' => 'Other customer', 'state_code' => '27',
            'ledger_id' => Ledger::create(['code' => 'CUSTOMER2', 'name' => 'Customer 2',
                'ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id])->id]);
        $foreign = postInvoiceFor($other, '100.00');
        expect(fn () => app(AllocateReceipt::class)->handle($receipt->id, [$foreign->id => '50.00']))
            ->toThrow(ValidationException::class, 'same customer');
        expect(ReceiptAllocation::count())->toBe(0);
    });
});

it('undoes an allocation through an insert-only record and restores the balance', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '600.00')->id, [$invoice->id => '600.00']);
        $allocation = ReceiptAllocation::sole();
        app(UndoReceiptAllocations::class)->handle($receipt->id, [$allocation->id]);
        expect(ReceiptAllocationReversal::count())->toBe(1)
            ->and((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('1000.00')
            ->and((string) app(SettlementService::class)->availableReceiptFunds($receipt->fresh()))->toBe('600.00');
        // History is preserved: the allocation row still exists.
        expect(ReceiptAllocation::count())->toBe(1);
        // Double undo is refused.
        expect(fn () => app(UndoReceiptAllocations::class)->handle($receipt->id, [$allocation->id]))
            ->toThrow(ValidationException::class, 'already been undone');
    });
});

it('reverses a receipt exactly and atomically undoes its active allocations', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    $reversal = null;
    $yearId = salesYear($company)->id;
    inTenantContext($company, function () use (&$reversal, $company, $yearId): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '600.00')->id, [$invoice->id => '600.00']);
        $reversal = app(ReverseReceiptAction::class)->handle($receipt->id, $yearId, now()->toDateString(), 'Payment returned to customer');
        $entries = $reversal->voucher->lines->mapWithKeys(fn (VoucherLine $l) => [
            $l->ledger->code => [(string) $l->debit, (string) $l->credit],
        ])->all();
        expect($entries)->toBe(['CASH' => ['0.00', '600.00'], 'CUSTOMER' => ['600.00', '0.00']])
            ->and($reversal->status)->toBe('posted')
            ->and($reversal->undone_allocations)->toHaveCount(1)
            ->and((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('1000.00')
            ->and((string) app(SettlementService::class)->ledgerBalance($party->ledger_id))->toBe('1000.00')
            ->and(ReceiptAllocation::count())->toBe(1); // history preserved
        // Duplicate reversal refused.
        expect(fn () => app(ReverseReceiptAction::class)->handle($receipt->id, $yearId, now()->toDateString(), 'Another attempt here'))
            ->toThrow(ValidationException::class, 'already been reversed');
    });
});

it('refuses reversing an invoice that still has active receipt allocations', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function () use ($company): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '300.00')->id, [$invoice->id => '300.00']);
        $yearId = salesYear($company)->id;
        expect(fn () => app(ReversalService::class)->reverse(
            $invoice->voucher_id, $yearId, now()->toDateString(), 'Invoice was raised in error'))
            ->toThrow(DomainException::class, 'active receipt allocations');
        // Undoing the allocation clears the path.
        app(UndoReceiptAllocations::class)->handle($receipt->id, [ReceiptAllocation::sole()->id]);
        $reversal = app(ReversalService::class)->reverse(
            $invoice->voucher_id, $yearId, now()->toDateString(), 'Invoice was raised in error');
        expect($reversal->status)->toBe('posted')
            // A reversed invoice can no longer be allocated.
            ->and(fn () => app(AllocateReceipt::class)->handle($receipt->id, [$invoice->id => '100.00']))
            ->toThrow(ValidationException::class, 'reversed');
    });
});

it('refuses the generic reversal path for receipt vouchers', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    $yearId = salesYear($company)->id;
    inTenantContext($company, function () use ($yearId): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '100.00')->id);
        expect(fn () => app(ReversalService::class)->reverse(
            $receipt->voucher_id, $yearId, now()->toDateString(), 'Wrong path attempt'))
            ->toThrow(DomainException::class);
    });
});

it('rolls back the whole receipt posting and consumes no number when allocation fails', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $other = postInvoiceFor(Party::create(['name' => 'Other', 'state_code' => '27',
            'ledger_id' => Ledger::create(['code' => 'CUSTOMER2', 'name' => 'Customer 2',
                'ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id])->id]), '100.00');
        $receipt = receiptDraft($party, '500.00');
        expect(fn () => app(PostReceipt::class)->handle($receipt->id, [$other->id => '100.00']))
            ->toThrow(DomainException::class);
        expect(Receipt::sole()->status)->toBe('draft')->and(Receipt::sole()->number)->toBeNull()
            ->and(Voucher::count())->toBe(1) // only the invoice voucher
            ->and(NumberSequence::where('series', 'SR')->count())->toBe(0);
    });
});

it('rejects duplicate receipt postings with the same request key', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party);
        $receipt = receiptDraft($party, '100.00');
        app(PostReceipt::class)->handle($receipt->id);
        expect(fn () => app(PostReceipt::class)->handle($receipt->id))->toThrow(DomainException::class)
            ->and(NumberSequence::where('series', 'SR')->sole()->last_number)->toBe(1);
    });
});
