<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\ReceiptDocuments;
use Illuminate\Http\Response;

final class ReceiptPdfController
{
    public function __invoke(int $receipt): Response
    {
        app(CorrectionAccess::class)->authorize('receipts.view');
        $document = Receipt::findOrFail($receipt);

        return app(ReceiptDocuments::class)->receipt($document)->download(
            $document->status === 'posted' ? 'receipt-'.$document->series.'-'.$document->number.'.pdf' : 'draft-receipt.pdf');
    }
}
