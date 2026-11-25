<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Services\NumberSequenceService;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\AdvanceReceiptVoucher;
use App\Domain\Shared\Models\Activity;
use App\Domain\Shared\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostAdvanceReceiptVoucher
{
    public function __construct(
        private NumberSequenceService $numbers,
    ) {}

    public function handle(int $voucherId, ?int $userId = null): AdvanceReceiptVoucher
    {
        return DB::transaction(function () use ($voucherId, $userId): AdvanceReceiptVoucher {
            $voucher = AdvanceReceiptVoucher::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();

            if ($voucher->status !== 'draft' || $voucher->number !== null) {
                throw new DomainException('Only unposted draft advance receipt vouchers can be posted.');
            }

            $year = FinancialYear::query()->whereKey($voucher->financial_year_id)->lockForUpdate()->firstOrFail();
            if ($year->is_closed) {
                throw new DomainException('Cannot post advance receipt voucher into a closed financial year.');
            }

            $party = Party::query()->whereKey($voucher->party_id)->firstOrFail();
            $company = Company::query()->whereKey(app(TenantContext::class)->id())->firstOrFail();

            $number = $this->numbers->next($year->id, $voucher->series);

            $partySnapshot = [
                'id' => $party->id,
                'name' => $party->name,
                'gstin' => $party->gstin,
                'address' => $party->address,
                'state_code' => $party->state_code,
            ];

            $supplierSnapshot = [
                'id' => $company->id,
                'name' => $company->name,
                'gstin' => $company->gstin,
                'address' => $company->address,
                'state_code' => $company->state_code,
            ];

            $voucher->fill([
                'number' => $number,
                'party_snapshot' => $partySnapshot,
                'supplier_snapshot' => $supplierSnapshot,
                'status' => 'posted',
            ])->save();

            Activity::create([
                'description' => 'Advance receipt voucher posted',
                'subject_type' => AdvanceReceiptVoucher::class,
                'subject_id' => $voucher->id,
                'causer_id' => $userId,
            ]);

            return $voucher->fresh(['party', 'receipt']);
        });
    }
}
