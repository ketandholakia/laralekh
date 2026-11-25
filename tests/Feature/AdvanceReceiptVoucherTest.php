<?php

declare(strict_types=1);

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostAdvanceReceiptVoucher;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\SaveAdvanceReceiptVoucherDraft;
use App\Domain\Sales\Models\AdvanceReceiptVoucher;
use App\Domain\Sales\Services\ReceiptService;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use App\Livewire\Sales\AdvanceReceiptVoucherEditor;
use App\Livewire\Sales\AdvanceReceiptVoucherView;
use App\Livewire\Sales\ReceiptView;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

it('creates and posts a statutory advance receipt voucher with intra-state CGST and SGST', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function () use ($user): void {
        [$party] = masters();
        // Gross advance of ₹1180.00 at 18% intra-state -> taxable ₹1000.00, CGST ₹90.00, SGST ₹90.00
        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance payment for architectural services',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            paymentReference: 'NEFT-ADV-1',
            userId: $user->id,
        );

        expect($draft->status)->toBe('draft')
            ->and((string) $draft->gross_amount)->toBe('1180.00')
            ->and((string) $draft->taxable_amount)->toBe('1000.00')
            ->and((string) $draft->cgst)->toBe('90.00')
            ->and((string) $draft->sgst)->toBe('90.00')
            ->and((string) $draft->igst)->toBe('0.00');

        $posted = app(PostAdvanceReceiptVoucher::class)->handle($draft->id, $user->id);

        expect($posted->status)->toBe('posted')
            ->and($posted->series)->toBe('ARV')
            ->and($posted->number)->toBe(1)
            ->and($posted->label())->toBe('ARV/1')
            ->and($posted->party_snapshot['name'])->toBe($party->name)
            ->and($posted->supplier_snapshot['name'])->toBe('Company');
    });
});

it('applies statutory Rule 50 provisos for indeterminable rate and nature of supply', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function () use ($user): void {
        [$party] = masters();

        // Proviso (i): when rate is not determinable, 18% fallback applies
        $indeterminableRate = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance where tax rate is not yet determined',
            supplyType: 'services',
            isRateDeterminable: false,
            isInterstateDeterminable: true,
            taxRate: '5.00', // Should be overridden by statutory 18%
            userId: $user->id,
        );

        expect((string) $indeterminableRate->tax_rate)->toBe('18.00')
            ->and((string) $indeterminableRate->taxable_amount)->toBe('1000.00')
            ->and((string) $indeterminableRate->cgst)->toBe('90.00')
            ->and((string) $indeterminableRate->sgst)->toBe('90.00');

        // Proviso (ii): when place of supply / nature is not determinable, inter-state IGST applies
        $indeterminablePos = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27', // Intra-state POS, but indeterminable flag is set
            grossAmount: '1180.00',
            description: 'Advance where supply destination is undetermined',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: false, // Forces IGST under Rule 50 proviso (ii)
            taxRate: '18.00',
            userId: $user->id,
        );

        expect((string) $indeterminablePos->igst)->toBe('180.00')
            ->and((string) $indeterminablePos->cgst)->toBe('0.00')
            ->and((string) $indeterminablePos->sgst)->toBe('0.00');

        // Notification 66/2017-CT: advances for goods have 0% advance tax
        $goodsAdvance = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '2000.00',
            description: 'Advance for supply of industrial machines',
            supplyType: 'goods',
            userId: $user->id,
        );

        expect((string) $goodsAdvance->tax_rate)->toBe('0.00')
            ->and((string) $goodsAdvance->taxable_amount)->toBe('2000.00')
            ->and((string) $goodsAdvance->cgst)->toBe('0.00')
            ->and((string) $goodsAdvance->sgst)->toBe('0.00')
            ->and((string) $goodsAdvance->igst)->toBe('0.00');
    });
});

