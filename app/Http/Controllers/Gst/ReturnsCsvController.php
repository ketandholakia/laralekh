<?php
declare(strict_types=1);
namespace App\Http\Controllers\Gst;

use App\Domain\Gst\Returns\{OfficialGstr1CsvExporter,ReturnDatasetBuilder,ReturnReviewCsvExporter,ReturnValidationReport};
use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReturnsCsvController
{
    private const FILES=['b2b,sez,de.csv','b2cl.csv','b2cs.csv','exp.csv','cdnr.csv','cdnur.csv','hsn(b2b).csv','hsn(b2c).csv','docs.csv','review-summary.csv'];
    public function __invoke(Request $request,string $period,string $filename,ReturnDatasetBuilder $builder,OfficialGstr1CsvExporter $exporter,ReturnReviewCsvExporter $reviewExporter,ReturnValidationReport $validation): Response
    {
        abort_unless($request->user()->can('returns.export'),403); abort_unless((bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period),404); abort_unless(in_array($filename,self::FILES,true),404);
        $turnover=$request->query('annual_turnover_rupees');
        abort_if($turnover!==null&&(!is_string($turnover)||!ctype_digit($turnover)),422,'Annual turnover must be a whole-rupee integer.');
        $company=Company::query()->findOrFail(app(CompanySession::class)->companyId()); $dataset=$builder->build($company,$period,$turnover===null?null:(int)$turnover); $report=$validation->forDataset($dataset);
        abort_if(!$report['valid'],422,'CSV export blocked by validation errors.');
        $content=$filename==='review-summary.csv'?$reviewExporter->export($dataset,$report):($exporter->export($dataset)[$filename]??null);
        abort_unless($content!==null,404);
        return response($content,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$filename.'"']);
    }
}
