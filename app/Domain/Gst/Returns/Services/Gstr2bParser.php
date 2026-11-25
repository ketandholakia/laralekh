<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns\Services;

use Carbon\Carbon;
use DomainException;

final class Gstr2bParser
{
    /**
     * Parse GSTR-2B content (JSON or CSV) into normalized documents.
     *
     * @return array{format: string, gstin: ?string, period: ?string, documents: array<int, array<string, mixed>>}
     */
    public function parse(string $content, ?string $filename = null): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new DomainException('GSTR-2B import content is empty.');
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return $this->parseJson($trimmed);
        }

        return $this->parseCsv($trimmed);
    }

    private function parseJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DomainException('Invalid GSTR-2B JSON format: '.$e->getMessage());
        }

        $gstin = $data['gstin'] ?? $data['data']['gstin'] ?? null;
        $rawFp = $data['fp'] ?? $data['data']['fp'] ?? null;
        $period = null;
        if ($rawFp && preg_match('/^(\d{2})(\d{4})$/', (string) $rawFp, $m)) {
            // Converts MMYYYY to YYYY-MM
            $period = $m[2].'-'.$m[1];
        }

        $docdata = $data['data']['docdata'] ?? $data['docdata'] ?? $data;
        $b2b = $docdata['b2b'] ?? [];
        $cdnr = $docdata['cdnr'] ?? [];

        $documents = [];

        foreach ($b2b as $supplierGroup) {
            $ctin = strtoupper(trim((string) ($supplierGroup['ctin'] ?? '')));
            $trdnm = trim((string) ($supplierGroup['trdnm'] ?? $supplierGroup['lgnm'] ?? ''));

            foreach ($supplierGroup['inv'] ?? [] as $inv) {
                $inum = trim((string) ($inv['inum'] ?? ''));
                if ($inum === '') {
                    continue;
                }
                $date = $this->parseDate((string) ($inv['dt'] ?? ''));
                $pos = str_pad(trim((string) ($inv['pos'] ?? '')), 2, '0', STR_PAD_LEFT);
                if (strlen($pos) > 2) {
                    $pos = substr($pos, 0, 2);
                }
                $rev = strtoupper(trim((string) ($inv['rev'] ?? 'N'))) === 'Y';
                $itcavl = strtoupper(trim((string) ($inv['itcavl'] ?? 'Y'))) === 'Y';
                $rsn = trim((string) ($inv['rsn'] ?? '')) ?: null;

                $taxable = '0.00';
                $cgst = '0.00';
                $sgst = '0.00';
                $utgst = '0.00';
                $igst = '0.00';
                $cess = '0.00';
                $rate = '0.00';

                foreach ($inv['items'] ?? [] as $line) {
                    $rate = number_format((float) ($line['rt'] ?? 0), 2, '.', '');
                    $taxable = bcadd($taxable, number_format((float) ($line['txval'] ?? 0), 2, '.', ''), 2);
                    $cgst = bcadd($cgst, number_format((float) ($line['camt'] ?? 0), 2, '.', ''), 2);
                    $sgst = bcadd($sgst, number_format((float) ($line['samt'] ?? 0), 2, '.', ''), 2);
                    $igst = bcadd($igst, number_format((float) ($line['iamt'] ?? 0), 2, '.', ''), 2);
                    $cess = bcadd($cess, number_format((float) ($line['csamt'] ?? 0), 2, '.', ''), 2);
                }

                $totalTax = bcadd(bcadd($cgst, $sgst, 2), bcadd($igst, $cess, 2), 2);
                $totalVal = (string) ($inv['val'] ?? bcadd($taxable, $totalTax, 2));

                $documents[] = [
                    'supplier_gstin' => $ctin,
                    'supplier_name' => $trdnm,
                    'document_number' => $inum,
                    'document_type' => 'invoice',
                    'date' => $date,
                    'place_of_supply' => $pos,
                    'reverse_charge' => $rev,
                    'rate' => $rate,
                    'taxable_amount' => $taxable,
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'utgst' => $utgst,
                    'igst' => $igst,
                    'cess' => $cess,
                    'total_tax' => $totalTax,
                    'total_value' => number_format((float) $totalVal, 2, '.', ''),
                    'itc_available' => $itcavl,
                    'ineligibility_reason' => $rsn,
                ];
            }
        }

        foreach ($cdnr as $supplierGroup) {
            $ctin = strtoupper(trim((string) ($supplierGroup['ctin'] ?? '')));
            $trdnm = trim((string) ($supplierGroup['trdnm'] ?? $supplierGroup['lgnm'] ?? ''));

            foreach ($supplierGroup['nt'] ?? [] as $nt) {
                $ntNum = trim((string) ($nt['nt_num'] ?? ''));
                if ($ntNum === '') {
                    continue;
                }
                $date = $this->parseDate((string) ($nt['dt'] ?? ''));
                $pos = str_pad(trim((string) ($nt['pos'] ?? '')), 2, '0', STR_PAD_LEFT);
                if (strlen($pos) > 2) {
                    $pos = substr($pos, 0, 2);
                }
                $ntTyp = strtoupper(trim((string) ($nt['ntty'] ?? 'C')));
                $docType = $ntTyp === 'C' ? 'credit_note' : 'debit_note';
                $itcavl = strtoupper(trim((string) ($nt['itcavl'] ?? 'Y'))) === 'Y';
                $rsn = trim((string) ($nt['rsn'] ?? '')) ?: null;

                $taxable = '0.00';
                $cgst = '0.00';
                $sgst = '0.00';
                $utgst = '0.00';
                $igst = '0.00';
                $cess = '0.00';
                $rate = '0.00';

                foreach ($nt['items'] ?? [] as $line) {
                    $rate = number_format((float) ($line['rt'] ?? 0), 2, '.', '');
                    $taxable = bcadd($taxable, number_format((float) ($line['txval'] ?? 0), 2, '.', ''), 2);
                    $cgst = bcadd($cgst, number_format((float) ($line['camt'] ?? 0), 2, '.', ''), 2);
                    $sgst = bcadd($sgst, number_format((float) ($line['samt'] ?? 0), 2, '.', ''), 2);
                    $igst = bcadd($igst, number_format((float) ($line['iamt'] ?? 0), 2, '.', ''), 2);
                    $cess = bcadd($cess, number_format((float) ($line['csamt'] ?? 0), 2, '.', ''), 2);
                }

                $totalTax = bcadd(bcadd($cgst, $sgst, 2), bcadd($igst, $cess, 2), 2);
                $totalVal = (string) ($nt['val'] ?? bcadd($taxable, $totalTax, 2));

                $documents[] = [
                    'supplier_gstin' => $ctin,
                    'supplier_name' => $trdnm,
                    'document_number' => $ntNum,
                    'document_type' => $docType,
                    'date' => $date,
                    'place_of_supply' => $pos,
                    'reverse_charge' => false,
                    'rate' => $rate,
                    'taxable_amount' => $taxable,
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'utgst' => $utgst,
                    'igst' => $igst,
                    'cess' => $cess,
                    'total_tax' => $totalTax,
                    'total_value' => number_format((float) $totalVal, 2, '.', ''),
                    'itc_available' => $itcavl,
                    'ineligibility_reason' => $rsn,
                ];
            }
        }

        return [
            'format' => 'json',
            'gstin' => $gstin,
            'period' => $period,
            'documents' => $documents,
        ];
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $firstRow = fgetcsv($stream);
        if ($firstRow === false || $firstRow === []) {
            fclose($stream);
            throw new DomainException('GSTR-2B CSV header is missing or empty.');
        }

        $headerMap = [];
        foreach ($firstRow as $idx => $heading) {
            $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $heading));
            $headerMap[$normalized] = $idx;
        }

        $findIdx = function (array $candidates) use ($headerMap): ?int {
            foreach ($candidates as $candidate) {
                if (isset($headerMap[$candidate])) {
                    return $headerMap[$candidate];
                }
            }

            return null;
        };

        $gstinIdx = $findIdx(['gstinofsupplier', 'suppliergstin', 'ctin', 'gstin']);
        $nameIdx = $findIdx(['tradelegalname', 'trdnm', 'legalname', 'suppliername', 'tradename']);
        $numIdx = $findIdx(['invoicenumber', 'invoiceno', 'invoicenum', 'billnumber', 'documentnumber', 'inum']);
        $dateIdx = $findIdx(['invoicedate', 'date', 'billdate', 'dt']);
        $valIdx = $findIdx(['invoicevalue', 'value', 'totalvalue', 'total', 'val']);
        $posIdx = $findIdx(['placeofsupply', 'pos', 'placeofsupplycode']);
        $revIdx = $findIdx(['supplyattractreversecharge', 'reversecharge', 'rev']);
        $rateIdx = $findIdx(['rate', 'taxrate', 'rt']);
        $txvalIdx = $findIdx(['taxablevalue', 'taxableamount', 'txval']);
        $iamtIdx = $findIdx(['integratedtax', 'integratedtaxamount', 'igst', 'iamt']);
        $camtIdx = $findIdx(['centraltax', 'centraltaxamount', 'cgst', 'camt']);
        $samtIdx = $findIdx(['stateuttax', 'statetax', 'sgst', 'utgst', 'samt']);
        $csamtIdx = $findIdx(['cess', 'cessamount', 'csamt']);
        $itcIdx = $findIdx(['itcavailability', 'itcavailable', 'itcavl']);
        $rsnIdx = $findIdx(['reason', 'rsn', 'ineligibilityreason']);

        if ($gstinIdx === null || $numIdx === null) {
            fclose($stream);
            throw new DomainException('GSTR-2B CSV must contain Supplier GSTIN and Invoice Number columns.');
        }

        // Group rows by supplier GSTIN + Invoice number (since an invoice may have multiple tax-rate lines)
        $grouped = [];

        while (($row = fgetcsv($stream)) !== false) {
            if ($row === [null] || count($row) < 2) {
                continue;
            }

            $ctin = strtoupper(trim((string) ($row[$gstinIdx] ?? '')));
            $inum = trim((string) ($row[$numIdx] ?? ''));
            if ($ctin === '' || $inum === '') {
                continue;
            }

            $key = $ctin.'|'.$inum;

            $dateRaw = $dateIdx !== null ? (string) ($row[$dateIdx] ?? '') : '';
            $posRaw = $posIdx !== null ? (string) ($row[$posIdx] ?? '') : '';
            $pos = preg_replace('/^(\d{2}).*$/', '$1', trim($posRaw));
            if (strlen($pos) === 1) {
                $pos = '0'.$pos;
            }

            $revRaw = $revIdx !== null ? strtoupper(trim((string) ($row[$revIdx] ?? 'N'))) : 'N';
            $itcRaw = $itcIdx !== null ? strtoupper(trim((string) ($row[$itcIdx] ?? 'Y'))) : 'Y';
            $rsnRaw = $rsnIdx !== null ? trim((string) ($row[$rsnIdx] ?? '')) : '';

            $txval = $txvalIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$txvalIdx] ?? 0)), 2, '.', '') : '0.00';
            $camt = $camtIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$camtIdx] ?? 0)), 2, '.', '') : '0.00';
            $samt = $samtIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$samtIdx] ?? 0)), 2, '.', '') : '0.00';
            $iamt = $iamtIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$iamtIdx] ?? 0)), 2, '.', '') : '0.00';
            $csamt = $csamtIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$csamtIdx] ?? 0)), 2, '.', '') : '0.00';
            $rate = $rateIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$rateIdx] ?? 0)), 2, '.', '') : '0.00';
            $val = $valIdx !== null ? number_format((float) preg_replace('/[^\d.]/', '', (string) ($row[$valIdx] ?? 0)), 2, '.', '') : '0.00';

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'supplier_gstin' => $ctin,
                    'supplier_name' => $nameIdx !== null ? trim((string) ($row[$nameIdx] ?? '')) : '',
                    'document_number' => $inum,
                    'document_type' => 'invoice',
                    'date' => $this->parseDate($dateRaw),
                    'place_of_supply' => $pos,
                    'reverse_charge' => in_array($revRaw, ['Y', 'YES', 'TRUE'], true),
                    'rate' => $rate,
                    'taxable_amount' => '0.00',
                    'cgst' => '0.00',
                    'sgst' => '0.00',
                    'utgst' => '0.00',
                    'igst' => '0.00',
                    'cess' => '0.00',
                    'total_tax' => '0.00',
                    'total_value' => $val,
                    'itc_available' => in_array($itcRaw, ['Y', 'YES', 'TRUE'], true),
                    'ineligibility_reason' => $rsnRaw ?: null,
                ];
            }

            $grouped[$key]['taxable_amount'] = bcadd($grouped[$key]['taxable_amount'], $txval, 2);
            $grouped[$key]['cgst'] = bcadd($grouped[$key]['cgst'], $camt, 2);
            $grouped[$key]['sgst'] = bcadd($grouped[$key]['sgst'], $samt, 2);
            $grouped[$key]['igst'] = bcadd($grouped[$key]['igst'], $iamt, 2);
            $grouped[$key]['cess'] = bcadd($grouped[$key]['cess'], $csamt, 2);
            $grouped[$key]['total_tax'] = bcadd(
                bcadd($grouped[$key]['cgst'], $grouped[$key]['sgst'], 2),
                bcadd($grouped[$key]['igst'], $grouped[$key]['cess'], 2),
                2
            );

            if (bccomp($val, '0.00', 2) > 0) {
                $grouped[$key]['total_value'] = $val;
            }
        }

        fclose($stream);

        // Compute total_value where missing
        foreach ($grouped as &$doc) {
            if (bccomp($doc['total_value'], '0.00', 2) === 0) {
                $doc['total_value'] = bcadd($doc['taxable_amount'], $doc['total_tax'], 2);
            }
        }

        return [
            'format' => 'csv',
            'gstin' => null,
            'period' => null,
            'documents' => array_values($grouped),
        ];
    }

    private function parseDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return now()->toDateString();
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'j-M-y', 'd-M-Y'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $raw);
                if ($c) {
                    return $c->toDateString();
                }
            } catch (\Throwable) {
                // Try next
            }
        }

        return $raw;
    }
}
