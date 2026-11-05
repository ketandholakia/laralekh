<?php
declare(strict_types=1);
namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Services\{CustomerStatement, ReceiptDocuments};
use Illuminate\Http\Response;

final class StatementPdfController
{
    public function __invoke(int $party, \Illuminate\Http\Request $request): Response
    {
        app(CorrectionAccess::class)->authorize('receipts.view');
        $statement = app(CustomerStatement::class)->build(
            $party,
            (string) $request->query('from', now()->startOfYear()->toDateString()),
            (string) $request->query('as_of', now()->toDateString()),
        );
        return app(ReceiptDocuments::class)->statement($statement)
            ->download('statement-'.$statement['party']->id.'-'.$statement['as_of'].'.pdf');
    }
}
