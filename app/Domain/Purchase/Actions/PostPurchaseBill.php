<?php
declare(strict_types=1);
namespace App\Domain\Purchase\Actions;
use App\Domain\Accounting\Models\{Ledger, LedgerGroup, Voucher};
use App\Domain\Accounting\Services\{PostingService, VoucherNumberAllocator};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Gst\Services\{GstCalculator, PlaceOfSupplyResolver};
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Masters\Models\{Company, FinancialYear, Party};
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use DomainException;

final class PostPurchaseBill
{
    public function __construct(private GstCalculator $gst, private PlaceOfSupplyResolver $place, private VoucherNumberAllocator $numbers, private PostingService $posting) {}
    public function handle(int $billId): PurchaseBill
    {
        return DB::transaction(function () use ($billId) {
            $bill = PurchaseBill::whereKey($billId)->lockForUpdate()->sole();
            if ($bill->status !== 'draft' || $bill->voucher_id !== null) throw new DomainException('Purchase bill is already posted.');
            $year = FinancialYear::whereKey($bill->financial_year_id)->lockForUpdate()->sole();
            if ($year->is_closed || $bill->date->lt($year->starts_on) || $bill->date->gt($year->ends_on)) throw new DomainException('Bill date must be inside an open financial year.');
            $company = Company::whereKey(app(TenantContext::class)->id())->lockForUpdate()->sole();
            $supplier = Party::whereKey($bill->party_id)->lockForUpdate()->sole();
            $payable = $this->ledger($supplier->ledger_id, 'liability');
            if (!$supplier->state_code) throw new DomainException('Supplier state is required to determine place of supply.');
            $supply = $this->place->resolve($supplier->state_code, $company->state_code, $bill->supply_type);
            $items = $bill->items()->orderBy('position')->lockForUpdate()->get();
            if ($items->isEmpty()) throw new DomainException('Cannot post an empty purchase bill.');
            $taxes = ['cgst','sgst','utgst','igst','cess']; $totals = array_fill_keys(array_merge(['taxable_amount','total'], $taxes), Money::of('0'));
            $debits = []; $liabilities = []; $stock = [];
            foreach ($items as $line) {
                $ledger = Ledger::whereKey($line->purchase_ledger_id)->lockForUpdate()->sole();
                if (!in_array(LedgerGroup::whereKey($ledger->ledger_group_id)->sole()->nature, ['expense', 'asset'], true)) throw new DomainException('Purchase ledger must be an expense or inventory ledger.');
                $rate = null; $rateValue = '0'; $cessRate = '0';
                if ($line->tax_category === 'taxable') {
                    $rate = TaxRate::whereKey($line->tax_rate_id)->lockForUpdate()->first();
                    if (!$rate || !$rate->is_verified || $bill->date->lt($rate->effective_from) || ($rate->effective_to && $bill->date->gt($rate->effective_to))) throw new DomainException('A verified GST rate effective on the bill date is required.');
                    $rateValue = $rate->rate; $cessRate = $rate->cess_rate ?? '0';
                }
                $result = $this->gst->calculate(unitPrice: $line->unit_price, quantity: $line->quantity, rate: $rateValue,
                    supply: $supply, discount: $line->discount, category: $line->tax_category,
                    regime: $company->gst_regime, cessRate: $cessRate);
                $line->fill($result->amounts())->save();
                $capitalized = $result->taxableAmount;
                $debits[$ledger->id] = ($debits[$ledger->id] ?? Money::of('0'))->add($capitalized);
                foreach ($taxes as $tax) {
                    $amount = $result->$tax;
                    if ($amount->isZero()) continue;
                    if ($line->itc_eligibility === 'eligible') {
                        $inputLedger = Ledger::where('code', 'INPUT_'.strtoupper($tax))->sole(); $this->ledger($inputLedger->id, 'asset');
                        $debits[$inputLedger->id] = ($debits[$inputLedger->id] ?? Money::of('0'))->add($amount);
                    } else $debits[$ledger->id] = $debits[$ledger->id]->add($amount);
                    if ($bill->reverse_charge) {
                        $rcm = Ledger::where('code', 'RCM_PAYABLE')->sole();
                        $liabilities[$rcm->id] = ($liabilities[$rcm->id] ?? Money::of('0'))->add($amount);
                    }
                }
                foreach ($result->amounts() as $field => $amount) $totals[$field] = $totals[$field]->add($amount);
                if ($line->item_id !== null && $line->item?->tracks_stock) $stock[] = $line;
            }
            $supplierDue = $bill->reverse_charge ? $totals['taxable_amount'] : $totals['total'];
            $entries = [];
            foreach ($debits as $id => $amount) $entries[] = ['ledger_id' => $id, 'debit' => $amount, 'credit' => Money::of('0')];
            $entries[] = ['ledger_id' => $payable->id, 'debit' => Money::of('0'), 'credit' => $supplierDue];
            foreach ($liabilities as $id => $amount) $entries[] = ['ledger_id' => $id, 'debit' => Money::of('0'), 'credit' => $amount];
            $this->posting->assertBalanced($entries);
            $number = $this->numbers->next($year->id, 'PB');
            $voucher = Voucher::create(['financial_year_id' => $year->id, 'type' => 'purchase', 'date' => $bill->date,
                'series' => 'PB', 'number' => $number, 'narration' => 'Purchase bill '.$bill->supplier_bill_number]);
            foreach ($entries as $position => $entry) $voucher->lines()->create($entry + ['financial_year_id' => $year->id, 'position' => $position + 1]);
            $this->posting->assertBalanced($voucher->lines()->get()->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all());
            // Stock movements are completed after voucher creation to persist the real voucher reference.
            foreach ($stock as $line) StockMovement::create(['financial_year_id' => $year->id, 'item_id' => $line->item_id,
                'voucher_id' => $voucher->id, 'date' => $bill->date->format('Y-m-d'), 'quantity' => $line->quantity,
                'unit_cost' => $line->unit_price, 'reference' => $bill->supplier_bill_number]);
            $bill->fill($totals + ['voucher_id' => $voucher->id, 'number' => $number, 'status' => 'posted',
                'supplier_snapshot' => $supplier->only(['name','gstin','state_code','address','registration_type'])])->save();
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);
            return $bill->fresh(['items','party']);
        }, 3);
    }
    private function ledger(int $id, string $nature): Ledger
    {
        $ledger = Ledger::whereKey($id)->lockForUpdate()->sole();
        if (LedgerGroup::whereKey($ledger->ledger_group_id)->sole()->nature !== $nature) throw new DomainException('Ledger has an inappropriate accounting nature.');
        return $ledger;
    }
}
