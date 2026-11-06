<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\ReceiptService;

// Posts a receipt draft and applies its initial allocations atomically.
final class PostReceipt
{
    public function __construct(private ReceiptService $receipts) {}

    /** @param array<int|string, string> $allocations invoice_id => decimal amount */
    public function handle(int $receiptId, array $allocations = []): Receipt
    {
        return $this->receipts->post(Receipt::findOrFail($receiptId), $allocations, auth()->id());
    }
}
