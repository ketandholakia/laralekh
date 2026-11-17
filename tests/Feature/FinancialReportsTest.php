<?php

use App\Domain\Accounting\Services\FinancialReports;
use App\Domain\Purchase\Actions\PostPayment;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Livewire\Accounting\BalanceSheet;
use App\Livewire\Accounting\ProfitAndLoss;
use App\Livewire\Accounting\TrialBalance;
use Livewire\Livewire;

// Financial report tests (LL-P1-004/005). Reports are read-only aggregates
// over POSTED voucher movements, reconciled against each other: the trial
// balance columns balance, the balance sheet satisfies
// assets = liabilities + equity + retained profit, and tenant isolation
// keeps companies separate.

function reportSession(array $permissions = ['reports.view', 'invoices.view', 'purchases.view', 'receipts.view'], string $email = 'reports@example.test'): array
{
    return webSession('reports-clerk', $permissions, $email);
}

/** Posts a purchase bill (1180.00 payable), a sales invoice (1000.00) and a 400.00 supplier payment. */
/** Purchase bill taxable 600.00 (total 708.00), sales invoice 1000.00, payment 400.00. */
function postReportScenario(): void
{
    $bill = supplierBillFixture([], ['unit_price' => '600.00']);
    app(PostPurchaseBill::class)->handle($bill->id);
    [$party] = masters();
    postInvoiceFor($party, '1000.00');
    app(PostPayment::class)->post(
        supplierPaymentDraft($bill->party, '400.00')->id, [$bill->id => '400.00']);
}

it('produces a balanced trial balance over posted movements', function () {
    [$user, $company] = reportSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        postReportScenario();
        $report = app(FinancialReports::class)->trialBalance('2026-11-01', '2026-11-30');

        expect((string) $report['totals']['debit'])->toBe((string) $report['totals']['credit'])
            ->and($report['totals']['debit']->greaterThan(\App\Domain\Shared\ValueObjects\Money::of('0')))->toBeTrue()
            // The opening column is empty because the period covers everything.
            ->and($report['totals']['opening_debit']->isZero())->toBeTrue()
            ->and($report['totals']['opening_credit']->isZero())->toBeTrue();
        // Every closing debit has a matching closing credit total.
        expect((string) $report['totals']['closing_debit'])->toBe((string) $report['totals']['closing_credit']);
    });
});

it('splits opening, movement and closing columns by period', function () {
    [$user, $company] = reportSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id); // 2026-11-05
        [$party] = masters();
        postInvoiceFor($party, '1000.00', '2026-11-10'); // after the cut-off

        // Cut-off 2026-11-08: the bill (11-05) is opening; the invoice (11-10) is period movement.
        $report = app(FinancialReports::class)->trialBalance('2026-11-08', '2026-11-30');
        $sales = collect($report['rows'])->firstWhere('code', 'SALES');
        expect($sales)->not->toBeNull()
            ->and($sales['opening_credit'])->toBe('0.00')
            ->and($sales['debit'])->toBe('0.00')->and($sales['credit'])->toBe('1000.00')
            ->and($sales['closing_credit'])->toBe('1000.00');
        $purchases = collect($report['rows'])->firstWhere('code', 'PURCHASES');
        expect($purchases['opening_debit'])->toBe('1000.00')
            ->and($purchases['debit'])->toBe('0.00')
            ->and($purchases['closing_debit'])->toBe('1000.00');
        // Totals still balance per column.
        expect((string) $report['totals']['opening_debit'])->toBe((string) $report['totals']['opening_credit'])
            ->and((string) $report['totals']['debit'])->toBe((string) $report['totals']['credit']);
    });
});

it('computes profit and loss from income and expense ledgers only', function () {
    [$user, $company] = reportSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        postReportScenario(); // sales 1000 income; purchases 1180 expense
        $report = app(FinancialReports::class)->profitAndLoss('2026-11-01', '2026-11-30');

        expect((string) $report['income'])->toBe('1000.00')
            ->and((string) $report['expense'])->toBe('600.00')
            ->and((string) $report['net'])->toBe('400.00');
        // Balance-sheet ledgers (cash, supplier, customer, GST) never appear.
        $codes = array_column($report['rows'], 'code');
        expect($codes)->toContain('SALES')->and($codes)->toContain('PURCHASES')
            ->and($codes)->not->toContain('CASH')->and($codes)->not->toContain('SUPPLIER')
            ->and($codes)->not->toContain('CUSTOMER');
    });
});

it('produces a reconciling balance sheet as at a date', function () {
    [$user, $company] = reportSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        postReportScenario();
        $report = app(FinancialReports::class)->balanceSheet('2026-11-30');

        // Assets: customer receivable 1000 + input GST 108 less cash paid 400.
        expect((string) $report['assets'])->toBe('708.00')
            // Liabilities: supplier payable 708 less 400 paid.
            ->and((string) $report['liabilities'])->toBe('308.00')
            ->and((string) $report['retained'])->toBe('400.00')
            ->and($report['balanced'])->toBeTrue();
        // The reconciliation identity holds exactly.
        $rhs = $report['liabilities']->add($report['equity'])->add($report['retained']);
        expect($report['assets']->equals($rhs))->toBeTrue();
    });
});

it('keeps reports inside the session company', function () {
    [$user, $company] = reportSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        postReportScenario();
    });

    $other = companyFixture('Other Report Co');
    addMembership($user, $other);
    assignCompanyRole($user, $other, 'reports-clerk', ['reports.view', 'invoices.view', 'purchases.view', 'receipts.view']);
    inTenantContext($other, function (): void {
        $report = app(FinancialReports::class)->trialBalance('2026-11-01', '2026-11-30');
        expect($report['rows'])->toBe([])->and($report['totals']['debit']->isZero())->toBeTrue();

        $pl = app(FinancialReports::class)->profitAndLoss('2026-11-01', '2026-11-30');
        expect($pl['rows'])->toBe([])->and($pl['net']->isZero())->toBeTrue();

        $bs = app(FinancialReports::class)->balanceSheet('2026-11-30');
        expect($bs['assets']->isZero())->toBeTrue()->and($bs['balanced'])->toBeTrue();
    });
});

it('renders the report screens for authorized users and 403s others', function () {
    [$user, $company] = reportSession(['purchases.view', 'invoices.view', 'receipts.view']); // no reports.view
    $this->actingAs($user);
    $this->get(route('reports.trial-balance'))->assertForbidden();

    $viewer = webUser('report-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'report-viewer', ['reports.view']);
    $this->actingAs($viewer);
    inTenantContext($company, function (): void {
        $this->get(route('reports.trial-balance'))->assertOk()->assertSee('Trial balance', false);
        $this->get(route('reports.profit-and-loss'))->assertOk()->assertSee('Profit', false);
        $this->get(route('reports.balance-sheet'))->assertOk()->assertSee('Balance sheet', false);

        // Empty company: the components render sensible empty states.
        Livewire::test(TrialBalance::class)->assertSee('No posted movements yet', false);
        Livewire::test(ProfitAndLoss::class)->assertSee('No income or expense movements yet', false);
        Livewire::test(BalanceSheet::class)->assertSee('No posted movements yet', false);
    });
});

it('reflects new postings on the screens after they are made', function () {
    [$user, $company] = reportSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party, '1000.00');

        Livewire::test(ProfitAndLoss::class, ['from' => '2026-11-01', 'until' => '2026-11-30'])
            ->assertSee('1,000.00', false);

        Livewire::test(BalanceSheet::class, ['until' => '2026-11-30'])
            ->assertSee('Balanced', false);
    });
});
