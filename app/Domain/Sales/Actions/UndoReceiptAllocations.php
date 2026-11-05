<?php
declare(strict_types=1);
namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

// Undoes selected active allocations through insert-only reversal records.
final class UndoReceiptAllocations
{
    public function __construct(private ReceiptService $receipts) {}

    /** @param list<int> $allocationIds */
    public function handle(int $receiptId, array $allocationIds): int
    {
        try {
            return DB::transaction(
                fn (): int => $this->receipts->undoAllocations(Receipt::findOrFail($receiptId), $allocationIds, auth()->id()),
                3,
            );
        } catch (Throwable $e) {
            if ($e instanceof \DomainException) {
                throw ValidationException::withMessages(['allocations' => $e->getMessage()]);
            }
            throw $e;
        }
    }
}
