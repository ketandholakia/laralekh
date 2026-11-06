<?php
declare(strict_types=1);

namespace App\Domain\Gst\Returns;

use App\Domain\Gst\Services\GstCalculator;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;

/** Books-to-portal reconciliation worksheet; it does not file GSTR-3B. */
final class Gstr3bSummaryBuilder
{
    public function __construct(private readonly GstCalculator $calculator, private readonly ReturnLedgerTieOut $tieOut) {}

    public function build(array $dataset): array
    {
        if (!($dataset['validation']['valid'] ?? false)) throw new DomainException('Cannot build GSTR-3B worksheet from a dataset with blocking validation errors.');
        $this->tieOut->assert($dataset);
        $heads=['cgst','sgst','utgst','igst','cess'];
        $output=array_fill_keys($heads,'0.00');$taxableOutward='0.00';
        $zeroRated='0.00'; $nil='0.00'; $exempt='0.00'; $nonGst='0.00';
        foreach (['b2b','b2cl','exports','sez','reverse_charge'] as $table) foreach ($dataset[$table] ?? [] as $document) {
            if (in_array($table,['exports','sez'],true)) foreach($document['items']??[] as $zeroLine)$zeroRated=bcadd($zeroRated,(string)$zeroLine['taxable_amount'],2);
            foreach ($document['items'] ?? [] as $line) {
                if ($line['tax_category']!=='taxable') {
                    $category=$line['tax_category']; if($category==='nil_rated')$nil=bcadd($nil,$line['taxable_amount'],2);elseif($category==='exempt')$exempt=bcadd($exempt,$line['taxable_amount'],2);else $nonGst=bcadd($nonGst,$line['taxable_amount'],2);
                    continue;
                }
                if(!in_array($table,['exports','sez'],true))$taxableOutward=bcadd($taxableOutward,(string)$line['taxable_amount'],2);
                if (!empty($document['reverse_charge'])) continue;
                foreach($heads as $head)$output[$head]=bcadd($output[$head],(string)$line[$head],2);
            }
        }
        foreach($dataset['b2cs']??[] as $summary){$taxableOutward=bcadd($taxableOutward,(string)$summary['taxable_value'],2);foreach($heads as $head)$output[$head]=bcadd($output[$head],(string)$summary[$head],2);}
        $nil=bcadd($nil,(string)($dataset['b2cs_non_taxable']['nil_rated']??'0.00'),2);
        $exempt=bcadd($exempt,(string)($dataset['b2cs_non_taxable']['exempt']??'0.00'),2);
        $nonGst=bcadd($nonGst,(string)($dataset['b2cs_non_taxable']['non_gst']??'0.00'),2);
        foreach(['registered','unregistered'] as $bucket)foreach($dataset['notes'][$bucket]??[] as $note){
            if($bucket==='unregistered'&&($note['gstr1_table']??null)==='B2CS')continue;
            $sign=$note['kind']==='credit'?-1:1;
            $base=(string)$note['taxable_amount'];
            if(in_array($note['original_supply_type']??null,['export','sez'],true))$zeroRated=bcadd($zeroRated,$sign<0?bcsub('0.00',$base,2):$base,2);
            else $taxableOutward=bcadd($taxableOutward,$sign<0?bcsub('0.00',$base,2):$base,2);
            foreach($heads as $head)$output[$head]=bcadd($output[$head],$sign<0?bcsub('0.00',(string)$note[$head],2):(string)$note[$head],2);
        }
        $itc=$dataset['purchases']['itc']['eligible']??array_fill_keys($heads,'0.00');
        $blocked=$dataset['purchases']['itc']['blocked']??array_fill_keys($heads,'0.00');
        $review=$dataset['purchases']['itc']['review']??array_fill_keys($heads,'0.00');
        $rcm=array_fill_keys($heads,'0.00');
        foreach($dataset['purchases']['rcm']??[] as $bill)foreach($bill['items'] as $line)foreach($heads as $head)$rcm[$head]=bcadd($rcm[$head],(string)$line[$head],2);
        $payable=[];foreach($heads as $head){
            $raw=bcsub(bcadd($output[$head],$rcm[$head],2),(string)$itc[$head],2);
            $payable[$head]=(string)$this->calculator->roundStatutoryPayable(Money::of($raw));
        }
        return ['period'=>$dataset['meta']['period'],'basis'=>'books reconciliation only; portal values may be auto-populated from GSTR-1/1A and GSTR-2B',
            '3_1'=>['taxable_outward'=>['taxable_value'=>$taxableOutward,'tax'=>$output],'zero_rated_taxable_value'=>$zeroRated,
                'nil_rated_value'=>$nil,'exempt_value'=>$exempt,'non_gst_value'=>$nonGst,'reverse_charge_inward_tax'=>$rcm],
            '4_itc'=>['eligible'=>$itc,'blocked'=>$blocked,'review_required'=>$review],
            'net_payable_after_book_itc'=>$payable,
            'warnings'=>['Reconcile portal-generated GSTR-1/1A and GSTR-2B values before filing. RCM ITC eligibility and statutory reversal categories require CA review.']];
    }
}
