<?php

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Actions\PostPayment;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Livewire\Purchase\SupplierPaymentEditor;
use App\Livewire\Purchase\SupplierPaymentList;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Supplier-payment UI tests (LL-P1-002). The editor reuses PostPayment for
// every accounting rule; these tests prove permissions (per-method guards),
// tenancy, the draft → post workflow, and that the register surfaces derived
// outstanding balances without duplicating accounting logic. All Livewire
// interactions run inside the tenant context, matching the receipt UI tests.

function supplierUiSession(array $permissions, string $email = 'payments@example.test'): array
{
    return webSession('payments-clerk', $permissions, $email);
}

it('lists payments for the session company and hides the register without purchases.view', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '400.00')->id, [$bill->id => '400.00']);
        Livewire::test(SupplierPaymentList::class)
            ->assertSee('SP/1', false)
            ->assertSee('400.00', false);
    });

    // A member without purchases.view cannot reach the register.
    $viewer = webUser('no-purchase@example.test');
    addMembership($viewer, $company);
    $this->actingAs($viewer);
    $this->get(route('supplier-payments.index'))->assertForbidden();
});

it('guards editor state changes per method for create-only users', function () {
    [$creator, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $viewer = webUser('purchase-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'purchase-viewer', ['purchases.view']);

    $this->actingAs($creator);
    $partyId = inTenantContext($company, function (): int {
        supplierBillFixture();

        return \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id;
    });
    $component = inTenantContext($company, fn () => Livewire::test(SupplierPaymentEditor::class));

    $this->actingAs($viewer);
    inTenantContext($company, function () use ($component, $partyId): void {
        assert403(fn () => $component->instance()->selectParty($partyId));
        assert403(fn () => $component->instance()->save());
        assert403(fn () => $component->instance()->post());
    });

    // The route itself requires purchases.create even to mount.
    $this->actingAs($viewer);
    $this->get(route('supplier-payments.create'))->assertForbidden();
});

it('records a payment through the editor and reduces the bill outstanding', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    $billId = inTenantContext($company, function (): int {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);

        return $bill->id;
    });

    inTenantContext($company, function () use ($billId): void {
        $component = Livewire::test(SupplierPaymentEditor::class)
            ->set('partyId', \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id)
            ->set('partyName', 'Supplier');
        $bills = $component->instance()->eligibleBills;
        expect($bills)->toHaveCount(1)->and($bills[0]['outstanding'])->toBe('1180.00');

        $component->set('ledgerId', cashLedger()->id)
            ->set('date', '2026-11-10')
            ->set('amount', '400.00')
            ->set('allocations.'.$billId, '400.00')
            ->call('post')
            ->assertRedirect();

        $payment = SupplierPayment::sole();
        expect($payment->status)->toBe('posted')->and((string) $payment->amount)->toBe('400.00')
            ->and((string) $payment->allocations->sole()->amount)->toBe('400.00');
        // Posted bill documents were not mutated by the settlement.
        expect(DB::table('purchase_bills')->where('id', $billId)->value('total'))->toBe('1180.00');
    });
});

it('shows validation and domain errors without posting', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    $billId = inTenantContext($company, function (): int {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);

        return $bill->id;
    });

    inTenantContext($company, function () use ($billId): void {
        $component = Livewire::test(SupplierPaymentEditor::class);
        // Missing everything: the editor reports field errors and posts nothing.
        $component->call('save')->assertHasErrors(['party', 'ledger', 'amount']);
        expect(SupplierPayment::count())->toBe(0);

        // Over-allocation is reported through PostPayment's own rule.
        $component->set('partyId', \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id)
            ->set('ledgerId', cashLedger()->id)
            ->set('date', '2026-11-10')
            ->set('amount', '1180.00')
            ->set('allocations.'.$billId, '1180.01')
            ->call('post')
            ->assertHasErrors(['amount']);
        expect(SupplierPayment::count())->toBe(0);
    });
});

it('never exposes another company payments or bills through the register', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1180.00')->id, [$bill->id => '1180.00']);
    });

    // A second company of the same user: the role must be granted inside that
    // company's scope (roles are company-scoped), and it has no payments.
    $other = companyFixture('Other Pay Co');
    addMembership($user, $other);
    assignCompanyRole($user, $other, 'payments-clerk', ['purchases.view', 'purchases.create', 'purchases.post']);
    inTenantContext($other, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
    });

    inTenantContext($other, function (): void {
        Livewire::test(SupplierPaymentList::class)
            ->assertSee('No supplier payments yet', false)
            ->assertDontSee('1180.00', false);
    });
});


// --- Audit remediations F-01/F-02 (session-derived financial year; view tests) ---

it('files payments into the selected financial year even when it is not 2026-27', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    $billId = null;
    $newYearId = inTenantContext($company, function () use (&$billId, $user, $company): int {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $billId = $bill->id;
        // A later financial year, deliberately not the fixture's default one.
        $year = FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        app(CompanySession::class)->select($user, $company->id, $year->id);

        return $year->id;
    });

    inTenantContext($company, function () use ($billId, $newYearId): void {
        $partyId = \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id;
        Livewire::test(SupplierPaymentEditor::class)
            ->set('partyId', $partyId)->set('partyName', 'Supplier')
            ->set('ledgerId', cashLedger()->id)
            ->set('date', '2027-05-10') // inside the selected 2027-28 year
            ->set('amount', '400.00')
            ->set('allocations.'.$billId, '400.00')
            ->call('post')
            ->assertRedirect();

        $payment = SupplierPayment::sole();
        expect($payment->financial_year_id)->toBe($newYearId)
            ->and($payment->date->toDateString())->toBe('2027-05-10')
            ->and($payment->status)->toBe('posted');
    });
});

