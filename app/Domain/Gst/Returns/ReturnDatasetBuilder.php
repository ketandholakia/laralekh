<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns;

use App\Domain\Gst\Models\TaxRate;
use App\Domain\Gst\Returns\Models\Gstr2bImport;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\State;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\AdvanceReceiptVoucher;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;

final class ReturnDatasetBuilder
{
    /** Build an in-memory, tenant-scoped GSTR-1/GSTR-3B source dataset for YYYY-MM. */
    public function build(Company $company, string $period, ?int $annualTurnoverRupees = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new DomainException('Return period must use YYYY-MM.');
        }
        if ((int) $company->id !== app(TenantContext::class)->id()) {
            throw new DomainException('Return company must match the active tenant.');
        }
        if ($company->gst_regime === 'composition') {
            throw new DomainException('Composition taxpayers do not file this outward-supplies return type.');
        }

        $start = CarbonImmutable::createFromFormat('!Y-m-d', $period.'-01');
        $end = $start->endOfMonth();
        $errors = [];
        $warnings = [];
        $taxHeads = ['cgst', 'sgst', 'utgst', 'igst', 'cess'];
        $documents = Invoice::query()->with(['items.item', 'party', 'voucher'])->where('status', 'posted')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();
        $cancelledInvoices = Invoice::query()->where('status', 'cancelled')->whereNotNull('number')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();
        $corrections = SalesCorrection::query()->with(['lines.originalItem.item', 'invoice.party'])->where('status', 'posted')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('reporting_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(fn ($fallback) => $fallback->whereNull('reporting_date')->whereBetween('date', [$start->toDateString(), $end->toDateString()]));
            })
            ->orderByRaw('COALESCE(reporting_date, date)')->orderBy('id')->get();
        $purchases = PurchaseBill::query()->with(['items.item', 'party', 'voucher'])->where('status', 'posted')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();
        $advanceVouchers = AdvanceReceiptVoucher::query()->with('party')->where('status', 'posted')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();
        $gstr2bImport = Gstr2bImport::query()->where('period', $period)->first();

        $out = ['b2b' => [], 'b2cl' => [], 'b2cs' => [], 'exports' => [], 'sez' => [], 'notes' => ['registered' => [], 'unregistered' => []],
            'nil_exempt_non_gst' => ['nil' => [], 'exempt' => [], 'non_gst' => []], 'hsn' => [], 'documents_issued' => [], 'reverse_charge' => [],
            'purchases' => ['itc' => ['eligible' => [], 'blocked' => [], 'review' => []], 'rcm' => []],
            'advances' => ['received' => [], 'table_11a' => []],
            'gstr2b' => $gstr2bImport?->summary,
            'gstr2b_meta' => $gstr2bImport ? ['filename' => $gstr2bImport->source_filename, 'source_filename' => $gstr2bImport->source_filename, 'file_format' => $gstr2bImport->file_format, 'imported_at' => $gstr2bImport->imported_at->toIso8601String()] : null];
        foreach (['eligible', 'blocked', 'review'] as $bucket) {
            $out['purchases']['itc'][$bucket] = array_fill_keys($taxHeads, '0.00');
        }
        $b2cs = [];
        $b2csNonTaxable = ['nil_rated' => '0.00', 'exempt' => '0.00', 'non_gst' => '0.00'];
        $hsn = ['b2b' => [], 'b2c' => []];
        $seenNumbers = [];

        foreach ($documents as $invoice) {
            $number = trim((string) $invoice->series).'/'.trim((string) $invoice->number);
            if (isset($seenNumbers[$number])) {
                $errors[] = $this->issue('duplicate_document_number', "Duplicate posted invoice number {$number}.", $invoice->id);
            }
            $seenNumbers[$number] = true;
            $recipient = $invoice->party_snapshot ?? [];
            $registered = ($recipient['registration_type'] ?? $invoice->party?->registration_type) === 'regular'
                || ! empty($recipient['gstin']) || ! empty($invoice->party?->gstin);
            $record = ['id' => $invoice->id, 'document_number' => $number, 'date' => $invoice->date->toDateString(), 'value' => (string) $invoice->total,
                'place_of_supply' => $invoice->place_of_supply, 'supply_type' => $invoice->supply_type, 'zero_rated_mode' => $invoice->zero_rated_mode,
                'recipient' => $recipient, 'items' => [], 'taxes' => array_fill_keys($taxHeads, '0.00')];
            foreach ($invoice->items as $line) {
                $record['items'][] = $this->line($line, $errors, $invoice->id, $annualTurnoverRupees, $invoice->date->toDateString());
                foreach ($taxHeads as $head) {
                    $record['taxes'][$head] = bcadd($record['taxes'][$head], (string) $line->{$head}, 2);
                }
                if ($line->tax_category === 'taxable') {
                    $this->addHsn($hsn[$registered ? 'b2b' : 'b2c'], $line, $errors, $annualTurnoverRupees, $invoice->id);
                } else {
                    $bucket = match ($line->tax_category) {
                        'nil_rated' => 'nil','exempt' => 'exempt',default => 'non_gst'
                    };
                    $out['nil_exempt_non_gst'][$bucket][] = $this->line($line, $errors, $invoice->id, $annualTurnoverRupees, $invoice->date->toDateString());
                }
            }
            if ($invoice->reverse_charge) {
                $out['reverse_charge'][] = $record;
            }
            if ($invoice->supply_type === 'export') {
                $out['exports'][$invoice->zero_rated_mode === 'with_payment' ? 'with_payment' : 'without_payment'][] = $record;
            } elseif ($invoice->supply_type === 'sez') {
                $out['sez'][] = $record;
            } elseif ($registered) {
                if (empty($recipient['gstin']) && empty($invoice->party?->gstin)) {
                    $errors[] = $this->issue('missing_recipient_gstin', "Registered recipient GSTIN missing for {$number}.", $invoice->id);
                }
                $out['b2b'][] = $record;
            } elseif ($invoice->place_of_supply !== $company->state_code && bccomp((string) $invoice->total, (string) $this->threshold($period), 2) > 0) {
                $out['b2cl'][] = $record;
            } else {
                foreach ($record['items'] as $line) {
                    if ($line['tax_category'] !== 'taxable') {
                        $bucket = match ($line['tax_category']) {
                            'nil_rated' => 'nil_rated','exempt' => 'exempt',default => 'non_gst'
                        };
                        $b2csNonTaxable[$bucket] = bcadd($b2csNonTaxable[$bucket], $line['taxable_amount'], 2);

                        continue;
                    }
                    $key = implode('|', [$invoice->place_of_supply, $line['rate'], $invoice->reverse_charge ? 'Y' : 'N']);
                    $b2cs[$key] ??= ['place_of_supply' => $invoice->place_of_supply, 'rate' => $line['rate'], 'taxable_value' => '0.00', 'cess' => '0.00', 'cgst' => '0.00', 'sgst' => '0.00', 'utgst' => '0.00', 'igst' => '0.00'];
                    $b2cs[$key]['taxable_value'] = bcadd($b2cs[$key]['taxable_value'], $line['taxable_amount'], 2);
                    $b2cs[$key]['cess'] = bcadd($b2cs[$key]['cess'], $line['cess'], 2);
                    foreach (['cgst', 'sgst', 'utgst', 'igst'] as $head) {
                        $b2cs[$key][$head] = bcadd($b2cs[$key][$head], $line[$head], 2);
                    }
                }
            }
        }
        foreach ($corrections as $note) {
            $key = $note->series.'/'.$note->number;
            if (isset($seenNumbers[$key])) {
                $errors[] = $this->issue('duplicate_document_number', "Duplicate posted note number {$key}.", $note->id);
            }
            $seenNumbers[$key] = true;
            $snapshot = $note->party_snapshot ?? [];
            $registered = ! empty($snapshot['gstin']);
            $originalValue = (string) ($note->invoice?->total ?? '0.00');
            $originalPos = (string) ($note->original_snapshot['place_of_supply'] ?? $note->invoice?->place_of_supply ?? '');
            $unregisteredTable = $note->invoice?->supply_type === 'export' ? 'CDNUR' : ($originalPos !== $company->state_code && bccomp($originalValue, (string) $this->threshold($period), 2) > 0 ? 'CDNUR' : 'B2CS');
            $out['notes'][$registered ? 'registered' : 'unregistered'][] = [
                'id' => $note->id, 'document_number' => $key, 'date' => ($note->reporting_date ?? $note->date)->toDateString(),
                'kind' => $note->kind, 'treatment' => $note->treatment, 'recipient' => $snapshot, 'place_of_supply' => $originalPos,
                'value' => (string) $note->total, 'original_supply_type' => $note->invoice?->supply_type,
                'original_zero_rated_mode' => $note->invoice?->zero_rated_mode, 'original_value' => (string) ($note->invoice?->total ?? '0.00'),
                'gstr1_table' => $registered ? 'CDNR' : $unregisteredTable,
                'unregistered_type' => $note->invoice?->supply_type === 'export' ? ($note->invoice?->zero_rated_mode === 'with_payment' ? 'EXPWP' : 'EXPWOP') : 'B2CL',
                'items' => $note->lines->map(fn ($line) => ['description' => $line->description, 'hsn_sac' => $line->hsn_sac, 'quantity' => (string) $line->quantity,
                    'rate' => (string) $line->rate, 'taxable_amount' => (string) $line->taxable_amount, 'cgst' => (string) $line->cgst, 'sgst' => (string) $line->sgst,
                    'utgst' => (string) $line->utgst, 'igst' => (string) $line->igst, 'cess' => (string) $line->cess])->all(),
                'taxable_amount' => (string) $note->taxable_amount, 'cgst' => (string) $note->cgst, 'sgst' => (string) $note->sgst,
                'utgst' => (string) $note->utgst, 'igst' => (string) $note->igst, 'cess' => (string) $note->getAttribute('cess')];
            foreach ($note->lines as $line) {
                $this->addHsn($hsn[$registered ? 'b2b' : 'b2c'], $line, $errors, $annualTurnoverRupees, $note->id);
            }
            if (! $registered && $unregisteredTable === 'B2CS' && $note->treatment === 'gst') {
                foreach ($note->lines as $line) {
                    $rate = (string) $line->rate;
                    $key = implode('|', [$originalPos, $rate, 'N']);
                    $b2cs[$key] ??= ['place_of_supply' => $originalPos, 'rate' => $rate, 'taxable_value' => '0.00', 'cess' => '0.00', 'cgst' => '0.00', 'sgst' => '0.00', 'utgst' => '0.00', 'igst' => '0.00'];
                    $sign = $note->kind === 'credit' ? -1 : 1;
                    foreach (['taxable_value' => 'taxable_amount', 'cess' => 'cess', 'cgst' => 'cgst', 'sgst' => 'sgst', 'utgst' => 'utgst', 'igst' => 'igst'] as $to => $from) {
                        $amount = (string) $line->{$from};
                        $b2cs[$key][$to] = bcadd($b2cs[$key][$to], $sign < 0 ? bcsub('0.00', $amount, 2) : $amount, 2);
                    }
                }
            }
        }
        $out['b2cs'] = array_values($b2cs);
        $out['b2cs_non_taxable'] = $b2csNonTaxable;

        foreach ($purchases as $bill) {
            $record = ['id' => $bill->id, 'document_number' => $bill->supplier_bill_number, 'date' => $bill->date->toDateString(),
                'reverse_charge' => $bill->reverse_charge, 'place_of_supply' => $bill->place_of_supply, 'taxes' => array_fill_keys($taxHeads, '0.00'), 'items' => []];
            foreach ($bill->items as $line) {
                $record['items'][] = $this->line($line, $errors, $bill->id, $annualTurnoverRupees, $bill->date->toDateString());
                if ($line->tax_category === 'taxable' && ! config('gst_returns.rules.blocked_itc_rules.verified')) {
                    $errors[] = $this->issue('unverified_itc_eligibility', 'Input-tax eligibility has not been verified against current statutory restrictions; review this purchase with a CA.', $bill->id);
                }
                $bucket = match ($line->itc_eligibility) {
                    'eligible' => 'eligible','blocked' => 'blocked',default => 'review'
                };
                foreach ($taxHeads as $head) {
                    $out['purchases']['itc'][$bucket][$head] = bcadd($out['purchases']['itc'][$bucket][$head] ?? '0.00', (string) $line->{$head}, 2);
                }
            }
            if ($bill->reverse_charge) {
                $out['purchases']['rcm'][] = $record;
            }
        }

        $table11a = [];
        foreach ($advanceVouchers as $advance) {
            $number = trim((string) $advance->series).'/'.trim((string) $advance->number);
            if (isset($seenNumbers[$number])) {
                $errors[] = $this->issue('duplicate_document_number', "Duplicate posted advance voucher number {$number}.", $advance->id);
            }
            $seenNumbers[$number] = true;
            $recipient = $advance->party_snapshot ?? [];
            if (! preg_match('/^\d{2}$/', (string) $advance->place_of_supply) || ! State::query()->where('code', $advance->place_of_supply)->where('is_active', true)->exists()) {
                $errors[] = $this->issue('invalid_place_of_supply', "Advance voucher {$number} place of supply must be an active state/UT code.", $advance->id);
            }
            $rateStr = number_format((float) $advance->tax_rate, 2, '.', '');
            $record = [
                'id' => $advance->id,
                'document_number' => $number,
                'date' => $advance->date->toDateString(),
                'place_of_supply' => (string) $advance->place_of_supply,
                'supply_type' => $advance->supply_type,
                'gross_amount' => (string) $advance->gross_amount,
                'taxable_amount' => (string) $advance->taxable_amount,
                'rate' => $rateStr,
                'is_rate_determinable' => (bool) $advance->is_rate_determinable,
                'is_interstate_determinable' => (bool) $advance->is_interstate_determinable,
                'recipient' => $recipient,
                'cgst' => (string) $advance->cgst,
                'sgst' => (string) $advance->sgst,
                'utgst' => (string) $advance->utgst,
                'igst' => (string) $advance->igst,
                'cess' => (string) $advance->cess,
            ];
            $out['advances']['received'][] = $record;

            if ($advance->supply_type === 'services' && bccomp((string) $advance->taxable_amount, '0.00', 2) > 0) {
                $isInter = bccomp((string) $advance->igst, '0.00', 2) > 0;
                $key = ($isInter ? 'inter' : 'intra').'|'.$advance->place_of_supply.'|'.$rateStr;
                $table11a[$key] ??= [
                    'place_of_supply' => (string) $advance->place_of_supply,
                    'rate' => $rateStr,
                    'is_interstate' => $isInter,
                    'gross_amount' => '0.00',
                    'taxable_amount' => '0.00',
                    'cgst' => '0.00',
                    'sgst' => '0.00',
                    'utgst' => '0.00',
                    'igst' => '0.00',
                    'cess' => '0.00',
                ];
                $table11a[$key]['gross_amount'] = bcadd($table11a[$key]['gross_amount'], (string) $advance->gross_amount, 2);
                $table11a[$key]['taxable_amount'] = bcadd($table11a[$key]['taxable_amount'], (string) $advance->taxable_amount, 2);
                foreach ($taxHeads as $head) {
                    $table11a[$key][$head] = bcadd($table11a[$key][$head], (string) $advance->{$head}, 2);
                }
            }
        }
        $out['advances']['table_11a'] = array_values($table11a);

        foreach ($out['b2b'] as $r) {
            $this->validatePos($r, $errors);
        }
        foreach ($out['b2cl'] as $r) {
            $this->validatePos($r, $errors);
        }
        foreach ($out['sez'] as $r) {
            $this->validatePos($r, $errors);
        }
        foreach ($out['b2cs'] as $r) {
            if (! State::query()->where('code', $r['place_of_supply'])->where('is_active', true)->exists()) {
                $errors[] = $this->issue('invalid_place_of_supply', 'B2CS place of supply must be an active state/UT code.', 0);
            }
        }
        $out['hsn'] = ['b2b' => array_values($hsn['b2b']), 'b2c' => array_values($hsn['b2c'])];
        $out['documents_issued'] = $this->documentsIssued($documents, $corrections, $cancelledInvoices, $advanceVouchers);
        $out['validation'] = ['errors' => $errors, 'warnings' => $warnings, 'valid' => count($errors) === 0];
        $out['meta'] = ['company_id' => $company->id, 'period' => $period, 'turnover_rupees' => $annualTurnoverRupees,
            'b2cl_threshold_rupees' => $this->threshold($period), 'built_at' => now()->toIso8601String()];

        return $out;
    }

    private function line(object $line, array &$errors, int $documentId, ?int $turnover, ?string $documentDate = null, bool $checkRate = true): array
    {
        $hsn = preg_replace('/\s+/', '', (string) $line->hsn_sac);
        $digits = $this->requiredHsnDigits($turnover);
        if ($line->tax_category === 'taxable' && (! $digits || ! preg_match('/^\d{'.$digits.',8}$/', $hsn))) {
            $errors[] = $this->issue('missing_or_invalid_hsn', 'HSN/SAC missing or fails the configured turnover rule; turnover/Hsn rule may be unverified.', $documentId);
        }
        if ($checkRate && $line->tax_category === 'taxable' && ! $this->rateIsVerified($line->tax_rate_id, $documentDate)) {
            $errors[] = $this->issue('unverified_tax_rate', 'Tax rate lacks an effective, verified tax-rate record.', $documentId);
        }

        return ['description' => (string) $line->description, 'hsn_sac' => $hsn, 'quantity' => (string) $line->quantity,
            'unit' => $line->unit ?? $line->item?->unit ?? $line->originalItem?->item?->unit ?? null, 'rate' => (string) $line->rate, 'tax_category' => $line->tax_category,
            'taxable_amount' => (string) $line->taxable_amount, 'cgst' => (string) $line->cgst, 'sgst' => (string) $line->sgst,
            'utgst' => (string) $line->utgst, 'igst' => (string) $line->igst, 'cess' => (string) $line->cess, 'total' => (string) $line->total];
    }

    private function validateTaxRate(?int $id, string $date, array &$errors, int $documentId): void
    {
        if (! $this->rateIsVerified($id, $date)) {
            $errors[] = $this->issue('unverified_tax_rate', 'Tax rate not marked verified/effective for the document date.', $documentId);
        }
    }

    private function rateIsVerified(?int $id, ?string $date = null): bool
    {
        if (! $id) {
            return false;
        }
        $rate = TaxRate::query()->find($id);

        return $rate && $rate->is_verified && (! $date || $rate->effective_from->toDateString() <= $date) && (! $date || ! $rate->effective_to || $rate->effective_to->toDateString() >= $date);
    }

    private function addHsn(array &$rows, object $line, array &$errors, ?int $turnover, int $documentId): void
    {
        $item = $line->item ?? $line->originalItem?->item;
        $code = preg_replace('/\s+/', '', (string) $line->hsn_sac);
        if (! $item?->unit) {
            $errors[] = $this->issue('missing_uqc', 'UQC is missing for an HSN summary line.', $documentId);
        }
        $digits = $this->requiredHsnDigits($turnover);
        if (! $digits || ! preg_match('/^\d{'.$digits.',8}$/', $code)) {
            $errors[] = $this->issue('invalid_hsn', 'HSN/SAC does not meet a verified digit rule.', $documentId);

            return;
        }
        $key = $code.'|'.(string) $line->rate;
        $rows[$key] ??= ['hsn' => $code, 'description' => $line->description, 'uqc' => $item?->unit, 'rate' => (string) $line->rate, 'quantity' => '0.000', 'taxable_value' => '0.00', 'igst' => '0.00', 'cgst' => '0.00', 'sgst' => '0.00', 'utgst' => '0.00', 'cess' => '0.00'];
        $rows[$key]['quantity'] = bcadd($rows[$key]['quantity'], (string) $line->quantity, 3);
        foreach (['taxable_value' => 'taxable_amount', 'igst' => 'igst', 'cgst' => 'cgst', 'sgst' => 'sgst', 'utgst' => 'utgst', 'cess' => 'cess'] as $to => $from) {
            $rows[$key][$to] = bcadd($rows[$key][$to], (string) $line->{$from}, 2);
        }
    }

    private function requiredHsnDigits(?int $turnover): ?int
    {
        if (! config('gst_returns.rules.hsn_digits.verified') || $turnover === null) {
            return null;
        }

return $turnover <= 50000000 ? 4 : 6;
    }

    private function threshold(string $period): int
    {
        foreach (config('gst_returns.rules.b2cl_threshold.versions', []) as $rule) {
            if ($period >= $rule['from'] && ($rule['to'] === null || $period <= $rule['to'])) {
                if (! $rule['verified']) {
                    throw new DomainException('B2CL threshold is unverified.');
                }

return $rule['value'];
            }
        } throw new DomainException('B2CL threshold is not configured for period '.$period.'.');
    }

    private function validatePos(array $record, array &$errors): void
    {
        if (! preg_match('/^\d{2}$/', (string) $record['place_of_supply']) || ! State::query()->where('code', $record['place_of_supply'])->where('is_active', true)->exists()) {
            $errors[] = $this->issue('invalid_place_of_supply', 'Place of supply must be an active state/UT code.', $record['id']);
        }
    }

    private function documentsIssued($invoices, $notes, $cancelledInvoices, $advanceVouchers = []): array
    {
        $groups = ['Invoices for outward supply' => ['issued' => [], 'cancelled' => []]];
        foreach ($invoices as $document) {
            $groups['Invoices for outward supply']['issued'][] = (int) $document->number;
        }
        foreach ($cancelledInvoices as $document) {
            $groups['Invoices for outward supply']['cancelled'][] = (int) $document->number;
        }
        foreach ($notes as $document) {
            $nature = $document->kind === 'credit' ? 'Credit Note' : 'Debit Note';
            $groups[$nature] ??= ['issued' => [], 'cancelled' => []];
            $groups[$nature]['issued'][] = (int) $document->number;
        }
        foreach ($advanceVouchers as $document) {
            $groups['Receipt voucher'] ??= ['issued' => [], 'cancelled' => []];
            $groups['Receipt voucher']['issued'][] = (int) $document->number;
        }
        $rows = [];
        foreach ($groups as $nature => $numbers) {
            $issued = array_merge($numbers['issued'], $numbers['cancelled']);
            if ($issued === []) {
                continue;
            }sort($issued, SORT_NUMERIC);
            $rows[] = ['nature_of_document' => $nature, 'from' => (string) min($issued), 'to' => (string) max($issued),
                'total' => count($issued), 'cancelled' => count($numbers['cancelled'])];
        }

        return $rows;
    }

    private function issue(string $code,string $message,int $id): array
    {
        return ['code' => $code, 'message' => $message, 'document_id' => $id, 'severity' => 'error'];
    }
}
