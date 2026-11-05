<?php
declare(strict_types=1);
namespace App\Domain\Accounting\Services;
use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Masters\Models\FinancialYear;
use Illuminate\Support\Facades\DB;
use DomainException;

final class VoucherNumberAllocator
{
    public function next(int $financialYearId, string $series): int
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('Number allocation requires a transaction.');
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9\\/-]{0,9}$/D', $series)) {
            throw new DomainException('Invalid invoice series.');
        }
        // The year row also serializes creation of a previously unused series.
        $year = FinancialYear::whereKey($financialYearId)->lockForUpdate()->sole();
        if ($year->is_closed) {
            throw new DomainException('Financial year is closed.');
        }
        // A current read is essential under MySQL REPEATABLE READ when a caller
        // has already established a snapshot before waiting for the year lock.
        $sequence = NumberSequence::where('financial_year_id', $year->id)->where('series', $series)->lockForUpdate()->first();
        $sequence ??= NumberSequence::create(['financial_year_id' => $year->id, 'series' => $series, 'last_number' => 0]);
        $sequence = NumberSequence::whereKey($sequence->id)->lockForUpdate()->sole();
        $number = (int) $sequence->last_number + 1;
        if (strlen($series.'/'.$number) > 16) {
            throw new DomainException('Invoice number exceeds 16 characters.');
        }
        $sequence->update(['last_number' => $number]);
        return $number;
    }
}

