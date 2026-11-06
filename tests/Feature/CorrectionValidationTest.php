<?php

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Accounting\Services\ReversalService;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Services\CorrectionService;
use App\Domain\Shared\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-05 12:00:00')));

it('preserves supply treatments across both note directions', function ($supply, $line, $field, $tax, $total, $state) {
    inSalesCompany(function ($company) use ($supply, $line, $field, $tax, $total, $state) {
        $company->update(['state_code' => $state]);
        $i = correctionSource($supply, $line);
        foreach (['credit', 'debit'] as $kind) {
            $n = saveCorrection($i, '100.00', ['kind' => $kind, 'reason_code' => $kind === 'credit' ? 'goods_return' : 'undercharge']);
            $n = app(CorrectionService::class)->post($n->id);
            expect((string) $n->$field)->toBe($tax)->and((string) $n->total)->toBe($total);
        }
    });
})->with([
    'IGST' => [['place_of_supply' => '29'], [], 'igst', '18.00', '118.00', '27'],
    'UTGST' => [['place_of_supply' => '35'], [], 'utgst', '9.00', '118.00', '35'],
    'RCM' => [['reverse_charge' => true], [], 'recipient_tax', '18.00', '100.00', '27'],
    'export LUT' => [['supply_type' => 'export', 'place_of_supply' => '99', 'zero_rated_mode' => 'lut', 'zero_rated_eligible' => true], [], 'igst', '0.00', '100.00', '27'],
    'export paid' => [['supply_type' => 'export', 'place_of_supply' => '99', 'zero_rated_mode' => 'with_payment', 'zero_rated_eligible' => true], [], 'igst', '18.00', '118.00', '27'],
    'SEZ LUT' => [['supply_type' => 'sez', 'zero_rated_mode' => 'lut', 'zero_rated_eligible' => true, 'sez_authorized' => true], [], 'igst', '0.00', '100.00', '27'],
    'SEZ paid' => [['supply_type' => 'sez', 'zero_rated_mode' => 'with_payment', 'zero_rated_eligible' => true, 'sez_authorized' => true], [], 'igst', '18.00', '118.00', '27'],
    'exempt' => [[], ['tax_category' => 'exempt'], 'cgst', '0.00', '100.00', '27'],
    'nil' => [[], ['tax_category' => 'nil_rated'], 'cgst', '0.00', '100.00', '27'],
]);

it('uses commercial treatment for composition suppliers', function () {
    inSalesCompany(function ($company) {
        $company->update(['gst_regime' => 'composition']);
        $i = correctionSource();
        expect(fn () => saveCorrection($i))->toThrow(DomainException::class, 'regime');
        $n = app(CorrectionService::class)->post(saveCorrection($i, '100.00', ['treatment' => 'commercial'])->id);
        expect((string) $n->total)->toBe('100.00');
    });
});

it('rejects excessive quantities invalid dates and closed years', function ($scenario) {
    inSalesCompany(function () use ($scenario) {
        $i = correctionSource();
        if ($scenario === 'closed') {
            FinancialYear::whereKey($i->financial_year_id)->update(['is_closed' => true]);
        }
        $input = match ($scenario) {
            'before' => ['date' => '2026-10-31'], 'future' => ['date' => '2026-11-06'],
            'invalid' => ['date' => '2026-02-30'], default => [],
        };
        expect(fn () => saveCorrection($i, '100.00', $input, $scenario === 'quantity' ? '10.001' : '0'))->toThrow(DomainException::class);
    });
})->with(['quantity', 'before', 'future', 'invalid', 'closed']);

it('allows reporting exactly at the deadline but rejects later reporting', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        expect(saveCorrection($i, '100.00', ['reporting_date' => '2027-11-30'])->id)->not->toBeNull();
        expect(fn () => saveCorrection($i, '100.00', ['reporting_date' => '2027-12-01']))->toThrow(DomainException::class, 'deadline');
    });
});

it('posts a commercial correction in a later open year while preserving the original year', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $this->travelTo(Carbon::parse('2027-04-02'));
        FinancialYear::whereKey($i->financial_year_id)->update(['is_closed' => true]);
        $year = FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        $n = app(CorrectionService::class)->saveDraft(null, $i->id, $year->id,
            correctionInput(['date' => '2027-04-02', 'treatment' => 'commercial']),
            [['invoice_item_id' => $i->items->first()->id, 'amount' => '100.00', 'quantity' => '0']]);
        $n = app(CorrectionService::class)->post($n->id);
        expect($n->financial_year_id)->toBe($year->id)->and($n->original_financial_year_id)->toBe($i->financial_year_id);
    });
});

it('rejects a foreign original line and changed ledger classification', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $other = correctionSource();
        expect(fn () => app(CorrectionService::class)->saveDraft(null, $i->id, $i->financial_year_id, correctionInput(),
            [['invoice_item_id' => $other->items->first()->id, 'amount' => '100.00', 'quantity' => '0']]))->toThrow(DomainException::class);
        $n = saveCorrection($i);
        Ledger::where('code', 'SALES')->update(['ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id]);
        expect(fn () => app(CorrectionService::class)->post($n->id))->toThrow(DomainException::class, 'nature');
        expect($n->fresh()->number)->toBeNull();
    });
});

it('rolls back all reversal state and reuses the number after audit failure', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $fail = true;
        Event::listen('eloquent.creating: '.Activity::class, function () use (&$fail) {
            if ($fail) {
                throw new RuntimeException('audit failed');
            }
        });
        $post = fn () => app(ReversalService::class)->reverse($i->voucher_id, $i->financial_year_id, '2026-11-05', 'Duplicate entry');
        expect($post)->toThrow(RuntimeException::class, 'audit failed');
        expect(VoucherReversal::count())->toBe(0)->and(Voucher::where('type', 'reversal')->count())->toBe(0)
            ->and(NumberSequence::where('series', 'RV')->count())->toBe(0);
        $fail = false;
        expect($post()->voucher->number)->toBe(1);
    });
});

it('protects posted reversal metadata from direct mutation', function () {
    inSalesCompany(function () {
        $i = correctionSource();
        $r = app(ReversalService::class)->reverse($i->voucher_id, $i->financial_year_id, '2026-11-05', 'Duplicate entry');
        expect(fn () => DB::table('voucher_reversals')->where('id', $r->id)->update(['reason' => 'Changed']))
            ->toThrow(QueryException::class);
    });
});
