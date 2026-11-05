<?php
declare(strict_types=1);
namespace App\Domain\Sales\Services;

use App\Domain\Gst\Services\{GstCalculator, PlaceOfSupplyResolver};
use App\Domain\Sales\Models\{Invoice, SalesCorrectionLine};
use App\Domain\Shared\ValueObjects\Money;
use DomainException;

final class CorrectionCalculator
{
    public const TAXES = ['cgst', 'sgst', 'utgst', 'igst', 'recipient_tax'];
    public const TOTALS = ['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'recipient_tax', 'total'];

    public function remaining(Invoice $invoice): array
    {
        $used = [];
        // Sum decimal values in PHP; SQLite numeric SUM would introduce floats.
        $credits = SalesCorrectionLine::where('invoice_id', $invoice->id)
            ->whereHas('correction', fn ($q) => $q->where('status', 'posted')->where('kind', 'credit'))->get();
        foreach ($credits as $line) {
            $id = $line->invoice_item_id;
            $used[$id] ??= ['amount' => Money::of('0'), 'quantity' => '0.000'];
            $used[$id]['amount'] = $used[$id]['amount']->add($line->taxable_amount);
            $used[$id]['quantity'] = bcadd($used[$id]['quantity'], $line->quantity, 3);
        }
        $result = [];
        foreach ($invoice->items()->orderBy('position')->get() as $item) {
            $amount = $used[$item->id]['amount'] ?? Money::of('0');
            $quantity = $used[$item->id]['quantity'] ?? '0.000';
            $result[$item->id] = ['amount' => (string) $item->taxable_amount->subtract($amount),
                'quantity' => bcsub($item->quantity, $quantity, 3),
                'used_amount' => (string) $amount, 'description' => $item->description];
        }
        return $result;
    }

    public function calculate(Invoice $invoice, string $kind, string $treatment, array $input): array
    {
        if (!$input || count($input) > 500) { throw new DomainException('Select between one and 500 correction lines.'); }
        $items = $invoice->items()->orderBy('position')->get()->keyBy('id');
        $remaining = $this->remaining($invoice);
        $rows = []; $seen = [];
        $totals = array_fill_keys(self::TOTALS, Money::of('0'));
        foreach ($input as $position => $line) {
            $id = filter_var($line['invoice_item_id'] ?? null, FILTER_VALIDATE_INT);
            $item = $items->get($id);
            if (!$item || isset($seen[$id])) { throw new DomainException('Each selected line must belong to the original invoice and appear once.'); }
            $seen[$id] = true;
            $amount = Money::of($line['amount'] ?? null);
            $quantity = $line['quantity'] ?? '0';
            if (!is_string($quantity) || !preg_match('/^\d{1,12}(?:\.\d{1,3})?$/D', $quantity)) {
                throw new DomainException('Affected quantity must be a non-negative decimal with up to three places.');
            }
            if ($amount->isNegative() || $amount->isZero()) { throw new DomainException('Correction amount must be positive.'); }
            if ($kind === 'credit' && (bccomp((string) $amount, $remaining[$id]['amount'], 2) > 0 ||
                bccomp($quantity, $remaining[$id]['quantity'], 3) > 0)) {
                throw new DomainException('Credit exceeds the remaining original amount or quantity.');
            }
            $row = ['invoice_item_id' => $id, 'position' => $position + 1, 'description' => $item->description,
                'hsn_sac' => $item->hsn_sac, 'quantity' => $quantity, 'rate' => $item->rate,
                'sales_ledger_id' => $item->sales_ledger_id, 'taxable_amount' => $amount];
            $basis = [];
            if ($kind === 'credit') {
                foreach (self::TAXES as $tax) {
                    // Allocate cumulative original rounded tax, then subtract the preceding allocation.
                    $before = $remaining[$id]['used_amount'];
                    $after = bcadd($before, (string) $amount, 2);
                    $originalBase = (string) $item->taxable_amount;
                    $previous = Money::rounded(bcdiv(bcmul((string) $item->$tax, $before, 4), $originalBase, 12));
                    $cumulative = Money::rounded(bcdiv(bcmul((string) $item->$tax, $after, 4), $originalBase, 12));
                    $basis[$tax] = $cumulative->subtract($previous);
                }
            } else {
                $supply = app(PlaceOfSupplyResolver::class)->resolve($invoice->supplier_snapshot['state_code'], $invoice->place_of_supply, $invoice->supply_type);
                $tax = app(GstCalculator::class)->calculate(unitPrice: $amount, quantity: '1', rate: $item->rate,
                    supply: $supply, category: $item->tax_category, reverseCharge: $invoice->reverse_charge,
                    regime: $invoice->supplier_snapshot['gst_regime'], zeroRatedMode: $invoice->zero_rated_mode,
                    zeroRatedEligible: $invoice->zero_rated_eligible, sezAuthorized: $invoice->sez_authorized);
                $basis = ['cgst' => $tax->cgst, 'sgst' => $tax->sgst, 'utgst' => $tax->utgst, 'igst' => $tax->igst, 'recipient_tax' => $tax->recipientTax];
            }
            $total = $amount;
            foreach (self::TAXES as $tax) {
                $row['basis_'.$tax] = $basis[$tax];
                $row[$tax] = $treatment === 'gst' ? $basis[$tax] : Money::of('0');
                if ($tax !== 'recipient_tax') { $total = $total->add($row[$tax]); }
            }
            $row['total'] = $total;
            foreach (self::TOTALS as $field) { $totals[$field] = $totals[$field]->add($row[$field]); }
            $rows[] = $row;
        }
        return ['lines' => $rows, 'totals' => $totals, 'remaining' => $remaining];
    }
}

