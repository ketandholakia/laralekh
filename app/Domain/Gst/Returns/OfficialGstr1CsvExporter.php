<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

use App\Domain\Masters\Models\State;
use DomainException;

/** Emits the official Offline Tool section CSVs using checked-in template headers. */
final class OfficialGstr1CsvExporter
{
    public function __construct(private readonly ReturnValidationReport $validation) {}

    /** @return array<string,string> filename => UTF-8 CSV content */
    public function export(array $dataset):array
    {
        $report=$this->validation->forDataset($dataset);
        if(!$report['valid'])throw new DomainException('CSV export blocked: '.implode(', ',array_unique(array_column($report['errors'],'code'))));
        $tables=['b2b,sez,de.csv'=>[],'b2cl.csv'=>[],'b2cs.csv'=>[],'exp.csv'=>[],'cdnr.csv'=>[],'cdnur.csv'=>[],'hsn.csv'=>[],'docs.csv'=>[]];
        foreach($dataset['b2b']??[] as $doc)$this->addInvoiceRows($tables['b2b,sez,de.csv'],$doc,'b2b');
        foreach($dataset['sez']??[] as $doc)$this->addInvoiceRows($tables['b2b,sez,de.csv'],$doc,'sez');
        foreach($dataset['b2cl']??[] as $doc)$this->addInvoiceRows($tables['b2cl.csv'],$doc,'b2cl');
        foreach($dataset['b2cs']??[] as $row)$tables['b2cs.csv'][]=['Type'=>'OE','Place Of Supply'=>$this->pos((string)$row['place_of_supply']),
            'Rate'=>$row['rate'],'Applicable % of Tax Rate'=>'','Taxable Value'=>$row['taxable_value'],'Cess Amount'=>$row['cess'],'E-Commerce GSTIN'=>''];
        foreach($dataset['exports']['with_payment']??[] as $doc)$this->addExportRows($tables['exp.csv'],$doc,'WPAY');
        foreach($dataset['exports']['without_payment']??[] as $doc)$this->addExportRows($tables['exp.csv'],$doc,'WOPAY');
        foreach(['registered'=>'cdnr.csv','unregistered'=>'cdnur.csv'] as $bucket=>$filename)foreach($dataset['notes'][$bucket]??[] as $note)
            if($bucket==='registered'||($note['gstr1_table']??'CDNUR')==='CDNUR')$this->addNoteRows($tables[$filename],$note,$bucket);
        foreach($dataset['hsn']??[] as $line){
            $total=bcadd($line['taxable_value'],bcadd(bcadd($line['igst'],$line['cgst'],2),bcadd(bcadd($line['sgst'],$line['utgst'],2),$line['cess'],2),2),2);
            $tables['hsn.csv'][]=['HSN'=>$line['hsn'],'Description'=>$line['description'],'UQC'=>$line['uqc'],'Total Quantity'=>$line['quantity'],'Total Value'=>$total,
                'Taxable Value'=>$line['taxable_value'],'Integrated Tax Amount'=>$line['igst'],'Central Tax Amount'=>$line['cgst'],
                'State/UT Tax Amount'=>bcadd($line['sgst'],$line['utgst'],2),'Cess Amount'=>$line['cess'],'Rate'=>$line['rate']??''];
        }
        foreach($dataset['documents_issued']??[] as $row)$tables['docs.csv'][]=['Nature of Document'=>$row['nature_of_document'],'Sr. No. From'=>$row['from'],
            'Sr. No. To'=>$row['to'],'Total Number'=>$row['total'],'Cancelled'=>$row['cancelled']];
        $files=[];foreach($tables as $filename=>$rows)$files[$filename]=$this->render($filename,$rows);
        return $files;
    }

