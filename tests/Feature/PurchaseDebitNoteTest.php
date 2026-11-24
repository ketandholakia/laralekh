<?php

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Purchase\Actions\PostPurchaseDebitNote;
use App\Domain\Purchase\Actions\SavePurchaseDebitNoteDraft;
use App\Domain\Purchase\Models\PurchaseDebitNote;
use App\Livewire\Purchase\PurchaseDebitNoteEditor;
use App\Livewire\Purchase\PurchaseDebitNoteView;
use Livewire\Livewire;

function postedBillFixture(array $billData = [], array $lineData = [])
{
    $bill = supplierBillFixture($billData, $lineData);

    return app(PostPurchaseBill::class)->handle($bill->id);
}

it('restricts debit note creation and view to authorized users', function () {
    [$user, $company] = webSession('viewer', ['purchases.view']);
    $this->actingAs($user);

    $bill = inTenantContext($company, fn () => postedBillFixture());

    // Viewer can view the bill but cannot reach the debit note create screen
    $this->get(route('purchase-debit-notes.create', $bill->id))->assertForbidden();

    // User without purchases.view gets 403 everywhere
    $stranger = webUser('stranger@example.test');
    addMembership($stranger, $company);
    $this->actingAs($stranger);

    $this->get(route('purchases.show', $bill->id))->assertForbidden();
});

it('saves a draft purchase debit note against a posted purchase bill', function () {
    [$user, $company] = webSession('purchase-clerk', ['purchases.view', 'purchases.create']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($company): void {
        $bill = postedBillFixture([], ['quantity' => '5', 'unit_price' => '200.00']);
        $item = $bill->items->first();

        $debitNote = app(SavePurchaseDebitNoteDraft::class)->handle(
            null,
            $bill->id,
            ['date' => '2026-11-06', 'reason' => 'Defective goods returned'],
            [['purchase_bill_item_id' => $item->id, 'quantity' => '2', 'description' => 'Stock return']]
        );

        expect($debitNote->status)->toBe('draft')
            ->and($debitNote->series)->toBe('PDN')
            ->and($debitNote->number)->toBeNull()
            ->and($debitNote->lines->count())->toBe(1)
            ->and((string) $debitNote->lines->first()->quantity)->toBe('2.000')
            // Proportion: 2 / 5 = 40% of 1000 = 400 taxable
            ->and((string) $debitNote->taxable_amount)->toBe('400.00')
            ->and((string) $debitNote->cgst)->toBe('36.00')
            ->and((string) $debitNote->sgst)->toBe('36.00')
            ->and((string) $debitNote->total)->toBe('472.00');
    });
});

it('enforces capacity and prevents returning more than remaining bill quantity', function () {
    [$user, $company] = webSession('purchase-clerk', ['purchases.view', 'purchases.create']);
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        $bill = postedBillFixture([], ['quantity' => '3', 'unit_price' => '100.00']);
        $item = $bill->items->first();

        // Returning 4 when only 3 were purchased must fail
        expect(fn () => app(SavePurchaseDebitNoteDraft::class)->handle(
            null,
            $bill->id,
            ['date' => '2026-11-06', 'reason' => 'Excess return attempt'],
            [['purchase_bill_item_id' => $item->id, 'quantity' => '4']]
        ))->toThrow(DomainException::class, 'exceeds remaining returnable quantity');
    });
});

