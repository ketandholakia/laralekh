<?php
declare(strict_types=1);
namespace App\Domain\Sales\Services;

use App\Domain\Accounting\Models\{Ledger, LedgerGroup, Voucher};
use App\Domain\Accounting\Services\{PostingService, VoucherNumberAllocator};
use App\Domain\Masters\Models\{FinancialYear, Party};
use App\Domain\Sales\Models\{Invoice, Receipt, ReceiptAllocation, ReceiptAllocationReversal, ReceiptReversal};
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use DomainException;

// Customer receipt posting, allocation and reversal. Accounting truth:
// posting Dr cash/bank / Cr receivable; allocations are insert-only links that
// never create accounting entries; reversals swap the original entries and undo
// active allocations through linked records. All decision figures are read with
// locking reads inside the posting transaction (MySQL REPEATABLE READ safe).
final class ReceiptService
{
    public function __construct(
        private PostingService $posting,
        private VoucherNumberAllocator $numbers,
        private SettlementService $settlement,
    ) {}

    /**
     * Validates a list of intended allocations against the receipt and the
     * invoice balances. Returns canonical rows: invoice_id => Money.
     *
     * @param array<int|string, string> $allocations invoice_id => decimal amount
     * @return list<array{invoice: Invoice, amount: Money}>
     */
    public function validateAllocations(Receipt $receipt, array $allocations): array
    {
        $funds = $this->settlement->availableReceiptFunds($receipt);
        $canonical = [];
        $total = Money::of('0');
        foreach ($allocations as $invoiceId => $amount) {
            $money = Money::of((string) $amount);
            if ($money->isZero() || $money->isNegative()) {
                throw new DomainException('Allocated amounts must be positive.');
            }
            $invoice = Invoice::whereKey((int) $invoiceId)->lockForUpdate()->first();
            if ($invoice === null) {
                throw new DomainException('Allocated invoice not found in this company.');
            }
            if ($invoice->party_id !== $receipt->party_id) {
                throw new DomainException('Receipts can only be allocated to documents of the same customer.');
            }
            if ($invoice->status !== 'posted' || $invoice->voucher_id === null) {
                throw new DomainException('Only posted invoices can be allocated.');
            }
            if ($this->settlement->invoiceReversed($invoice)) {
                throw new DomainException('Invoice '.$invoice->series.'/'.$invoice->number.' has been reversed and cannot be allocated.');
            }
            $outstanding = $this->settlement->documentOutstanding($invoice);
            if ($money->greaterThan($outstanding)) {
                throw new DomainException('Allocation exceeds the outstanding balance of invoice '
                    .$invoice->series.'/'.$invoice->number.' ('.(string) $outstanding.').');
            }
            $total = $total->add($money);
            $canonical[] = ['invoice' => $invoice, 'amount' => $money];
        }
        if ($total->greaterThan($funds)) {
            throw new DomainException('Allocations exceed the unallocated receipt funds ('.(string) $funds.').');
        }
        return $canonical;
    }

