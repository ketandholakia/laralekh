<?php
use App\Domain\Accounting\Models\{Ledger, LedgerGroup, NumberSequence, Voucher, VoucherLine};
use App\Domain\Accounting\Services\{PostingService, VoucherNumberAllocator};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{Company, FinancialYear, Party};
use App\Domain\Sales\Models\{Invoice, InvoiceItem};
use App\Domain\Shared\Models\Activity;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\{DB, Event};
use Illuminate\Database\QueryException;
use Database\Seeders\{ChartOfAccountsSeeder, StateSeeder};

function salesFixture(array $invoiceOverrides = [], array $lineOverrides = []): Invoice
{
    (new ChartOfAccountsSeeder)->run();
    $year = FinancialYear::firstOrCreate(['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    $ledger = Ledger::firstOrCreate(['code' => 'CUSTOMER'], [
        'name' => 'Customer', 'ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id]);
    $party = Party::firstOrCreate(['ledger_id' => $ledger->id], ['name' => 'Customer', 'state_code' => '27']);
    $item = Item::firstOrCreate(['sku' => 'WIDGET'], ['name' => 'Widget', 'hsn_sac' => '8471']);
    $rate = TaxRate::firstOrCreate(['code' => 'TEST_18'], [
        'rate' => '18', 'effective_from' => '2026-04-01', 'is_verified' => true, 'legal_reference' => 'Test fixture only']);
    $invoice = Invoice::create(array_merge([
        'financial_year_id' => $year->id, 'party_id' => $party->id, 'date' => '2026-11-05',
        'place_of_supply' => '27', 'series' => 'SI',
    ], $invoiceOverrides));
    $invoice->items()->create(array_merge([
        'financial_year_id' => $year->id, 'item_id' => $item->id, 'sales_ledger_id' => Ledger::where('code', 'SALES')->sole()->id,
        'tax_rate_id' => $rate->id, 'position' => 1, 'description' => 'Widget', 'hsn_sac' => '8471',
        'quantity' => '1', 'unit_price' => '1000.00', 'discount' => '0.00',
    ], $lineOverrides));
    return $invoice;
}

it('posts exact balanced sales entries', function ($state, $expected) {
    inSalesCompany(function () use ($state, $expected) {
        $invoice = salesFixture(['place_of_supply' => $state]);
        $voucher = app(PostingService::class)->postSalesInvoice($invoice->id);
        $entries = $voucher->lines->mapWithKeys(fn ($line) => [
            $line->ledger->code => [(string) $line->debit, (string) $line->credit],
        ])->all();
        expect($entries)->toBe($expected)->and($voucher->status)->toBe('posted')
            ->and($invoice->fresh()->status)->toBe('posted')->and($invoice->fresh()->number)->toBe(1)
            ->and(Activity::count())->toBe(1);
    });
})->with([
    ['27', ['CUSTOMER' => ['1180.00', '0.00'], 'SALES' => ['0.00', '1000.00'], 'OUTPUT_CGST' => ['0.00', '90.00'], 'OUTPUT_SGST' => ['0.00', '90.00']]],
    ['24', ['CUSTOMER' => ['1180.00', '0.00'], 'SALES' => ['0.00', '1000.00'], 'OUTPUT_IGST' => ['0.00', '180.00']]],
]);

it('posts outward RCM without output tax credits', function () {
    inSalesCompany(function () {
        $i = salesFixture(['reverse_charge' => true]);
        $v = app(PostingService::class)->postSalesInvoice($i->id);
        expect($v->lines)->toHaveCount(2)->and((string) $i->fresh()->total)->toBe('1000.00')
            ->and((string) $i->fresh()->recipient_tax)->toBe('180.00');
    });
});

it('posts LUT exports without output tax', function () {
    inSalesCompany(function () {
        $i = salesFixture(['supply_type' => 'export', 'place_of_supply' => '99', 'zero_rated_mode' => 'lut', 'zero_rated_eligible' => true]);
        expect(app(PostingService::class)->postSalesInvoice($i->id)->lines)->toHaveCount(2);
    });
});

it('posts composition bills without collected GST', function () {
    inSalesCompany(function ($company) {
        $company->update(['gst_regime' => 'composition']);
        $i = salesFixture();
        expect(app(PostingService::class)->postSalesInvoice($i->id)->lines)->toHaveCount(2);
    });
});

it('recalculates persisted amounts and preserves party snapshots', function () {
    inSalesCompany(function () {
        $i = salesFixture(['total' => '1.00'], ['discount' => '100.00']);
        app(PostingService::class)->postSalesInvoice($i->id);
        $i = $i->fresh();
        $i->party->update(['name' => 'Changed later']);
        expect((string) $i->total)->toBe('1062.00')->and($i->party_snapshot['name'])->toBe('Customer');
    });
});

it('rejects an unbalanced voucher', function () {
    app(PostingService::class)->assertBalanced([
        ['debit' => '100.00', 'credit' => '0.00'], ['debit' => '0.00', 'credit' => '99.99'],
    ]);
})->throws(DomainException::class, 'debits do not equal credits');

it('rejects invalid voucher line signs and sides', function ($debit, $credit) {
    app(PostingService::class)->assertBalanced([
        ['debit' => $debit, 'credit' => $credit], ['debit' => '0.00', 'credit' => '1.00'],
    ]);
})->with([['-1.00', '0.00'], ['0.00', '0.00'], ['1.00', '1.00']])->throws(DomainException::class);

it('prevents posting the same invoice twice', function () {
    inSalesCompany(function () {
        $i = salesFixture(); app(PostingService::class)->postSalesInvoice($i->id);
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(DomainException::class);
        expect(Voucher::count())->toBe(1)->and(NumberSequence::sole()->last_number)->toBe(1);
    });
});

it('rolls back every write and number when auditing fails after allocation', function () {
    inSalesCompany(function () {
        $i = salesFixture();
        $fail = true;
        Event::listen('eloquent.creating: '.Activity::class, function () use (&$fail) {
            if ($fail) { throw new RuntimeException('Simulated audit failure'); }
        });
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(RuntimeException::class, 'Simulated');
        expect(Voucher::count())->toBe(0)->and(VoucherLine::count())->toBe(0)->and(NumberSequence::count())->toBe(0)
            ->and($i->fresh()->status)->toBe('draft')->and((string) $i->fresh()->total)->toBe('0.00');
        $fail = false;
        expect(app(PostingService::class)->postSalesInvoice($i->id)->number)->toBe(1);
    });
});

it('allocates independent sequences per series and financial year', function () {
    inSalesCompany(function () {
        $a = salesFixture(); $b = salesFixture(); $c = salesFixture(['series' => 'EXPORT']);
        $nextYear = FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        $d = salesFixture(['financial_year_id' => $nextYear->id, 'date' => '2027-04-01'], ['financial_year_id' => $nextYear->id]);
        $service = app(PostingService::class);
        expect([$service->postSalesInvoice($a->id)->number, $service->postSalesInvoice($b->id)->number,
            $service->postSalesInvoice($c->id)->number, $service->postSalesInvoice($d->id)->number])->toBe([1, 2, 1, 1]);
    });
});

it('rejects closed or out-of-year invoices', function ($mode) {
    inSalesCompany(function () use ($mode) {
        $i = salesFixture();
        if ($mode === 'closed') { FinancialYear::query()->update(['is_closed' => true]); }
        else { $i->update(['date' => '2027-04-01']); }
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(DomainException::class, 'financial year');
        expect(Voucher::count())->toBe(0);
    });
})->with(['closed', 'date']);

it('rejects unverified future or expired rates', function ($mode) {
    inSalesCompany(function () use ($mode) {
        $i = salesFixture();
        TaxRate::query()->update(match ($mode) {
            'unverified' => ['is_verified' => false],
            'future' => ['effective_from' => '2027-01-01'],
            default => ['effective_to' => '2026-10-31'],
        });
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(DomainException::class, 'verified GST rate');
        expect(Voucher::count())->toBe(0);
    });
})->with(['unverified', 'future', 'expired']);

it('rejects superseded 12 and 28 percent slabs from 22 September 2025 onward', function ($rate, $date, $passes) {
    inSalesCompany(function () use ($rate, $date, $passes) {
        $priorYear = FinancialYear::firstOrCreate(['name' => '2025-26'], ['starts_on' => '2025-04-01', 'ends_on' => '2026-03-31']);
        $i = salesFixture(['date' => $date, 'financial_year_id' => $priorYear->id], ['financial_year_id' => $priorYear->id, 'tax_rate_id' => TaxRate::create([
            'code' => 'OLD_'.$rate, 'rate' => $rate, 'effective_from' => '2017-07-01',
            'effective_to' => '2025-09-21', 'is_verified' => true,
        ])->id]);
        if ($passes) {
            expect(app(PostingService::class)->postSalesInvoice($i->id)->status)->toBe('posted');
        } else {
            expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))
                ->toThrow(DomainException::class, 'verified GST rate');
        }
    });
})->with([
    ['12', '2025-09-21', true], ['28', '2025-09-21', true],
    ['12', '2025-09-22', false], ['28', '2025-09-22', false],
]);

