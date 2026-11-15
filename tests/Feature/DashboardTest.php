<?php

use App\Domain\Accounting\Actions\ReverseVoucher;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\ReverseReceipt as ReverseReceiptAction;
use App\Domain\Sales\Services\CorrectionService;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

// Dashboard receivables must mirror the SettlementService outstanding
// definition. Every scenario posts real documents through the domain actions
// and asserts on the rendered HTML, so both the math and the authorization
// (what a member may see) are covered end to end.

const DASH_FULL = ['invoices.view', 'invoices.create', 'invoices.post',
    'receipts.view', 'receipts.create', 'receipts.post', 'receipts.allocate',
    'corrections.view', 'corrections.create', 'corrections.post', 'reversals.post'];

function postCreditNote(Invoice $invoice, string $amount = '500.00'): void
{
    $note = saveCorrection($invoice, $amount, ['kind' => 'credit']);
    app(CorrectionService::class)->post($note->id);
}

function postDebitNote(Invoice $invoice, string $amount = '500.00'): void
{
    $note = saveCorrection($invoice, $amount, ['kind' => 'debit', 'reason_code' => 'undercharge']);
    app(CorrectionService::class)->post($note->id);
}

it('counts a fully unpaid posted invoice as receivable', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party, '1000.00');
    });

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Receivables (open)')
        ->assertSee('₹1,000.00', false);
});

it('reduces receivables for partially allocated invoices', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        app(PostReceipt::class)->handle(receiptDraft($party, '400.00')->id, [$invoice->id => '400.00']);
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('600.00');
    });

    $this->get(route('dashboard'))->assertOk()->assertSee('₹600.00', false);
});

it('shows fully settled invoices as no receivable', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        app(PostReceipt::class)->handle(receiptDraft($party, '1000.00')->id, [$invoice->id => '1000.00']);
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('0.00');
    });

    $this->get(route('dashboard'))->assertOk()->assertSee('₹0.00', false);
});

it('reduces receivables by posted credit notes', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $invoice = correctionSource(); // posted GST invoice, total 1180.00
        postCreditNote($invoice, '500.00'); // note total 590.00 (GST added)
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('590.00');
    });

    $this->get(route('dashboard'))->assertOk()->assertSee('₹590.00', false);
});

it('nets combined credit and debit notes against one invoice', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $invoice = correctionSource(); // total 1180.00
        // 590 credit and 590 debit cancel out: outstanding returns to 1180.
        postCreditNote($invoice, '500.00');
        postDebitNote($invoice, '500.00');
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('1180.00');
    });

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Receivables (open)', '₹1,180.00'], false);
});

it('increases receivables by posted debit notes', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $invoice = correctionSource();
        postDebitNote($invoice, '500.00'); // note total 590.00
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('1770.00');
    });

    $this->get(route('dashboard'))->assertOk()->assertSee('₹1,770.00', false);
});

it('excludes internally reversed invoices from receivables', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        app(ReverseVoucher::class)->handle($invoice->voucher_id, '2026-11-14', 'Test internal reversal', true);
        expect(app(SettlementService::class)->invoiceReversed($invoice->fresh()))->toBeTrue();
    });

    $this->get(route('dashboard'))
        ->assertOk()
        // The KPI value directly after the unique label proves the receivable
        // is zero; the recent-invoices table legitimately still lists the
        // reversed document's total.
        ->assertSeeInOrder(['Receivables (open)', '₹0.00'], false);
});

it('restores receivables when a receipt allocation is reversed with its receipt', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    $yearId = null;
    inTenantContext($company, function () use ($company, &$yearId): void {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '1000.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '400.00')->id, [$invoice->id => '400.00']);
        $yearId = app(CompanySession::class)->financialYearId();
        app(ReverseReceiptAction::class)->handle($receipt->id, $yearId, '2026-11-14', 'Test reversal');
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('1000.00');
    });

    $this->get(route('dashboard'))->assertOk()->assertSee('₹1,000.00', false);
});

it('reports excess customer credits separately and never as receivable', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters(); // correctionSource uses this same seeded party
        $invoice = correctionSource(); // posted GST invoice, total 1180.00
        // 700 received and allocated, then a 590 credit note: outstanding -110.
        app(PostReceipt::class)->handle(receiptDraft($party, '700.00')->id, [$invoice->id => '700.00']);
        postCreditNote($invoice, '500.00');
        expect((string) app(SettlementService::class)->documentOutstanding($invoice->fresh()))->toBe('-110.00');
    });

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Excess customer credits')
        ->assertSee('₹110.00', false)
        // Receivables themselves settle at zero; the credit is not folded in.
        ->assertSee('₹0.00', false);
});

it('never mixes other companies documents into the session company dashboard', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        postInvoiceFor($party, '1234.00');
    });

    // A second company the user also belongs to, with the same role granted
    // inside that company's scope (roles are company-scoped, so permissions
    // must be granted here again — membership alone grants nothing).
    $other = companyFixture('Other Co');
    addMembership($user, $other);
    app(TenantContext::class)->run($other->id, function () use ($user, $other): void {
        (new Database\Seeders\ChartOfAccountsSeeder)->run();
        assignCompanyRole($user, $other, 'accountant', DASH_FULL);
        FinancialYear::firstOrCreate(
            ['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'],
        );
    });
    $year = app(TenantContext::class)->run($other->id,
        fn (): FinancialYear => FinancialYear::where('name', '2026-27')->sole());
    app(CompanySession::class)->select($user, $other->id, $year->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Receivables (open)', '₹0.00'], false)
        ->assertDontSee('₹1,234.00', false)
        ->assertDontSee('SI/1', false);
});

it('hides every financial figure from users without the matching permissions', function () {
    [$user] = webSession('clerk', ['returns.view']);
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Receivables (open)')
        ->assertDontSee('Sales ·')
        ->assertDontSee('Purchases ·')
        ->assertDontSee('Receipts ·')
        ->assertDontSee('Recent invoices')
        ->assertDontSee('₹', false);

    // And with no view permissions at all, the page stays neutral.
    [$bare, $bareCompany] = webSession('clerk', [], 'bare@example.test');
    $this->actingAs($bare);
    expect(app(CompanySession::class)->companyId())->toBe($bareCompany->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('₹', false)
        ->assertDontSee('Receivables (open)');
});

// Guards against accidental schema drift while asserting money arithmetic
// stays exact through the dashboard path. Allocations are capped at the
// invoice outstanding by the domain, so the first invoice settles fully.
it('sums mixed documents with exact paise arithmetic', function () {
    [$user, $company] = webSession('accountant', DASH_FULL);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party] = masters();
        $a = postInvoiceFor($party, '100.10');
        postInvoiceFor($party, '200.20');
        app(PostReceipt::class)->handle(receiptDraft($party, '100.10')->id, [$a->id => '100.10']);
        expect(DB::table('invoices')->where('status', 'posted')->count())->toBe(2);
    });

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Receivables (open)', '₹200.20'], false);
});
