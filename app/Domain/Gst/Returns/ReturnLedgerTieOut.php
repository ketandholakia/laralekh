<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

use App\Domain\Accounting\Models\{Ledger, Voucher, VoucherLine};
use DomainException;

/** Compares return tax movements with the accounting ledgers for a period. */
final class ReturnLedgerTieOut
{
    public function assert(array $dataset): array
    {
        $period=$dataset['meta']['period']; $start=$period.'-01'; $end=date('Y-m-t',strtotime($start));
        $heads=['cgst'=>'CGST','sgst'=>'SGST','utgst'=>'UTGST','igst'=>'IGST','cess'=>'CESS'];
        $reportedOutput=array_fill_keys(array_keys($heads),'0.00');
        foreach(['b2b','b2cl','exports','sez','reverse_charge'] as $table)foreach($dataset[$table]??[] as $document){
            if(!empty($document['reverse_charge']))continue;
            foreach($document['items']??[] as $line)foreach($heads as $head=>$suffix)$reportedOutput[$head]=bcadd($reportedOutput[$head],(string)$line[$head],2);
        }
        foreach($dataset['b2cs']??[] as $row)foreach($heads as $head=>$suffix)$reportedOutput[$head]=bcadd($reportedOutput[$head],(string)($row[$head]??'0.00'),2);
        foreach(['registered','unregistered'] as $bucket)foreach($dataset['notes'][$bucket]??[] as $note){$sign=$note['kind']==='credit'?-1:1;foreach($heads as $head=>$suffix)$reportedOutput[$head]=bcadd($reportedOutput[$head],$sign<0?bcsub('0.00',(string)$note[$head],2):(string)$note[$head],2);}
        $reportedItc=$dataset['purchases']['itc']['eligible']??array_fill_keys(array_keys($heads),'0.00');
        $result=['output'=>[],'itc'=>[]];
        foreach($heads as $head=>$suffix){
            $outputLedger=Ledger::query()->where('code','OUTPUT_'.$suffix)->value('id');
            $inputLedger=Ledger::query()->where('code','INPUT_'.$suffix)->value('id');
            $output=$this->movement($outputLedger,$start,$end,'credit','debit');
            $input=$this->movement($inputLedger,$start,$end,'debit','credit');
            $result['output'][$head]=['dataset'=>$reportedOutput[$head],'ledger'=>$output];
            $result['itc'][$head]=['summary'=>$reportedItc[$head]??'0.00','ledger'=>$input];
            if(bccomp($reportedOutput[$head],$output,2)!==0)throw new DomainException("GSTR-1 output tax tie-out failed for {$head}: dataset {$reportedOutput[$head]}, ledger {$output}.");
            if(bccomp($reportedItc[$head]??'0.00',$input,2)!==0)throw new DomainException("GSTR-3B ITC tie-out failed for {$head}: eligible dataset ".($reportedItc[$head]??'0.00').", ledger {$input}.");
        }
        return $result;
    }
    private function movement(?int $ledgerId,string $start,string $end,string $positive,string $negative):string
    {
        if(!$ledgerId)return '0.00';
        $totals=VoucherLine::query()->where('ledger_id',$ledgerId)->whereHas('voucher',fn($q)=>$q->where('status','posted')->whereBetween('date',[$start,$end]))
            ->selectRaw("COALESCE(SUM({$positive}),0) AS positive_total, COALESCE(SUM({$negative}),0) AS negative_total")->first();
        return bcsub((string)$totals->positive_total,(string)$totals->negative_total,2);
    }
}
