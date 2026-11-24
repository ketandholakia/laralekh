<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Sales\Models\CustomerRefundAllocation;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SaveCustomerRefundDraft
{
    public function __construct(private SettlementService $settlement) {}

    /**
     * @param  list<array{allocation_type: string, target_id: int, amount: string}>  $allocations
     */
    public function handle(
        int $partyId,
        int $ledgerId,
        string $date,
        array $allocations,
        ?string $paymentReference = null,
        ?string $narration = null,
        ?int $refundId = null,
        ?int $userId = null
    ): CustomerRefund {
        $tenantId = app(TenantContext::class)->id();

        return DB::transaction(function () use (
            $partyId, $ledgerId, $date, $allocations, $paymentReference, $narration, $refundId, $userId
        ): CustomerRefund {
            $party = Party::query()->whereKey($partyId)->lockForUpdate()->firstOrFail();
            if ($party->ledger_id === null) {
                throw new DomainException('The customer must have a receivable ledger before refunds can be issued.');
            }
            $customerLedger = Ledger::query()->whereKey($party->ledger_id)->firstOrFail();
            $group = LedgerGroup::query()->whereKey($customerLedger->ledger_group_id)->sole();
            if ($group->nature !== 'asset') {
                throw new DomainException('The customer ledger must be a receivable (asset) ledger.');
            }

            $bankLedger = Ledger::query()->whereKey($ledgerId)->firstOrFail();
            $bankGroup = LedgerGroup::query()->whereKey($bankLedger->ledger_group_id)->sole();
            if ($bankGroup->nature !== 'asset' || $bankGroup->code !== 'CASH_BANK') {
                throw new DomainException('Customer refund must use a cash or bank asset ledger.');
            }

            $day = Carbon::createFromFormat('Y-m-d', $date);
            if (! $day) {
                throw new DomainException('Invalid refund date.');
            }

            $year = FinancialYear::query()
                ->where('starts_on', '<=', $date)
                ->where('ends_on', '>=', $date)
                ->lockForUpdate()
                ->first();

            if (! $year || $year->is_closed) {
                throw new DomainException('Refund date must fall inside an open financial year.');
            }

            if (empty($allocations)) {
                throw new DomainException('At least one refundable advance or excess credit must be selected.');
            }

            $refund = null;
            if ($refundId !== null) {
                $refund = CustomerRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();
                if ($refund->status !== 'draft') {
                    throw new DomainException('Only draft refunds can be modified.');
                }
            }

            $canonical = [];
            $totalAmount = Money::of('0');

            foreach ($allocations as $item) {
                $money = Money::of((string) $item['amount']);
                if ($money->isZero() || $money->isNegative()) {
                    throw new DomainException('Refund line amounts must be positive.');
                }

                $type = $item['allocation_type'];
                $targetId = (int) $item['target_id'];

                if ($type === 'receipt') {
                    $receipt = Receipt::query()->whereKey($targetId)->lockForUpdate()->first();
                    if (! $receipt || $receipt->party_id !== $partyId || $receipt->status !== 'posted') {
                        throw new DomainException('Receipt not found or not posted for this customer.');
                    }
                    if (ReceiptReversal::query()->where('original_receipt_id', $receipt->id)->exists()) {
                        throw new DomainException('Receipt has been reversed.');
                    }

                    $available = $this->settlement->availableReceiptFunds($receipt);
                    // If editing, add back what this draft had allocated
                    if ($refund !== null) {
                        $priorAllocation = CustomerRefundAllocation::query()
                            ->where('customer_refund_id', $refund->id)
                            ->where('receipt_id', $receipt->id)
                            ->first();
                        if ($priorAllocation) {
                            $available = $available->add($priorAllocation->amount);
                        }
                    }

                    if ($money->greaterThan($available)) {
                        throw new DomainException('Refund amount ('.$money.') exceeds available receipt advance ('.$available.').');
                    }

                    $canonical[] = [
                        'allocation_type' => 'receipt',
                        'receipt_id' => $receipt->id,
                        'invoice_id' => null,
                        'amount' => $money,
                    ];
                } elseif ($type === 'invoice') {
                    $invoice = Invoice::query()->whereKey($targetId)->lockForUpdate()->first();
                    if (! $invoice || $invoice->party_id !== $partyId || $invoice->status !== 'posted') {
                        throw new DomainException('Invoice not found or not posted for this customer.');
                    }
                    if ($this->settlement->invoiceReversed($invoice)) {
                        throw new DomainException('Invoice has been reversed.');
                    }

                    $outstanding = $this->settlement->documentOutstanding($invoice);
                    if ($refund !== null) {
                        $priorAllocation = CustomerRefundAllocation::query()
                            ->where('customer_refund_id', $refund->id)
                            ->where('invoice_id', $invoice->id)
                            ->first();
                        if ($priorAllocation) {
                            $outstanding = $outstanding->subtract($priorAllocation->amount);
                        }
                    }

                    if (! $outstanding->isNegative()) {
                        throw new DomainException('Invoice '.$invoice->series.'/'.$invoice->number.' does not have an excess credit.');
                    }

                    $maxRefundable = Money::of(bcmul((string) $outstanding, '-1', 2));
                    if ($money->greaterThan($maxRefundable)) {
                        throw new DomainException('Refund amount ('.$money.') exceeds invoice excess credit ('.$maxRefundable.').');
                    }

                    $canonical[] = [
                        'allocation_type' => 'invoice',
                        'receipt_id' => null,
                        'invoice_id' => $invoice->id,
                        'amount' => $money,
                    ];
                } else {
                    throw new DomainException('Invalid allocation type: '.$type);
                }

                $totalAmount = $totalAmount->add($money);
            }

            if ($refund === null) {
                $refund = CustomerRefund::create([
                    'financial_year_id' => $year->id,
                    'party_id' => $partyId,
                    'ledger_id' => $ledgerId,
                    'status' => 'draft',
                    'series' => 'CRF',
                    'date' => $date,
                    'amount' => $totalAmount,
                    'payment_reference' => $paymentReference,
                    'narration' => $narration,
                    'created_by' => $userId,
                ]);
            } else {
                $refund->fill([
                    'financial_year_id' => $year->id,
                    'party_id' => $partyId,
                    'ledger_id' => $ledgerId,
                    'date' => $date,
                    'amount' => $totalAmount,
                    'payment_reference' => $paymentReference,
                    'narration' => $narration,
                ])->save();

                $refund->allocations()->delete();
            }

            foreach ($canonical as $line) {
                CustomerRefundAllocation::create([
                    'financial_year_id' => $year->id,
                    'customer_refund_id' => $refund->id,
                    'allocation_type' => $line['allocation_type'],
                    'receipt_id' => $line['receipt_id'],
                    'invoice_id' => $line['invoice_id'],
                    'amount' => $line['amount'],
                ]);
            }

            return $refund->fresh(['allocations', 'party', 'ledger']);
        }, 3);
    }
}