    private function addInvoiceRows(array &$rows,array $doc,string $section):void
    {
        foreach($doc['items'] as $line){
            if($line['tax_category']!=='taxable')continue;
            if($section==='b2cl')$rows[]=['Invoice Number'=>$doc['document_number'],'Invoice date'=>$this->date($doc['date']),'Invoice Value'=>$doc['value'],
                'Place Of Supply'=>$this->pos($doc['place_of_supply']),'Applicable % of Tax Rate'=>'','Rate'=>$line['rate'],'Taxable Value'=>$line['taxable_amount'],'Cess Amount'=>$line['cess'],'E-Commerce GSTIN'=>''];
            else $rows[]=['GSTIN/UIN of Recipient'=>$doc['recipient']['gstin']??'','Receiver Name'=>$doc['recipient']['name']??'',
                'Invoice Number'=>$doc['document_number'],'Invoice date'=>$this->date($doc['date']),'Invoice Value'=>$doc['value'],
                'Place Of Supply'=>$this->pos($doc['place_of_supply']),'Reverse Charge'=>!empty($doc['reverse_charge'])?'Y':'N',
                'Applicable % of Tax Rate'=>'','Invoice Type'=>$section==='sez'?($doc['zero_rated_mode']==='with_payment'?'SEZ supplies with payment':'SEZ supplies without payment'):'Regular B2B',
                'E-Commerce GSTIN'=>'','Rate'=>$line['rate'],'Taxable Value'=>$line['taxable_amount'],'Cess Amount'=>$line['cess']];
        }
    }
    private function addExportRows(array &$rows,array $doc,string $exportType):void
    { foreach($doc['items'] as $line)if($line['tax_category']==='taxable')$rows[]=['Export Type'=>$exportType,'Invoice Number'=>$doc['document_number'],'Invoice date'=>$this->date($doc['date']),
        'Invoice Value'=>$doc['value'],'Port Code'=>'','Shipping Bill Number'=>'','Shipping Bill Date'=>'','Rate'=>$line['rate'],'Taxable Value'=>$line['taxable_amount'],'Cess Amount'=>$line['cess']]; }
    private function addNoteRows(array &$rows,array $note,string $bucket):void
    {
        $lines=$note['items']??[['rate'=>'','taxable_amount'=>$note['taxable_amount'],'cess'=>$note['cess']]];
        foreach($lines as $line){
            if($bucket==='registered')$rows[]=['GSTIN/UIN of Recipient'=>$note['recipient']['gstin']??'','Receiver Name'=>$note['recipient']['name']??'',
                'Note Number'=>$note['document_number'],'Note Date'=>$this->date($note['date']),'Note Type'=>$note['kind']==='credit'?'C':'D',
                'Place Of Supply'=>$this->pos((string)$note['place_of_supply']),'Reverse Charge'=>'N','Note Supply Type'=>'Regular B2B','Note Value'=>$this->signedNoteValue($note),
                'Applicable % of Tax Rate'=>'','Rate'=>$line['rate']??'','Taxable Value'=>$line['taxable_amount'],'Cess Amount'=>$line['cess']];
            else $rows[]=['UR Type'=>$note['unregistered_type'],'Note Number'=>$note['document_number'],'Note Date'=>$this->date($note['date']),
                'Note Type'=>$note['kind']==='credit'?'C':'D','Place Of Supply'=>$this->pos((string)$note['place_of_supply']),'Note Value'=>$this->signedNoteValue($note),
                'Applicable % of Tax Rate'=>'','Rate'=>$line['rate']??'','Taxable Value'=>$line['taxable_amount'],'Cess Amount'=>$line['cess']];
        }
    }
    private function render(string $filename,array $rows):string
    {
        $path=config('gst_returns.csv_templates.directory').DIRECTORY_SEPARATOR.$filename;
        if(!is_file($path))throw new DomainException('Official GSTR-1 CSV template is missing: '.$filename);
        $stream=fopen($path,'rb');$header=str_getcsv((string)fgets($stream));fclose($stream);
        $output=fopen('php://temp','r+');fputcsv($output,$header,',','"','\\',"\r\n");
        foreach($rows as $row){$values=[];foreach($header as $column)$values[]=$row[$column]??'';fputcsv($output,$values,',','"','\\',"\r\n");}
        rewind($output);$csv=stream_get_contents($output);fclose($output);return $csv;
    }
    private function pos(string $code):string
    { $state=State::query()->where('code',$code)->first();return $state?$state->code.'-'.$state->name:$code; }
    private function signedNoteValue(array $note):string
    { return $note['kind']==='credit'?bcsub('0.00',(string)$note['value'],2):(string)$note['value']; }
    private function date(string $date):string{return date('j-M-y',strtotime($date));}
}
