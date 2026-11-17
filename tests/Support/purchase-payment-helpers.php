<?php

// Shared supplier-payment fixtures (LL-P1-001/002). Names are distinct from
// the purchase test fixtures so both files can coexist in one suite run.

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use Database\Seeders\ChartOfAccountsSeeder;

function supplierBillFixture(array $billData = [], array $lineData = []): PurchaseBill
{
    static $sequence = 0;
    $sequence++;
    (new ChartOfAccountsSeeder)->run();
    $year = FinancialYear::firstOrCreate(['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    $ledger = Ledger::firstOrCreate(['code' => 'SUPPLIER'], ['name' => 'Supplier', 'ledger_group_id' => LedgerGroup::where('code', 'PAYABLES')->sole()->id]);
    $party = Party::firstOrCreate(['ledger_id' => $ledger->id], ['name' => 'Supplier', 'state_code' => '27']);
    $rate = TaxRate::where('code', 'PURCHASE_18')->first()
        ?? TaxRate::create(['code' => 'PURCHASE_18', 'effective_from' => '2026-04-01', 'rate' => '18', 'is_verified' => true]);
    $bill = PurchaseBill::create($billData + ['financial_year_id' => $year->id, 'party_id' => $party->id,
        'supplier_bill_number' => 'SUP-'.$sequence, 'supplier_bill_date' => '2026-11-05', 'date' => '2026-11-05', 'place_of_supply' => '27']);
    $bill->items()->create($lineData + ['financial_year_id' => $year->id, 'item_id' => Item::firstOrCreate(
            ['sku' => 'PURCHASE-STOCK'], ['name' => 'Purchase stock', 'hsn_sac' => '1234', 'tracks_stock' => true])->id,
        'purchase_ledger_id' => Ledger::where('code', 'PURCHASES')->sole()->id, 'tax_rate_id' => $rate->id,
        'position' => 1, 'description' => 'Stock', 'hsn_sac' => '1234', 'quantity' => '1',
        'unit_price' => '1000.00', 'discount' => '0.00']);

    return $bill;
}

function supplierPaymentDraft(Party $party, string $amount, array $overrides = []): SupplierPayment
{
    $year = FinancialYear::firstOrCreate(['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
    (new ChartOfAccountsSeeder)->run();

    return SupplierPayment::create($overrides + [
        'financial_year_id' => $year->id, 'party_id' => $party->id,
        'ledger_id' => cashLedger()->id, 'date' => '2026-11-06', 'amount' => $amount,
    ]);
}
