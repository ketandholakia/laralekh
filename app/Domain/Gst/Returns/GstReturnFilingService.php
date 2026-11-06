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
        try{$date=\Carbon\CarbonImmutable::parse($filedOn)->toDateString();}catch(\Throwable){throw new DomainException('Enter a valid filing date.');}
        return DB::transaction(function()use($snapshotId,$arn,$date){$snapshot=GstReturnSnapshot::query()->whereKey($snapshotId)->lockForUpdate()->firstOrFail();
            if($snapshot->status!=='final')throw new DomainException('Only a finalized snapshot can be marked as filed.');
            return GstReturnFiling::create(['period'=>$snapshot->period,'type'=>$snapshot->type,'snapshot_id'=>$snapshot->id,'arn'=>$arn,'filed_on'=>$date,'marked_by'=>Auth::id()]);});
    }
}