it('posts a purchase debit note with balanced accounting and stock reduction', function () {
    [$user, $company] = webSession('purchase-clerk', ['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        $bill = postedBillFixture([], ['quantity' => '10', 'unit_price' => '500.00']);
        $item = $bill->items->first();

        $draft = app(SavePurchaseDebitNoteDraft::class)->handle(
            null,
            $bill->id,
            ['date' => '2026-11-06', 'reason' => 'Damaged in transit'],
            [['purchase_bill_item_id' => $item->id, 'quantity' => '3']]
        );

        $posted = app(PostPurchaseDebitNote::class)->handle($draft->id);

        expect($posted->status)->toBe('posted')
            ->and($posted->number)->toBe(1)
            ->and($posted->voucher_id)->not->toBeNull()
            ->and($posted->voucher->type)->toBe('debit_note');

        // Verify voucher lines:
        // Dr Supplier payable = 1770.00
        // Cr Purchase expense = 1500.00
        // Cr Input CGST = 135.00
        // Cr Input SGST = 135.00
        $voucherLines = $posted->voucher->lines;
        $totalDebits = $voucherLines->sum(fn ($l) => (float) (string) $l->debit);
        $totalCredits = $voucherLines->sum(fn ($l) => (float) (string) $l->credit);

        expect($totalDebits)->toEqualWithDelta(1770.00, 0.01)
            ->and($totalCredits)->toEqualWithDelta(1770.00, 0.01);

        // Verify stock movement was reduced for tracked inventory (inward was 10, return is -3)
        $movement = StockMovement::where('voucher_id', $posted->voucher_id)->first();
        expect($movement)->not->toBeNull()
            ->and((string) $movement->quantity)->toBe('-3.000')
            ->and((string) $movement->reference)->toBe('PDN/1');
    });
});

it('tracks cumulative returns across multiple debit notes against the same bill', function () {
    [$user, $company] = webSession('purchase-clerk', ['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        $bill = postedBillFixture([], ['quantity' => '5', 'unit_price' => '100.00']);
        $item = $bill->items->first();

        // First debit note returns 3
        $draft1 = app(SavePurchaseDebitNoteDraft::class)->handle(
            null,
            $bill->id,
            ['date' => '2026-11-06', 'reason' => 'First partial return'],
            [['purchase_bill_item_id' => $item->id, 'quantity' => '3']]
        );
        app(PostPurchaseDebitNote::class)->handle($draft1->id);

        // Second debit note can return up to 2
        $draft2 = app(SavePurchaseDebitNoteDraft::class)->handle(
            null,
            $bill->id,
            ['date' => '2026-11-06', 'reason' => 'Second partial return'],
            [['purchase_bill_item_id' => $item->id, 'quantity' => '2']]
        );
        $posted2 = app(PostPurchaseDebitNote::class)->handle($draft2->id);
        expect($posted2->number)->toBe(2);

        // Third debit note attempt must fail as 5/5 have been returned
        expect(fn () => app(SavePurchaseDebitNoteDraft::class)->handle(
            null,
            $bill->id,
            ['date' => '2026-11-06', 'reason' => 'Excess third return'],
            [['purchase_bill_item_id' => $item->id, 'quantity' => '1']]
        ))->toThrow(DomainException::class, 'exceeds remaining returnable quantity 0.000');
    });
});

it('operates through the Livewire PurchaseDebitNoteEditor and View screens', function () {
    [$user, $company] = webSession('purchase-clerk', ['purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($company): void {
        $bill = postedBillFixture([], ['quantity' => '4', 'unit_price' => '250.00']);
        $item = $bill->items->first();

        // Mount editor and issue debit note
        $component = Livewire::test(PurchaseDebitNoteEditor::class, ['bill' => $bill->id])
            ->assertSee('Issue Purchase Debit Note')
            ->assertSee($bill->supplier_bill_number)
            ->set('reason', 'Quality defective')
            ->set('lines.'.$item->id.'.selected', true)
            ->set('lines.'.$item->id.'.quantity', '2')
            ->call('post')
            ->assertHasNoErrors();

        $debitNote = PurchaseDebitNote::where('purchase_bill_id', $bill->id)->firstOrFail();
        expect($debitNote->status)->toBe('posted')
            ->and((string) $debitNote->lines->first()->quantity)->toBe('2.000');

        // Mount view screen and check assertions
        Livewire::test(PurchaseDebitNoteView::class, ['debitNote' => $debitNote->id])
            ->assertSee('PDN/'.$debitNote->number)
            ->assertSee('Quality defective')
            ->assertSee('Accounting Voucher Entries')
            ->assertSee('Stock');
    });
});
