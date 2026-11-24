<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Accounting\Services\VoucherNumberAllocator;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostCustomerRefund
{
    public function __construct(
        private PostingService $posting,
        private VoucherNumberAllocator $numbers,
        private SettlementService $settlement,
    ) {}

    public function handle(int $refundId, ?int $userId = null): CustomerRefund
    {
        return DB::transaction(function () use ($refundId, $userId): CustomerRefund {
            $refund = CustomerRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'draft' || $refund->voucher_id !== null || $refund->number !== null) {
                throw new DomainException('Customer refund is already posted.');
            }

            $party = Party::query()->whereKey($refund->party_id)->lockForUpdate()->firstOrFail();
            if ($party->ledger_id === null) {
                throw new DomainException('The customer needs a receivable ledger before refunds can be posted.');
            }

            $customerLedger = Ledger::query()->whereKey($party->ledger_id)->lockForUpdate()->firstOrFail();
            $customerGroup = LedgerGroup::query()->whereKey($customerLedger->ledger_group_id)->sole();
            if ($customerGroup->nature !== 'asset') {
                throw new DomainException('The customer ledger must be a receivable (asset) ledger.');
            }

            $bankLedger = Ledger::query()->whereKey($refund->ledger_id)->lockForUpdate()->firstOrFail();
            $bankGroup = LedgerGroup::query()->whereKey($bankLedger->ledger_group_id)->sole();
            if ($bankGroup->nature !== 'asset' || $bankGroup->code !== 'CASH_BANK') {
                throw new DomainException('Refunds must use a cash or bank asset ledger.');
            }

            if ($refund->amount->isZero() || $refund->amount->isNegative()) {
                throw new DomainException('A refund must have a positive amount.');
            }

            $year = FinancialYear::query()->whereKey($refund->financial_year_id)->lockForUpdate()->firstOrFail();
            if ($year->is_closed || $refund->date->lt($year->starts_on) || $refund->date->gt($year->ends_on)) {
                throw new DomainException('Refund date must fall inside an open financial year.');
            }

            $allocations = $refund->allocations()->lockForUpdate()->get();
            if ($allocations->isEmpty()) {
                throw new DomainException('Refund must have at least one allocation.');
            }

            $sumAllocations = Money::of('0');
            foreach ($allocations as $alloc) {
                $sumAllocations = $sumAllocations->add($alloc->amount);
                if ($alloc->allocation_type === 'receipt') {
                    $receipt = Receipt::query()->whereKey($alloc->receipt_id)->lockForUpdate()->firstOrFail();
                    if ($receipt->status !== 'posted') {
                        throw new DomainException('Receipt must be posted.');
                    }
                    if (ReceiptReversal::query()->where('original_receipt_id', $receipt->id)->exists()) {
                        throw new DomainException('Receipt has been reversed.');
                    }
                    $available = $this->settlement->availableReceiptFunds($receipt);
                    if ($alloc->amount->greaterThan($available)) {
                        throw new DomainException('Refund amount exceeds available receipt advance.');
                    }
                } elseif ($alloc->allocation_type === 'invoice') {
                    $invoice = Invoice::query()->whereKey($alloc->invoice_id)->lockForUpdate()->firstOrFail();
                    if ($invoice->status !== 'posted') {
                        throw new DomainException('Invoice must be posted.');
                    }
                    if ($this->settlement->invoiceReversed($invoice)) {
                        throw new DomainException('Invoice has been reversed.');
                    }
                    $outstanding = $this->settlement->documentOutstanding($invoice);
                    if (! $outstanding->isNegative()) {
                        throw new DomainException('Invoice does not have an excess credit.');
                    }
                    $excessCredit = Money::of(bcmul((string) $outstanding, '-1', 2));
                    if ($alloc->amount->greaterThan($excessCredit)) {
                        throw new DomainException('Refund amount exceeds invoice excess credit.');
                    }
                }
            }

            if (! $sumAllocations->equals($refund->amount)) {
                throw new DomainException('Sum of allocations does not match total refund amount.');
            }

            $entries = [
                ['ledger_id' => $customerLedger->id, 'debit' => $refund->amount, 'credit' => Money::of('0')],
                ['ledger_id' => $bankLedger->id, 'debit' => Money::of('0'), 'credit' => $refund->amount],
            ];
            $this->posting->assertBalanced($entries);

            $number = $this->numbers->next($year->id, $refund->series);
            $voucher = Voucher::create([
                'financial_year_id' => $year->id,
                'type' => 'payment',
                'date' => $refund->date->format('Y-m-d'),
                'series' => $refund->series,
                'number' => $number,
                'status' => 'draft',
                'narration' => 'Customer credit refund '.$refund->series.'/'.$number.' to '.$party->name
                    .($refund->payment_reference !== null ? ' ref '.$refund->payment_reference : ''),
            ]);

            foreach ($entries as $position => $entry) {
                $voucher->lines()->create($entry + [
                    'financial_year_id' => $year->id,
                    'position' => $position + 1,
                ]);
            }

            $this->posting->assertBalanced(
                $voucher->lines()->get()->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all()
            );

            $refund->fill([
                'voucher_id' => $voucher->id,
                'number' => $number,
                'status' => 'posted',
                'party_snapshot' => $party->only(['name', 'gstin', 'state_code', 'address', 'registration_type']),
            ])->save();

            $voucher->update(['status' => 'posted', 'posted_at' => now()]);

            activity('accounting')->performedOn($refund)->event('posted')
                ->withProperties([
                    'refund_id' => $refund->id,
                    'amount' => (string) $refund->amount,
                ])
                ->tap(fn ($a) => $a->financial_year_id = $year->id)
                ->log('Customer credit refund posted');

            return $refund->fresh(['allocations', 'voucher', 'party', 'ledger']);
        }, 3);
    }
}