it('blocks raw SQL changes to posted documents and their lines', function ($table, $operation) {
    inSalesCompany(function () use ($table, $operation) {
        $i = salesFixture(); $v = app(PostingService::class)->postSalesInvoice($i->id);
        $id = match ($table) {
            'invoices' => $i->id, 'invoice_items' => $i->items()->sole()->id,
            'vouchers' => $v->id, default => $v->lines->first()->id,
        };
        expect(function () use ($table, $operation, $id) {
            $q = DB::table($table)->where('id', $id);
            if ($operation === 'delete') { $q->delete(); }
            else { $q->update(['updated_at' => now()->addSecond()]); }
        })->toThrow(QueryException::class);
    });
})->with([
    ['invoices', 'update'], ['invoices', 'delete'], ['invoice_items', 'update'], ['invoice_items', 'delete'],
    ['vouchers', 'update'], ['vouchers', 'delete'], ['voucher_lines', 'update'], ['voucher_lines', 'delete'],
]);

it('rejects attaching new lines to a posted voucher', function () {
    inSalesCompany(function () {
        $v = app(PostingService::class)->postSalesInvoice(salesFixture()->id);
        expect(fn () => $v->lines()->create([
            'financial_year_id' => $v->financial_year_id, 'position' => 99,
            'ledger_id' => $v->lines->first()->ledger_id, 'debit' => '1.00', 'credit' => '0.00',
        ]))->toThrow(QueryException::class);
    });
});

