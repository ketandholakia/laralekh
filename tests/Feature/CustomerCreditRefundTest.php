<?php

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\AllocateReceipt;
use App\Domain\Sales\Actions\PostCustomerRefund;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\ReverseCustomerRefund;
use App\Domain\Sales\Actions\SaveCustomerRefundDraft;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Sales\Models\CustomerRefundAllocation;
use App\Domain\Sales\Models\CustomerRefundReversal;
use App\Domain\Sales\Services\CorrectionService;
use App\Domain\Sales\Services\CustomerStatement;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use App\Livewire\Sales\CustomerRefundEditor;
use App\Livewire\Sales\CustomerRefundView;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('enforces authentication, company tenancy and permissions on customer refund routes', function () {
    [$user, $company] = webSession('clerk', ['invoices.view', 'receipts.view']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user, $company): void {
        [$party] = masters();

        // 1. User without receipts.create cannot access create route
        $this->get(route('customers.refunds.create', $party))->assertForbidden();

        // Grant receipts.create
        assignCompanyRole($user, $company, 'refund-creator', ['invoices.view', 'receipts.view', 'receipts.create']);
        $this->get(route('customers.refunds.create', $party))->assertSuccessful();

        // User without receipts.post cannot post
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1000.00')->id);
        $draft = app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'receipt', 'target_id' => $receipt->id, 'amount' => '300.00'],
            ],
        );

        $this->get(route('customer-refunds.edit', $draft->id))->assertSuccessful();
        $this->get(route('customer-refunds.show', $draft->id))->assertSuccessful();

        // Guest is redirected
        auth()->logout();
        $this->get(route('customers.refunds.create', $party))->assertRedirect(route('login'));
    });
});

it('validates capacity and saves draft refund against receipt advances and invoice excess credits', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1000.00')->id);

        // 1. Attempting to refund more than available advance fails
        expect(fn () => app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'receipt', 'target_id' => $receipt->id, 'amount' => '1500.00'],
            ],
        ))->toThrow(DomainException::class, 'exceeds available receipt advance');

        // 2. Valid draft creation succeeds
        $draft = app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'receipt', 'target_id' => $receipt->id, 'amount' => '400.00'],
            ],
            paymentReference: 'REF-1234',
            narration: 'Refund partial advance',
        );

        expect($draft->status)->toBe('draft')
            ->and((string) $draft->amount)->toBe('400.00')
            ->and($draft->number)->toBeNull()
            ->and($draft->allocations)->toHaveCount(1)
            ->and($draft->allocations->first()->allocation_type)->toBe('receipt')
            ->and((string) $draft->allocations->first()->amount)->toBe('400.00');

        // 3. Modifying draft to 600.00 succeeds
        $updated = app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'receipt', 'target_id' => $receipt->id, 'amount' => '600.00'],
            ],
            refundId: $draft->id,
        );

        expect((string) $updated->amount)->toBe('600.00');
    });
});

