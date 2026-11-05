<?php
declare(strict_types=1);
namespace App\Domain\Sales\Services;

use App\Domain\Accounting\Models\{Voucher, VoucherReversal};
use App\Domain\Sales\Models\{Invoice, Receipt, ReceiptAllocation, ReceiptAllocationReversal, ReceiptReversal, SalesCorrection};
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use DomainException;

// Derived settlement balances. Posted documents are never updated; every figure
// here is computed from immutable accounting history. Callers that make
// settlement decisions must already hold the relevant locks and read these
// figures with locking reads (this class always uses them), because MySQL
// REPEATABLE READ snapshots taken before a lock would miss committed changes.
final class SettlementService
{
    /** Active (not undone) allocated total of one receipt. Locking read. */
    public function allocatedFromReceipt(int $receiptId): Money
    {
        $rows = ReceiptAllocation::query()->where('receipt_id', $receiptId)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('receipt_allocation_reversals')
                ->whereColumn('receipt_allocation_reversals.allocation_id', 'receipt_allocations.id'))
            ->lockForUpdate()->get(['amount']);
        $sum = Money::of('0');
        foreach ($rows as $row) { $sum = $sum->add($row->amount); }
        return $sum;
    }

    /** Active allocated total against one invoice, across all receipts. Locking read. */
    public function allocatedToInvoice(int $invoiceId): Money
    {
        $rows = ReceiptAllocation::query()->where('invoice_id', $invoiceId)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('receipt_allocation_reversals')
                ->whereColumn('receipt_allocation_reversals.allocation_id', 'receipt_allocations.id'))
            ->lockForUpdate()->get(['amount']);
        $sum = Money::of('0');
        foreach ($rows as $row) { $sum = $sum->add($row->amount); }
        return $sum;
    }

    /** Posted credit/debit note effect on one invoice's balance. Locking read. */
    public function noteAdjustments(int $invoiceId): array
    {
        $rows = SalesCorrection::query()->where('invoice_id', $invoiceId)
            ->where('status', 'posted')->lockForUpdate()->get(['kind', 'total']);
        $credits = $debits = Money::of('0');
        foreach ($rows as $row) {
            if ($row->kind === 'credit') { $credits = $credits->add($row->total); }
            else { $debits = $debits->add($row->total); }
        }
        return ['credits' => $credits, 'debits' => $debits];
    }

    /**
     * Outstanding amount of one invoice: total less posted credit notes and
     * active receipt allocations, plus posted debit notes. Can go negative
     * when later credits exceed what is still owed; that is a customer credit
     * and is shown as such, never silently absorbed.
     */
    public function documentOutstanding(Invoice $invoice): Money
    {
        $adjustments = $this->noteAdjustments($invoice->id);
        return $invoice->total->subtract($adjustments['credits'])
            ->subtract($this->allocatedToInvoice($invoice->id))->add($adjustments['debits']);
    }

    public function availableReceiptFunds(Receipt $receipt): Money
    {
        return $receipt->amount->subtract($this->allocatedFromReceipt($receipt->id));
    }

    /** True when the invoice's original voucher has a posted internal reversal. */
    public function invoiceReversed(Invoice $invoice): bool
    {
        return VoucherReversal::query()->where('original_voucher_id', $invoice->voucher_id)->lockForUpdate()->exists();
    }

    /** Authoritative receivable-ledger balance (debits minus credits). */
    public function ledgerBalance(int $ledgerId, ?string $beforeExclusive = null, ?string $untilInclusive = null): Money
    {
        $query = DB::table('voucher_lines')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')
            ->where('voucher_lines.ledger_id', $ledgerId)
            ->where('vouchers.status', 'posted');
        if ($beforeExclusive !== null) {
            $query->where('vouchers.date', '<', $beforeExclusive);
        }
        if ($untilInclusive !== null) {
            $query->where('vouchers.date', '<=', $untilInclusive);
        }
        // Privileged aggregate over posted accounting rows; money columns are
        // decimal on MySQL and text on SQLite, so sum in application code.
        $rows = $query->orderBy('voucher_lines.id')->lockForUpdate()->get(['debit', 'credit']);
        $balance = Money::of('0');
        foreach ($rows as $row) {
            $balance = $balance->add(Money::of($row->debit))->subtract(Money::of($row->credit));
        }
        return $balance;
    }

    /**
     * Per-customer settlement overview. Receipt funds the customer paid but
     * that are not yet applied are advances; document balances below zero are
     * excess customer credits. Both are shown separately and never auto-applied.
     *
     * @return array{documents: list<array<string, mixed>>, advances: Money, excess_credits: Money,
     *     invoices_balance: Money, ledger_balance: Money}
     */
    public function customerSummary(int $partyId): array
    {
        app(TenantContext::class)->id(); // guard: callers must be inside a tenant scope
        $documents = [];
        $invoicesBalance = Money::of('0');
        $excessCredits = Money::of('0');
        $party = \App\Domain\Masters\Models\Party::query()->whereKey($partyId)->firstOrFail();

        $invoices = Invoice::query()->where('party_id', $partyId)
            ->where('status', 'posted')->with('voucher')->orderBy('date')->orderBy('id')->get();
        foreach ($invoices as $invoice) {
            $reversed = $invoice->voucher_id !== null && $this->invoiceReversed($invoice);
            $outstanding = $reversed ? Money::of('0') : $this->documentOutstanding($invoice);
            $documents[] = [
                'kind' => 'invoice', 'id' => $invoice->id, 'label' => 'Invoice '.$invoice->series.'/'.$invoice->number,
                'date' => $invoice->date->format('Y-m-d'), 'total' => (string) $invoice->total,
                'outstanding' => (string) $outstanding, 'reversed' => $reversed,
            ];
            if (!$reversed) {
                if ($outstanding->isNegative()) {
                    $excessCredits = $excessCredits->add(Money::of(bcmul((string) $outstanding, '-1', 2)));
                } else {
                    $invoicesBalance = $invoicesBalance->add($outstanding);
                }
            }
        }
        $advances = Money::of('0');
        $receipts = Receipt::query()->where('party_id', $partyId)
            ->where('status', 'posted')->orderBy('date')->orderBy('id')->get();
        foreach ($receipts as $receipt) {
            $funds = $this->availableReceiptFunds($receipt);
            if (!$funds->isZero()) {
                $advances = $advances->add($funds);
                $documents[] = [
                    'kind' => 'receipt_advance', 'id' => $receipt->id,
                    'label' => 'Receipt '.$receipt->label().' (unapplied advance)',
                    'date' => $receipt->date->format('Y-m-d'), 'total' => (string) $receipt->amount,
                    'outstanding' => (string) $funds, 'reversed' => false,
                ];
            }
        }
        usort($documents, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
        return [
            'documents' => $documents, 'advances' => $advances, 'excess_credits' => $excessCredits,
            'invoices_balance' => $invoicesBalance, 'ledger_balance' => $this->ledgerBalance($party->ledger_id),
            'party' => $party,
        ];
    }
}
