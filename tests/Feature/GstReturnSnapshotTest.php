<?php
use App\Domain\Gst\Returns\{FiledPeriodBackdatingMonitor,GstReturnFilingService,GstReturnSnapshotService};
use App\Domain\Gst\Returns\Models\GstReturnSnapshot;
use App\Domain\Masters\Models\Company;
use Illuminate\Database\QueryException;

it('finalizes immutable snapshots and supersedes on regeneration',function(){
    inSalesCompany(function(Company $company){
        $service=app(GstReturnSnapshotService::class);$one=$service->generate($company,'2026-11','gstr1',['validation'=>['valid'=>true],'rows'=>[1]]);
        $one=$service->finalize($one->id);
        expect($one->status)->toBe('final')->and($one->sha256)->toBe(GstReturnSnapshotService::hashPayload($one->payload));
        expect(fn()=>$one->update(['payload'=>['corrupted'=>true]]))->toThrow(QueryException::class);
        expect(fn()=>$one->delete())->toThrow(QueryException::class);
        $filing=app(GstReturnFilingService::class)->markFiled($one->id,'ARN-2026-TEST','2026-11-05');
        expect($filing->arn)->toBe('ARN-2026-TEST')->and($filing->filed_on->toDateString())->toBe('2026-11-05')
            ->and(app(FiledPeriodBackdatingMonitor::class)->warnings('2026-11'))->toBeArray();
        $two=$service->generate($company,'2026-11','gstr1',['validation'=>['valid'=>true],'rows'=>[2]]);
        expect($two->version)->toBe(2)->and($two->status)->toBe('draft')->and($one->fresh()->status)->toBe('superseded');
    });
});

it('warns when a document is posted after filing into an already filed period',function(){
    Carbon\CarbonImmutable::setTestNow('2026-11-30 12:00:00');
    try {
        inSalesCompany(function(Company $company){
            $snapshot=app(GstReturnSnapshotService::class)->generate($company,'2026-11','gstr1',['validation'=>['valid'=>true]]);
            $snapshot=app(GstReturnSnapshotService::class)->finalize($snapshot->id);
            app(GstReturnFilingService::class)->markFiled($snapshot->id,'ARN-LATE-POST','2026-11-10');
            $invoice=returnSalesFixture(['date'=>'2026-11-05']);app(App\Domain\Accounting\Services\PostingService::class)->postSalesInvoice($invoice->id);
            expect(app(FiledPeriodBackdatingMonitor::class)->warnings('2026-11'))->toHaveCount(1);
        });
    } finally { Carbon\CarbonImmutable::setTestNow(); }
});

it('blocks finalizing a snapshot with validation errors',function(){
    inSalesCompany(function(Company $company){$snapshot=app(GstReturnSnapshotService::class)->generate($company,'2026-11','gstr3b',['validation'=>['valid'=>false]]);
        expect(fn()=>app(GstReturnSnapshotService::class)->finalize($snapshot->id))->toThrow(DomainException::class,'blocking');});
});
