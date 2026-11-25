<?php

use App\Domain\Gst\Returns\GstinValidator;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Gst\Returns\ReturnValidationReport;
use App\Domain\Masters\Models\Company;
use App\Domain\Purchase\Actions\PostPurchaseBill;

it('reports missing or invalid B2B GSTINs and warns that portal existence is unverified', function () {
    $report = app(ReturnValidationReport::class);
    $base = ['validation' => ['errors' => [], 'warnings' => []], 'meta' => ['period' => '2026-11'], 'b2b' => [
        ['id' => 1, 'date' => '2026-11-10', 'recipient' => [], 'items' => []],
        ['id' => 2, 'date' => '2026-11-10', 'recipient' => ['gstin' => 'NOT-A-GSTIN'], 'items' => []],
        ['id' => 3, 'date' => '2026-11-10', 'recipient' => ['gstin' => '27AAPFU0939F1ZV'], 'items' => []],
    ]];
    $result = $report->forDataset($base);
    $codes = collect($result['errors'])->pluck('code');
    expect($codes)->toContain('missing_recipient_gstin', 'invalid_recipient_gstin')->not->toContain('gstin_checksum_unverified')
        ->and($result['valid'])->toBeFalse()
        ->and(collect($result['warnings'])->pluck('code'))->toContain('gstin_portal_existence_unverified');
});

it('validates GSTIN structure, assigned state code, and mod-36 check digit case-insensitively', function () {
    $validator = app(GstinValidator::class);
    expect($validator->isValid('27AAPFU0939F1ZV'))->toBeTrue()
        ->and($validator->isValid('29AAACB2894G1ZJ'))->toBeTrue()
        ->and($validator->isValid('27aapfu0939f1zv'))->toBeTrue()
        ->and($validator->isValid('27AAPFU0939F1ZA'))->toBeFalse()
        ->and($validator->isValid('00AAPFU0939F1ZV'))->toBeFalse()
        ->and($validator->isValid('39AAPFU0939F1ZV'))->toBeFalse()
        ->and($validator->isValid('27AAPFU0939F1Z'))->toBeFalse();
});

it('reports documents outside period and missing HSN or UQC', function () {
    $dataset = ['validation' => ['errors' => [], 'warnings' => []], 'meta' => ['period' => '2026-11'], 'b2cl' => [
        ['id' => 4, 'date' => '2026-10-31', 'items' => [['tax_category' => 'taxable', 'hsn_sac' => '', 'unit' => '']]],
    ]];
    $result = app(ReturnValidationReport::class)->forDataset($dataset);
    $codes = collect($result['errors'])->pluck('code');
    expect($codes)->toContain('document_outside_period', 'missing_hsn', 'missing_uqc');
});

it('accepts verified Table 13 document-issued categories and fails closed on unknown categories', function () {
    $validDataset = [
        'validation' => ['errors' => [], 'warnings' => []],
        'meta' => ['period' => '2026-11'],
        'documents_issued' => [
            ['nature_of_document' => 'Invoices for outward supply', 'from' => '1', 'to' => '2', 'total' => 2, 'cancelled' => 0],
            ['nature_of_document' => 'Credit Note', 'from' => '1', 'to' => '1', 'total' => 1, 'cancelled' => 0],
            ['nature_of_document' => 'Debit Note', 'from' => '1', 'to' => '1', 'total' => 1, 'cancelled' => 0],
            ['nature_of_document' => 'Receipt voucher', 'from' => '1', 'to' => '1', 'total' => 1, 'cancelled' => 0],
        ],
    ];
    $validResult = app(ReturnValidationReport::class)->forDataset($validDataset);
    expect($validResult['valid'])->toBeTrue()
        ->and($validResult['errors'])->toBeEmpty();

    $invalidDataset = [
        'validation' => ['errors' => [], 'warnings' => []],
        'meta' => ['period' => '2026-11'],
        'documents_issued' => [
            ['nature_of_document' => 'Custom Unverified Slip', 'from' => '1', 'to' => '1', 'total' => 1, 'cancelled' => 0],
        ],
    ];
    $invalidResult = app(ReturnValidationReport::class)->forDataset($invalidDataset);
    expect($invalidResult['valid'])->toBeFalse()
        ->and(collect($invalidResult['errors'])->pluck('code'))->toContain('unverified_document_nature');
});

it('accepts verified ITC eligibility without blocking errors and warns on lines marked for review', function () {
    inSalesCompany(function (Company $company) {
        $eligibleBill = returnPurchaseFixture(['supplier_bill_number' => 'PB-ELIG-1']);
        app(PostPurchaseBill::class)->handle($eligibleBill->id);

        $reviewBill = returnPurchaseFixture(['supplier_bill_number' => 'PB-REV-1']);
        $reviewBill->items()->update(['itc_eligibility' => 'ineligible_reversal_review']);
        app(PostPurchaseBill::class)->handle($reviewBill->id);

        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
        $report = app(ReturnValidationReport::class)->forDataset($dataset);

        expect($report['valid'])->toBeTrue()
            ->and(collect($report['errors'])->pluck('code'))->not->toContain('unverified_itc_eligibility')
            ->and(collect($report['warnings'])->pluck('code'))->toContain('itc_eligibility_pending_review');
    });
});
