<?php
use App\Domain\Accounting\Models\{Ledger,LedgerGroup,Voucher};
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{FinancialYear,Party};
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Purchase\Models\{PurchaseBill,PurchaseBillItem};
use App\Domain\Shared\Tenancy\TenantContext;
use Database\Seeders\{ChartOfAccountsSeeder,StateSeeder};
use Illuminate\Database\QueryException;

function purchaseFixture(array $billData = [], array $lineData = []): PurchaseBill
{
    (new ChartOfAccountsSeeder)->run();
    $year=FinancialYear::firstOrCreate(['name'=>'2026-27'],['starts_on'=>'2026-04-01','ends_on'=>'2027-03-31']);
    $ledger=Ledger::firstOrCreate(['code'=>'SUPPLIER'],['name'=>'Supplier','ledger_group_id'=>LedgerGroup::where('code','PAYABLES')->sole()->id]);
    $party=Party::firstOrCreate(['ledger_id'=>$ledger->id],['name'=>'Supplier','state_code'=>'27']);
    $item=Item::firstOrCreate(['sku'=>'PURCHASE-STOCK'],['name'=>'Purchase stock','hsn_sac'=>'1234','tracks_stock'=>true]);
    $rate=TaxRate::where('code','PURCHASE_18')->whereDate('effective_from','2026-04-01')->first();
    $rate ??= TaxRate::create(['code'=>'PURCHASE_18','effective_from'=>'2026-04-01','rate'=>'18','is_verified'=>true]);
    $bill=PurchaseBill::create($billData+['financial_year_id'=>$year->id,'party_id'=>$party->id,
        'supplier_bill_number'=>'SUP-1','supplier_bill_date'=>'2026-11-05','date'=>'2026-11-05','place_of_supply'=>'27']);
    $bill->items()->create($lineData+['financial_year_id'=>$year->id,'item_id'=>$item->id,
        'purchase_ledger_id'=>Ledger::where('code','PURCHASES')->sole()->id,'tax_rate_id'=>$rate->id,
        'position'=>1,'description'=>'Stock','hsn_sac'=>'1234','quantity'=>'1','unit_price'=>'1000.00','discount'=>'0.00']);
    return $bill;
}

it('posts a balanced bill with eligible and blocked ITC and records stock', function () {
    (new StateSeeder)->run(); $company=companyFixture();
    app(TenantContext::class)->run($company->id,function(){
        $bill=purchaseFixture();
        $bill->items()->create(['financial_year_id'=>$bill->financial_year_id,'item_id'=>null,
            'purchase_ledger_id'=>Ledger::where('code','PURCHASES')->sole()->id,'tax_rate_id'=>TaxRate::where('code','PURCHASE_18')->sole()->id,
            'position'=>2,'description'=>'Blocked expense','hsn_sac'=>'','quantity'=>'1','unit_price'=>'100.00','discount'=>'0.00',
            'itc_eligibility'=>'blocked','itc_reason'=>'Section 17(5) review']);
        app(PostPurchaseBill::class)->handle($bill->id); $voucher=$bill->fresh('voucher.lines.ledger')->voucher;
        $codes=$voucher->lines->load('ledger')->mapWithKeys(fn($line)=>[$line->ledger->code=>[(string)$line->debit,(string)$line->credit]])->all();
        expect($codes['INPUT_CGST'][0])->toBe('90.00')->and($codes['INPUT_SGST'][0])->toBe('90.00')
            ->and($codes['PURCHASES'][0])->toBe('1118.00')->and($voucher->status)->toBe('posted')
            ->and(App\Domain\Inventory\Models\StockMovement::count())->toBe(1);
        app(PostingService::class)->assertBalanced($voucher->lines->map(fn($l)=>['debit'=>$l->debit,'credit'=>$l->credit])->all());
    });
});

it('posts reverse charge to a dedicated payable ledger', function () {
    (new StateSeeder)->run(); $company=companyFixture();
    app(TenantContext::class)->run($company->id,function(){
        $bill=purchaseFixture(['reverse_charge'=>true]); app(PostPurchaseBill::class)->handle($bill->id); $voucher=$bill->fresh('voucher.lines.ledger')->voucher;
        $lines=$voucher->lines->load('ledger');
        expect((string)$lines->first(fn($l)=>$l->ledger->code==='SUPPLIER')->credit)->toBe('1000.00')
            ->and((string)$lines->first(fn($l)=>$l->ledger->code==='RCM_PAYABLE')->credit)->toBe('180.00')
            ->and((string)$lines->first(fn($l)=>$l->ledger->code==='INPUT_CGST')->debit)->toBe('90.00');
    });
});

it('rejects duplicate supplier document numbers within a year', function () {
    (new StateSeeder)->run(); $company=companyFixture();
    app(TenantContext::class)->run($company->id,function(){ $bill=purchaseFixture();
        expect(fn()=>PurchaseBill::create($bill->only(['financial_year_id','party_id','supplier_bill_number','supplier_bill_date','date','place_of_supply'])))->toThrow(QueryException::class);
    });
});

it('rejects closed financial years and unverified rates', function ($case) {
    (new StateSeeder)->run(); $company=companyFixture();
    app(TenantContext::class)->run($company->id,function()use($case){
        $bill=purchaseFixture();
        if($case==='closed') FinancialYear::whereKey($bill->financial_year_id)->update(['is_closed'=>true]);
        elseif($case==='unverified') TaxRate::where('code','PURCHASE_18')->update(['is_verified'=>false]);
        else TaxRate::where('code','PURCHASE_18')->update(['effective_to'=>'2026-10-31']);
        expect(fn()=>app(PostPurchaseBill::class)->handle($bill->id))->toThrow(DomainException::class);
        expect(Voucher::count())->toBe(0);
    });
})->with(['closed','unverified','expired']);

it('does not allow purchase bill IDs to cross tenant boundaries', function () {
    (new StateSeeder)->run(); $first=companyFixture('Supplier A'); $second=companyFixture('Supplier B');
    $bill=app(TenantContext::class)->run($first->id,fn()=>purchaseFixture());
    app(TenantContext::class)->run($second->id,fn()=>expect(fn()=>app(PostPurchaseBill::class)->handle($bill->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class));
});

it('allocates unique sequential purchase voucher numbers through the shared locked series allocator', function () {
    (new StateSeeder)->run(); $company=companyFixture();
    app(TenantContext::class)->run($company->id,function(){
        $one=purchaseFixture(); $two=purchaseFixture(['supplier_bill_number'=>'SUP-2']);
        expect(app(PostPurchaseBill::class)->handle($one->id)->number)->toBe(1)
            ->and(app(PostPurchaseBill::class)->handle($two->id)->number)->toBe(2);
    });
});
