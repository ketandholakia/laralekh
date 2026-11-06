<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

/** Internal CA review summary; explicitly not a GST Portal/Offline Tool import template. */
final class ReturnReviewCsvExporter
{
    public function export(array $dataset, array $report):string
    {
        $headers=['Period','Section','Records','Taxable value','CGST','SGST','UTGST','IGST','Cess','Validation'];
        $rows=[];$zero=['cgst'=>'0.00','sgst'=>'0.00','utgst'=>'0.00','igst'=>'0.00','cess'=>'0.00'];
        foreach(['b2b','b2cl','exports','sez','reverse_charge'] as $section){
            $records=$dataset[$section]??[];$taxes=$zero;$taxable='0.00';
            foreach($records as $record)foreach($record['items']??[] as $line){if($line['tax_category']!=='taxable')continue;$taxable=bcadd($taxable,(string)$line['taxable_amount'],2);foreach(array_keys($taxes) as $head)$taxes[$head]=bcadd($taxes[$head],(string)$line[$head],2);}
            $rows[]=[$dataset['meta']['period'],$section,count($records),$taxable,$taxes['cgst'],$taxes['sgst'],$taxes['utgst'],$taxes['igst'],$taxes['cess'],$report['valid']?'Review':'Blocked'];
        }
        foreach($dataset['b2cs']??[] as $row)$rows[]=[$dataset['meta']['period'],'b2cs',1,$row['taxable_value'],$row['cgst'],$row['sgst'],$row['utgst'],$row['igst'],$row['cess'],$report['valid']?'Review':'Blocked'];
        foreach(['registered','unregistered'] as $bucket)foreach($dataset['notes'][$bucket]??[] as $note){
            $sign=$note['kind']==='credit'?-1:1;$tax=[];foreach(array_keys($zero) as $head)$tax[$head]=$sign<0?bcsub('0.00',(string)$note[$head],2):(string)$note[$head];
            $value=$sign<0?bcsub('0.00',(string)$note['taxable_amount'],2):(string)$note['taxable_amount'];
            $rows[]=[$dataset['meta']['period'],$bucket==='registered'?'credit/debit notes (registered)':'credit/debit notes (unregistered)',1,$value,$tax['cgst'],$tax['sgst'],$tax['utgst'],$tax['igst'],$tax['cess'],$report['valid']?'Review':'Blocked'];
        }
        $stream=fopen('php://temp','r+');fputcsv($stream,$headers,',','"','\\',"\r\n");foreach($rows as $row)fputcsv($stream,$row,',','"','\\',"\r\n");rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
    }
}
