<?php
declare(strict_types=1);
namespace App\Domain\Accounting\Actions;
use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Accounting\Services\ReversalService;
use App\Domain\Sales\Actions\CorrectionAccess;
use DomainException;
final class ReverseVoucher
{
    public function handle(int $voucherId, string $date, string $reason, bool $confirmed): VoucherReversal
    {
        $access = app(CorrectionAccess::class);
        $access->authorize('reversals.post');
        if (!$confirmed) { throw new DomainException('Confirm the displayed reversal entries before posting.'); }
        return app(ReversalService::class)->reverse($voucherId, $access->year(), $date, $reason);
    }
}

