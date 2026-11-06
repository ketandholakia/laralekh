<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

// Exact receipt reversal: swaps the posted entries and atomically undoes all
// active allocations, preserving history. Requires the reversal permission.
final class ReverseReceipt
{
    public function __construct(private ReceiptService $receipts) {}

    public function handle(int $receiptId, int $yearId, string $date, string $reason): ReceiptReversal
    {
        try {
            return DB::transaction(fn (): ReceiptReversal => $this->receipts->reverse(Receipt::findOrFail($receiptId), $yearId, $date, $reason, auth()->id()), 3);
        } catch (Throwable $e) {
            if ($e instanceof \DomainException) {
                throw ValidationException::withMessages(['reason' => $e->getMessage()]);
            }
            throw $e;
        }
    }
}
