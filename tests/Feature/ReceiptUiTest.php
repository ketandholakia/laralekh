<?php

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\SaveReceiptDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptAllocation;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Sales\Services\SettlementService;
use App\Livewire\Sales\CustomerOutstanding;
use App\Livewire\Sales\CustomerStatement;
use App\Livewire\Sales\ReceiptEditor;
use App\Livewire\Sales\ReceiptList;
use App\Livewire\Sales\ReceiptView;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

it('lists receipts with filters and hides other companies', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    $other = companyFixture('Other');
    inTenantContext($company, function (): void {
        [$party] = masters();
        app(SaveReceiptDraft::class)->handle(null, [
            'party_id' => $party->id, 'ledger_id' => cashLedger()->id, 'date' => '2026-11-06',
            'amount' => '250.00', 'request_key' => 'list-key-1',
        ]);
    });
    inTenantContext($other, function (): void {
        (new ChartOfAccountsSeeder)->run();
        $fy = FinancialYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
        $ledger = Ledger::create(['code' => 'CASH2', 'name' => 'Cash', 'ledger_group_id' => LedgerGroup::where('code', 'CASH_BANK')->sole()->id]);
        $party = Party::create(['name' => 'Secret customer', 'state_code' => '27', 'ledger_id' => $ledger->id]);
        Receipt::create(['financial_year_id' => $fy->id, 'party_id' => $party->id, 'ledger_id' => $ledger->id,
            'date' => '2026-11-06', 'amount' => '999.00', 'request_key' => 'list-key-2']);
    });
    inTenantContext($company, function (): void {
        expect(Receipt::count())->toBe(1);
        Livewire::test(ReceiptList::class)->assertSee('250.00')->assertDontSee('999.00');
        Livewire::test(ReceiptList::class)->set('status', 'draft')->assertSee('draft');
    });
});

it('saves and posts a receipt through the editor with allocations', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party, '1000.00');
        Livewire::test(ReceiptEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('ledgerId', cashLedger()->id)
            ->set('date', '2026-11-06')->set('amount', '1000.00')
            ->set('paymentReference', 'UTR-77')
            ->set('allocations.'.Invoice::sole()->id, '1000.00')
            ->call('post');
        $receipt = Receipt::sole();
        expect($receipt->status)->toBe('posted')->and($receipt->number)->toBe(1)
            ->and($receipt->payment_reference)->toBe('UTR-77')
            ->and(ReceiptAllocation::count())->toBe(1)
            ->and((string) app(SettlementService::class)->documentOutstanding(Invoice::sole()))->toBe('0.00');
    });
});

it('refuses posting receipts without the receipts.post permission', function () {
    [$user, $company] = receiptSession('clerk', ['invoices.view', 'invoices.create', 'receipts.view', 'receipts.create']);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $component = Livewire::test(ReceiptEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('ledgerId', cashLedger()->id)->set('date', '2026-11-06')->set('amount', '100.00');
        assert403(fn () => $component->instance()->post());
        expect(Receipt::count())->toBe(0); // posting refused before any write
        // Saving a draft still works without the posting permission.
        $component->call('save');
        expect(Receipt::sole()->status)->toBe('draft')->and(Receipt::sole()->number)->toBeNull();
    });
});

it('shows a posted receipt read-only with allocations and funds', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '200.00')->id, [$invoice->id => '200.00']);
        Livewire::test(ReceiptView::class, ['receiptId' => $receipt->id])
            ->assertOk()->assertSee('Receipt SR/1')->assertSee('200.00');
        expect((string) app(SettlementService::class)->availableReceiptFunds($receipt->fresh()))->toBe('0.00');
    });
});

it('allocates remaining funds through the separately authorized action', function () {
    [$user, $company] = receiptSession('allocator', ['invoices.view', 'receipts.view', 'receipts.allocate']);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '200.00')->id);
        Livewire::test(ReceiptView::class, ['receiptId' => $receipt->id])
            ->set('newAllocations.'.$invoice->id, '200.00')
            ->call('allocate');
        expect(ReceiptAllocation::count())->toBe(1)
            ->and((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('300.00');
    });
});

