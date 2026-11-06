<?php
use App\Domain\Accounting\Services\PostingService;
use App\Domain\Gst\Returns\{OfficialGstr1CsvExporter,ReturnDatasetBuilder,ReturnValidationReport};
use App\Domain\Masters\Models\Company;

it('uses the exact official Offline Tool CSV headers and column order',function(){
    inSalesCompany(function(Company $company){
        $invoice=returnSalesFixture(['date'=>'2026-11-06','place_of_supply'=>'24'],['unit_price'=>'150000.00']);
        app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        $files=app(OfficialGstr1CsvExporter::class)->export($dataset);
        foreach($files as $filename=>$csv){
            $stream=fopen('php://temp','r+');fwrite($stream,$csv);rewind($stream);$actual=fgetcsv($stream);fclose($stream);
            $template=file_get_contents(config('gst_returns.csv_templates.directory').DIRECTORY_SEPARATOR.$filename);
            $expected=str_getcsv(preg_split('/\r\n|\n|\r/',$template,2)[0]??'');
            expect($actual)->toBe($expected);
        }
        expect($files)->toHaveKeys(['b2b,sez,de.csv','b2cl.csv','b2cs.csv','exp.csv','cdnr.csv','cdnur.csv','hsn(b2b).csv','hsn(b2c).csv','docs.csv']);
    });
});

it('blocks CSV export when required recipient GSTIN checksum verification is not configured',function(){
    inSalesCompany(function(Company $company){
        $invoice=returnSalesFixture(['date'=>'2026-11-06']);$invoice->party->update(['registration_type'=>'regular','gstin'=>'27ABCDE1234F1Z5']);
        app(PostingService::class)->postSalesInvoice($invoice->id);
        $dataset=app(ReturnDatasetBuilder::class)->build($company,'2026-11',50000000);
        expect(collect(app(ReturnValidationReport::class)->forDataset($dataset)['errors'])
            ->contains(fn($issue)=>$issue['code']==='gstin_checksum_unverified'))->toBeTrue();
    });
});

it('blocks CSV export for datasets with validation errors',function(){
    $exporter=app(OfficialGstr1CsvExporter::class);
    expect(fn()=>$exporter->export(['validation'=>['errors'=>[['code'=>'invalid','message'=>'bad']]],'b2b'=>[]]))
        ->toThrow(DomainException::class,'CSV export blocked');
});
