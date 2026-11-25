<?php

declare(strict_types=1);

use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Gst\Returns\ReturnReviewCsvExporter;
use App\Domain\Gst\Returns\ReturnValidationReport;
use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Actions\PostAdvanceReceiptVoucher;
use App\Domain\Sales\Actions\SaveAdvanceReceiptVoucherDraft;
use App\Livewire\Gst\Returns\Detail;
use Livewire\Livewire;

it('integrates posted advance receipt vouchers into return dataset advances and Table 11A', function () {
    [$user, $company] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user, $company): void {
        [$party] = masters();

        // Create and post an intra-state service advance voucher (18% tax: ₹1000 taxable, ₹90 CGST, ₹90 SGST)
        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance payment for architectural consultation',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            paymentReference: 'NEFT-ADV-101',
            userId: $user->id,
        );
        $posted = app(PostAdvanceReceiptVoucher::class)->handle($draft->id, $user->id);

        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);

        expect($dataset['advances']['received'])->toHaveCount(1)
            ->and($dataset['advances']['received'][0]['document_number'])->toBe('ARV/1')
            ->and($dataset['advances']['received'][0]['gross_amount'])->toBe('1180.00')
            ->and($dataset['advances']['received'][0]['taxable_amount'])->toBe('1000.00')
            ->and($dataset['advances']['received'][0]['cgst'])->toBe('90.00')
            ->and($dataset['advances']['received'][0]['sgst'])->toBe('90.00')
            ->and($dataset['advances']['received'][0]['igst'])->toBe('0.00');

        expect($dataset['advances']['table_11a'])->toHaveCount(1)
            ->and($dataset['advances']['table_11a'][0]['place_of_supply'])->toBe('27')
            ->and($dataset['advances']['table_11a'][0]['rate'])->toBe('18.00')
            ->and($dataset['advances']['table_11a'][0]['gross_amount'])->toBe('1180.00')
            ->and($dataset['advances']['table_11a'][0]['taxable_amount'])->toBe('1000.00')
            ->and($dataset['advances']['table_11a'][0]['cgst'])->toBe('90.00')
            ->and($dataset['advances']['table_11a'][0]['sgst'])->toBe('90.00')
            ->and($dataset['advances']['table_11a'][0]['igst'])->toBe('0.00');
    });
});

it('excludes goods advances from Table 11A tax liability per Notification 66/2017-CT', function () {
    [$user, $company] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user, $company): void {
        [$party] = masters();

        // Advance for supply of goods is tax-exempt at receipt stage under Notification No. 66/2017-CT
        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-10',
            placeOfSupply: '27',
            grossAmount: '5000.00',
            description: 'Advance payment for machinery purchase (goods)',
            supplyType: 'goods',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            paymentReference: 'IMPS-GOODS-1',
            userId: $user->id,
        );
        $posted = app(PostAdvanceReceiptVoucher::class)->handle($draft->id, $user->id);

        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);

        // Tracked in advances.received as statutory record
        expect($dataset['advances']['received'])->toHaveCount(1)
            ->and($dataset['advances']['received'][0]['supply_type'])->toBe('goods')
            ->and($dataset['advances']['received'][0]['taxable_amount'])->toBe('0.00')
            ->and($dataset['advances']['received'][0]['cgst'])->toBe('0.00')
            ->and($dataset['advances']['received'][0]['sgst'])->toBe('0.00');

        // Excluded from Table 11A outward tax liability
        expect($dataset['advances']['table_11a'])->toBeEmpty();
    });
});

it('handles Rule 50 provisos for undetermined rate and nature of supply in Table 11A', function () {
    [$user, $company] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user, $company): void {
        [$party] = masters();

        // Proviso (i): Rate undetermined -> 18% statutory fallback
        $draftUndeterminedRate = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-12',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance where tax rate is not determinable',
            supplyType: 'services',
            isRateDeterminable: false,
            isInterstateDeterminable: true,
            taxRate: '5.00', // Overridden by statutory 18%
            userId: $user->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($draftUndeterminedRate->id, $user->id);

        // Proviso (ii): Destination undetermined -> Inter-state IGST
        $draftUndeterminedPos = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-15',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance where supply destination is undetermined',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: false,
            taxRate: '18.00',
            userId: $user->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($draftUndeterminedPos->id, $user->id);

        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);

        expect($dataset['advances']['received'])->toHaveCount(2);

        $intraEntry = collect($dataset['advances']['table_11a'])->firstWhere('igst', '0.00');
        expect($intraEntry)->not->toBeNull()
            ->and($intraEntry['rate'])->toBe('18.00')
            ->and($intraEntry['cgst'])->toBe('90.00')
            ->and($intraEntry['sgst'])->toBe('90.00');

        $interEntry = collect($dataset['advances']['table_11a'])->firstWhere('cgst', '0.00');
        expect($interEntry)->not->toBeNull()
            ->and($interEntry['rate'])->toBe('18.00')
            ->and($interEntry['igst'])->toBe('180.00');
    });
});

