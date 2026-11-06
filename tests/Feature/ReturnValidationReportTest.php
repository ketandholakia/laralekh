<?php
use App\Domain\Gst\Returns\ReturnValidationReport;

it('reports missing or invalid B2B GSTINs and warns that portal existence is unverified',function(){
    $report=app(ReturnValidationReport::class);
    $base=['validation'=>['errors'=>[],'warnings'=>[]],'meta'=>['period'=>'2026-11'],'b2b'=>[
        ['id'=>1,'date'=>'2026-11-10','recipient'=>[],'items'=>[]],
        ['id'=>2,'date'=>'2026-11-10','recipient'=>['gstin'=>'NOT-A-GSTIN'],'items'=>[]],
        ['id'=>3,'date'=>'2026-11-10','recipient'=>['gstin'=>'27AAPFU0939F1ZV'],'items'=>[]],
    ]];
    $result=$report->forDataset($base);$codes=collect($result['errors'])->pluck('code');
    expect($codes)->toContain('missing_recipient_gstin','invalid_recipient_gstin')->not->toContain('gstin_checksum_unverified')
        ->and($result['valid'])->toBeFalse()
        ->and(collect($result['warnings'])->pluck('code'))->toContain('gstin_portal_existence_unverified');
});

it('validates GSTIN structure, assigned state code, and mod-36 check digit case-insensitively',function(){
    $validator=app(\App\Domain\Gst\Returns\GstinValidator::class);
    expect($validator->isValid('27AAPFU0939F1ZV'))->toBeTrue()
        ->and($validator->isValid('29AAACB2894G1ZJ'))->toBeTrue()
        ->and($validator->isValid('27aapfu0939f1zv'))->toBeTrue()
        ->and($validator->isValid('27AAPFU0939F1ZA'))->toBeFalse()
        ->and($validator->isValid('00AAPFU0939F1ZV'))->toBeFalse()
        ->and($validator->isValid('39AAPFU0939F1ZV'))->toBeFalse()
        ->and($validator->isValid('27AAPFU0939F1Z'))->toBeFalse();
});

it('reports documents outside period and missing HSN or UQC',function(){
    $dataset=['validation'=>['errors'=>[],'warnings'=>[]],'meta'=>['period'=>'2026-11'],'b2cl'=>[
        ['id'=>4,'date'=>'2026-10-31','items'=>[['tax_category'=>'taxable','hsn_sac'=>'','unit'=>'']]],
    ]];
    $result=app(ReturnValidationReport::class)->forDataset($dataset);$codes=collect($result['errors'])->pluck('code');
    expect($codes)->toContain('document_outside_period','missing_hsn','missing_uqc');
});
