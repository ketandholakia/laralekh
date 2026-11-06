<?php
namespace Database\Seeders;
use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'ASSETS' => ['Assets', 'asset', null], 'LIABILITIES' => ['Liabilities', 'liability', null],
            'EQUITY' => ['Equity', 'equity', null], 'INCOME' => ['Income', 'income', null],
            'EXPENSES' => ['Expenses', 'expense', null],
            'CASH_BANK' => ['Cash and bank', 'asset', 'ASSETS'],
            'RECEIVABLES' => ['Sundry debtors', 'asset', 'ASSETS'],
            'INVENTORY' => ['Inventory', 'asset', 'ASSETS'],
            'INPUT_GST' => ['Input GST', 'asset', 'ASSETS'],
            'PAYABLES' => ['Sundry creditors', 'liability', 'LIABILITIES'],
            'OUTPUT_GST' => ['Output GST', 'liability', 'LIABILITIES'],
            'SALES' => ['Sales accounts', 'income', 'INCOME'],
            'PURCHASES' => ['Purchase accounts', 'expense', 'EXPENSES'],
        ];
        foreach ($groups as $code => [$name, $nature, $parent]) {
            LedgerGroup::firstOrCreate(['code' => $code], [
                'name' => $name, 'nature' => $nature,
                'parent_id' => $parent ? LedgerGroup::where('code', $parent)->sole()->id : null,
            ]);
        }
        $ledgers = [
            'CASH' => ['Cash', 'CASH_BANK'], 'BANK' => ['Bank', 'CASH_BANK'],
            'SALES' => ['Sales', 'SALES'], 'PURCHASES' => ['Purchases', 'PURCHASES'],
            'INVENTORY' => ['Inventory', 'INVENTORY'], 'CAPITAL' => ['Owner capital', 'EQUITY'],
            'ROUND_OFF' => ['Rounding adjustment', 'EXPENSES'],
        ];
        foreach (['CGST', 'SGST', 'UTGST', 'IGST'] as $tax) {
            $ledgers['OUTPUT_'.$tax] = ['Output '.$tax, 'OUTPUT_GST'];
            $ledgers['INPUT_'.$tax] = ['Input '.$tax, 'INPUT_GST'];
        }
        $ledgers['OUTPUT_CESS'] = ['Output cess', 'OUTPUT_GST'];
        $ledgers['INPUT_CESS'] = ['Input cess', 'INPUT_GST'];
        foreach ($ledgers as $code => [$name, $group]) {
            Ledger::firstOrCreate(['code' => $code], [
                'name' => $name, 'ledger_group_id' => LedgerGroup::where('code', $group)->sole()->id,
            ]);
        }
    }
}