it('includes advance receipt vouchers in documents issued Table 13', function () {
    [$user, $company] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user, $company): void {
        [$party] = masters();

        $v1 = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-05',
            placeOfSupply: '27',
            grossAmount: '500.00',
            description: 'Advance 1',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            userId: $user->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($v1->id, $user->id);

        $v2 = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-18',
            placeOfSupply: '27',
            grossAmount: '700.00',
            description: 'Advance 2',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            userId: $user->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($v2->id, $user->id);

        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);

        $receiptVouchers = collect($dataset['documents_issued'])->firstWhere('nature_of_document', 'Receipt voucher');
        expect($receiptVouchers)->not->toBeNull()
            ->and($receiptVouchers['from'])->toBe('1')
            ->and($receiptVouchers['to'])->toBe('2')
            ->and($receiptVouchers['total'])->toBe(2)
            ->and($receiptVouchers['cancelled'])->toBe(0);
    });
});

it('maintains strict tenant isolation for advance receipt vouchers in return datasets', function () {
    [$userA, $companyA] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export'], 'userA@example.test');
    [$userB, $companyB] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export'], 'userB@example.test');

    $this->actingAs($userA);
    inTenantContext($companyA, function () use ($userA): void {
        [$partyA] = masters();
        $draftA = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $partyA->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Company A advance',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            userId: $userA->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($draftA->id, $userA->id);
    });

    $this->actingAs($userB);
    inTenantContext($companyB, function () use ($userB, $companyB): void {
        [$partyB] = masters();
        $draftB = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $partyB->id,
            date: '2026-11-08',
            placeOfSupply: '24',
            grossAmount: '2360.00',
            description: 'Company B advance',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            userId: $userB->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($draftB->id, $userB->id);

        $datasetB = app(ReturnDatasetBuilder::class)->build($companyB, '2026-11', 50000000);
        expect($datasetB['advances']['received'])->toHaveCount(1)
            ->and($datasetB['advances']['received'][0]['document_number'])->toBe('ARV/1')
            ->and($datasetB['advances']['received'][0]['gross_amount'])->toBe('2360.00')
            ->and($datasetB['advances']['received'][0]['place_of_supply'])->toBe('24');
    });

    $this->actingAs($userA);
    inTenantContext($companyA, function () use ($companyA): void {
        $datasetA = app(ReturnDatasetBuilder::class)->build($companyA, '2026-11', 50000000);
        expect($datasetA['advances']['received'])->toHaveCount(1)
            ->and($datasetA['advances']['received'][0]['document_number'])->toBe('ARV/1')
            ->and($datasetA['advances']['received'][0]['gross_amount'])->toBe('1180.00')
            ->and($datasetA['advances']['received'][0]['place_of_supply'])->toBe('27');
    });
});

it('includes advance tax summary in ReturnReviewCsvExporter when advances exist', function () {
    [$user, $company] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user, $company): void {
        [$party] = masters();

        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance consultation',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            userId: $user->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($draft->id, $user->id);

        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
        $report = app(ReturnValidationReport::class)->forDataset($dataset);
        $csv = app(ReturnReviewCsvExporter::class)->export($dataset, $report);

        expect($csv)->toContain('advances (Table 11A - POS 27, 18.00%)')
            ->and($csv)->toContain('1000.00')
            ->and($csv)->toContain('90.00');
    });
});

it('renders advance receipt vouchers in GSTR return detail Livewire component', function () {
    [$user, $company] = receiptSession('accountant', [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'returns.view', 'returns.finalize', 'returns.export']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($user): void {
        [$party] = masters();

        $draft = app(SaveAdvanceReceiptVoucherDraft::class)->handle(
            partyId: $party->id,
            date: '2026-11-06',
            placeOfSupply: '27',
            grossAmount: '1180.00',
            description: 'Advance consultation',
            supplyType: 'services',
            isRateDeterminable: true,
            isInterstateDeterminable: true,
            taxRate: '18.00',
            userId: $user->id,
        );
        app(PostAdvanceReceiptVoucher::class)->handle($draft->id, $user->id);

        Livewire::test(Detail::class, ['period' => '2026-11'])
            ->assertSuccessful()
            ->assertSee('Advances received (Table 11A)')
            ->assertSee('18.00%')
            ->assertSee('1,180.00')
            ->assertSee('1,000.00');
    });
});
