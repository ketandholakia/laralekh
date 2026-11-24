<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Actions;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Accounting\Services\VoucherNumberAllocator;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\PurchaseDebitNote;
use App\Domain\Purchase\Models\PurchaseDebitNoteLine;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostPurchaseDebitNote
{
    public function __construct(
        private VoucherNumberAllocator $numbers,
        private PostingService $posting
    ) {}

    public function handle(int $debitNoteId): PurchaseDebitNote
    {
        return DB::transaction(function () use ($debitNoteId): PurchaseDebitNote {
            $debitNote = PurchaseDebitNote::whereKey($debitNoteId)->lockForUpdate()->sole();
            if ($debitNote->status !== 'draft' || $debitNote->voucher_id !== null) {
                throw new DomainException('Purchase debit note is already posted.');
            }

            $bill = PurchaseBill::whereKey($debitNote->purchase_bill_id)->lockForUpdate()->sole();
            if ($bill->status !== 'posted') {
                throw new DomainException('Cannot post debit note against an unposted purchase bill.');
            }

            $year = FinancialYear::whereKey($debitNote->financial_year_id)->lockForUpdate()->sole();
            if ($year->is_closed || $debitNote->date->lt($year->starts_on) || $debitNote->date->gt($year->ends_on)) {
                throw new DomainException('Debit note date must be inside an open financial year.');
            }

            $supplier = $bill->party()->lockForUpdate()->sole();
            $payableLedger = Ledger::whereKey($supplier->ledger_id)->lockForUpdate()->sole();

            $lines = $debitNote->lines()->with('billItem.item')->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw new DomainException('Cannot post an empty debit note.');
            }

            // Re-verify capacity under lock
            foreach ($lines as $line) {
                $billItem = $line->billItem;
                $priorDebitedQty = (string) PurchaseDebitNoteLine::where('purchase_bill_item_id', $billItem->id)
                    ->where('purchase_debit_note_id', '!=', $debitNote->id)
                    ->whereHas('debitNote', fn ($q) => $q->where('status', 'posted'))
                    ->sum('quantity');

                $maxReturnableQty = bcsub((string) $billItem->quantity, $priorDebitedQty, 3);
                if (bccomp((string) $line->quantity, $maxReturnableQty, 3) > 0) {
                    throw new DomainException(sprintf(
                        'Capacity exceeded: bill item "%s" has only %s remaining returnable quantity.',
                        $billItem->description,
                        $maxReturnableQty
                    ));
                }
            }

            $zero = Money::of('0');
            $credits = [];
            $stockMovements = [];

            foreach ($lines as $line) {
                $billItem = $line->billItem;
                $expenseLedgerId = $billItem->purchase_ledger_id;
                $credits[$expenseLedgerId] = ($credits[$expenseLedgerId] ?? $zero)->add($line->taxable_amount);

                // Input tax credit reversal
                $taxes = ['cgst', 'sgst', 'utgst', 'igst', 'cess'];
                foreach ($taxes as $tax) {
                    $taxAmount = $line->$tax;
                    if ($taxAmount->isZero()) {
                        continue;
                    }
                    if ($billItem->itc_eligibility === 'eligible') {
                        $inputLedger = Ledger::where('code', 'INPUT_'.strtoupper($tax))->sole();
                        $credits[$inputLedger->id] = ($credits[$inputLedger->id] ?? $zero)->add($taxAmount);
                    } else {
                        // If capitalized into expense originally, reverse expense
                        $credits[$expenseLedgerId] = $credits[$expenseLedgerId]->add($taxAmount);
                    }
                }

                if ($billItem->item_id !== null && $billItem->item?->tracks_stock) {
                    $stockMovements[] = [
                        'item_id' => $billItem->item_id,
                        'quantity' => bcmul((string) $line->quantity, '-1', 3),
                        'unit_cost' => $billItem->unit_price,
                    ];
                }
            }

            $entries = [];
            // Debit Supplier Payable (reduces amount payable to supplier)
            $entries[] = [
                'ledger_id' => $payableLedger->id,
                'debit' => $debitNote->total,
                'credit' => $zero,
            ];
            // Credits
            foreach ($credits as $ledgerId => $creditAmount) {
                if (! $creditAmount->isZero()) {
                    $entries[] = [
                        'ledger_id' => $ledgerId,
                        'debit' => $zero,
                        'credit' => $creditAmount,
                    ];
                }
            }

            $this->posting->assertBalanced($entries);

            // Allocate sequential number
            $number = $this->numbers->next($year->id, 'PDN');

            $voucher = Voucher::create([
                'financial_year_id' => $year->id,
                'type' => 'debit_note',
                'date' => $debitNote->date,
                'series' => 'PDN',
                'number' => $number,
                'narration' => 'Purchase debit note PDN/'.$number.' against bill '.$bill->supplier_bill_number,
            ]);

            foreach ($entries as $pos => $entry) {
                $voucher->lines()->create($entry + [
                    'financial_year_id' => $year->id,
                    'position' => $pos + 1,
                ]);
            }

            $this->posting->assertBalanced(
                $voucher->lines()->get()->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all()
            );

            // Persist stock movements
            foreach ($stockMovements as $movement) {
                StockMovement::create([
                    'financial_year_id' => $year->id,
                    'item_id' => $movement['item_id'],
                    'voucher_id' => $voucher->id,
                    'date' => $debitNote->date->format('Y-m-d'),
                    'quantity' => $movement['quantity'],
                    'unit_cost' => $movement['unit_cost'],
                    'reference' => 'PDN/'.$number,
                ]);
            }

            $debitNote->update([
                'number' => $number,
                'status' => 'posted',
                'voucher_id' => $voucher->id,
                'supplier_snapshot' => $bill->supplier_snapshot,
            ]);

            $voucher->update(['status' => 'posted', 'posted_at' => now()]);

            return $debitNote->fresh(['lines.billItem', 'bill.party', 'voucher']);
        }, 3);
    }
}