    /** Creates the immutable allocation rows inside the caller's transaction. */
    public function applyAllocations(Receipt $receipt, array $canonical, ?int $userId): void
    {
        foreach ($canonical as $row) {
            ReceiptAllocation::create([
                'financial_year_id' => $receipt->financial_year_id,
                'receipt_id' => $receipt->id,
                'invoice_financial_year_id' => $row['invoice']->financial_year_id,
                'invoice_id' => $row['invoice']->id,
                'amount' => $row['amount'],
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * Posts a draft receipt: Dr payment ledger, Cr customer receivable, with a
     * company/year/series receipt number allocated inside the same transaction.
     *
     * @param array<int|string, string> $allocations optional initial allocations
     */
    public function post(Receipt $receipt, array $allocations = [], ?int $userId = null): Receipt
    {
        return DB::transaction(function () use ($receipt, $allocations, $userId): Receipt {
            $receipt = Receipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status !== 'draft' || $receipt->voucher_id !== null || $receipt->number !== null) {
                throw new DomainException('Receipt is already posted.');
            }
            $party = Party::whereKey($receipt->party_id)->lockForUpdate()->firstOrFail();
            if ($party->ledger_id === null) {
                throw new DomainException('The customer needs a receivable ledger before receipts can be posted.');
            }
            $paymentLedger = $this->paymentLedger($receipt->ledger_id);
            $customerLedger = $this->customerLedger($party->ledger_id);
            if ($receipt->amount->isZero() || $receipt->amount->isNegative()) {
                throw new DomainException('A receipt needs a positive amount.');
            }
            $year = FinancialYear::whereKey($receipt->financial_year_id)->lockForUpdate()->firstOrFail();
            if ($year->is_closed || $receipt->date->lt($year->starts_on) || $receipt->date->gt($year->ends_on)) {
                throw new DomainException('Receipt date must be inside the selected open financial year.');
            }
            $canonical = $this->validateAllocations($receipt, $allocations);

            $entries = [
                ['ledger_id' => $paymentLedger->id, 'debit' => $receipt->amount, 'credit' => Money::of('0')],
                ['ledger_id' => $customerLedger->id, 'debit' => Money::of('0'), 'credit' => $receipt->amount],
            ];
            $this->posting->assertBalanced($entries);
            $number = $this->numbers->next($year->id, $receipt->series);
            $voucher = Voucher::create([
                'financial_year_id' => $year->id, 'type' => 'receipt', 'date' => $receipt->date->format('Y-m-d'),
                'series' => $receipt->series, 'number' => $number, 'status' => 'draft',
                'narration' => 'Customer receipt '.$receipt->series.'/'.$number
                    .($receipt->payment_reference !== null ? ' ref '.$receipt->payment_reference : ''),
            ]);
            foreach ($entries as $position => $entry) {
                $voucher->lines()->create($entry + ['financial_year_id' => $year->id, 'position' => $position + 1]);
            }
            $this->posting->assertBalanced($voucher->lines()->get()
                ->map(fn ($line) => ['debit' => $line->debit, 'credit' => $line->credit])->all());
            $receipt->fill([
                'voucher_id' => $voucher->id, 'number' => $number, 'status' => 'posted',
                'party_snapshot' => $party->only(['name', 'gstin', 'state_code', 'address', 'registration_type']),
                // Frozen view of the allocations made at posting, printed on the
                // original receipt document; later allocations stay in the register.
                'allocations_snapshot' => array_map(fn ($row) => [
                    'invoice_label' => $row['invoice']->series.'/'.$row['invoice']->number,
                    'invoice_date' => $row['invoice']->date->format('d-m-Y'),
                    'invoice_total' => (string) $row['invoice']->total,
                    'amount' => (string) $row['amount'],
                ], $canonical),
            ])->save();
            $this->applyAllocations($receipt, $canonical, $userId);
            $allocated = Money::of('0');
            foreach ($canonical as $row) { $allocated = $allocated->add($row['amount']); }
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);
            activity('accounting')->performedOn($voucher)->event('posted')
                ->withProperties(['receipt_id' => $receipt->id, 'amount' => (string) $receipt->amount,
                    'allocated' => (string) $allocated])
                ->tap(fn ($a) => $a->financial_year_id = $year->id)
                ->log('Customer receipt posted');
            return $receipt->fresh(['allocations', 'voucher']);
        }, 3);
    }

    /**
     * Undoes the given active allocations of a posted receipt through
     * insert-only reversal records. No accounting entries change, because
     * allocation never created any.
     *
     * @param list<int> $allocationIds
     */
    public function undoAllocations(Receipt $receipt, array $allocationIds, ?int $userId): int
    {
        return DB::transaction(function () use ($receipt, $allocationIds, $userId): int {
            $receipt = Receipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status !== 'posted') {
                throw new DomainException('Only a posted receipt has allocations to undo.');
            }
            $undone = 0;
            foreach ($allocationIds as $allocationId) {
                $allocation = ReceiptAllocation::whereKey((int) $allocationId)->lockForUpdate()->first();
                if ($allocation === null || (int) $allocation->receipt_id !== $receipt->id) {
                    throw new DomainException('Allocation does not belong to this receipt.');
                }
                if (ReceiptAllocationReversal::where('allocation_id', $allocation->id)->lockForUpdate()->first()) {
                    throw new DomainException('This allocation has already been undone.');
                }
                ReceiptAllocationReversal::create([
                    'financial_year_id' => $allocation->financial_year_id,
                    'allocation_id' => $allocation->id, 'created_by' => $userId,
                ]);
                $undone++;
            }
            if ($undone === 0) {
                throw new DomainException('Select at least one active allocation to undo.');
            }
            activity('accounting')->performedOn($receipt)->event('allocation-undone')
                ->withProperties(['count' => $undone])
                ->tap(fn ($a) => $a->financial_year_id = $receipt->financial_year_id)
                ->log('Receipt allocations undone');
            return $undone;
        }, 3);
    }

    /**
     * Exact reversal of a posted receipt: swaps the original voucher entries,
     * undoes every active allocation through linked records, and preserves all
     * history. The receipt keeps its number; funds become unavailable.
     */
    public function reverse(Receipt $receipt, int $yearId, string $date, string $reason, ?int $userId): ReceiptReversal
    {
        return DB::transaction(function () use ($receipt, $yearId, $date, $reason, $userId): ReceiptReversal {
            $receipt = Receipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status !== 'posted' || $receipt->voucher_id === null) {
                throw new DomainException('Only a posted receipt can be reversed.');
            }
            if (ReceiptReversal::where('original_receipt_id', $receipt->id)->lockForUpdate()->first()) {
                throw new DomainException('This receipt has already been reversed.');
            }
            if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) {
                throw new DomainException('Provide a reversal reason of 5 to 2000 characters.');
            }
            $year = FinancialYear::whereKey($yearId)->lockForUpdate()->firstOrFail();
            $day = \Illuminate\Support\Carbon::hasFormat($date, 'Y-m-d')
                ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date)->startOfDay() : false;
            if ($year->is_closed || $day === false
                || $day->lt($year->starts_on) || $day->gt($year->ends_on) || $day->gt(\Illuminate\Support\Carbon::today())) {
                throw new DomainException('Reversal date must be today or earlier, inside the selected open financial year.');
            }
            $originalVoucher = Voucher::whereKey($receipt->voucher_id)->lockForUpdate()->firstOrFail();
            if ($originalVoucher->status !== 'posted') {
                throw new DomainException('The receipt voucher is not posted.');
            }
            // Atomically undo every active allocation; the undo rows record what.
            $active = ReceiptAllocation::query()->where('receipt_id', $receipt->id)
                ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('receipt_allocation_reversals')
                    ->whereColumn('receipt_allocation_reversals.allocation_id', 'receipt_allocations.id'))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($active as $allocation) {
                ReceiptAllocationReversal::create([
                    'financial_year_id' => $allocation->financial_year_id,
                    'allocation_id' => $allocation->id, 'created_by' => $userId,
                ]);
            }
            $entries = $originalVoucher->lines()->orderBy('position')->with('ledger')->get()->map(fn ($line) => [
                'ledger_id' => $line->ledger_id, 'debit' => $line->credit, 'credit' => $line->debit,
                'ledger_code' => $line->ledger->code, 'ledger_name' => $line->ledger->name,
            ])->all();
            $this->posting->assertBalanced($entries);
            $number = $this->numbers->next($year->id, 'RV');
            $voucher = Voucher::create(['financial_year_id' => $year->id, 'type' => 'reversal', 'date' => $date,
                'series' => 'RV', 'number' => $number, 'reverses_voucher_id' => $originalVoucher->id,
                'narration' => 'Reversal of receipt '.$receipt->label().': '.$reason]);
            foreach ($entries as $index => $entry) {
                $voucher->lines()->create(['financial_year_id' => $year->id, 'position' => $index + 1,
                    'ledger_id' => $entry['ledger_id'], 'debit' => $entry['debit'], 'credit' => $entry['credit']]);
            }
            $this->posting->assertBalanced($voucher->lines()->get()
                ->map(fn ($line) => ['debit' => $line->debit, 'credit' => $line->credit])->all());
            $reversal = ReceiptReversal::create([
                'financial_year_id' => $year->id,
                'original_financial_year_id' => $receipt->financial_year_id,
                'original_receipt_id' => $receipt->id, 'voucher_id' => $voucher->id,
                'date' => $date, 'reason' => $reason,
                'original_snapshot' => ['number' => $receipt->label(), 'date' => $receipt->date->format('Y-m-d'),
                    'amount' => (string) $receipt->amount, 'party' => $receipt->party_snapshot,
                    'payment_reference' => $receipt->payment_reference],
                'undone_allocations' => $active->map(fn ($a) => [
                    'allocation_id' => $a->id, 'invoice_id' => $a->invoice_id, 'amount' => (string) $a->amount,
                ])->all(),
            ]);
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);
            $reversal->update(['status' => 'posted']);
            activity('accounting')->performedOn($reversal)->event('posted')
                ->withProperties(['receipt_id' => $receipt->id, 'amount' => (string) $receipt->amount,
                    'undone_allocations' => $active->count()])
                ->tap(fn ($a) => $a->financial_year_id = $year->id)
                ->log('Customer receipt reversed');
            return $reversal;
        }, 3);
    }

    /** The money side of the receipt must be a cash/bank asset ledger. */
    private function paymentLedger(int $id): Ledger
    {
        $ledger = Ledger::whereKey($id)->lockForUpdate()->firstOrFail();
        $group = LedgerGroup::whereKey($ledger->ledger_group_id)->sole();
        if ($group->nature !== 'asset' || $group->code !== 'CASH_BANK') {
            throw new DomainException('Receipts must use a cash or bank ledger.');
        }
        return $ledger;
    }

    private function customerLedger(int $id): Ledger
    {
        $ledger = Ledger::whereKey($id)->lockForUpdate()->firstOrFail();
        $group = LedgerGroup::whereKey($ledger->ledger_group_id)->sole();
        if ($group->nature !== 'asset') {
            throw new DomainException('The customer ledger must be a receivable (asset) ledger.');
        }
        return $ledger;
    }
}