it('links statutory advance receipt voucher to a posted receipt and validates capacity', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function () use ($user): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '1000.00')->id);

        // Advance voucher within available funds succeeds
        $voucher = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1000.00',
            description: 'Statutory voucher for receipt SR/1',
            receiptId: $receipt->id,
            userId: $user->id,
        );

        expect($voucher->receipt_id)->toBe($receipt->id)
            ->and((string) $voucher->gross_amount)->toBe('1000.00');

        // Exceeding available receipt funds throws DomainException
        expect(fn () => app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1000.01',
            description: 'Excessive advance voucher',
            receiptId: $receipt->id,
            userId: $user->id,
        ))->toThrow(DomainException::class, 'exceeds available receipt funds');
    });
});

it('enforces immutability triggers on posted advance receipt vouchers', function () {
    [$user, $company] = receiptSession();
    $this->actingAs($user);

    inTenantContext($company, function () use ($user): void {
        [$party] = masters();
        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '500.00',
            description: 'Advance for services',
            userId: $user->id,
        );

        $posted = app(PostAdvanceReceiptVoucher::class)->handle($draft->id, $user->id);

        // Direct UPDATE on posted voucher is aborted by trigger
        expect(fn () => AdvanceReceiptVoucher::where('id', $posted->id)->update(['gross_amount' => '600.00']))
            ->toThrow(QueryException::class);

        // Direct DELETE on posted voucher is aborted by trigger
        expect(fn () => AdvanceReceiptVoucher::where('id', $posted->id)->delete())
            ->toThrow(QueryException::class);
    });
});

it('isolates advance receipt vouchers across companies', function () {
    [$userA, $companyA] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post'], 'userA@example.test');
    [$userB, $companyB] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post'], 'userB@example.test');

    $voucherId = inTenantContext($companyA, function () use ($userA): int {
        [$party] = masters();
        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '800.00',
            description: 'Company A secret advance',
            userId: $userA->id,
        );

        return $draft->id;
    });

    $this->actingAs($userB);
    inTenantContext($companyB, function () use ($voucherId): void {
        expect(AdvanceReceiptVoucher::find($voucherId))->toBeNull();
    });
});

it('renders Livewire AdvanceReceiptVoucherEditor, AdvanceReceiptVoucherView, and ReceiptView link', function () {
    [$user, $company] = receiptSession();
    assignCompanyRole($user, $company, 'full-settlement', [
        'invoices.view', 'receipts.view', 'receipts.create', 'receipts.post',
    ]);
    $this->actingAs($user);

    inTenantContext($company, function (): void {
        [$party] = masters();
        $receipt = app(PostReceipt::class)->handle(receiptDraft($party, '2360.00')->id);

        // Test Livewire Editor
        $editor = Livewire::test(AdvanceReceiptVoucherEditor::class, [
            'party' => $party->id,
            'receipt' => $receipt->id,
        ])
            ->assertSee($party->name)
            ->assertSet('grossAmount', '2360.00')
            ->set('description', 'Consulting advance under Section 31(3)(d)')
            ->set('placeOfSupply', '27')
            ->set('taxRate', '18.00')
            ->call('postVoucher');

        $voucher = AdvanceReceiptVoucher::where('party_id', $party->id)->first();
        expect($voucher)->not->toBeNull()
            ->and($voucher->status)->toBe('posted')
            ->and((string) $voucher->gross_amount)->toBe('2360.00')
            ->and((string) $voucher->taxable_amount)->toBe('2000.00')
            ->and((string) $voucher->cgst)->toBe('180.00')
            ->and((string) $voucher->sgst)->toBe('180.00');

        $editor->assertRedirect(route('advance-vouchers.show', $voucher->id));

        // Test Livewire View
        Livewire::test(AdvanceReceiptVoucherView::class, ['id' => $voucher->id])
            ->assertSee($voucher->label())
            ->assertSee('₹2360.00')
            ->assertSee('₹2000.00')
            ->assertSee('Consulting advance under Section 31(3)(d)')
            ->assertSee('CGST Act s. 31(3)(d)');

        // Test ReceiptView displays linked ARV
        Livewire::test(ReceiptView::class, ['receiptId' => $receipt->id])
            ->assertSee($voucher->label());
    });
});
