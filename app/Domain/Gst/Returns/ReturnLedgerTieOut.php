<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Shared\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Compares return tax movements with the accounting ledgers for a period. */
final class ReturnLedgerTieOut
{
    public function assert(array $dataset): array
    {
        $period = $dataset['meta']['period'];
        $start = $period.'-01';
        $end = date('Y-m-t', strtotime($start));
        $heads = ['cgst' => 'CGST', 'sgst' => 'SGST', 'utgst' => 'UTGST', 'igst' => 'IGST', 'cess' => 'CESS'];
        $reportedOutput = array_fill_keys(array_keys($heads), '0.00');
        foreach (['b2b', 'b2cl', 'exports', 'sez', 'reverse_charge'] as $table) {
            foreach ($dataset[$table] ?? [] as $document) {
                if (! empty($document['reverse_charge'])) {
                    continue;
                }
                foreach ($document['items'] ?? [] as $line) {
                    foreach ($heads as $head => $suffix) {
                        $reportedOutput[$head] = bcadd($reportedOutput[$head], (string) $line[$head], 2);
                    }
                }
            }
        }
        foreach ($dataset['b2cs'] ?? [] as $row) {
            foreach ($heads as $head => $suffix) {
                $reportedOutput[$head] = bcadd($reportedOutput[$head], (string) ($row[$head] ?? '0.00'), 2);
            }
        }
        foreach (['registered', 'unregistered'] as $bucket) {
            foreach ($dataset['notes'][$bucket] ?? [] as $note) {
                if ($bucket === 'unregistered' && ($note['gstr1_table'] ?? null) === 'B2CS') {
                    continue;
                }$sign = $note['kind'] === 'credit' ? -1 : 1;
                foreach ($heads as $head => $suffix) {
                    $reportedOutput[$head] = bcadd($reportedOutput[$head], $sign < 0 ? bcsub('0.00', (string) $note[$head], 2) : (string) $note[$head], 2);
                }
            }
        }
        $reportedItc = $dataset['purchases']['itc']['eligible'] ?? array_fill_keys(array_keys($heads), '0.00');
        $result = ['output' => [], 'itc' => []];
        foreach ($heads as $head => $suffix) {
            $outputLedger = Ledger::query()->where('code', 'OUTPUT_'.$suffix)->value('id');
            $inputLedger = Ledger::query()->where('code', 'INPUT_'.$suffix)->value('id');
            $output = $this->movement($outputLedger, $start, $end, 'credit', 'debit', true);
            $input = $this->movement($inputLedger, $start, $end, 'debit', 'credit');
            $result['output'][$head] = ['dataset' => $reportedOutput[$head], 'ledger' => $output];
            $result['itc'][$head] = ['summary' => $reportedItc[$head] ?? '0.00', 'ledger' => $input];
            if (bccomp($reportedOutput[$head], $output, 2) !== 0) {
                throw new DomainException("GSTR-1 output tax tie-out failed for {$head}: dataset {$reportedOutput[$head]}, ledger {$output}.");
            }
            if (bccomp($reportedItc[$head] ?? '0.00', $input, 2) !== 0) {
                throw new DomainException("GSTR-3B ITC tie-out failed for {$head}: eligible dataset ".($reportedItc[$head] ?? '0.00').", ledger {$input}.");
            }
        }

        return $result;
    }

    private function movement(?int $ledgerId, string $start, string $end, string $positive, string $negative, bool $useCorrectionReportingDate = false): string
    {
        if (! $ledgerId) {
            return '0.00';
        }
        $companyId = app(TenantContext::class)->id();
        $query = DB::table('voucher_lines as vl')->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')->where('vl.company_id', $companyId)
            ->where('v.company_id', $companyId)->where('vl.ledger_id', $ledgerId)->where('v.status', 'posted');
        if ($useCorrectionReportingDate) {
            $query->leftJoin('sales_corrections as sc', function ($join) {
                $join->on('sc.voucher_id', '=', 'v.id')->on('sc.company_id', '=', 'v.company_id')->where('sc.status', '=', 'posted');
            })
                ->where(fn ($q) => $q->where(fn ($byVoucherDate) => $byVoucherDate->whereNull('sc.id')->whereBetween('v.date', [$start, $end]))
                    ->orWhere(fn ($byReportPeriod) => $byReportPeriod->whereNotNull('sc.id')->whereRaw('COALESCE(sc.reporting_date, sc.date) BETWEEN ? AND ?', [$start, $end])));
        } else {
            $query->whereBetween('v.date', [$start, $end]);
        }
        $totals = $query->selectRaw("COALESCE(SUM(vl.{$positive}),0) AS positive_total, COALESCE(SUM(vl.{$negative}),0) AS negative_total")->first();

        return bcsub((string) $totals->positive_total,(string) $totals->negative_total,2);
    }
}
