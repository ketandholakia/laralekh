<?php

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptAllocation;
use App\Domain\Sales\Models\ReceiptAllocationReversal;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Models\SalesCorrection;
use App\Livewire\Accounting\ReversalEditor;
use App\Livewire\Sales\CorrectionEditor;
use App\Livewire\Sales\InvoiceEditor;
use App\Livewire\Sales\ReceiptEditor;
use App\Livewire\Sales\ReceiptView;
use Livewire\Livewire;

// Every Livewire method that changes state must abort with 403 for a user who
// holds only the view permission of its route group. Components are mounted as
// an authorised user, then the acting user is switched to the view-only one,
// proving the per-method guards do not rely on mount-time checks alone.

it('aborts all invoice editor state changes for invoices.view-only users', function () {
    [$creator, $company] = webSession();
    $viewer = webUser('inv-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'inv-viewer', ['invoices.view']);
    $this->actingAs($creator);
    $draft = inTenantContext($company, function (): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();

        return app(SaveInvoiceDraft::class)->handle(
            null, draftPayload($party->id), [linePayload($item->id, $sales->id, $rate->id)]);
    });
    $component = inTenantContext($company, fn () => Livewire::test(InvoiceEditor::class, ['invoice' => $draft->id]));

    $this->actingAs($viewer);
    inTenantContext($company, function () use ($component, $draft): void {
        [$party, $item] = masters();
        assert403(fn () => $component->instance()->selectParty($party->id));
        assert403(fn () => $component->instance()->addItem($item->id));
        assert403(fn () => $component->instance()->addBlankLine());
        assert403(fn () => $component->instance()->removeLine(0));
        assert403(fn () => $component->instance()->save());
        assert403(fn () => $component->instance()->post());
        // Nothing was changed or written.
        expect($draft->fresh()->status)->toBe('draft')->and($draft->fresh()->items->count())->toBe(1)
            ->and(Invoice::count())->toBe(1)->and(Voucher::count())->toBe(0);
    });
});

it('aborts all receipt editor state changes for receipts.view-only users', function () {
    [$creator, $company] = receiptSession();
    $viewer = webUser('rcp-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'rcp-viewer', ['receipts.view']);
    $this->actingAs($creator);
    $component = inTenantContext($company, fn () => Livewire::test(ReceiptEditor::class));

    $this->actingAs($viewer);
    inTenantContext($company, function () use ($component): void {
        [$party] = masters();
        assert403(fn () => $component->instance()->selectParty($party->id));
        assert403(fn () => $component->instance()->save());
        assert403(fn () => $component->instance()->post());
        expect(Receipt::count())->toBe(0)->and(Voucher::where('type', 'receipt')->count())->toBe(0);
    });
});

it('aborts receipt view settlement actions for receipts.view-only users', function () {
    [$creator, $company] = receiptSession();
    $viewer = webUser('rcp-settle-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'rcp-settle-viewer', ['receipts.view']);
    $this->actingAs($creator);
    [$receipt, $allocationCount] = inTenantContext($company, function (): array {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '200.00')->id, [$invoice->id => '200.00']);

        return [$receipt, Receipt::find($receipt->id)->allocations()->count()];
    });
    $this->actingAs($viewer);
    inTenantContext($company, function () use ($receipt, $allocationCount): void {
        $component = Livewire::test(ReceiptView::class, ['receiptId' => $receipt->id]);
        assert403(fn () => $component->instance()->allocate());
        assert403(fn () => $component->instance()->undoAllocations());
        assert403(fn () => $component->instance()->reverse());
        // Settlement history untouched.
        expect(ReceiptAllocation::count())->toBe($allocationCount)
            ->and(ReceiptAllocationReversal::count())->toBe(0)
            ->and(ReceiptReversal::count())->toBe(0);
    });
});

