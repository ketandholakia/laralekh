<?php

declare(strict_types=1);

namespace App\Domain\Gst\Services;

use App\Domain\Gst\ValueObjects\GstBreakdown;
use App\Domain\Gst\ValueObjects\ResolvedSupply;
use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class GstCalculator
{
    public function calculate(
        Money $unitPrice,
        string $quantity,
        string $rate,
        ResolvedSupply $supply,
        ?Money $discount = null,
        string $category = 'taxable',
        bool $reverseCharge = false,
        string $regime = 'regular',
        string $zeroRatedMode = 'none',
        bool $zeroRatedEligible = false,
        bool $sezAuthorized = false,
        string $cessRate = '0',
    ): GstBreakdown {
        $discount ??= Money::of('0');
        if (! preg_match('/^\d{1,12}(?:\.\d{1,3})?$/D', $quantity) || bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('Quantity must be positive with at most three decimals.');
        }
        if (! preg_match('/^\d{1,3}(?:\.\d{1,3})?$/D', $rate) || bccomp($rate, '100', 3) > 0) {
            throw new InvalidArgumentException('Invalid GST percentage.');
        }
        if (! in_array($category, ['taxable', 'nil_rated', 'exempt', 'non_gst'], true) ||
            ! in_array($regime, ['regular', 'composition', 'unregistered'], true) ||
            ! in_array($zeroRatedMode, ['none', 'lut', 'with_payment'], true)) {
            throw new InvalidArgumentException('Invalid tax classification.');
        }
        if ($unitPrice->isNegative() || $discount->isNegative()) {
            throw new InvalidArgumentException('Negative sales amounts require a credit note.');
        }
        $gross = Money::rounded(bcmul((string) $unitPrice, $quantity, 5));
        $base = $gross->subtract($discount);
        if ($base->isNegative()) {
            throw new InvalidArgumentException('Discount exceeds line value.');
        }
        if ($category !== 'taxable' && bccomp($rate, '0', 3) !== 0) {
            throw new InvalidArgumentException('Exempt, nil-rated and non-GST lines require a zero rate.');
        }
        if ($regime === 'composition' && $supply->interState) {
            throw new InvalidArgumentException('Composition outward inter-state supplies are not supported.');
        }
        if ($supply->type === 'domestic' && $zeroRatedMode !== 'none') {
            throw new InvalidArgumentException('Domestic supplies cannot use zero-rated treatment.');
        }
        if ($supply->type !== 'domestic') {
            if (! $zeroRatedEligible || $zeroRatedMode === 'none' || $regime !== 'regular' ||
                ($supply->type === 'sez' && ! $sezAuthorized)) {
                throw new InvalidArgumentException('Export/SEZ treatment requires verified eligibility and an explicit route.');
            }
            if ($reverseCharge) {
                throw new InvalidArgumentException('Reverse charge with zero-rated treatment needs separate review.');
            }
        }
        if ($regime !== 'regular' && $reverseCharge) {
            throw new InvalidArgumentException('Outward reverse-charge classification needs separate review for this regime.');
        }
        $zero = Money::of('0');
        if (! preg_match('/^\d{1,3}(?:\.\d{1,3})?$/D', $cessRate) || bccomp($cessRate, '100', 3) > 0) {
            throw new InvalidArgumentException('Invalid cess percentage.');
        }
        $cgst = $sgst = $utgst = $igst = $cess = $recipientTax = $recipientCess = $zero;
        if ($category === 'taxable' && $regime === 'regular' && $zeroRatedMode !== 'lut') {
            if ($supply->interState) {
                $igst = Money::rounded(bcdiv(bcmul((string) $base, $rate, 5), '100', 7));
            } else {
                $cgst = Money::rounded(bcdiv(bcmul((string) $base, $rate, 5), '200', 7));
                if ($supply->usesUtgst) {
                    $utgst = $cgst;
                } else {
                    $sgst = $cgst;
                }
            }
            $cess = Money::rounded(bcdiv(bcmul((string) $base, $cessRate, 5), '100', 7));
        }
        $tax = $cgst->add($sgst)->add($utgst)->add($igst)->add($cess);
        if ($reverseCharge) {
            $recipientTax = $tax;
            $recipientCess = $cess;
            $cgst = $sgst = $utgst = $igst = $cess = $tax = $zero;
        }

        return new GstBreakdown($base, $cgst, $sgst, $utgst, $igst, $cess, $base->add($tax), $recipientTax, $recipientCess, $category);
    }

    public function roundStatutoryPayable(Money $amount): Money
    {
        $adjustment = $amount->isNegative() ? '-0.50' : '0.50';

        return Money::of(bcadd((string) $amount, $adjustment, 0));
    }
}
