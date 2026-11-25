<?php

declare(strict_types=1);

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\GstReturnFilingService;
use App\Domain\Gst\Returns\GstReturnSnapshotService;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Sales\Actions\PostAdvanceReceiptVoucher;
use App\Domain\Sales\Actions\SaveAdvanceReceiptVoucherDraft;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('installs immutability triggers on every protected accounting and GST table across SQLite and MySQL', function () {
    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        $rows = DB::select("SELECT name, tbl_name AS table_name FROM sqlite_master WHERE type = 'trigger'");
    } elseif ($driver === 'mysql') {
        $db = config('database.connections.mysql.database');
        $rows = DB::select(
            'SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS table_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?',
            [$db]
        );
    } else {
        $this->markTestSkipped("Unsupported driver {$driver} for trigger parity verification.");
    }

    $tablesWithTriggers = collect($rows)->pluck('table_name')->unique()->values()->all();

    $expectedTables = [
        'vouchers',
        'voucher_entries',
        'invoices',
        'invoice_items',
        'sales_corrections',
        'sales_correction_lines',
        'receipts',
        'receipt_allocations',
        'purchase_bills',
        'purchase_bill_items',
        'supplier_payments',
        'supplier_payment_allocations',
        'purchase_debit_notes',
        'purchase_debit_note_lines',
        'gst_return_snapshots',
        'gst_return_filings',
        'customer_refunds',
        'customer_refund_allocations',
        'advance_receipt_vouchers',
    ];

    foreach ($expectedTables as $table) {
        expect($tablesWithTriggers)->toContain($table);
    }

    expect(count($rows))->toBeGreaterThanOrEqual(46);
});

it('rejects raw SQL tampering on posted vouchers, invoices, purchase bills, advance vouchers, and GST filings while permitting draft edits', function () {
    inSalesCompany(function (Company $company) {
        // 1. Draft invoice can be modified freely before posting
        $draftInvoice = returnSalesFixture(['date' => '2026-11-06']);
        DB::table('invoices')->where('id', $draftInvoice->id)->update(['notes' => 'draft-edit-ok']);
        expect(DB::table('invoices')->where('id', $draftInvoice->id)->value('notes'))->toBe('draft-edit-ok');

        // 2. Posted invoice & its accounting voucher / entries cannot be tampered with or deleted
        $postedInvoice = app(PostingService::class)->postSalesInvoice($draftInvoice->id);

        expect(fn () => DB::table('invoices')->where('id', $postedInvoice->id)->update(['total' => '9999.00']))
            ->toThrow(QueryException::class);
        expect(fn () => DB::table('invoices')->where('id', $postedInvoice->id)->delete())
            ->toThrow(QueryException::class);

        expect(fn () => DB::table('vouchers')->where('id', $postedInvoice->voucher_id)->update(['narration' => 'tamper']))
            ->toThrow(QueryException::class);
        expect(fn () => DB::table('vouchers')->where('id', $postedInvoice->voucher_id)->delete())
            ->toThrow(QueryException::class);

        expect(fn () => DB::table('voucher_entries')->where('voucher_id', $postedInvoice->voucher_id)->delete())
            ->toThrow(QueryException::class);

        // 3. Posted purchase bill cannot be tampered with or deleted
        $bill = returnPurchaseFixture();
        $postedBill = app(PostPurchaseBill::class)->handle($bill->id);

        expect(fn () => DB::table('purchase_bills')->where('id', $postedBill->id)->update(['total' => '1.00']))
            ->toThrow(QueryException::class);
        expect(fn () => DB::table('purchase_bills')->where('id', $postedBill->id)->delete())
            ->toThrow(QueryException::class);

        // 4. Posted advance receipt voucher cannot be tampered with or deleted
        $year = FinancialYear::where('name', '2026-27')->sole();
        $cashLedger = Ledger::where('code', 'CASH')->sole();
        $advDraft = app(SaveAdvanceReceiptVoucherDraft::class)->handle($company, [
            'financial_year_id' => $year->id,
            'party_id' => $postedInvoice->party_id,
            'cash_bank_ledger_id' => $cashLedger->id,
            'date' => '2026-11-08',
            'supply_type' => 'services',
            'place_of_supply' => '27',
            'gross_amount' => '1180.00',
            'tax_rate' => '18.00',
        ]);
        $postedAdv = app(PostAdvanceReceiptVoucher::class)->handle($advDraft->id);

        expect(fn () => DB::table('advance_receipt_vouchers')->where('id', $postedAdv->id)->update(['gross_amount' => '100.00']))
            ->toThrow(QueryException::class);
        expect(fn () => DB::table('advance_receipt_vouchers')->where('id', $postedAdv->id)->delete())
            ->toThrow(QueryException::class);

        // 5. Finalized GST return snapshot and filing record are immutable
        $dataset = app(ReturnDatasetBuilder::class)->build($company, '2026-11', 50000000);
        $snapshot = app(GstReturnSnapshotService::class)->generate($company, '2026-11', 'gstr1', $dataset, 'csv-tool-3.2.4');
        $finalSnapshot = app(GstReturnSnapshotService::class)->finalize($snapshot->id);
        $filing = app(GstReturnFilingService::class)->markFiled($finalSnapshot->id, 'AA271126000001Z', '2026-12-11');

        expect(fn () => DB::table('gst_return_snapshots')->where('id', $finalSnapshot->id)->update(['sha256' => str_repeat('0', 64)]))
            ->toThrow(QueryException::class);
        expect(fn () => DB::table('gst_return_snapshots')->where('id', $finalSnapshot->id)->delete())
            ->toThrow(QueryException::class);

        expect(fn () => DB::table('gst_return_filings')->where('id', $filing->id)->update(['arn' => 'TAMPER']))
            ->toThrow(QueryException::class);
        expect(fn () => DB::table('gst_return_filings')->where('id', $filing->id)->delete())
            ->toThrow(QueryException::class);
    });
});
