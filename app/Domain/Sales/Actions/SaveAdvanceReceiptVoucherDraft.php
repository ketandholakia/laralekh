<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Masters\Models\State;
use App\Domain\Sales\Models\AdvanceReceiptVoucher;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAdvanceReceiptVoucherDraft
{
    /**
     * Create or update a draft statutory Advance Receipt Voucher (CGST s. 31(3)(d), Rule 50).
     */
    public function handle(
        int $partyId,
        string $date,
        string $placeOfSupply,
        string $grossAmount,
        string $description,
        string $supplyType = 'services',
        bool $isRateDeterminable = true,
        bool $isInterstateDeterminable = true,
        string $taxRate = '18.00',
        ?int $receiptId = null,
        ?string $paymentReference = null,
        ?string $narration = null,
        ?int $voucherId = null,
        ?int $userId = null,
    ): AdvanceReceiptVoucher {
        $tenantId = app(TenantContext::class)->id();

        return DB::transaction(function () use (
            $partyId, $date, $placeOfSupply, $grossAmount, $description,
            $supplyType, $isRateDeterminable, $isInterstateDeterminable, $taxRate,
            $receiptId, $paymentReference, $narration, $voucherId, $userId
        ): AdvanceReceiptVoucher {
            $company = Company::query()->whereKey(app(TenantContext::class)->id())->sole();
            $party = Party::query()->whereKey($partyId)->lockForUpdate()->firstOrFail();

            $gross = Money::of($grossAmount);
            if ($gross->isZero() || $gross->isNegative()) {
                throw new DomainException('Gross advance amount must be positive.');
            }

            $day = Carbon::createFromFormat('Y-m-d', $date);
            if (! $day) {
                throw new DomainException('Invalid voucher date.');
            }

            $year = FinancialYear::query()
                ->where('starts_on', '<=', $date)
                ->where('ends_on', '>=', $date)
                ->lockForUpdate()
                ->first();

            if (! $year || $year->is_closed) {
                throw new DomainException('Voucher date must fall inside an open financial year.');
            }

            if (trim($description) === '') {
                throw new DomainException('Description of goods or services is required for statutory receipt vouchers.');
            }

            if (! preg_match('/^\d{2}$/', $placeOfSupply)) {
                throw new DomainException('Place of supply must be a valid 2-digit state code.');
            }

            // Receipt capacity validation if linked to an existing receipt
            $receipt = null;
            if ($receiptId !== null) {
                $receipt = Receipt::query()->whereKey($receiptId)->lockForUpdate()->firstOrFail();
                if ($receipt->party_id !== $partyId) {
                    throw new DomainException('Linked receipt must belong to the selected customer.');
                }
                if ($receipt->status !== 'posted') {
                    throw new DomainException('Linked receipt must be in posted status.');
                }
                $settlement = app(SettlementService::class);
                $available = $settlement->availableReceiptFunds($receipt);
                if ($gross->greaterThan($available)) {
                    throw new DomainException("Gross advance amount (₹{$gross}) exceeds available receipt funds (₹{$available}).");
                }
            }

            // Rule 50 Provisos and GST Calculations:
            // 1. If rate is not determinable, Rule 50 proviso (i) mandates 18% fallback.
            // 2. If supply is goods, Notif 66/2017-CT exempts advances from tax -> rate is 0%.
            // 3. If nature/POS is not determinable, Rule 50 proviso (ii) mandates inter-state supply (IGST).
            $effectiveRate = '0.00';
            if ($supplyType === 'services') {
                $effectiveRate = $isRateDeterminable ? $taxRate : '18.00';
            }

            $taxable = $gross;
            $zero = Money::of('0');
            $cgst = $sgst = $utgst = $igst = $cess = $zero;

            if (bccomp($effectiveRate, '0.00', 2) > 0) {
                // Back-calculate taxable value from inclusive gross
                $taxable = Money::rounded(bcdiv(bcmul((string) $gross, '100', 7), bcadd('100', $effectiveRate, 7), 7));
                $totalTax = $gross->subtract($taxable);

                $supplierState = $company->state_code;
                $isInterstate = (! $isInterstateDeterminable) || ($placeOfSupply !== $supplierState);

                if ($isInterstate) {
                    $igst = $totalTax;
                } else {
                    $cgst = Money::rounded(bcdiv((string) $totalTax, '2', 7));
                    $halfTax = $totalTax->subtract($cgst);

                    $posState = State::query()->where('code', $placeOfSupply)->first();
                    if ($posState?->uses_utgst) {
                        $utgst = $halfTax;
                    } else {
                        $sgst = $halfTax;
                    }
                }
            }

            $voucher = null;
            if ($voucherId !== null) {
                $voucher = AdvanceReceiptVoucher::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();
                if ($voucher->status !== 'draft') {
                    throw new DomainException('Only draft advance receipt vouchers can be modified.');
                }
            }

            $data = [
                'financial_year_id' => $year->id,
                'party_id' => $partyId,
                'receipt_id' => $receiptId,
                'series' => 'ARV',
                'date' => $date,
                'placeOfSupply' => $placeOfSupply,
                'place_of_supply' => $placeOfSupply,
                'supply_type' => $supplyType,
                'description' => $description,
                'is_rate_determinable' => $isRateDeterminable,
                'is_interstate_determinable' => $isInterstateDeterminable,
                'tax_rate' => $effectiveRate,
                'gross_amount' => $gross,
                'taxable_amount' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'utgst' => $utgst,
                'igst' => $igst,
                'cess' => $cess,
                'payment_reference' => $paymentReference,
                'narration' => $narration,
            ];

            unset($data['placeOfSupply']);

            if ($voucher === null) {
                $data['status'] = 'draft';
                $data['created_by'] = $userId;
                $voucher = AdvanceReceiptVoucher::create($data);
            } else {
                $voucher->fill($data)->save();
            }

            return $voucher;
        });
    }
}
