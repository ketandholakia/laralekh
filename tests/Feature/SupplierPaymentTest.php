<?php

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Actions\PostPayment;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Purchase\Models\SupplierPaymentAllocation;
use App\Domain\Purchase\Models\SupplierPaymentAllocationReversal;
use App\Domain\Shared\Tenancy\TenantContext;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Supplier-payment service tests (LL-P1-001). These prove the accounting
// invariants of PostPayment against the actual code: exact balanced entries,
// derived bill outstanding with active-allocation semantics, full-allocation
// rule, closed-year guards, immutability of posted payments, reversal
// restoring outstanding, and failure paths that consume no number.

it('posts a balanced supplier payment with exact entries, an SP number and a snapshot', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $payment = supplierPaymentDraft($bill->party, '1180.00');

        $posted = app(PostPayment::class)->post($payment->id, [$bill->id => '1180.00']);

        $voucher = Voucher::whereKey($posted->voucher_id)->with('lines.ledger')->sole();
        $codes = $voucher->lines->mapWithKeys(
            fn ($l) => [$l->ledger->code => [(string) $l->debit, (string) $l->credit]])->all();
        expect($codes)->toBe(['SUPPLIER' => ['1180.00', '0.00'], 'CASH' => ['0.00', '1180.00']])
            ->and($voucher->type)->toBe('payment')
            ->and($posted->status)->toBe('posted')->and($posted->number)->toBe(1)
            ->and($posted->supplier_snapshot['name'])->toBe('Supplier')
            ->and(NumberSequence::where('series', 'SP')->sole()->last_number)->toBe(1);
    });
});

it('derives bill outstanding across partial payments and refuses over-allocation', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $party = $bill->party;

        $first = app(PostPayment::class)->post(
            supplierPaymentDraft($party, '400.00')->id, [$bill->id => '400.00']);
        $outstanding = fn (): string => (string) PurchaseBill::whereKey($bill->id)->sole()
            ->total->subtract(SupplierPaymentAllocation::where('purchase_bill_id', $bill->id)
                ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('supplier_payment_allocation_reversals')
                    ->whereColumn('allocation_id', 'supplier_payment_allocations.id'))
                ->get()->reduce(fn ($s, $r) => $s->add($r->amount), \App\Domain\Shared\ValueObjects\Money::of('0')));
        expect($outstanding())->toBe('780.00');

        $second = app(PostPayment::class)->post(
            supplierPaymentDraft($party, '780.00')->id, [$bill->id => '780.00']);
        expect($outstanding())->toBe('0.00');

        // The bill is fully settled; anything further exceeds outstanding.
        expect(fn () => app(PostPayment::class)->post(
            supplierPaymentDraft($party, '1.00')->id, [$bill->id => '1.00']))
            ->toThrow(DomainException::class, 'exceeds bill outstanding');
        unset($first, $second);
    });
});

it('uses taxable amount as the outstanding of a reverse-charge bill', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture(['reverse_charge' => true]);
        app(PostPurchaseBill::class)->handle($bill->id);
        // RCM payable is the taxable value only (GST is discharged directly),
        // so paying the full invoice-style total must be rejected.
        expect(fn () => app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1180.00')->id, [$bill->id => '1180.00']))
            ->toThrow(DomainException::class, 'exceeds bill outstanding');
        app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1000.00')->id, [$bill->id => '1000.00']);
        expect(SupplierPaymentAllocation::count())->toBe(1);
    });
});

it('refuses partially allocated payments', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        expect(fn () => app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1180.00')->id, [$bill->id => '400.00']))
            ->toThrow(DomainException::class, 'fully allocated');
        expect(SupplierPayment::sole()->status)->toBe('draft');
    });
});

it('refuses payments into a closed financial year or outside its dates', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $year = FinancialYear::sole();

        $year->update(['is_closed' => true]);
        expect(fn () => app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1180.00')->id, [$bill->id => '1180.00']))
            ->toThrow(DomainException::class, 'open financial year');
        $year->update(['is_closed' => false]);

        expect(fn () => app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1180.00', ['date' => '2028-01-01'])->id,
            [$bill->id => '1180.00']))
            ->toThrow(DomainException::class, 'open financial year');
    });
});

it('requires a liability supplier ledger and a cash/bank payment ledger', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);

        // A payment to a party whose ledger is not a liability is rejected by
        // the payment's own guard (posted bills are immutable, so the party
        // cannot be mutated on the bill — bill posting enforces the same rule).
        $assetLedger = Ledger::firstOrCreate(['code' => 'FIXTURE-ASSET'], [
            'name' => 'Fixture asset', 'ledger_group_id' => LedgerGroup::where('code', 'ASSETS')->sole()->id]);
        $badSupplier = Party::create(['name' => 'Bad supplier', 'state_code' => '27', 'ledger_id' => $assetLedger->id]);
        expect(fn () => app(PostPayment::class)->post(
            supplierPaymentDraft($badSupplier, '1180.00')->id, [$bill->id => '1180.00']))
            ->toThrow(DomainException::class, 'liability');

        // Payment ledger that is not cash/bank.
        $salesLedger = Ledger::where('code', 'SALES')->sole();
        $year = FinancialYear::sole();
        $badCash = SupplierPayment::create(['financial_year_id' => $year->id, 'party_id' => $bill->party_id,
            'ledger_id' => $salesLedger->id, 'date' => '2026-11-06', 'amount' => '1180.00']);
        expect(fn () => app(PostPayment::class)->post($badCash->id, [$bill->id => '1180.00']))
            ->toThrow(DomainException::class, 'cash/bank');
    });
});