it('enforces balance in the database when bypassing the posting service', function () {
    inSalesCompany(function () {
        $i = salesFixture();
        $v = Voucher::create(['financial_year_id' => $i->financial_year_id, 'type' => 'journal',
            'date' => $i->date, 'series' => 'JV', 'number' => 1]);
        expect(fn () => DB::table('vouchers')->where('id', $v->id)->update(['status' => 'posted']))->toThrow(QueryException::class);
    });
});

it('enforces the voucher number unique constraint', function () {
    inSalesCompany(function () {
        $v = app(PostingService::class)->postSalesInvoice(salesFixture()->id);
        expect(fn () => Voucher::create(['financial_year_id' => $v->financial_year_id, 'type' => 'sales',
            'date' => $v->date, 'series' => $v->series, 'number' => $v->number]))->toThrow(QueryException::class);
    });
});

it('posts UTGST into a separate output ledger', function () {
    inSalesCompany(function ($company) {
        $company->update(['state_code' => '04']);
        $v = app(PostingService::class)->postSalesInvoice(salesFixture(['place_of_supply' => '04'])->id);
        expect($v->lines->map(fn ($line) => $line->ledger->code)->all())->toContain('OUTPUT_UTGST')->not->toContain('OUTPUT_SGST');
    });
});

it('rejects a cross-company invoice ID', function () {
    inSalesCompany(function () {
        $invoice = salesFixture();
        $other = companyFixture();
        app(TenantContext::class)->run($other->id, function () use ($invoice) {
            expect(fn () => app(PostingService::class)->postSalesInvoice($invoice->id))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        });
        expect($invoice->fresh()->status)->toBe('draft');
    });
});

it('rejects empty invoices', function () {
    inSalesCompany(function () {
        $i = salesFixture(); $i->items()->delete();
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(DomainException::class, 'without items');
    });
});

it('rejects an inappropriate sales ledger', function () {
    inSalesCompany(function () {
        $i = salesFixture([], ['sales_ledger_id' => Ledger::firstOrCreate(['code' => 'WRONG'], [
            'name' => 'Wrong account', 'ledger_group_id' => LedgerGroup::create(['code' => 'WRONG', 'name' => 'Wrong', 'nature' => 'asset'])->id,
        ])->id]);
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(DomainException::class, 'accounting nature');
    });
});

it('enforces financial year consistency on invoice items', function () {
    inSalesCompany(function () {
        $i = salesFixture();
        $otherYear = FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        expect(fn () => $i->items()->update(['financial_year_id' => $otherYear->id]))->toThrow(QueryException::class);
    });
});

it('rejects invalid invoice series without consuming a number', function ($series) {
    inSalesCompany(function () use ($series) {
        $i = salesFixture(['series' => $series]);
        expect(fn () => app(PostingService::class)->postSalesInvoice($i->id))->toThrow(DomainException::class, 'series');
        expect(NumberSequence::count())->toBe(0);
    });
})->with(['bad space', '*BAD', '']);