it('aborts correction editor save and post for corrections.view-only users', function () {
    [$creator, $company] = webSession('note-user', ['invoices.view', 'invoices.create', 'invoices.post',
        'corrections.view', 'corrections.create', 'corrections.post']);
    $viewer = webUser('note-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'note-viewer', ['corrections.view']);
    $this->actingAs($creator);
    [$draft, $component] = inTenantContext($company, function (): array {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');
        $component = Livewire::test(CorrectionEditor::class, ['invoice' => $invoice->id]);

        return [$invoice, $component];
    });

    $this->actingAs($viewer);
    inTenantContext($company, function () use ($component, $draft): void {
        assert403(fn () => $component->instance()->save());
        assert403(fn () => $component->instance()->post());
        expect(SalesCorrection::count())->toBe(0)
            ->and($draft->fresh()->status)->toBe('posted');
    });
});

it('aborts the reversal editor post for users without reversals.post', function () {
    [$creator, $company] = webSession('reversal-user', ['invoices.view', 'invoices.create', 'invoices.post',
        'corrections.view', 'reversals.post']);
    $viewer = webUser('rvs-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'rvs-viewer', ['corrections.view']);
    $this->actingAs($creator);
    [$voucherId, $component] = inTenantContext($company, function (): array {
        [$party] = masters();
        $invoice = postInvoiceFor($party, '500.00');
        $component = Livewire::test(ReversalEditor::class, ['voucher' => $invoice->voucher_id]);

        return [$invoice->voucher_id, $component];
    });

    $this->actingAs($viewer);
    inTenantContext($company, function () use ($component, $voucherId): void {
        assert403(fn () => $component->instance()->post());
        expect(Voucher::where('type', 'reversal')->count())->toBe(0)
            ->and(VoucherReversal::count())->toBe(0)
            ->and(Voucher::whereKey($voucherId)->sole()->status)->toBe('posted');
    });
});

it('aborts purchase bill editor state changes for purchases.view-only users', function () {
    [$creator, $company] = webSession('purchase-user', ['invoices.view', 'purchases.view', 'purchases.create', 'purchases.post']);
    $viewer = webUser('pur-viewer@example.test');
    addMembership($viewer, $company);
    assignCompanyRole($viewer, $company, 'purchase-viewer', ['purchases.view']);

    $this->actingAs($creator);
    $draft = inTenantContext($company, function () use ($company): \App\Domain\Purchase\Models\PurchaseBill {
        session(['company_id' => $company->id, 'financial_year_id' => salesYear($company)->id]);
        $year = salesYear($company);
        $payables = \App\Domain\Accounting\Models\LedgerGroup::where('code', 'PAYABLES')->sole();
        $ledger = \App\Domain\Accounting\Models\Ledger::firstOrCreate(['code' => 'SUPPLIER'],
            ['name' => 'Supplier', 'ledger_group_id' => $payables->id]);
        $party = \App\Domain\Masters\Models\Party::firstOrCreate(['ledger_id' => $ledger->id],
            ['name' => 'Supplier', 'state_code' => '27']);
        $item = \App\Domain\Inventory\Models\Item::firstOrCreate(['sku' => 'PURCHASE-STOCK'],
            ['name' => 'Purchase stock', 'hsn_sac' => '1234', 'tracks_stock' => true]);
        $rate = \App\Domain\Gst\Models\TaxRate::where('code', 'PURCHASE_18')
            ->whereDate('effective_from', '2026-04-01')->first()
            ?? \App\Domain\Gst\Models\TaxRate::create(['code' => 'PURCHASE_18',
                'effective_from' => '2026-04-01', 'rate' => '18', 'is_verified' => true]);
        $bill = \App\Domain\Purchase\Models\PurchaseBill::create(['financial_year_id' => $year->id,
            'party_id' => $party->id, 'supplier_bill_number' => 'SUP-1',
            'supplier_bill_date' => '2026-11-05', 'date' => '2026-11-05', 'place_of_supply' => '27']);
        $bill->items()->create(['financial_year_id' => $year->id, 'item_id' => $item->id,
            'purchase_ledger_id' => \App\Domain\Accounting\Models\Ledger::where('code', 'PURCHASES')->sole()->id,
            'tax_rate_id' => $rate->id, 'position' => 1, 'description' => 'Stock', 'hsn_sac' => '1234',
            'quantity' => '1', 'unit_price' => '1000.00', 'discount' => '0.00']);

        return $bill;
    });

    $component = inTenantContext($company, fn () => Livewire::test(\App\Livewire\Purchase\PurchaseBillEditor::class, ['bill' => $draft->id]));

    $this->actingAs($viewer);
    inTenantContext($company, function () use ($component, $draft): void {
        assert403(fn () => $component->instance()->addLine());
        assert403(fn () => $component->instance()->save());
        assert403(fn () => $component->instance()->post());
        // Nothing was written: still a one-line draft, no voucher.
        expect($draft->fresh()->status)->toBe('draft')
            ->and($draft->fresh()->items->count())->toBe(1)
            ->and($draft->fresh()->voucher_id)->toBeNull()
            ->and(\App\Domain\Accounting\Models\Voucher::count())->toBe(0);
    });
});

it('hides the post buttons from create-only users in the editors', function () {
    // Invoice editor: create-only user sees Save draft but not Post invoice.
    [$user, $company] = webSession('inv-creator', ['invoices.view', 'invoices.create'], 'inv-creator@example.test');
    $this->actingAs($user);
    $draft = inTenantContext($company, function (): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();

        return app(\App\Domain\Sales\Actions\SaveInvoiceDraft::class)->handle(
            null, draftPayload($party->id), [linePayload($item->id, $sales->id, $rate->id)]);
    });
    inTenantContext($company, function () use ($draft): void {
        Livewire::test(\App\Livewire\Sales\InvoiceEditor::class, ['invoice' => $draft->id])
            ->assertOk()->assertSee('Save draft')->assertDontSee('Post invoice');
    });

    // Receipt editor: create-only user sees Save draft but not Post receipt.
    [$user, $company] = receiptSession('rcpt-creator', ['receipts.view', 'receipts.create']);
    $this->actingAs($user);
    inTenantContext($company, function () use ($company): void {
        [$party] = masters();
        Livewire::test(\App\Livewire\Sales\ReceiptEditor::class)
            ->assertOk()->assertSee('Save draft')->assertDontSee('Post receipt');
    });

    // Purchase editor: create-only user sees Save draft but not Save and post.
    [$user, $company] = webSession('pur-creator', ['purchases.view', 'purchases.create'], 'pur-creator@example.test');
    $this->actingAs($user);
    inTenantContext($company, function () use ($company): void {
        session(['company_id' => $company->id, 'financial_year_id' => salesYear($company)->id]);
        $year = salesYear($company);
        $payables = \App\Domain\Accounting\Models\LedgerGroup::where('code', 'PAYABLES')->sole();
        $ledger = \App\Domain\Accounting\Models\Ledger::firstOrCreate(['code' => 'SUPPLIER'],
            ['name' => 'Supplier', 'ledger_group_id' => $payables->id]);
        $party = \App\Domain\Masters\Models\Party::firstOrCreate(['ledger_id' => $ledger->id],
            ['name' => 'Supplier', 'state_code' => '27']);
        $bill = \App\Domain\Purchase\Models\PurchaseBill::create(['financial_year_id' => $year->id,
            'party_id' => $party->id, 'supplier_bill_number' => 'SUP-2',
            'supplier_bill_date' => '2026-11-05', 'date' => '2026-11-05', 'place_of_supply' => '27']);
        Livewire::test(\App\Livewire\Purchase\PurchaseBillEditor::class, ['bill' => $bill->id])
            ->assertOk()->assertSee('Save draft')->assertDontSee('Save and post');
    });
});