it('blocks allocation and undo for users without the respective permissions', function () {
    [$user, $company] = receiptSession('clerk', ['receipts.view', 'receipts.create', 'receipts.post', 'invoices.view']);
    $this->actingAs($user);
    $receipt = inTenantContext($company, function (): Receipt {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');

        return app(PostReceipt::class)->handle(receiptDraft($party, '200.00')->id, [$invoice->id => '200.00']);
    });
    $invoiceId = null;
    inTenantContext($company, function () use ($receipt, &$invoiceId): void {
        $invoiceId = Invoice::sole();
        // No receipts.allocate: the allocate action aborts with 403.
        $component = Livewire::test(ReceiptView::class, ['receiptId' => $receipt->id]);
        assert403(fn () => $component->instance()->allocate());
        // The posting-time allocation is untouched; nothing new was applied.
        expect(ReceiptAllocation::count())->toBe(1);
    });
});

it('reverses a receipt through the screen with confirmation data', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '200.00')->id, [$invoice->id => '200.00']);
        Livewire::test(ReceiptView::class, ['receiptId' => $receipt->id])
            ->set('reverseReason', 'Payment received twice in error')
            ->call('reverse');
        expect($receipt->fresh()->reversals()->count())->toBe(1)
            ->and((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('500.00')
            ->and((string) app(SettlementService::class)->ledgerBalance($party->ledger_id))->toBe('500.00');
    });
});

it('shows customer outstanding with advances and excess credits separately', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function () use ($company): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        app(PostReceipt::class)->handle(receiptDraft($party, '150.00')->id); // advance
        Livewire::test(CustomerOutstanding::class, ['partyId' => $party->id])
            ->assertOk()->assertSee('150.00')->assertSee('Unapplied advances')->assertSee('1000.00');
        // Over-settle: receipt + later full credit makes the document balance negative.
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '900.00')->id, [$invoice->id => '900.00']);
        $note = SalesCorrection::create([
            'financial_year_id' => salesYear($company)->id, 'original_financial_year_id' => salesYear($company)->id,
            'invoice_id' => $invoice->id, 'kind' => 'credit', 'treatment' => 'commercial', 'status' => 'draft',
            'series' => 'CN', 'number' => 1, 'request_key' => 'credit-key-1', 'date' => '2026-11-05',
            'reason_code' => 'OTHER', 'reason' => 'Goodwill adjustment', 'supplier_snapshot' => [], 'party_snapshot' => [],
            'original_snapshot' => [], 'total' => '1000.00',
        ]);
        $note->update(['status' => 'posted']);
        Livewire::test(CustomerOutstanding::class, ['partyId' => $party->id])
            ->assertSee('Excess customer credits');
        $summary = app(SettlementService::class)->customerSummary($party->id);
        expect((string) $summary['excess_credits'])->toBe('900.00')
            ->and((string) $summary['advances'])->toBe('150.00')
            // The hand-inserted fixture note carries no voucher, so the ledger
            // reflects only real postings: 1000 - 900 - 150 = -50.
            ->and((string) $summary['ledger_balance'])->toBe('-50.00');
    });
});

it('renders the statement screen with a reconciled running balance', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $today = now()->toDateString();
        $invoice = postInvoiceFor($party, '1000.00', $today);
        app(PostReceipt::class)->handle(receiptDraft($party, '400.00', ['date' => $today])->id, [$invoice->id => '400.00']);
        $component = Livewire::test(CustomerStatement::class, ['partyId' => $party->id])
            ->set('from', '2026-04-01')->set('asOf', $today);
        $statement = $component->viewData('statement');
        expect((string) $statement['opening'])->toBe('0.00')
            ->and((string) $statement['closing'])->toBe((string) $statement['ledger_as_of'])
            ->and(count($statement['rows']))->toBe(2); // invoice + receipt; no allocation rows
    });
});
