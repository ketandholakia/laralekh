<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Read-only financial reports computed from POSTED voucher movements only.
// No locking and no writes: decisions still belong to the posting and
// settlement services. Money columns are decimal on MySQL and text on
// SQLite, so sums are computed in application code with exact decimal
// arithmetic (same convention as SettlementService::ledgerBalance). Every
// query inherits the active tenant scope; reports never cross companies.
final class FinancialReports
{
    /** @return list<array{ledger_id:int, debit:string, credit:string}> */
    private function movements(?string $from, ?string $until): array
    {
        $query = DB::table('voucher_lines')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')
            ->where('vouchers.status', 'posted');
        if ($from !== null) {
            $query->where('vouchers.date', '>=', $from);
        }
        if ($until !== null) {
            // Date columns store midnight timestamps on SQLite; compare below
            // the next day so the inclusive day is fully covered on both engines.
            $query->where('vouchers.date', '<', Carbon::parse($until)->addDay()->toDateString());
        }

        return $query->orderBy('voucher_lines.id')
            ->get(['voucher_lines.ledger_id', 'voucher_lines.debit', 'voucher_lines.credit'])
            ->map(fn ($r) => ['ledger_id' => (int) $r->ledger_id, 'debit' => (string) $r->debit, 'credit' => (string) $r->credit])
            ->all();
    }

    /** @return array<string, \Illuminate\Support\Collection> ledger_id => movements */
    private function movementsByLedger(?string $from, ?string $until): \Illuminate\Support\Collection
    {
        return collect($this->movements($from, $until))->groupBy('ledger_id');
    }

    /** @return array{0: Money, 1: Money} [debit, credit] sums of one ledger's movements */
    private function sum($group): array
    {
        $debit = Money::of('0');
        $credit = Money::of('0');
        foreach ($group as $m) {
            $debit = $debit->add(Money::of($m['debit']));
            $credit = $credit->add(Money::of($m['credit']));
        }

        return [$debit, $credit];
    }

