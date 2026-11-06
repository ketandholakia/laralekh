<?php
use App\Domain\Gst\Returns\ReturnValidationReport;

it('reports missing or invalid B2B recipient GSTIN and blocks unverified checksum results',function(){
    $report=app(ReturnValidationReport::class);
    $base=['validation'=>['errors'=>[],'warnings'=>[]],'meta'=>['period'=>'2026-11'],'b2b'=>[
        ['id'=>1,'date'=>'2026-11-10','recipient'=>[],'items'=>[]],
        ['id'=>2,'date'=>'2026-11-10','recipient'=>['gstin'=>'NOT-A-GSTIN'],'items'=>[]],
        ['id'=>3,'date'=>'2026-11-10','recipient'=>['gstin'=>'27ABCDE1234F1Z5'],'items'=>[]],
    ]];
    $result=$report->forDataset($base);$codes=collect($result['errors'])->pluck('code');
    expect($codes)->toContain('missing_recipient_gstin','invalid_recipient_gstin','gstin_checksum_unverified')->and($result['valid'])->toBeFalse();
});

it('reports documents outside period and missing HSN or UQC',function(){
    $dataset=['validation'=>['errors'=>[],'warnings'=>[]],'meta'=>['period'=>'2026-11'],'b2cl'=>[
        ['id'=>4,'date'=>'2026-10-31','items'=>[['tax_category'=>'taxable','hsn_sac'=>'','unit'=>'']]],
    ]];
    $result=app(ReturnValidationReport::class)->forDataset($dataset);$codes=collect($result['errors'])->pluck('code');
    expect($codes)->toContain('document_outside_period','missing_hsn','missing_uqc');
});
