<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

use App\Domain\Gst\Returns\Models\{GstReturnFiling,GstReturnSnapshot};
use Illuminate\Support\Facades\{Auth,DB};
use DomainException;

final class GstReturnFilingService
{
    public function markFiled(int $snapshotId,string $arn,string $filedOn):GstReturnFiling
    {
        $arn=trim($arn);if($arn===''||strlen($arn)>80)throw new DomainException('Enter the ARN recorded by the GST Portal.');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$filedOn))throw new DomainException('Enter a valid filing date in YYYY-MM-DD format.');
        try{$parsed=\Carbon\CarbonImmutable::createFromFormat('!Y-m-d',$filedOn);}catch(\Throwable){throw new DomainException('Enter a valid filing date.');}
        if(!$parsed||$parsed->format('Y-m-d')!==$filedOn||$parsed->gt(\Carbon\CarbonImmutable::today()))throw new DomainException('Enter a valid filing date that is not in the future.');
        $date=$parsed->toDateString();
        return DB::transaction(function()use($snapshotId,$arn,$date){$snapshot=GstReturnSnapshot::query()->whereKey($snapshotId)->lockForUpdate()->firstOrFail();
            if($snapshot->status!=='final')throw new DomainException('Only a finalized snapshot can be marked as filed.');
            if(GstReturnFiling::query()->where('period',$snapshot->period)->where('type',$snapshot->type)->exists())throw new DomainException('This return period and type already has a filing record.');
            return GstReturnFiling::create(['period'=>$snapshot->period,'type'=>$snapshot->type,'snapshot_id'=>$snapshot->id,'arn'=>$arn,'filed_on'=>$date,'marked_by'=>Auth::id()]);});
    }
}
