<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Services\PostingService;
use App\Domain\Sales\Models\Invoice;

// Posts a draft through PostingService inside the caller's authorized tenant
// context. Duplicate submissions fail inside the same transaction that locks
// the invoice row: a second post either sees the committed posted status and
// throws, or serializes behind the lock and throws before any number is used.
final class PostInvoice
{
    public function handle(int $invoiceId): Invoice
    {
        app(PostingService::class)->postSalesInvoice($invoiceId);

        return Invoice::whereKey($invoiceId)->with('items')->sole();
    }
}
