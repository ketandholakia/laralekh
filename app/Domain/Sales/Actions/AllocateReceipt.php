<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

// Applies unallocated receipt funds to customer invoices as a separately
// authorized follow-up action. Insert-only; never creates accounting entries.
final class AllocateReceipt
{
    public function __construct(private ReceiptService $receipts) {}

    /** @param array<int|string, string> $allocations invoice_id => decimal amount */
    public function handle(int $receiptId, array $allocations): int
    {
        try {
            return DB::transaction(function () use ($receiptId, $allocations): int {
                $receipt = Receipt::findOrFail($receiptId);
                $canonical = $this->receipts->validateAllocations($receipt, $allocations);
                if ($canonical === []) {
                    throw new \DomainException('Enter at least one allocation amount.');
                }
                $this->receipts->applyAllocations($receipt, $canonical, auth()->id());
                activity('accounting')->performedOn($receipt)->event('allocated')
                    ->withProperties(['count' => count($canonical)])
                    ->tap(fn ($a) => $a->financial_year_id = $receipt->financial_year_id)
                    ->log('Receipt allocated to customer documents');

                return count($canonical);
            }, 3);
        } catch (Throwable $e) {
            if ($e instanceof \DomainException) {
                throw ValidationException::withMessages(['allocations' => $e->getMessage()]);
            }
            throw $e;
        }
    }
}
