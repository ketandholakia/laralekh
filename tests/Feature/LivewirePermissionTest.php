<?php
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Sales\Actions\{PostInvoice, PostReceipt};
use App\Domain\Sales\Models\{Invoice, Receipt};
use App\Livewire\Accounting\ReversalEditor;
use App\Livewire\Sales\{CorrectionEditor, InvoiceEditor, ReceiptEditor, ReceiptView};
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
        return app(\App\Domain\Sales\Actions\SaveInvoiceDraft::class)->handle(
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
        expect(\App\Domain\Sales\Models\ReceiptAllocation::count())->toBe($allocationCount)
            ->and(\App\Domain\Sales\Models\ReceiptAllocationReversal::count())->toBe(0)
            ->and(\App\Domain\Sales\Models\ReceiptReversal::count())->toBe(0);
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
        expect(\App\Domain\Sales\Models\SalesCorrection::count())->toBe(0)
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
            ->and(\App\Domain\Accounting\Models\VoucherReversal::count())->toBe(0)
            ->and(Voucher::whereKey($voucherId)->sole()->status)->toBe('posted');
    });
});