it('reports a missing selected financial year as validation, not a 500', function () {
    [$user, $company] = webSession('payments-clerk', ['purchases.view', 'purchases.create', 'purchases.post'], 'fyless@example.test');
    $this->actingAs($user);
    // Clear the session selection entirely: re-selecting the same company
    // would keep its year (years reset only on company switch).
    app(CompanySession::class)->forget();
    app(CompanySession::class)->select($user, $company->id);

    inTenantContext($company, function (): void {
        supplierBillFixture(); // creates the Supplier party for this company
        Livewire::test(SupplierPaymentEditor::class)
            ->set('partyId', \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id)
            ->set('ledgerId', cashLedger()->id)
            ->set('date', '2026-11-10')
            ->set('amount', '100.00')
            ->call('save')
            ->assertHasErrors(['date']);
        expect(SupplierPayment::count())->toBe(0);
    });
});

it('rejects drafts into a closed selected financial year and dates outside it', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    $yearId = inTenantContext($company, function () use ($user, $company): int {
        supplierBillFixture();
        $year = FinancialYear::sole();
        $year->update(['is_closed' => true]);
        app(CompanySession::class)->select($user, $company->id, $year->id);

        return $year->id;
    });

    inTenantContext($company, function () use ($yearId): void {
        $partyId = \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id;
        Livewire::test(SupplierPaymentEditor::class)
            ->set('partyId', $partyId)->set('ledgerId', cashLedger()->id)
            ->set('date', '2026-11-10')->set('amount', '100.00')
            ->call('save')
            ->assertHasErrors(['date']);
        expect(SupplierPayment::count())->toBe(0)->and(FinancialYear::find($yearId)->is_closed)->toBeTrue();
    });

    inTenantContext($company, function (): void {
        // Open the year again for the date-boundary case (the screen never
        // reopens; this only resets the fixture for the next assertion).
        FinancialYear::sole()->update(['is_closed' => false]);
        Livewire::test(SupplierPaymentEditor::class)
            ->set('partyId', \App\Domain\Masters\Models\Party::where('name', 'Supplier')->sole()->id)
            ->set('ledgerId', cashLedger()->id)
            ->set('date', '2028-01-01')->set('amount', '100.00') // outside 2026-27
            ->call('save')
            ->assertHasErrors(['date']);
        expect(SupplierPayment::count())->toBe(0);
    });
});

it('renders a posted payment with its allocations for authorized users only', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    [$paymentId, $billNumber] = inTenantContext($company, function (): array {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $posted = app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '400.00')->id, [$bill->id => '400.00']);

        return [$posted->id, $bill->supplier_bill_number]; // sequence varies per suite run
    });

    inTenantContext($company, function () use ($paymentId, $billNumber): void {
        Livewire::test(\App\Livewire\Purchase\SupplierPaymentView::class, ['payment' => $paymentId])
            ->assertSee('SP/1', false)
            ->assertSee('400.00', false)
            ->assertSee('Supplier', false)
            ->assertSee($billNumber, false); // allocation bill reference
    });

    // A member without purchases.view cannot reach the view screen: the
    // route's can:purchases.view middleware rejects the request.
    $viewer = webUser('payment-viewer@example.test');
    addMembership($viewer, $company);
    $this->actingAs($viewer);
    $this->get(route('supplier-payments.show', $paymentId))->assertForbidden();
});

it('hides foreign-company payments behind 404 on the view route', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    $foreignId = null;
    $other = companyFixture('Foreign Pay Co');
    addMembership($user, $other);
    assignCompanyRole($user, $other, 'payments-clerk', ['purchases.view', 'purchases.create', 'purchases.post']);
    inTenantContext($other, function () use (&$foreignId): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $foreignId = app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '100.00')->id, [$bill->id => '100.00'])->id;
    });

    // Session company is still $company: the foreign id resolves to nothing.
    $this->get(route('supplier-payments.show', $foreignId))->assertNotFound();
});

it('shows the reversal banner on a reversed payment like the receipt view does', function () {
    [$user, $company] = supplierUiSession(['purchases.view', 'purchases.create', 'purchases.post', 'reversals.post']);
    $this->actingAs($user);
    $paymentId = null;
    $yearId = null;
    inTenantContext($company, function () use (&$paymentId, &$yearId): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $posted = app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '400.00')->id, [$bill->id => '400.00']);
        $paymentId = $posted->id;
        $yearId = $posted->financial_year_id;
        app(PostPayment::class)->reverse($posted->id, $yearId, '2026-11-10', 'Supplier returned the funds');
    });

    inTenantContext($company, function () use ($paymentId): void {
        Livewire::test(\App\Livewire\Purchase\SupplierPaymentView::class, ['payment' => $paymentId])
            ->assertSee('This payment has been reversed', false)
            ->assertSee('Supplier returned the funds', false);
    });
});
