<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Services\CorrectionDocuments;
use Illuminate\Http\Response;

final class ReversalPdfController
{
    public function __invoke(int $reversal): Response
    {
        app(CorrectionAccess::class)->authorize();
        $document = VoucherReversal::findOrFail($reversal);

        return app(CorrectionDocuments::class)->reversal($document)->download('internal-reversal-'.$document->id.'.pdf');
    }
}
