<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Sales\Services\CorrectionService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostSalesCorrection
{
    public function handle(int $id, ?array $input = null, ?array $lines = null): SalesCorrection
    {
        $access = app(CorrectionAccess::class);
        $access->authorize('corrections.post');

        return DB::transaction(function () use ($id, $input, $lines, $access) {
            $note = SalesCorrection::findOrFail($id);
            if ($note->financial_year_id !== $access->year()) {
                throw new DomainException('Select the correction financial year before posting.');
            }
            if ($input !== null && $lines !== null) {
                app(SaveSalesCorrection::class)->handle($id, $note->invoice_id, $input, $lines);
            }

            return app(CorrectionService::class)->post($id);
        }, 3);
    }
}
