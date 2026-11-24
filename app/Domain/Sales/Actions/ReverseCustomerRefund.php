<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Accounting\Services\VoucherNumberAllocator;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Sales\Models\CustomerRefundReversal;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReverseCustomerRefund
{
    public function __construct(
        private PostingService $posting,
        private VoucherNumberAllocator $numbers,
    ) {}

    public function handle(int $refundId, int $yearId, string $date, string $reason, ?int $userId = null): CustomerRefundReversal
    {
        return DB::transaction(function () use ($refundId, $yearId, $date, $reason, $userId): CustomerRefundReversal {
            $refund = CustomerRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'posted' || $refund->voucher_id === null) {
                throw new DomainException('Only a posted customer refund can be reversed.');
            }

            if (CustomerRefundReversal::query()->where('original_customer_refund_id', $refund->id)->lockForUpdate()->exists()) {
                throw new DomainException('This customer refund has already been reversed.');
            }

            if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) {
                throw new DomainException('Provide a reversal reason of 5 to 2000 characters.');
            }

            $year = FinancialYear::query()->whereKey($yearId)->lockForUpdate()->firstOrFail();
            $day = Carbon::hasFormat($date, 'Y-m-d')
                ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay() : false;

            if ($year->is_closed || $day === false
                || $day->lt($year->starts_on) || $day->gt($year->ends_on) || $day->gt(Carbon::today())) {
                throw new DomainException('Reversal date must be today or earlier, inside the selected open financial year.');
            }

            $originalVoucher = Voucher::query()->whereKey($refund->voucher_id)->lockForUpdate()->firstOrFail();
            if ($originalVoucher->status !== 'posted') {
                throw new DomainException('The refund voucher is not posted.');
            }

            $entries = $originalVoucher->lines()->orderBy('position')->with('ledger')->get()->map(fn ($line) => [
                'ledger_id' => $line->ledger_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ])->all();

            $this->posting->assertBalanced($entries);

            $number = $this->numbers->next($year->id, 'RV');
            $voucher = Voucher::create([
                'financial_year_id' => $year->id,
                'type' => 'reversal',
                'date' => $date,
                'series' => 'RV',
                'number' => $number,
                'reverses_voucher_id' => $originalVoucher->id,
                'narration' => 'Reversal of customer refund '.$refund->label().': '.$reason,
            ]);

            foreach ($entries as $index => $entry) {
                $voucher->lines()->create([
                    'financial_year_id' => $year->id,
                    'position' => $index + 1,
                    'ledger_id' => $entry['ledger_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                ]);
            }

            $this->posting->assertBalanced(
                $voucher->lines()->get()->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all()
            );

            $reversal = CustomerRefundReversal::create([
                'financial_year_id' => $year->id,
                'original_financial_year_id' => $refund->financial_year_id,
                'original_customer_refund_id' => $refund->id,
                'voucher_id' => $voucher->id,
                'status' => 'posted',
                'date' => $date,
                'reason' => $reason,
                'original_snapshot' => [
                    'number' => $refund->label(),
                    'date' => $refund->date->format('Y-m-d'),
                    'amount' => (string) $refund->amount,
                    'party' => $refund->party_snapshot,
                    'payment_reference' => $refund->payment_reference,
                ],
                'created_by' => $userId,
            ]);

            $voucher->update(['status' => 'posted', 'posted_at' => now()]);

            activity('accounting')->performedOn($reversal)->event('posted')
                ->withProperties([
                    'refund_id' => $refund->id,
                    'amount' => (string) $refund->amount,
                ])
                ->tap(fn ($a) => $a->financial_year_id = $year->id)
                ->log('Customer credit refund reversed');

            return $reversal;
        }, 3);
    }
}
