<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Actions;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\PurchaseBillItem;
use App\Domain\Purchase\Models\PurchaseDebitNote;
use App\Domain\Purchase\Models\PurchaseDebitNoteLine;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class SavePurchaseDebitNoteDraft
{
    /**
     * @param array{date: string, reason: string} $header
     * @param list<array{purchase_bill_item_id: int, quantity: string|float|int, description?: ?string}> $lines
     */
    public function handle(?int $debitNoteId, int $purchaseBillId, array $header, array $lines): PurchaseDebitNote
    {
        return DB::transaction(function () use ($debitNoteId, $purchaseBillId, $header, $lines): PurchaseDebitNote {
            $validatedHeader = Validator::make($header, [
                'date' => ['required', 'date_format:Y-m-d'],
                'reason' => ['required', 'string', 'min:3', 'max:1000'],
            ])->validate();

            $bill = PurchaseBill::whereKey($purchaseBillId)->lockForUpdate()->firstOrFail();
            if ($bill->status !== 'posted') {
                throw new DomainException('Debit notes can only be issued against posted purchase bills.');
            }

            if ($header['date'] < $bill->date->format('Y-m-d')) {
                throw new DomainException('Debit note date cannot be earlier than purchase bill date.');
            }

            $yearId = app(CompanySession::class)->financialYearId() ?? $bill->financial_year_id;
            $year = FinancialYear::whereKey($yearId)->lockForUpdate()->firstOrFail();
            if ($year->is_closed) {
                throw new DomainException('Cannot save debit note in a closed financial year.');
            }

            if ($debitNoteId !== null) {
                $debitNote = PurchaseDebitNote::whereKey($debitNoteId)->lockForUpdate()->firstOrFail();
                if ($debitNote->status !== 'draft') {
                    throw new DomainException('Cannot edit a posted debit note.');
                }
            } else {
                $debitNote = new PurchaseDebitNote([
                    'financial_year_id' => $year->id,
                    'purchase_bill_id' => $bill->id,
                    'series' => 'PDN',
                    'status' => 'draft',
                    'supplier_snapshot' => $bill->supplier_snapshot ?? [],
                ]);
            }

            if (empty($lines)) {
                throw new DomainException('Debit note must contain at least one line item.');
            }

            $zero = Money::of('0');
            $totals = [
                'taxable_amount' => $zero,
                'cgst' => $zero,
                'sgst' => $zero,
                'utgst' => $zero,
                'igst' => $zero,
                'cess' => $zero,
                'total' => $zero,
            ];

            $processedLines = [];
            $position = 1;

            foreach ($lines as $lineInput) {
                $billItem = PurchaseBillItem::whereKey($lineInput['purchase_bill_item_id'])
                    ->where('purchase_bill_id', $bill->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (string) $lineInput['quantity'];
                if (bccomp($qty, '0', 3) <= 0) {
                    continue;
                }

                // Check prior debited quantity from other debit notes
                $priorDebitedQty = (string) PurchaseDebitNoteLine::where('purchase_bill_item_id', $billItem->id)
                    ->where('purchase_debit_note_id', '!=', $debitNote->id ?? 0)
                    ->sum('quantity');

                $maxReturnableQty = bcsub((string) $billItem->quantity, $priorDebitedQty, 3);
                if (bccomp($qty, $maxReturnableQty, 3) > 0) {
                    throw new DomainException(sprintf(
                        'Requested return quantity %s exceeds remaining returnable quantity %s for "%s".',
                        $qty,
                        $maxReturnableQty,
                        $billItem->description
                    ));
                }

                // Proportional tax calculation
                $ratio = bcdiv($qty, (string) $billItem->quantity, 8);
                $lineTaxable = Money::of(bcmul((string) $billItem->taxable_amount, $ratio, 2));
                $lineCgst = Money::of(bcmul((string) $billItem->cgst, $ratio, 2));
                $lineSgst = Money::of(bcmul((string) $billItem->sgst, $ratio, 2));
                $lineUtgst = Money::of(bcmul((string) $billItem->utgst, $ratio, 2));
                $lineIgst = Money::of(bcmul((string) $billItem->igst, $ratio, 2));
                $lineCess = Money::of(bcmul((string) $billItem->cess, $ratio, 2));
                $lineTotal = $lineTaxable->add($lineCgst)->add($lineSgst)->add($lineUtgst)->add($lineIgst)->add($lineCess);

                $processedLines[] = [
                    'bill_financial_year_id' => $bill->financial_year_id,
                    'purchase_bill_id' => $bill->id,
                    'purchase_bill_item_id' => $billItem->id,
                    'position' => $position++,
                    'description' => $lineInput['description'] ?? $billItem->description,
                    'quantity' => $qty,
                    'itc_treatment' => $billItem->itc_eligibility ?? 'eligible',
                    'taxable_amount' => $lineTaxable,
                    'cgst' => $lineCgst,
                    'sgst' => $lineSgst,
                    'utgst' => $lineUtgst,
                    'igst' => $lineIgst,
                    'cess' => $lineCess,
                    'total' => $lineTotal,
                ];

                $totals['taxable_amount'] = $totals['taxable_amount']->add($lineTaxable);
                $totals['cgst'] = $totals['cgst']->add($lineCgst);
                $totals['sgst'] = $totals['sgst']->add($lineSgst);
                $totals['utgst'] = $totals['utgst']->add($lineUtgst);
                $totals['igst'] = $totals['igst']->add($lineIgst);
                $totals['cess'] = $totals['cess']->add($lineCess);
                $totals['total'] = $totals['total']->add($lineTotal);
            }

            if (empty($processedLines)) {
                throw new DomainException('Debit note must return at least one item with quantity greater than zero.');
            }

            $debitNote->fill([
                'date' => $validatedHeader['date'],
                'reason' => $validatedHeader['reason'],
                'taxable_amount' => $totals['taxable_amount'],
                'cgst' => $totals['cgst'],
                'sgst' => $totals['sgst'],
                'utgst' => $totals['utgst'],
                'igst' => $totals['igst'],
                'cess' => $totals['cess'],
                'total' => $totals['total'],
            ])->save();

            $debitNote->lines()->delete();
            foreach ($processedLines as $line) {
                $debitNote->lines()->create($line + ['financial_year_id' => $debitNote->financial_year_id]);
            }

            return $debitNote->fresh(['lines.billItem', 'bill.party']);
        });
    }
}
