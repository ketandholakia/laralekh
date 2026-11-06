<?php
declare(strict_types=1);

namespace App\Domain\Gst\Returns;

use App\Domain\Gst\Models\TaxRate;
use App\Domain\Masters\Models\{Company, State};
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\{Invoice, SalesCorrection};
use Carbon\CarbonImmutable;
use DomainException;

final class ReturnDatasetBuilder
{
    /** Build an in-memory, tenant-scoped GSTR-1/GSTR-3B source dataset for YYYY-MM. */
    public function build(Company $company, string $period, ?int $annualTurnoverRupees = null): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) throw new DomainException('Return period must use YYYY-MM.');
        if ((int) $company->id !== app(\App\Domain\Shared\Tenancy\TenantContext::class)->id()) throw new DomainException('Return company must match the active tenant.');
        if ($company->gst_regime === 'composition') throw new DomainException('Composition taxpayers do not file this outward-supplies return type.');

        $start = CarbonImmutable::createFromFormat('!Y-m-d', $period.'-01');
        $end = $start->endOfMonth();
        $errors = [];
        $warnings = [];
        $taxHeads = ['cgst','sgst','utgst','igst','cess'];
        $documents = Invoice::query()->with(['items.item', 'party', 'voucher'])->where('status', 'posted')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();
        $corrections = SalesCorrection::query()->with(['lines.originalItem.item', 'invoice.party'])->where('status', 'posted')
            ->whereBetween('reporting_date', [$start->toDateString(), $end->toDateString()])->orderBy('reporting_date')->orderBy('id')->get();
        $purchases = PurchaseBill::query()->with(['items.item', 'party', 'voucher'])->where('status', 'posted')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();

        $out = ['b2b'=>[], 'b2cl'=>[], 'b2cs'=>[], 'exports'=>[], 'sez'=>[], 'notes'=>['registered'=>[], 'unregistered'=>[]],
            'nil_exempt_non_gst'=>['nil'=>[], 'exempt'=>[], 'non_gst'=>[]], 'hsn'=>[], 'documents_issued'=>[], 'reverse_charge'=>[],
            'purchases'=>['itc'=>['eligible'=>[], 'blocked'=>[], 'review'=>[]], 'rcm'=>[]]];
        foreach (['eligible','blocked','review'] as $bucket) $out['purchases']['itc'][$bucket] = array_fill_keys($taxHeads, '0.00');
        $b2cs = [];
        $hsn = [];
        $seenNumbers = [];

        foreach ($documents as $invoice) {
            $number = trim((string) $invoice->series).'/'.trim((string) $invoice->number);
            if (isset($seenNumbers[$number])) $errors[] = $this->issue('duplicate_document_number', "Duplicate posted invoice number {$number}.", $invoice->id);
            $seenNumbers[$number] = true;
            $recipient = $invoice->party_snapshot ?? [];
            $registered = ($recipient['registration_type'] ?? $invoice->party?->registration_type) === 'regular'
                || !empty($recipient['gstin']) || !empty($invoice->party?->gstin);
            $record = ['id'=>$invoice->id,'document_number'=>$number,'date'=>$invoice->date->toDateString(),'value'=>(string)$invoice->total,
                'place_of_supply'=>$invoice->place_of_supply,'supply_type'=>$invoice->supply_type,'zero_rated_mode'=>$invoice->zero_rated_mode,
                'recipient'=>$recipient,'items'=>[],'taxes'=>array_fill_keys($taxHeads,'0.00')];
            foreach ($invoice->items as $line) {
                $this->validateTaxRate($line->tax_rate_id, $invoice->date->toDateString(), $errors, $invoice->id);
                $record['items'][] = $this->line($line, $errors, $invoice->id, $annualTurnoverRupees);
                foreach ($taxHeads as $head) $record['taxes'][$head] = bcadd($record['taxes'][$head], (string) $line->{$head}, 2);
                if ($line->tax_category === 'taxable') $this->addHsn($hsn, $line, $errors, $annualTurnoverRupees, $invoice->id);
                else $out['nil_exempt_non_gst'][$line->tax_category][] = $this->line($line, $errors, $invoice->id, $annualTurnoverRupees);
            }
            if ($invoice->reverse_charge) $out['reverse_charge'][] = $record;
            if ($invoice->supply_type === 'export') $out['exports'][$invoice->zero_rated_mode === 'with_payment' ? 'with_payment' : 'without_payment'][] = $record;
            elseif ($invoice->supply_type === 'sez') $out['sez'][] = $record;
            elseif ($registered) {
                if (empty($recipient['gstin']) && empty($invoice->party?->gstin)) $errors[] = $this->issue('missing_recipient_gstin', "Registered recipient GSTIN missing for {$number}.", $invoice->id);
                $out['b2b'][] = $record;
            } elseif ($invoice->place_of_supply !== $company->state_code && bccomp((string)$invoice->total, (string)$this->threshold($period), 2) > 0) $out['b2cl'][] = $record;
            else {
                foreach ($record['items'] as $line) {
                    if ($line['tax_category'] !== 'taxable') continue;
                    $key = implode('|', [$invoice->place_of_supply, $line['rate'], $invoice->reverse_charge ? 'Y' : 'N']);
                    $b2cs[$key] ??= ['place_of_supply'=>$invoice->place_of_supply,'rate'=>$line['rate'],'taxable_value'=>'0.00','cess'=>'0.00','cgst'=>'0.00','sgst'=>'0.00','utgst'=>'0.00','igst'=>'0.00'];
                    $b2cs[$key]['taxable_value'] = bcadd($b2cs[$key]['taxable_value'], $line['taxable_amount'], 2);
                    $b2cs[$key]['cess'] = bcadd($b2cs[$key]['cess'], $line['cess'], 2);
                    foreach (['cgst','sgst','utgst','igst'] as $head) $b2cs[$key][$head] = bcadd($b2cs[$key][$head], $line[$head], 2);
                }
            }
        }
        $out['b2cs'] = array_values($b2cs);

        foreach ($corrections as $note) {
            $key = $note->series.'/'.$note->number;
            if (isset($seenNumbers[$key])) $errors[] = $this->issue('duplicate_document_number', "Duplicate posted note number {$key}.", $note->id);
            $seenNumbers[$key] = true;
            $snapshot = $note->party_snapshot ?? [];
            $registered = !empty($snapshot['gstin']);
            $out['notes'][$registered ? 'registered' : 'unregistered'][] = [
                'id'=>$note->id,'document_number'=>$key,'date'=>($note->reporting_date ?? $note->date)->toDateString(),
                'kind'=>$note->kind,'treatment'=>$note->treatment,'recipient'=>$snapshot,'place_of_supply'=>$note->original_snapshot['place_of_supply'] ?? null,
                'taxable_amount'=>(string)$note->taxable_amount,'cgst'=>(string)$note->cgst,'sgst'=>(string)$note->sgst,
                'utgst'=>(string)$note->utgst,'igst'=>(string)$note->igst,'cess'=>(string)$note->getAttribute('cess')];
            foreach ($note->lines as $line) $this->addHsn($hsn, $line, $errors, $annualTurnoverRupees, $note->id);
        }

        foreach ($purchases as $bill) {
            $record = ['id'=>$bill->id,'document_number'=>$bill->supplier_bill_number,'date'=>$bill->date->toDateString(),
                'reverse_charge'=>$bill->reverse_charge,'place_of_supply'=>$bill->place_of_supply,'taxes'=>array_fill_keys($taxHeads,'0.00'),'items'=>[]];
            foreach ($bill->items as $line) {
                $record['items'][] = $this->line($line, $errors, $bill->id, $annualTurnoverRupees);
                $bucket = match ($line->itc_eligibility) { 'eligible'=>'eligible','blocked'=>'blocked',default=>'review' };
                foreach ($taxHeads as $head) $out['purchases']['itc'][$bucket][$head] = bcadd($out['purchases']['itc'][$bucket][$head] ?? '0.00', (string)$line->{$head}, 2);
            }
            if ($bill->reverse_charge) $out['purchases']['rcm'][] = $record;
        }

        foreach ($out['b2b'] as $r) $this->validatePos($r, $errors);
        foreach ($out['b2cl'] as $r) $this->validatePos($r, $errors);
        foreach ($out['sez'] as $r) $this->validatePos($r, $errors);
        $out['hsn'] = array_values($hsn);
        $out['documents_issued'] = $this->documentsIssued($documents, $corrections);
        $out['validation'] = ['errors'=>$errors,'warnings'=>$warnings,'valid'=>count($errors)===0];
        $out['meta'] = ['company_id'=>$company->id,'period'=>$period,'turnover_rupees'=>$annualTurnoverRupees,
            'b2cl_threshold_rupees'=>$this->threshold($period),'built_at'=>now()->toIso8601String()];
        return $out;
    }

    private function line(object $line, array &$errors, int $documentId, ?int $turnover): array
    {
        $hsn = preg_replace('/\s+/', '', (string)$line->hsn_sac);
        $digits = $this->requiredHsnDigits($turnover);
        if ($line->tax_category === 'taxable' && (!$digits || !preg_match('/^\d{'.$digits.',8}$/', $hsn)))
            $errors[] = $this->issue('missing_or_invalid_hsn', 'HSN/SAC missing or fails the configured turnover rule; turnover/Hsn rule may be unverified.', $documentId);
        if ($line->tax_category === 'taxable' && !$this->rateIsVerified($line->tax_rate_id))
            $errors[] = $this->issue('unverified_tax_rate', 'Tax rate lacks an effective, verified tax-rate record.', $documentId);
        return ['description'=>(string)$line->description,'hsn_sac'=>$hsn,'quantity'=>(string)$line->quantity,
            'unit'=>$line->unit ?? $line->item?->unit ?? $line->originalItem?->item?->unit ?? null,'rate'=>(string)$line->rate,'tax_category'=>$line->tax_category,
            'taxable_amount'=>(string)$line->taxable_amount,'cgst'=>(string)$line->cgst,'sgst'=>(string)$line->sgst,
            'utgst'=>(string)$line->utgst,'igst'=>(string)$line->igst,'cess'=>(string)$line->cess,'total'=>(string)$line->total];
    }

    private function validateTaxRate(?int $id, string $date, array &$errors, int $documentId): void
    { if (!$this->rateIsVerified($id, $date)) $errors[]=$this->issue('unverified_tax_rate','Tax rate not marked verified/effective for the document date.',$documentId); }
    private function rateIsVerified(?int $id, ?string $date = null): bool
    {
        if (!$id) return false;
        $rate=TaxRate::query()->find($id);
        return $rate && $rate->is_verified && (!$date || $rate->effective_from->toDateString() <= $date) && (!$date || !$rate->effective_to || $rate->effective_to->toDateString() >= $date);
    }
    private function addHsn(array &$rows, object $line, array &$errors, ?int $turnover, int $documentId): void
    {
        $item=$line->item ?? $line->originalItem?->item; $code=preg_replace('/\s+/', '', (string)$line->hsn_sac);
        if (!$item?->unit) $errors[]=$this->issue('missing_uqc','UQC is missing for an HSN summary line.',$documentId);
        $digits=$this->requiredHsnDigits($turnover);
        if (!$digits || !preg_match('/^\d{'.$digits.',8}$/',$code)) { $errors[]=$this->issue('invalid_hsn','HSN/SAC does not meet a verified digit rule.',$documentId); return; }
        $key=$code.'|'.(string)$line->rate;
        $rows[$key]??=['hsn'=>$code,'description'=>$line->description,'uqc'=>$item?->unit,'quantity'=>'0.000','taxable_value'=>'0.00','igst'=>'0.00','cgst'=>'0.00','sgst'=>'0.00','utgst'=>'0.00','cess'=>'0.00'];
        $rows[$key]['quantity']=bcadd($rows[$key]['quantity'],(string)$line->quantity,3);
        foreach(['taxable_value'=>'taxable_amount','igst'=>'igst','cgst'=>'cgst','sgst'=>'sgst','utgst'=>'utgst','cess'=>'cess'] as $to=>$from)$rows[$key][$to]=bcadd($rows[$key][$to],(string)$line->{$from},2);
    }
    private function requiredHsnDigits(?int $turnover): ?int
    { if (!config('gst_returns.rules.hsn_digits.verified') || $turnover===null) return null; return $turnover<=500000000?4:6; }
    private function threshold(string $period): int
    { foreach(config('gst_returns.rules.b2cl_threshold.versions',[]) as $rule) if($period>=$rule['from']&&($rule['to']===null||$period<=$rule['to'])) {if(!$rule['verified'])throw new DomainException('B2CL threshold is unverified.');return $rule['value'];} throw new DomainException('B2CL threshold is not configured for period '.$period.'.'); }
    private function validatePos(array $record,array &$errors):void
    { if(!preg_match('/^\d{2}$/',(string)$record['place_of_supply']) || !State::query()->where('code',$record['place_of_supply'])->where('is_active',true)->exists())$errors[]=$this->issue('invalid_place_of_supply','Place of supply must be an active state/UT code.',$record['id']); }
    private function documentsIssued($invoices,$notes):array
    { $groups=[]; foreach($invoices as $d)$groups[$d->series][]=$d->number;foreach($notes as $d)$groups[$d->series][]=$d->number; $out=[];foreach($groups as $series=>$nums){sort($nums);$out[]=['nature_of_document'=>$series,'from'=>(string)min($nums),'to'=>(string)max($nums),'total'=>count($nums),'cancelled'=>0];}return $out; }
    private function issue(string $code,string $message,int $id):array{return ['code'=>$code,'message'=>$message,'document_id'=>$id,'severity'=>'error'];}
}
