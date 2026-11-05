<?php
declare(strict_types=1);
namespace App\Domain\Sales\Actions;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Sales\Services\CorrectionService;
final class SaveSalesCorrection
{
    public function handle(?int $id, int $invoiceId, array $input, array $lines): SalesCorrection
    {
        $access = app(CorrectionAccess::class);
        $access->authorize('corrections.create');
        return app(CorrectionService::class)->saveDraft($id, $invoiceId, $access->year(), $input, $lines);
    }
}

