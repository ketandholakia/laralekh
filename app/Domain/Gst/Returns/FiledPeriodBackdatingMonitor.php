<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

use App\Domain\Gst\Returns\Models\GstReturnFiling;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\{Invoice,SalesCorrection};

final class FiledPeriodBackdatingMonitor
{
    /** Documents posted after filing with a document/reporting date in a filed period. */
    public function warnings(string $period):array
    {
        [$start,$end]=[$period.'-01',date('Y-m-t',strtotime($period.'-01'))];
        $filings=GstReturnFiling::query()->where('period',$period)->get()->keyBy('type');if($filings->isEmpty())return [];
        $gstr1FiledOn=$filings->get('gstr1')?->filed_on?->toDateString();$gstr3bFiledOn=$filings->get('gstr3b')?->filed_on?->toDateString();$warnings=[];
        foreach(Invoice::query()->with('voucher')->where('status','posted')->whereBetween('date',[$start,$end])->get() as $doc)
            if($gstr1FiledOn&&$doc->voucher?->posted_at && $doc->voucher->posted_at->toDateString()>$gstr1FiledOn)$warnings[]=['type'=>'sales_invoice','id'=>$doc->id,'number'=>$doc->series.'/'.$doc->number,'date'=>$doc->date->toDateString(),'posted_at'=>$doc->voucher->posted_at->toIso8601String()];
        foreach(PurchaseBill::query()->with('voucher')->where('status','posted')->whereBetween('date',[$start,$end])->get() as $doc)
            if($gstr3bFiledOn&&$doc->voucher?->posted_at && $doc->voucher->posted_at->toDateString()>$gstr3bFiledOn)$warnings[]=['type'=>'purchase_bill','id'=>$doc->id,'number'=>$doc->supplier_bill_number,'date'=>$doc->date->toDateString(),'posted_at'=>$doc->voucher->posted_at->toIso8601String()];
        foreach(SalesCorrection::query()->with('voucher')->where('status','posted')->where(function($q)use($start,$end){$q->whereBetween('reporting_date',[$start,$end])->orWhere(fn($fallback)=>$fallback->whereNull('reporting_date')->whereBetween('date',[$start,$end]));})->get() as $doc)
            if($gstr1FiledOn&&$doc->voucher?->posted_at && $doc->voucher->posted_at->toDateString()>$gstr1FiledOn)$warnings[]=['type'=>'sales_correction','id'=>$doc->id,'number'=>$doc->series.'/'.$doc->number,'date'=>($doc->reporting_date??$doc->date)->toDateString(),'posted_at'=>$doc->voucher->posted_at->toIso8601String()];
        return $warnings;
    }
}
