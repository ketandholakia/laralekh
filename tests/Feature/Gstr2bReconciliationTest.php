<?php

declare(strict_types=1);

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Gst\Returns\Gstr3bSummaryBuilder;
use App\Domain\Gst\Returns\Models\Gstr2bImport;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Gst\Returns\Services\Gstr2bImportService;
use App\Domain\Gst\Returns\Services\Gstr2bParser;
use App\Domain\Gst\Returns\Services\Gstr2bReconciler;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Livewire\Gst\Returns\Detail;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function gstr2bSupplierFixture(string $gstin, string $name = 'Supplier A'): Party
{
    (new ChartOfAccountsSeeder)->run();
    $ledger = Ledger::firstOrCreate(
        ['code' => 'SUPP_'.substr(md5($gstin), 0, 8)],
        ['name' => $name, 'ledger_group_id' => LedgerGroup::where('code', 'PAYABLES')->sole()->id]
    );

    return Party::firstOrCreate(
        ['gstin' => $gstin],
        ['ledger_id' => $ledger->id, 'name' => $name, 'state_code' => substr($gstin, 0, 2)]
    );
}

function gstr2bBillFixture(Party $supplier, string $billNo, string $amount, string $date = '2026-11-10', string $taxRate = '18'): PurchaseBill
{
    $year = FinancialYear::firstOrCreate(['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    $item = Item::firstOrCreate(['sku' => 'PURCHASE_ITEM_18'], ['name' => 'Test Purchase Item', 'hsn_sac' => '9983']);
    $rate = TaxRate::firstOrCreate(['code' => 'RATE_'.$taxRate], ['rate' => $taxRate, 'effective_from' => '2026-04-01', 'is_verified' => true]);

    $bill = PurchaseBill::create([
        'financial_year_id' => $year->id,
        'party_id' => $supplier->id,
        'supplier_bill_number' => $billNo,
        'supplier_bill_date' => $date,
        'date' => $date,
        'place_of_supply' => '27',
        'series' => 'PB',
    ]);

    $bill->items()->create([
        'financial_year_id' => $year->id,
        'item_id' => $item->id,
        'purchase_ledger_id' => Ledger::where('code', 'PURCHASES')->sole()->id,
        'tax_rate_id' => $rate->id,
        'position' => 1,
        'description' => 'Purchase line',
        'hsn_sac' => '9983',
        'quantity' => '1',
        'unit_price' => $amount,
        'discount' => '0.00',
    ]);

    app(PostPurchaseBill::class)->handle($bill->id);

    return $bill->fresh();
}

it('imports and parses official GST portal GSTR-2B JSON format with multi-item aggregation', function () {
    inSalesCompany(function (Company $company) {
        $supplier = gstr2bSupplierFixture('27AABCU9603R1ZM', 'Acme Components Ltd');
        $bill = gstr2bBillFixture($supplier, 'INV-2026-001', '1000.00', '2026-11-10', '18');

        // Official GSTR-2B JSON schema sample (docdata.b2b)
        $portalJson = json_encode([
            'gstin' => $company->gstin,
            'fp' => '112026',
            'data' => [
                'docdata' => [
                    'b2b' => [
                        [
                            'ctin' => '27AABCU9603R1ZM',
                            'trdnm' => 'Acme Components Ltd',
                            'inv' => [
                                [
                                    'inum' => 'INV-2026-001',
                                    'idt' => '10-11-2026',
                                    'val' => 1180.00,
                                    'pos' => '27',
                                    'rchrg' => 'N',
                                    'inv_typ' => 'R',
                                    'itcavl' => 'Y',
                                    'items' => [
                                        [
                                            'num' => 1,
                                            'txval' => 1000.00,
                                            'rt' => 18.0,
                                            'camt' => 90.00,
                                            'samt' => 90.00,
                                            'iamt' => 0.00,
                                            'csamt' => 0.00,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $import = app(Gstr2bImportService::class)->import($company, '2026-11', $portalJson, 'gstr2b_test.json');

        expect($import->file_format)->toBe('json')
            ->and($import->total_documents)->toBe(1)
            ->and($import->summary['comparison']['matched']['count'])->toBe(1)
            ->and($import->summary['comparison']['matched']['tax'])->toBe('180.00')
            ->and($import->summary['comparison']['tax_mismatch']['count'])->toBe(0)
            ->and($import->summary['comparison']['missing_in_portal']['count'])->toBe(0)
            ->and($import->summary['comparison']['missing_in_books']['count'])->toBe(0);

        expect($import->records[0]['status'])->toBe('matched')
            ->and($import->records[0]['document_number'])->toBe('INV-2026-001')
            ->and($import->records[0]['books_tax'])->toBe('180.00')
            ->and($import->records[0]['portal_tax'])->toBe('180.00')
            ->and($import->records[0]['itc_available'])->toBeTrue();
    });
});

it('reconciles portal B2B CSV table format and enforces ₹1 rounding tolerance', function () {
    inSalesCompany(function (Company $company) {
        $supplier = gstr2bSupplierFixture('27AABCU9603R1ZM', 'Acme Components Ltd');

        // Bill 1: ₹1000 @ 18% -> tax 180.00 in books. Portal has 180.50 (diff 0.50 -> MATCHED within ₹1)
        gstr2bBillFixture($supplier, 'INV-MATCH-01', '1000.00', '2026-11-12', '18');

        // Bill 2: ₹1000 @ 18% -> tax 180.00 in books. Portal has 185.00 (diff 5.00 -> TAX_MISMATCH)
        gstr2bBillFixture($supplier, 'INV-MISMATCH-02', '1000.00', '2026-11-14', '18');

        $csv = implode("\r\n", [
            '"GSTIN of supplier","Trade/Legal name","Invoice number","Invoice type","Invoice Date","Invoice Value(₹)","Place of supply","Supply Attract Reverse Charge","GSTR-1/IFF/GSTR-5 Filing Status","GSTR-1/IFF/GSTR-5 Filing Date","GSTR-1/IFF/GSTR-5 Filing Period","Taxable Value (₹)","Integrated Tax(₹)","Central Tax(₹)","State/UT Tax(₹)","Cess(₹)","ITC Availability","Reason for Ineligibility"',
            '"27AABCU9603R1ZM","Acme Components Ltd","INV-MATCH-01","Regular","12-11-2026","1180.50","27-Maharashtra","N","Yes","11-12-2026","Nov-2026","1000.00","0.00","90.25","90.25","0.00","Y",""',
            '"27AABCU9603R1ZM","Acme Components Ltd","INV-MISMATCH-02","Regular","14-11-2026","1185.00","27-Maharashtra","N","Yes","11-12-2026","Nov-2026","1000.00","0.00","92.50","92.50","0.00","Y",""',
        ]);

        $import = app(Gstr2bImportService::class)->import($company, '2026-11', $csv, 'GSTR2B_B2B.csv');

        expect($import->file_format)->toBe('csv')
            ->and($import->total_documents)->toBe(2)
            ->and($import->summary['comparison']['matched']['count'])->toBe(1)
            ->and($import->summary['comparison']['tax_mismatch']['count'])->toBe(1);

        $matchedRec = collect($import->records)->firstWhere('document_number', 'INV-MATCH-01');
        $mismatchRec = collect($import->records)->firstWhere('document_number', 'INV-MISMATCH-02');

        expect($matchedRec['status'])->toBe('matched')
            ->and($matchedRec['difference'])->toBe('0.00')
            ->and($mismatchRec['status'])->toBe('tax_mismatch')
            ->and($mismatchRec['difference'])->toBe('-5.00');
    });
});

it('flags Section 16(2)(aa) compliance risk for bills missing in portal and identifies unclaimed ITC for invoices missing in books', function () {
    inSalesCompany(function (Company $company) {
        $supplier = gstr2bSupplierFixture('27AABCU9603R1ZM', 'Acme Components Ltd');

        // Booked bill missing in portal
        gstr2bBillFixture($supplier, 'INV-MISSING-PORTAL', '2000.00', '2026-11-15', '18');

        // Portal document not booked in books
        $csv = implode("\r\n", [
            '"GSTIN of supplier","Trade/Legal name","Invoice number","Invoice type","Invoice Date","Invoice Value(₹)","Place of supply","Supply Attract Reverse Charge","GSTR-1/IFF/GSTR-5 Filing Status","GSTR-1/IFF/GSTR-5 Filing Period","Taxable Value (₹)","Integrated Tax(₹)","Central Tax(₹)","State/UT Tax(₹)","Cess(₹)","ITC Availability","Reason for Ineligibility"',
            '"27AABCU9603R1ZM","Acme Components Ltd","INV-UNBOOKED-PORTAL","Regular","16-11-2026","3540.00","27-Maharashtra","N","Yes","Nov-2026","3000.00","0.00","270.00","270.00","0.00","Y",""',
        ]);

        $import = app(Gstr2bImportService::class)->import($company, '2026-11', $csv, 'GSTR2B_Discrepancies.csv');

        expect($import->summary['comparison']['missing_in_portal']['count'])->toBe(1)
            ->and($import->summary['comparison']['missing_in_portal']['tax'])->toBe('360.00')
            ->and($import->summary['comparison']['missing_in_books']['count'])->toBe(1)
            ->and($import->summary['comparison']['missing_in_books']['tax'])->toBe('540.00');

        $missingPortal = collect($import->records)->firstWhere('document_number', 'INV-MISSING-PORTAL');
        $missingBooks = collect($import->records)->firstWhere('document_number', 'INV-UNBOOKED-PORTAL');

        expect($missingPortal['status'])->toBe('missing_in_portal')
            ->and($missingPortal['itc_available'])->toBeFalse()
            ->and($missingPortal['ineligibility_reason'])->toContain('16(2)(aa)')
            ->and($missingBooks['status'])->toBe('missing_in_books')
            ->and($missingBooks['itc_available'])->toBeTrue();
    });
});

it('handles statutory portal ineligible ITC flag (itcavl = N)', function () {
    inSalesCompany(function (Company $company) {
        $supplier = gstr2bSupplierFixture('27AABCU9603R1ZM', 'Acme Components Ltd');
        gstr2bBillFixture($supplier, 'INV-INELIGIBLE', '5000.00', '2026-11-18', '18');

        $portalJson = json_encode([
            'gstin' => $company->gstin,
            'data' => [
                'docdata' => [
                    'b2b' => [
                        [
                            'ctin' => '27AABCU9603R1ZM',
                            'inv' => [
                                [
                                    'inum' => 'INV-INELIGIBLE',
                                    'idt' => '18-11-2026',
                                    'val' => 5900.00,
                                    'pos' => '27',
                                    'itcavl' => 'N',
                                    'rsn' => 'Ineligible under Section 17(5)',
                                    'items' => [
                                        [
                                            'num' => 1,
                                            'txval' => 5000.00,
                                            'rt' => 18.0,
                                            'camt' => 450.00,
                                            'samt' => 450.00,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $import = app(Gstr2bImportService::class)->import($company, '2026-11', $portalJson, 'ineligible.json');

        expect($import->summary['portal_2b']['itc_ineligible_tax'])->toBe('900.00')
            ->and($import->summary['portal_2b']['itc_available_tax'])->toBe('0.00');

        $rec = collect($import->records)->firstWhere('document_number', 'INV-INELIGIBLE');
        expect($rec['status'])->toBe('matched')
            ->and($rec['itc_available'])->toBeFalse()
            ->and($rec['ineligibility_reason'])->toBe('Ineligible under Section 17(5)');
    });
});

it('integrates GSTR-2B reconciliation into ReturnDatasetBuilder and GSTR-3B summary with statutory warnings', function () {
    inSalesCompany(function (Company $company) {
        $supplier = gstr2bSupplierFixture('27AABCU9603R1ZM', 'Acme Components Ltd');

        // Booked bill missing from GSTR-2B
        gstr2bBillFixture($supplier, 'INV-RISK-16-2-AA', '1000.00', '2026-11-05', '18');

        // Portal doc missing from books
        $csv = implode("\r\n", [
            '"GSTIN of supplier","Trade/Legal name","Invoice number","Invoice type","Invoice Date","Invoice Value(₹)","Place of supply","Supply Attract Reverse Charge","GSTR-1/IFF/GSTR-5 Filing Status","GSTR-1/IFF/GSTR-5 Filing Period","Taxable Value (₹)","Integrated Tax(₹)","Central Tax(₹)","State/UT Tax(₹)","Cess(₹)","ITC Availability","Reason for Ineligibility"',
            '"27AABCU9603R1ZM","Acme","INV-UNCLAIMED","Regular","05-11-2026","1180.00","27-Maharashtra","N","Yes","Nov-2026","1000.00","0.00","90.00","90.00","0.00","Y",""',
        ]);

        app(Gstr2bImportService::class)->import($company, '2026-11', $csv, 'portal.csv');

        // Build dataset
        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);

        expect($dataset)->toHaveKey('gstr2b')
            ->and($dataset)->toHaveKey('gstr2b_meta')
            ->and($dataset['gstr2b_meta']['source_filename'])->toBe('portal.csv')
            ->and($dataset['gstr2b']['comparison']['missing_in_portal']['count'])->toBe(1);

        // Build GSTR-3B summary
        $dataset['validation'] = ['valid' => true, 'errors' => [], 'warnings' => []];
        $gstr3b = app(Gstr3bSummaryBuilder::class)->build($dataset);

        expect($gstr3b['4_itc'])->toHaveKey('gstr2b_reconciliation')
            ->and($gstr3b['warnings'])->toContain('Section 16(2)(aa) compliance risk: 1 purchase bill(s) totaling ₹180.00 tax are booked in LaraLekh but missing from GSTR-2B.')
            ->and($gstr3b['warnings'])->toContain('Unclaimed ITC opportunity: 1 document(s) in GSTR-2B totaling ₹180.00 eligible ITC have not been recorded in books.');
    });
});

it('strictly isolates GSTR-2B imports across tenants', function () {
    [$userA, $companyA] = webSession('user-a', ['returns.view', 'returns.finalize'], 'user-a@example.test');
    [$userB, $companyB] = webSession('user-b', ['returns.view', 'returns.finalize'], 'user-b@example.test');

    $json = json_encode([
        'gstin' => $companyA->gstin,
        'data' => ['docdata' => ['b2b' => []]],
    ], JSON_THROW_ON_ERROR);

    inTenantContext($companyA, function () use ($companyA, $companyB, $json) {
        $import = app(Gstr2bImportService::class)->import($companyA, '2026-11', $json, 'company_a.json');
        expect(Gstr2bImport::query()->where('period', '2026-11')->count())->toBe(1);

        // Trying to import company B data inside company A tenant context fails
        expect(fn () => app(Gstr2bImportService::class)->import($companyB, '2026-11', $json, 'other.json'))
            ->toThrow(DomainException::class, 'Company must match the active tenant context.');
    });

    inTenantContext($companyB, function () {
        // Company B cannot see Company A's import
        expect(Gstr2bImport::query()->where('period', '2026-11')->first())->toBeNull();
    });
});

it('allows uploading and deleting GSTR-2B statement via Livewire UI', function () {
    [$user, $company] = webSession('gstr2b-manager', ['returns.view', 'returns.finalize'], 'gstr2b-ui@example.test');
    $this->actingAs($user);

    inTenantContext($company, function () use ($company) {
        $json = json_encode([
            'gstin' => $company->gstin,
            'data' => [
                'docdata' => [
                    'b2b' => [
                        [
                            'ctin' => '27AABCU9603R1ZM',
                            'inv' => [
                                [
                                    'inum' => 'UI-TEST-INV-1',
                                    'idt' => '01-11-2026',
                                    'val' => 1180.00,
                                    'pos' => '27',
                                    'items' => [
                                        ['txval' => 1000.00, 'camt' => 90.00, 'samt' => 90.00],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('gstr2b_nov2026.json', $json);

        // Import via Livewire
        Livewire::test(Detail::class, ['period' => '2026-11'])
            ->set('gstr2bFile', $file)
            ->call('importGstr2b')
            ->assertSee('GSTR-2B statement imported and reconciled successfully.')
            ->assertSee('JSON · 1 portal docs')
            ->assertSee('Matched (±₹1)')
            ->assertSee('Missing in books');

        expect(Gstr2bImport::query()->where('period', '2026-11')->count())->toBe(1);

        // Delete via Livewire
        Livewire::test(Detail::class, ['period' => '2026-11'])
            ->call('deleteGstr2b')
            ->assertSee('GSTR-2B import removed.');

        expect(Gstr2bImport::query()->where('period', '2026-11')->count())->toBe(0);
    });
});