it('posts a refund against unapplied receipt advance and verifies balanced voucher and statement reconciliation', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '2000.00')->id);

        $settlement = app(SettlementService::class);
        expect((string) $settlement->availableReceiptFunds($receipt))->toBe('2000.00')
            ->and((string) $settlement->customerSummary($party->id)['advances'])->toBe('2000.00')
            ->and((string) $settlement->ledgerBalance($party->ledger_id))->toBe('-2000.00');

        $draft = app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'receipt', 'target_id' => $receipt->id, 'amount' => '800.00'],
            ],
            paymentReference: 'IMPS-9988',
        );

        $posted = app(PostCustomerRefund::class)->handle($draft->id);

        expect($posted->status)->toBe('posted')
            ->and($posted->number)->toBe(1)
            ->and($posted->voucher_id)->not->toBeNull()
            ->and($posted->voucher->type)->toBe('payment')
            ->and($posted->voucher->status)->toBe('posted');

        // Check voucher entries: Dr. Customer 800.00, Cr. Cash 800.00
        $lines = $posted->voucher->lines->mapWithKeys(fn ($l) => [
            $l->ledger->code => [(string) $l->debit, (string) $l->credit],
        ])->all();
        expect($lines)->toBe([
            'CUSTOMER' => ['800.00', '0.00'],
            'CASH' => ['0.00', '800.00'],
        ]);

        // Available funds and summary advances reduced from 2000.00 to 1200.00
        expect((string) $settlement->availableReceiptFunds($receipt->fresh()))->toBe('1200.00')
            ->and((string) $settlement->customerSummary($party->id)['advances'])->toBe('1200.00')
            ->and((string) $settlement->ledgerBalance($party->ledger_id))->toBe('-1200.00');

        // Verify statement reconciliation: opening 0.00, receipt Cr 2000.00, refund Dr 800.00 => closing -1200.00
        $statement = app(CustomerStatement::class)->build($party->id, '2026-11-01', '2026-11-30');
        expect((string) $statement['closing'])->toBe('-1200.00')
            ->and((string) $statement['ledger_as_of'])->toBe('-1200.00');

        $refundRow = collect($statement['rows'])->firstWhere('kind', 'customer_refund');
        expect($refundRow)->not->toBeNull()
            ->and($refundRow['debit'])->toBe('800.00')
            ->and($refundRow['credit'])->toBe('0.00');
    });
});

it('posts a refund against an invoice excess credit created by a credit note', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');

        // Customer pays invoice in full
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1000.00')->id, [
            $invoice->id => '1000.00',
        ]);

        $settlement = app(SettlementService::class);
        expect((string) $settlement->documentOutstanding($invoice->fresh()))->toBe('0.00');

        // Later a commercial credit note of 300.00 is posted against the invoice
        $creditNote = saveCorrection($invoice, '300.00', ['kind' => 'credit', 'treatment' => 'commercial', 'date' => '2026-11-06']);
        app(CorrectionService::class)->post($creditNote->id);

        // Invoice now has excess credit (outstanding = -300.00)
        expect((string) $settlement->documentOutstanding($invoice->fresh()))->toBe('-300.00')
            ->and((string) $settlement->customerSummary($party->id)['excess_credits'])->toBe('300.00')
            ->and((string) $settlement->ledgerBalance($party->ledger_id))->toBe('-300.00');

        // Refund the 300.00 excess credit to the customer
        $draft = app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'invoice', 'target_id' => $invoice->id, 'amount' => '300.00'],
            ],
            paymentReference: 'NEFT-5544',
        );
        $posted = app(PostCustomerRefund::class)->handle($draft->id);

        expect($posted->status)->toBe('posted')
            ->and((string) $posted->amount)->toBe('300.00');

        // Invoice outstanding is now 0.00 (settled, no longer negative)
        expect((string) $settlement->documentOutstanding($invoice->fresh()))->toBe('0.00')
            ->and((string) $settlement->customerSummary($party->id)['excess_credits'])->toBe('0.00')
            ->and((string) $settlement->ledgerBalance($party->ledger_id))->toBe('0.00');

        // Statement closing matches ledger_as_of
        $statement = app(CustomerStatement::class)->build($party->id, '2026-11-01', '2026-11-30');
        expect((string) $statement['closing'])->toBe('0.00')
            ->and((string) $statement['ledger_as_of'])->toBe('0.00');
    });
});

