<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Actions;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Accounting\Services\ReversalService;
use App\Domain\Accounting\Services\VoucherNumberAllocator;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Purchase\Models\SupplierPaymentAllocation;
use App\Domain\Purchase\Models\SupplierPaymentAllocationReversal;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostPayment
{
    public function post(int $paymentId, array $allocations): SupplierPayment
    {
        return DB::transaction(function () use ($paymentId, $allocations) {
            $payment = SupplierPayment::whereKey($paymentId)->lockForUpdate()->sole();
            if ($payment->status !== 'draft' || $payment->voucher_id !== null) {
                throw new DomainException('Payment is already posted.');
            }
            $year = FinancialYear::whereKey($payment->financial_year_id)->lockForUpdate()->sole();
            if ($year->is_closed || $payment->date->lt($year->starts_on) || $payment->date->gt($year->ends_on)) {
                throw new DomainException('Payment date must be inside an open financial year.');
            }
            $party = Party::whereKey($payment->party_id)->lockForUpdate()->sole();
            $supplier = Ledger::whereKey($party->ledger_id)->lockForUpdate()->sole();
            if (LedgerGroup::whereKey($supplier->ledger_group_id)->sole()->nature !== 'liability') {
                throw new DomainException('Supplier ledger must be a liability.');
            }
            $cash = Ledger::whereKey($payment->ledger_id)->lockForUpdate()->sole();
            if (LedgerGroup::whereKey($cash->ledger_group_id)->sole()->code !== 'CASH_BANK') {
                throw new DomainException('Payment must use cash/bank ledger.');
            }
            if ($payment->amount->isZero() || $payment->amount->isNegative()) {
                throw new DomainException('Payment amount must be positive.');
            }
            $allocated = Money::of('0');
            $canonical = [];
            foreach ($allocations as $billId => $amount) {
                $bill = PurchaseBill::whereKey((int) $billId)->lockForUpdate()->first();
                if (! $bill || $bill->party_id !== $payment->party_id || $bill->status !== 'posted') {
                    throw new DomainException('Allocation must reference a posted bill for this supplier.');
                }
                $value = Money::of((string) $amount);
                if ($value->isZero() || $value->isNegative()) {
                    throw new DomainException('Allocation must be positive.');
                }
                $paid = SupplierPaymentAllocation::where('purchase_bill_id', $bill->id)->whereNotExists(fn ($q) => $q->selectRaw(1)->from('supplier_payment_allocation_reversals')->whereColumn('allocation_id', 'supplier_payment_allocations.id'))->lockForUpdate()->get()->reduce(fn ($s, $r) => $s->add($r->amount), Money::of('0'));
                $billPayable = $bill->reverse_charge ? $bill->taxable_amount : $bill->total;
                $outstanding = $billPayable->subtract($paid);
                if ($value->greaterThan($outstanding)) {
                    throw new DomainException('Allocation exceeds bill outstanding.');
                }
                $canonical[] = [$bill, $value];
                $allocated = $allocated->add($value);
            }
            if (! $allocated->equals($payment->amount)) {
                throw new DomainException('Payment must be fully allocated; partial bill allocations are supported.');
            }
            $entries = [['ledger_id' => $supplier->id, 'debit' => $payment->amount, 'credit' => Money::of('0')], ['ledger_id' => $cash->id, 'debit' => Money::of('0'), 'credit' => $payment->amount]];
            app(PostingService::class)->assertBalanced($entries);
            $number = app(VoucherNumberAllocator::class)->next($year->id, 'SP');
            $voucher = Voucher::create(['financial_year_id' => $year->id, 'type' => 'payment', 'date' => $payment->date, 'series' => 'SP', 'number' => $number, 'narration' => 'Supplier payment']);
            foreach ($entries as $i => $entry) {
                $voucher->lines()->create($entry + ['financial_year_id' => $year->id, 'position' => $i + 1]);
            }
            foreach ($canonical as [$bill,$value]) {
                SupplierPaymentAllocation::create(['financial_year_id' => $year->id, 'payment_id' => $payment->id, 'bill_financial_year_id' => $bill->financial_year_id, 'purchase_bill_id' => $bill->id, 'amount' => $value]);
            }
            $payment->update(['voucher_id' => $voucher->id, 'number' => $number, 'status' => 'posted', 'supplier_snapshot' => $party->only(['name', 'gstin', 'state_code', 'address', 'registration_type'])]);
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);

            return $payment->fresh('allocations');
        }, 3);
    }

    public function reverse(int $paymentId, int $yearId, string $date, string $reason): void
    {
        DB::transaction(function () use ($paymentId, $yearId, $date, $reason) {
            $payment = SupplierPayment::whereKey($paymentId)->lockForUpdate()->sole();
            if ($payment->status !== 'posted') {
                throw new DomainException('Only posted payments can be reversed.');
            }
            app(ReversalService::class)->reverse($payment->voucher_id, $yearId, $date, $reason);
            foreach (SupplierPaymentAllocation::where('payment_id', $payment->id)->whereNotExists(fn ($q) => $q->selectRaw(1)->from('supplier_payment_allocation_reversals')->whereColumn('allocation_id', 'supplier_payment_allocations.id'))->lockForUpdate()->get() as $allocation) {
                SupplierPaymentAllocationReversal::create(['financial_year_id' => $yearId, 'allocation_id' => $allocation->id]);
            }
        }, 3);
    }
}
