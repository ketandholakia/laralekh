<?php
use App\Domain\Accounting\Models\{Ledger,LedgerGroup};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{FinancialYear,Party};
use App\Domain\Sales\Models\Invoice;
use App\Domain\Purchase\Models\PurchaseBill;

function returnSalesFixture(array $invoiceOverrides=[],array $lineOverrides=[]):Invoice
{
    (new Database\Seeders\ChartOfAccountsSeeder)->run();
    $year=FinancialYear::firstOrCreate(['name'=>'2026-27'],['starts_on'=>'2026-04-01','ends_on'=>'2027-03-31']);
    $ledger=Ledger::firstOrCreate(['code'=>'RETURN_CUSTOMER'],['name'=>'Return customer','ledger_group_id'=>LedgerGroup::where('code','RECEIVABLES')->sole()->id]);
    $party=Party::firstOrCreate(['ledger_id'=>$ledger->id],['name'=>'Return customer','state_code'=>'27']);
    $item=Item::firstOrCreate(['sku'=>'RETURN_ITEM'],['name'=>'Return item','hsn_sac'=>'8471']);
    $rate=TaxRate::firstOrCreate(['code'=>'RETURN_18'],['rate'=>'18','effective_from'=>'2026-04-01','is_verified'=>true]);
    $invoice=Invoice::create(array_merge(['financial_year_id'=>$year->id,'party_id'=>$party->id,'date'=>'2026-11-06','place_of_supply'=>'27','series'=>'SI'],$invoiceOverrides));
    $invoice->items()->create(array_merge(['financial_year_id'=>$year->id,'item_id'=>$item->id,'sales_ledger_id'=>Ledger::where('code','SALES')->sole()->id,
        'tax_rate_id'=>$rate->id,'position'=>1,'description'=>'Return item','hsn_sac'=>'8471','quantity'=>'1','unit_price'=>'1000.00','discount'=>'0.00'],$lineOverrides));
    return $invoice;
}

function returnPurchaseFixture(array $billOverrides=[]):PurchaseBill
{
    (new Database\Seeders\ChartOfAccountsSeeder)->run();
    $year=FinancialYear::firstOrCreate(['name'=>'2026-27'],['starts_on'=>'2026-04-01','ends_on'=>'2027-03-31']);
    $ledger=Ledger::firstOrCreate(['code'=>'RETURN_SUPPLIER'],['name'=>'Return supplier','ledger_group_id'=>LedgerGroup::where('code','PAYABLES')->sole()->id]);
    $party=Party::firstOrCreate(['ledger_id'=>$ledger->id],['name'=>'Return supplier','state_code'=>'27']);
    $item=Item::firstOrCreate(['sku'=>'RETURN_PURCHASE_ITEM'],['name'=>'Return purchase item','hsn_sac'=>'8471']);
    $rate=TaxRate::firstOrCreate(['code'=>'RETURN_PURCHASE_18'],['rate'=>'18','effective_from'=>'2026-04-01','is_verified'=>true]);
    $bill=PurchaseBill::create(array_merge(['financial_year_id'=>$year->id,'party_id'=>$party->id,'supplier_bill_number'=>'RET-PUR-1',
        'supplier_bill_date'=>'2026-11-06','date'=>'2026-11-06','place_of_supply'=>'27','series'=>'PB'],$billOverrides));
    $bill->items()->create(['financial_year_id'=>$year->id,'item_id'=>$item->id,'purchase_ledger_id'=>Ledger::where('code','PURCHASES')->sole()->id,
        'tax_rate_id'=>$rate->id,'position'=>1,'description'=>'Return purchase item','hsn_sac'=>'8471','quantity'=>'1','unit_price'=>'1000.00','discount'=>'0.00']);
    return $bill;
}