it('reverses a posted customer refund and restores available credits and advances', function () {
    [$user, $company] = receiptSession();
    assignCompanyRole($user, $company, 'accountant-rev', [
        'invoices.view', 'receipts.view', 'receipts.create', 'receipts.post', 'receipts.reverse',
    ]);
    $this->actingAs($user);

    inTenantContext($company, function () use ($company): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1000.00')->id);

        $draft = app(SaveCustomerRefundDraft::class)->handle(
            partyId: $party->id,
            ledgerId: cashLedger()->id,
            date: '2026-11-06',
            allocations: [
                ['allocation_type' => 'receipt', 'target_id' => $receipt->id, 'amount' => '500.00'],
            ],
        );
        $posted = app(PostCustomerRefund::class)->handle($draft->id);

        $settlement = app(SettlementService::class);
        expect((string) $settlement->availableReceiptFunds($receipt->fresh()))->toBe('500.00');

        // Reverse the customer refund
        $reversal = app(ReverseCustomerRefund::class)->handle(
            refundId: $posted->id,
            yearId: salesYear($company)->id,
            date: '2026-11-06',
            reason: 'Customer requested cancellation of refund payment',
        );

        expect($reversal->status)->toBe('posted')
            ->and($reversal->voucher->type)->toBe('reversal')
            ->and($posted->fresh()->isReversed())->toBeTrue();

        // Reversal voucher swapped entries: Dr. Cash 500.00, Cr. Customer 500.00
        $lines = $reversal->voucher->lines->mapWithKeys(fn ($l) => [
            $l->ledger->code => [(string) $l->debit, (string) $l->credit],
        ])->all();
        expect($lines)->toBe([
            'CUSTOMER' => ['0.00', '500.00'],
            'CASH' => ['500.00', '0.00'],
        ]);

        // Available advance is restored to 1000.00
        expect((string) $settlement->availableReceiptFunds($receipt->fresh()))->toBe('1000.00')
            ->and((string) $settlement->customerSummary($party->id)['advances'])->toBe('1000.00')
            ->and((string) $settlement->ledgerBalance($party->ledger_id))->toBe('-1000.00');

        // Statement statement reconciliation
        $statement = app(CustomerStatement::class)->build($party->id, '2026-11-01', '2026-11-30');
        expect((string) $statement['closing'])->toBe('-1000.00')
            ->and((string) $statement['ledger_as_of'])->toBe('-1000.00');

        $reversalRow = collect($statement['rows'])->firstWhere('kind', 'customer_refund_reversal');
        expect($reversalRow)->not->toBeNull()
            ->and($reversalRow['credit'])->toBe('500.00');
    });
});

it('renders Livewire CustomerRefundEditor and CustomerRefundView components and handles UI interactions', function () {
    [$user, $company] = receiptSession();
    assignCompanyRole($user, $company, 'full-settlement', [
        'invoices.view', 'receipts.view', 'receipts.create', 'receipts.post', 'receipts.reverse',
    ]);
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1500.00')->id);

        // Test Livewire Editor
        $editor = Livewire::test(CustomerRefundEditor::class, ['party' => $party->id])
            ->assertSee($party->name)
            ->assertSee('Receipt '.$receipt->label())
            ->assertSee('Unapplied Advance')
            ->set('allocations.receipt_'.$receipt->id, '750.00')
            ->assertSet('totalRefund', '750.00')
            ->set('paymentReference', 'CHQ-00123')
            ->call('postRefund');

        $refund = CustomerRefund::where('party_id', $party->id)->first();
        expect($refund)->not->toBeNull()
            ->and($refund->status)->toBe('posted')
            ->and((string) $refund->amount)->toBe('750.00');

        $editor->assertRedirect(route('customer-refunds.show', $refund->id));

        // Test Livewire View and Reversal
        Livewire::test(CustomerRefundView::class, ['id' => $refund->id])
            ->assertSee($refund->label())
            ->assertSee('₹750.00')
            ->assertSee('CHQ-00123')
            ->set('showReverseModal', true)
            ->set('reversalDate', '2026-11-06')
            ->set('reversalReason', 'Incorrect disbursement amount entered')
            ->call('reverseRefund')
            ->assertHasNoErrors()
            ->assertSee('REVERSED');

        expect($refund->fresh()->isReversed())->toBeTrue();
    });
});
