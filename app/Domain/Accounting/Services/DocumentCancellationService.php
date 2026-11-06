<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\Invoice;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Cancels drafts directly; posted documents require a posted accounting reversal first. */
final class DocumentCancellationService
{
    public function cancelInvoice(int $invoiceId, string $reason, ?int $reversalFinancialYearId = null, ?string $reversalDate = null): Invoice
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($invoiceId, $reason, $reversalFinancialYearId, $reversalDate) {
            $invoice = Invoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, ['draft', 'posted'], true)) {
                throw new DomainException('Only a draft or posted invoice can be cancelled.');
            }
            if ($invoice->status === 'draft') {
                if ($invoice->voucher_id !== null || $invoice->number !== null) {
                    throw new DomainException('A draft invoice with an allocated voucher or number cannot be cancelled directly.');
                }
            } else {
                if (! $invoice->voucher_id) {
                    throw new DomainException('A posted invoice without its accounting voucher cannot be cancelled.');
                }
                [$yearId,$date] = $this->reversalPeriod($reversalFinancialYearId, $reversalDate);
                app(ReversalService::class)->reverse((int) $invoice->voucher_id, $yearId, $date, $reason);
            }
            $invoice->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            activity('accounting')->performedOn($invoice)->event('cancelled')->tap(fn ($activity) => $activity->financial_year_id = $invoice->financial_year_id)->withProperties([
                'document_number' => $invoice->series.'/'.$invoice->number, 'reversal_voucher_id' => $invoice->voucher_id
                    ? Voucher::query()->where('reverses_voucher_id', $invoice->voucher_id)->value('id') : null,
            ])->log('Sales invoice cancelled');

            return $invoice->fresh();
        }, 3);
    }

    public function cancelPurchaseBill(int $billId, string $reason, ?int $reversalFinancialYearId = null, ?string $reversalDate = null): PurchaseBill
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($billId, $reason, $reversalFinancialYearId, $reversalDate) {
            $bill = PurchaseBill::query()->whereKey($billId)->lockForUpdate()->firstOrFail();
            if (! in_array($bill->status, ['draft', 'posted'], true)) {
                throw new DomainException('Only a draft or posted purchase bill can be cancelled.');
            }
            if ($bill->status === 'draft') {
                if ($bill->voucher_id !== null || $bill->number !== null) {
                    throw new DomainException('A draft purchase bill with an allocated voucher or number cannot be cancelled directly.');
                }
            } else {
                if (! $bill->voucher_id) {
                    throw new DomainException('A posted purchase bill without its accounting voucher cannot be cancelled.');
                }
                $activeAllocations = DB::table('supplier_payment_allocations as spa')->where('spa.company_id', $bill->company_id)
                    ->where('spa.purchase_bill_id', $bill->id)->whereNotExists(fn ($query) => $query->selectRaw('1')
                    ->from('supplier_payment_allocation_reversals as spar')->whereColumn('spar.allocation_id', 'spa.id'))->exists();
                if ($activeAllocations) {
                    throw new DomainException('Reverse supplier payments and undo their allocations before cancelling this bill.');
                }
                if (DB::table('purchase_debit_notes')->where('company_id', $bill->company_id)->where('purchase_bill_id', $bill->id)->where('status', 'posted')->exists()) {
                    throw new DomainException('A purchase bill with a posted debit note cannot be fully cancelled.');
                }
                [$yearId,$date] = $this->reversalPeriod($reversalFinancialYearId, $reversalDate);
                app(ReversalService::class)->reverse((int) $bill->voucher_id, $yearId, $date, $reason);
            }
            $bill->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            activity('accounting')->performedOn($bill)->event('cancelled')->tap(fn ($activity) => $activity->financial_year_id = $bill->financial_year_id)->withProperties([
                'document_number' => $bill->series.'/'.$bill->number, 'reversal_voucher_id' => $bill->voucher_id
                    ? Voucher::query()->where('reverses_voucher_id', $bill->voucher_id)->value('id') : null,
            ])->log('Purchase bill cancelled');

            return $bill->fresh();
        }, 3);
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw new DomainException('Provide a cancellation reason of 5 to 2000 characters.');
        }

        return $reason;
    }

    private function reversalPeriod(?int $yearId, ?string $date): array
    {
        if (! $yearId || ! $date) {
            throw new DomainException('A reversal date and open financial year are required to cancel a posted document.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            throw new DomainException('Use a valid reversal date in YYYY-MM-DD format.');
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        if (! $day || $day->format('Y-m-d') !== $date) {
            throw new DomainException('Use a valid reversal date in YYYY-MM-DD format.');
        }
        $year = FinancialYear::query()->whereKey($yearId)->firstOrFail();
        if ($year->is_closed || $day->lt($year->starts_on) || $day->gt($year->ends_on) || $day->gt(CarbonImmutable::today())) {
            throw new DomainException('Reversal date must be today or earlier, inside the selected open financial year.');
        }

        return [$yearId, $day->toDateString()];
    }
}
