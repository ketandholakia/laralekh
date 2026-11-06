<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Gst\Services\GstCalculator;
use App\Domain\Gst\Services\PlaceOfSupplyResolver;
use App\Domain\Gst\ValueObjects\GstBreakdown;
use App\Domain\Masters\Models\Company;
use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;

// Live totals for the invoice editor. It mirrors PostingService's inputs exactly
// (same resolver rule and calculator) so the preview can never disagree with the
// posted result; it persists nothing and validates nothing by itself — callers
// translate InvalidArgumentException into user-facing validation errors.
final class InvoicePreview
{
    public function __construct(
        private GstCalculator $calculator,
        private PlaceOfSupplyResolver $placeOfSupply,
    ) {}

    /**
     * @param  iterable<array{unit_price:string, quantity:string, discount:string, rate:string, tax_category:string}>  $lines
     * @return array{lines: array<int, array{taxable:string, cgst:string, sgst:string, utgst:string, igst:string, total:string}>,
     *     totals: array{taxable:string, cgst:string, sgst:string, utgst:string, igst:string, total:string, recipient_tax:string}}
     */
    public function totals(
        Company $company,
        string $invoiceDate,
        string $supplyType,
        string $placeOfSupply,
        bool $reverseCharge,
        iterable $lines,
        string $zeroRatedMode = 'none',
        bool $zeroRatedEligible = false,
        bool $sezAuthorized = false,
    ): array {
        $supply = $this->placeOfSupply->resolve($company->state_code, $placeOfSupply, $supplyType);
        $lineBreakup = [];
        $sums = array_fill_keys(['taxable', 'cgst', 'sgst', 'utgst', 'igst', 'cess', 'total', 'recipient_tax'], Money::of('0'));
        foreach ($lines as $line) {
            $breakdown = $this->calculator->calculate(
                unitPrice: Money::of($line['unit_price']),
                quantity: $line['quantity'],
                rate: $line['rate'],
                supply: $supply,
                discount: Money::of($line['discount']),
                category: $line['tax_category'],
                reverseCharge: $reverseCharge,
                regime: $company->gst_regime,
                zeroRatedMode: $zeroRatedMode,
                zeroRatedEligible: $zeroRatedEligible,
                sezAuthorized: $sezAuthorized,
                cessRate: $line['cess_rate'] ?? '0',
            );
            $lineBreakup[] = $this->stringify($breakdown);
            foreach ($sums as $field => $running) {
                $sums[$field] = $running->add(match ($field) {
                    'taxable' => $breakdown->taxableAmount,
                    'cgst' => $breakdown->cgst,
                    'sgst' => $breakdown->sgst,
                    'utgst' => $breakdown->utgst,
                    'igst' => $breakdown->igst,
                    'cess' => $breakdown->cess,
                    'total' => $breakdown->total,
                    default => $breakdown->recipientTax,
                });
            }
        }

        return ['lines' => $lineBreakup, 'totals' => array_map(fn (Money $m): string => (string) $m, $sums)];
    }

    /** @return array{taxable:string, cgst:string, sgst:string, utgst:string, igst:string, total:string} */
    public function breakup(GstBreakdown $breakdown): array
    {
        return $this->stringify($breakdown);
    }

    private function stringify(GstBreakdown $b): array
    {
        return [
            'taxable' => (string) $b->taxableAmount,
            'cgst' => (string) $b->cgst,
            'sgst' => (string) $b->sgst,
            'utgst' => (string) $b->utgst,
            'igst' => (string) $b->igst,
            'cess' => (string) $b->cess,
            'total' => (string) $b->total,
        ];
    }
}
