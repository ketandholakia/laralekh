<?php

use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Services\ReversalService;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Sales\Services\CorrectionService;
use App\Domain\Shared\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-05 12:00:00')));

it('posts partial credit and debit notes with exact original tax treatment', function ($kind, $expectedDebit, $expectedCredit) {
    inSalesCompany(function () use ($kind, $expectedDebit, $expectedCredit) {
        $invoice = correctionSource();
        $note = saveCorrection($invoice, '500.00', ['kind' => $kind, 'reason_code' => $kind === 'credit' ? 'goods_return' : 'undercharge']);
        expect($note->number)->toBeNull()->and((string) $note->total)->toBe('590.00');
        $posted = app(CorrectionService::class)->post($note->id);
        $party = $posted->voucher->lines()->orderBy('position')->first();
        expect((string) $party->debit)->toBe($expectedDebit)->and((string) $party->credit)->toBe($expectedCredit)
            ->and($posted->status)->toBe('posted')->and($posted->number)->toBe(1)
            ->and($invoice->fresh()->status)->toBe('posted')->and((string) $invoice->fresh()->total)->toBe('1180.00');
    });
})->with([['credit', '0.00', '590.00'], ['debit', '590.00', '0.00']]);

it('does not recalculate corrections using changed rate masters', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        TaxRate::query()->update(['rate' => '40', 'is_verified' => false]);
        $n = saveCorrection($i, '100.00', ['kind' => 'debit', 'reason_code' => 'undercharge']);
        expect((string) app(CorrectionService::class)->post($n->id)->total)->toBe('118.00');
    });
});

it('does not reclaim GST attributable to an earlier commercial credit', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $a = app(CorrectionService::class)->post(saveCorrection($i, '500.00', ['treatment' => 'commercial'])->id);
        $b = app(CorrectionService::class)->post(saveCorrection($i, '500.00')->id);
        expect((string) $a->total)->toBe('500.00')->and((string) $a->cgst)->toBe('0.00')
            ->and((string) $b->total)->toBe('590.00')->and((string) $b->cgst)->toBe('45.00');
        expect(fn () => saveCorrection($i, '0.01'))->toThrow(DomainException::class, 'remaining');
    });
});

it('allocates paise exactly across multiple partial credits', function () {
    inSalesCompany(function () {
        $i = correctionSource([], ['quantity' => '1', 'unit_price' => '0.10']);
        $a = app(CorrectionService::class)->post(saveCorrection($i, '0.05')->id);
        $b = app(CorrectionService::class)->post(saveCorrection($i, '0.05')->id);
        expect((string) $a->cgst->add($b->cgst))->toBe((string) $i->cgst)
            ->and((string) $a->total->add($b->total))->toBe((string) $i->total);
    });
});

it('rechecks capacity when a previously valid draft is posted', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $a = saveCorrection($i, '700.00');
        $b = saveCorrection($i, '700.00');
        app(CorrectionService::class)->post($a->id);
        expect(fn () => app(CorrectionService::class)->post($b->id))->toThrow(DomainException::class, 'remaining');
        expect($b->fresh()->status)->toBe('draft')->and(NumberSequence::where('series', 'CN')->sole()->last_number)->toBe(1);
    });
});

it('rejects duplicate creation requests and duplicate posts', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $input = correctionInput();
        $a = saveCorrection($i, '100.00', $input);
        expect(fn () => saveCorrection($i, '100.00', $input))->toThrow(DomainException::class, 'already saved');
        app(CorrectionService::class)->post($a->id);
        expect(fn () => app(CorrectionService::class)->post($a->id))->toThrow(DomainException::class, 'already posted');
    });
});

it('rolls back a correction and its number if auditing fails', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $a = saveCorrection($i);
        $fail = true;
        Event::listen('eloquent.creating: '.Activity::class, function () use (&$fail) {
            if ($fail) {
                throw new RuntimeException('audit failure');
            }
        });
        expect(fn () => app(CorrectionService::class)->post($a->id))->toThrow(RuntimeException::class, 'audit failure');
        expect($a->fresh()->status)->toBe('draft')->and(Voucher::where('type', 'credit_note')->count())->toBe(0)
            ->and(NumberSequence::where('series', 'CN')->count())->toBe(0);
        $fail = false;
        expect(app(CorrectionService::class)->post($a->id)->number)->toBe(1);
    });
});

it('swaps every original voucher entry exactly on reversal', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $r = app(ReversalService::class)->reverse($i->voucher_id, $i->financial_year_id, '2026-11-05', 'Invoice entered in error');
        $original = $i->voucher->lines()->orderBy('position')->get();
        foreach ($r->voucher->lines()->orderBy('position')->get() as $index => $line) {
            expect($line->ledger_id)->toBe($original[$index]->ledger_id)
                ->and((string) $line->debit)->toBe((string) $original[$index]->credit)
                ->and((string) $line->credit)->toBe((string) $original[$index]->debit);
        }
        expect($i->fresh()->status)->toBe('posted');
        expect(fn () => app(ReversalService::class)->reverse($i->voucher_id, $i->financial_year_id, '2026-11-05', 'Duplicate reversal'))->toThrow(DomainException::class);
        expect(fn () => saveCorrection($i))->toThrow(DomainException::class, 'reversed');
    });
});

it('rejects a reversal after a correction has posted', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        app(CorrectionService::class)->post(saveCorrection($i)->id);
        expect(fn () => app(ReversalService::class)->reverse($i->voucher_id, $i->financial_year_id, '2026-11-05', 'Reverse original'))->toThrow(DomainException::class, 'posted correction');
    });
});

it('blocks direct edits and deletes of posted notes and note lines', function ($table, $operation) {
    inSalesCompany(function () use ($table, $operation) {
        $i = correctionSource();
        $n = app(CorrectionService::class)->post(saveCorrection($i)->id);
        $id = $table === 'sales_corrections' ? $n->id : $n->lines->first()->id;
        expect(function () use ($table, $operation, $id) {
            $q = DB::table($table)->where('id', $id);
            $operation === 'delete' ? $q->delete() : $q->update(['updated_at' => now()->addSecond()]);
        })->toThrow(QueryException::class);
    });
})->with([['sales_corrections', 'update'], ['sales_corrections', 'delete'], ['sales_correction_lines', 'update'], ['sales_correction_lines', 'delete']]);

it('requires explicit GST eligibility and supported statutory reasons', function ($overrides) {
    inSalesCompany(function () use ($overrides) {
        expect(fn () => saveCorrection(correctionSource(), '100.00', $overrides))->toThrow(DomainException::class);
    });
})->with([
    [['eligibility_confirmed' => false]], [['eligibility_evidence' => '']], [['reason_code' => 'commercial']],
    [['reporting_date' => null]], [['annual_return_filed_on' => '2026-11-02']], [['reporting_date' => '2027-12-01']],
]);