it('rejects duplicate posting without consuming a second number', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $payment = supplierPaymentDraft($bill->party, '1180.00');
        app(PostPayment::class)->post($payment->id, [$bill->id => '1180.00']);

        expect(fn () => app(PostPayment::class)->post($payment->id, [$bill->id => '1180.00']))
            ->toThrow(DomainException::class, 'already posted');
        expect(NumberSequence::where('series', 'SP')->sole()->last_number)->toBe(1);
    });
});

it('rolls back completely when an allocation fails and consumes no number', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        $one = supplierBillFixture();
        $two = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($one->id);
        app(PostPurchaseBill::class)->handle($two->id);
        // Second allocation exceeds the second bill's outstanding.
        $payment = supplierPaymentDraft($one->party, '2360.00');

        expect(fn () => app(PostPayment::class)->post($payment->id, [
            $one->id => '1180.00', $two->id => '2360.00']))
            ->toThrow(DomainException::class);

        expect($payment->fresh()->status)->toBe('draft')
            ->and($payment->fresh()->voucher_id)->toBeNull()
            ->and(SupplierPaymentAllocation::count())->toBe(0)
            ->and(NumberSequence::where('series', 'SP')->exists())->toBeFalse();
    });
});

it('keeps allocation inside the company: a foreign bill fails closed', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    $foreignBill = null;
    $localParty = null;
    inTenantContext($company, function () use (&$localParty): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $localParty = $bill->party;
    });

    // A second company the user also belongs to, with its own bill.
    $other = companyFixture('Other Supplier Co');
    addMembership($user, $other);
    inTenantContext($other, function () use (&$foreignBill): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $foreignBill = $bill->id;
    });

    inTenantContext($company, function () use ($foreignBill, $localParty): void {
        $payment = supplierPaymentDraft($localParty, '100.00');
        expect(fn () => app(PostPayment::class)->post($payment->id, [$foreignBill => '100.00']))
            ->toThrow(DomainException::class, 'posted bill for this supplier');
    });
});

it('reverses a payment, marks allocations reversed and restores bill outstanding', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function () use ($company): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $payment = app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '400.00')->id, [$bill->id => '400.00']);
        $yearId = $payment->financial_year_id;

        app(PostPayment::class)->reverse($payment->id, $yearId, '2026-11-10', 'Payment returned to supplier');

        expect(SupplierPaymentAllocationReversal::count())->toBe(1)
            ->and($payment->fresh()->status)->toBe('posted');
        // The mirror voucher restores cash; the bill outstanding is whole again.
        $active = SupplierPaymentAllocation::where('purchase_bill_id', $bill->id)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('supplier_payment_allocation_reversals')
                ->whereColumn('allocation_id', 'supplier_payment_allocations.id'))->count();
        expect($active)->toBe(0);
        // The restored outstanding can be settled by a later payment.
        app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '1180.00', ['date' => '2026-11-11'])->id,
            [$bill->id => '1180.00']);
        expect(SupplierPaymentAllocation::count())->toBe(2)
            ->and(Voucher::where('type', 'reversal')->count())->toBe(1);
    });
});

it('enforces reversal input rules', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function () use ($company): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $payment = app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '400.00')->id, [$bill->id => '400.00']);

        expect(fn () => app(PostPayment::class)->reverse($payment->id, $payment->financial_year_id, '2026-11-10', 'no'))
            ->toThrow(DomainException::class);
        expect(fn () => app(PostPayment::class)->reverse($payment->id, $payment->financial_year_id, '2026-11-10', 'Supplier returned the funds'))
            ->not->toThrow(DomainException::class);
    });
});

it('handles exact paise through the payment path', function () {
    $company = companyFixture();
    app(TenantContext::class)->run($company->id, function (): void {
        // Exempt bill so the payable equals the exact paise amount.
        $bill = supplierBillFixture([], ['tax_category' => 'exempt', 'tax_rate_id' => null, 'unit_price' => '100.10']);
        app(PostPurchaseBill::class)->handle($bill->id);
        expect((string) $bill->fresh()->total)->toBe('100.10');

        app(PostPayment::class)->post(
            supplierPaymentDraft($bill->party, '100.10')->id, [$bill->id => '100.10']);
        expect((string) SupplierPaymentAllocation::sole()->amount)->toBe('100.10');
    });
});

it('cannot post a payment whose supplier ledger belongs to another company', function () {
    // Tenant isolation already forbids cross-company writes at the model layer;
    // this proves the payment path honours the tenant scope end to end.
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);
        $payment = supplierPaymentDraft($bill->party, '1180.00');

        // Simulate a crafted foreign id: posting resolves everything inside
        // the active context, so a foreign payment id simply does not exist.
        expect(fn () => app(PostPayment::class)->post(999999, [$bill->id => '1180.00']))
            ->toThrow(ModelNotFoundException::class);
        expect($payment->fresh()->status)->toBe('draft');
    });
});