    /**
     * Trial balance: per-ledger opening (before $from), period movement and
     * closing (through $until) balances, with total debit/credit columns per
     * stage. A balanced ledger set keeps total debits equal to total credits
     * in every column.
     *
     * @return array{rows: list<array<string, string>>, totals: array{opening_debit: Money, opening_credit: Money, debit: Money, credit: Money, closing_debit: Money, closing_credit: Money}}
     */
    public function trialBalance(?string $from = null, ?string $until = null): array
    {
        $ledgers = Ledger::query()->with('group')->orderBy('code')->get();
        $period = $this->movementsByLedger($from, $until);
        $openingMovement = $from !== null
            ? $this->movementsByLedger(null, Carbon::parse($from)->subDay()->toDateString())
            : collect();

        $rows = [];
        $totals = ['opening_debit' => Money::of('0'), 'opening_credit' => Money::of('0'),
            'debit' => Money::of('0'), 'credit' => Money::of('0'),
            'closing_debit' => Money::of('0'), 'closing_credit' => Money::of('0')];
        foreach ($ledgers as $ledger) {
            [$openingDebit, $openingCredit] = $this->sum($openingMovement->get($ledger->id, collect()));
            [$debit, $credit] = $this->sum($period->get($ledger->id, collect()));
            if ($openingDebit->isZero() && $openingCredit->isZero() && $debit->isZero() && $credit->isZero()) {
                continue;
            }
            // Signed balance (debit-positive); presented in whichever column
            // its sign belongs to, mirroring a manual trial balance.
            $openingNet = $openingDebit->subtract($openingCredit);
            $closingNet = $openingNet->add($debit->subtract($credit));
            $rows[] = [
                'code' => $ledger->code, 'name' => $ledger->name, 'group' => $ledger->group->name,
                'opening_debit' => $openingNet->greaterThan(Money::of('0')) ? (string) $openingNet : '0.00',
                'opening_credit' => $openingNet->lessThan(Money::of('0')) ? (string) Money::of(bcmul((string) $openingNet, '-1', 2)) : '0.00',
                'debit' => (string) $debit, 'credit' => (string) $credit,
                'closing_debit' => $closingNet->greaterThan(Money::of('0')) ? (string) $closingNet : '0.00',
                'closing_credit' => $closingNet->lessThan(Money::of('0')) ? (string) Money::of(bcmul((string) $closingNet, '-1', 2)) : '0.00',
            ];
            foreach ($rows[array_key_last($rows)] as $column => $value) {
                if (in_array($column, ['code', 'name', 'group'], true)) {
                    continue;
                }
                $totals[$column] = $totals[$column]->add(Money::of($value));
            }
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Profit & loss for a period: income and expense ledger movements from
     * posted vouchers. Net profit = income − expense (exact decimal).
     *
     * @return array{rows: list<array<string, string>>, income: Money, expense: Money, net: Money}
     */
    public function profitAndLoss(string $from, string $until): array
    {
        $ledgers = Ledger::query()->with('group')->get()
            ->filter(fn (Ledger $l) => in_array($l->group->nature, ['income', 'expense'], true));
        $period = $this->movementsByLedger($from, $until);

        $rows = [];
        $income = Money::of('0');
        $expense = Money::of('0');
        foreach ($ledgers as $ledger) {
            [$debit, $credit] = $this->sum($period->get($ledger->id, collect()));
            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }
            $net = $ledger->group->nature === 'income' ? $credit->subtract($debit) : $debit->subtract($credit);
            if ($ledger->group->nature === 'income') {
                $income = $income->add($net);
            } else {
                $expense = $expense->add($net);
            }
            $rows[] = [
                'code' => $ledger->code, 'name' => $ledger->name,
                'nature' => $ledger->group->nature, 'amount' => (string) $net,
            ];
        }

        return ['rows' => $rows, 'income' => $income, 'expense' => $expense, 'net' => $income->subtract($expense)];
    }

    /**
     * Balance sheet as at $until: cumulative posted balances by top-level
     * classification, plus retained profit (cumulative income − expense)
     * so the sheet reconciles: assets = liabilities + equity + retained.
     *
     * @return array{sections: array<string, list<array<string, string>>>, assets: Money, liabilities: Money, equity: Money, retained: Money, balanced: bool}
     */
    public function balanceSheet(string $until): array
    {
        $ledgers = Ledger::query()->with('group')->get();
        $cumulative = $this->movementsByLedger(null, $until);

        $sections = ['Assets' => [], 'Liabilities' => [], 'Equity' => []];
        $totals = ['Assets' => Money::of('0'), 'Liabilities' => Money::of('0'), 'Equity' => Money::of('0')];
        $income = Money::of('0');
        $expense = Money::of('0');
        foreach ($ledgers as $ledger) {
            [$debit, $credit] = $this->sum($cumulative->get($ledger->id, collect()));
            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }
            $nature = $ledger->group->nature;
            if ($nature === 'income') {
                $income = $income->add($credit->subtract($debit));

                continue;
            }
            if ($nature === 'expense') {
                $expense = $expense->add($debit->subtract($credit));

                continue;
            }
            // Assets carry debit balances; liabilities and equity credit ones.
            $balance = $nature === 'asset' ? $debit->subtract($credit) : $credit->subtract($debit);
            $section = $nature === 'asset' ? 'Assets' : ($nature === 'liability' ? 'Liabilities' : 'Equity');
            $sections[$section][] = [
                'code' => $ledger->code, 'name' => $ledger->name, 'balance' => (string) $balance,
            ];
            $totals[$section] = $totals[$section]->add($balance);
        }

        $retained = $income->subtract($expense);
        $balanced = $totals['Assets']->equals($totals['Liabilities']->add($totals['Equity'])->add($retained));

        return [
            'sections' => $sections, 'assets' => $totals['Assets'],
            'liabilities' => $totals['Liabilities'], 'equity' => $totals['Equity'],
            'retained' => $retained, 'balanced' => $balanced,
        ];
    }
}
