<?php
declare(strict_types=1);
namespace App\Domain\Purchase\Actions;
use App\Domain\Accounting\Models\{Ledger, LedgerGroup};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{Company, FinancialYear, Party};
use App\Domain\Purchase\Models\{PurchaseBill, PurchaseBillItem};
use App\Domain\Shared\Tenancy\{CompanySession, TenantContext};
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavePurchaseBillDraft
{
    public function handle(?int $billId, array $input, array $lines): PurchaseBill
    {
        $year = FinancialYear::whereKey(app(CompanySession::class)->financialYearId())->firstOrFail();
        $company = Company::whereKey(app(TenantContext::class)->id())->firstOrFail();
        $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', (string) ($input['date'] ?? ''));
        if (!$date || $year->is_closed || $date->lt($year->starts_on) || $date->gt($year->ends_on)) {
            throw ValidationException::withMessages(['date' => 'Bill date must be inside the selected open financial year.']);
        }
        $party = Party::whereKey((int) ($input['party_id'] ?? 0))->firstOrFail();
        if ($lines === [] || count($lines) > 500) throw ValidationException::withMessages(['lines' => 'Add between one and 500 lines.']);
        $canonical = [];
        foreach ($lines as $line) {
            $item = empty($line['item_id']) ? null : Item::whereKey((int) $line['item_id'])->firstOrFail();
            $ledger = Ledger::whereKey((int) ($line['purchase_ledger_id'] ?? 0))->firstOrFail();
            if (!in_array(LedgerGroup::whereKey($ledger->ledger_group_id)->sole()->nature, ['expense', 'asset'], true)) throw ValidationException::withMessages(['lines' => 'Purchase ledger must be an expense or inventory ledger.']);
            $quantity = (string) ($line['quantity'] ?? '1');
            if (!preg_match('/^\d{1,12}(?:\.\d{1,3})?$/D', $quantity) || bccomp($quantity, '0', 3) <= 0) throw ValidationException::withMessages(['lines' => 'Quantity must be positive.']);
            $category = (string) ($line['tax_category'] ?? 'taxable'); $rateId = $line['tax_rate_id'] ?? null; $rate = null;
            if ($category === 'taxable') {
                $rate = TaxRate::whereKey((int) $rateId)->firstOrFail();
                if (!$rate->is_verified || $date->lt($rate->effective_from) || ($rate->effective_to && $date->gt($rate->effective_to))) throw ValidationException::withMessages(['lines' => 'A verified effective GST rate is required.']);
            } else $rateId = null;
            $canonical[] = ['item_id' => $item?->id, 'purchase_ledger_id' => $ledger->id, 'tax_rate_id' => $rateId,
                'description' => (string) ($line['description'] ?? $item?->name ?? 'Expense'), 'hsn_sac' => (string) ($line['hsn_sac'] ?? $item?->hsn_sac ?? ''),
                'quantity' => $quantity, 'unit_price' => Money::of((string) ($line['unit_price'] ?? '0')),
                'discount' => Money::of((string) ($line['discount'] ?? '0')), 'rate' => $rate?->rate ?? '0',
                'tax_category' => $category, 'itc_eligibility' => $line['itc_eligibility'] ?? 'eligible',
                'itc_reason' => $line['itc_reason'] ?? null];
        }
        return DB::transaction(function () use ($billId, $input, $canonical, $year, $party, $company) {
            $bill = $billId === null ? new PurchaseBill : PurchaseBill::whereKey($billId)->lockForUpdate()->firstOrFail();
            if ($bill->exists && $bill->status !== 'draft') throw ValidationException::withMessages(['bill' => 'Posted bills are immutable; use a purchase debit note.']);
            $bill->fill(['financial_year_id' => $year->id, 'party_id' => $party->id,
                'supplier_bill_number' => trim((string) ($input['supplier_bill_number'] ?? '')),
                'supplier_bill_date' => $input['supplier_bill_date'] ?? $input['date'], 'date' => $input['date'],
                'place_of_supply' => $company->state_code, 'supply_type' => $input['supply_type'] ?? 'domestic',
                'reverse_charge' => (bool) ($input['reverse_charge'] ?? false), 'series' => 'PB'])->save();
            $bill->items()->delete();
            foreach ($canonical as $position => $row) $bill->items()->create($row + ['financial_year_id' => $year->id, 'position' => $position + 1]);
            return $bill->refresh();
        });
    }
}
