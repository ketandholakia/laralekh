<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns\Services;

use App\Domain\Masters\Models\Company;
use App\Domain\Purchase\Models\PurchaseBill;
use Carbon\CarbonImmutable;

final class Gstr2bReconciler
{
    /**
     * Reconciles booked purchase bills against parsed GSTR-2B inward documents.
     *
     * @param  array<int, array<string, mixed>>  $portalDocuments
     * @return array<string, mixed>
     */
    public function reconcile(Company $company, string $period, array $portalDocuments): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $period.'-01');
        $end = $start->endOfMonth();

        $booksBills = PurchaseBill::query()
            ->with(['party'])
            ->where('status', 'posted')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        // Index portal documents by normalized match key: SUPPLIER_GSTIN|BILL_NUMBER
        $portalIndex = [];
        $portalMatched = [];
        foreach ($portalDocuments as $idx => $pDoc) {
            $key = strtoupper(trim((string) ($pDoc['supplier_gstin'] ?? ''))).'|'.strtoupper(trim((string) ($pDoc['document_number'] ?? '')));
            $portalIndex[$key] = [
                'index' => $idx,
                'data' => $pDoc,
            ];
            $portalMatched[$key] = false;
        }

        $records = [];

        $booksTotals = [
            'count' => 0,
            'taxable_value' => '0.00',
            'cgst' => '0.00',
            'sgst' => '0.00',
            'utgst' => '0.00',
            'igst' => '0.00',
            'cess' => '0.00',
            'total_tax' => '0.00',
        ];

        $matchedSummary = ['count' => 0, 'taxable_value' => '0.00', 'tax' => '0.00'];
        $mismatchSummary = ['count' => 0, 'taxable_value' => '0.00', 'books_tax' => '0.00', 'portal_tax' => '0.00', 'difference' => '0.00'];
        $missingInPortalSummary = ['count' => 0, 'taxable_value' => '0.00', 'tax' => '0.00'];
        $unregisteredSummary = ['count' => 0, 'taxable_value' => '0.00', 'tax' => '0.00'];

        foreach ($booksBills as $bill) {
            $booksTotals['count']++;
            $booksTotals['taxable_value'] = bcadd($booksTotals['taxable_value'], (string) $bill->taxable_amount, 2);
            $booksTotals['cgst'] = bcadd($booksTotals['cgst'], (string) $bill->cgst, 2);
            $booksTotals['sgst'] = bcadd($booksTotals['sgst'], (string) $bill->sgst, 2);
            $booksTotals['utgst'] = bcadd($booksTotals['utgst'], (string) $bill->utgst, 2);
            $booksTotals['igst'] = bcadd($booksTotals['igst'], (string) $bill->igst, 2);
            $booksTotals['cess'] = bcadd($booksTotals['cess'], (string) $bill->cess, 2);
            $bTax = bcadd(
                bcadd((string) $bill->cgst, (string) $bill->sgst, 2),
                bcadd(bcadd((string) $bill->utgst, (string) $bill->igst, 2), (string) $bill->cess, 2),
                2
            );
            $booksTotals['total_tax'] = bcadd($booksTotals['total_tax'], $bTax, 2);

            $supplierGstin = strtoupper(trim((string) ($bill->party?->gstin ?? '')));
            $billNum = strtoupper(trim((string) $bill->supplier_bill_number));

            if ($supplierGstin === '') {
                // Inward supply from unregistered person (UR) - exempt from 2B
                $unregisteredSummary['count']++;
                $unregisteredSummary['taxable_value'] = bcadd($unregisteredSummary['taxable_value'], (string) $bill->taxable_amount, 2);
                $unregisteredSummary['tax'] = bcadd($unregisteredSummary['tax'], $bTax, 2);

                $records[] = [
                    'status' => 'unregistered_inward',
                    'supplier_gstin' => null,
                    'supplier_name' => $bill->party?->name ?? 'Unregistered Supplier',
                    'document_number' => $bill->supplier_bill_number,
                    'bill_id' => $bill->id,
                    'date' => $bill->date->toDateString(),
                    'books_taxable' => (string) $bill->taxable_amount,
                    'books_tax' => $bTax,
                    'portal_taxable' => '0.00',
                    'portal_tax' => '0.00',
                    'difference' => '0.00',
                    'itc_available' => true,
                    'ineligibility_reason' => null,
                ];

                continue;
            }

            $key = $supplierGstin.'|'.$billNum;

            if (isset($portalIndex[$key])) {
                $p = $portalIndex[$key]['data'];
                $portalMatched[$key] = true;

                $pTax = (string) ($p['total_tax'] ?? '0.00');
                $pTaxable = (string) ($p['taxable_amount'] ?? '0.00');
                $diff = bcsub($bTax, $pTax, 2);

                // Check ₹1.00 rounding tolerance
                $isMismatch = bccomp(bcsub('0.00', '1.00', 2), $diff, 2) > 0 || bccomp($diff, '1.00', 2) > 0;

                if ($isMismatch) {
                    $mismatchSummary['count']++;
                    $mismatchSummary['taxable_value'] = bcadd($mismatchSummary['taxable_value'], (string) $bill->taxable_amount, 2);
                    $mismatchSummary['books_tax'] = bcadd($mismatchSummary['books_tax'], $bTax, 2);
                    $mismatchSummary['portal_tax'] = bcadd($mismatchSummary['portal_tax'], $pTax, 2);
                    $mismatchSummary['difference'] = bcadd($mismatchSummary['difference'], $diff, 2);

                    $records[] = [
                        'status' => 'tax_mismatch',
                        'supplier_gstin' => $supplierGstin,
                        'supplier_name' => $bill->party?->name ?? ($p['supplier_name'] ?? ''),
                        'document_number' => $bill->supplier_bill_number,
                        'bill_id' => $bill->id,
                        'date' => $bill->date->toDateString(),
                        'books_taxable' => (string) $bill->taxable_amount,
                        'books_tax' => $bTax,
                        'portal_taxable' => $pTaxable,
                        'portal_tax' => $pTax,
                        'difference' => $diff,
                        'itc_available' => (bool) ($p['itc_available'] ?? true),
                        'ineligibility_reason' => $p['ineligibility_reason'] ?? null,
                    ];
                } else {
                    $matchedSummary['count']++;
                    $matchedSummary['taxable_value'] = bcadd($matchedSummary['taxable_value'], (string) $bill->taxable_amount, 2);
                    $matchedSummary['tax'] = bcadd($matchedSummary['tax'], $bTax, 2);

                    $records[] = [
                        'status' => 'matched',
                        'supplier_gstin' => $supplierGstin,
                        'supplier_name' => $bill->party?->name ?? ($p['supplier_name'] ?? ''),
                        'document_number' => $bill->supplier_bill_number,
                        'bill_id' => $bill->id,
                        'date' => $bill->date->toDateString(),
                        'books_taxable' => (string) $bill->taxable_amount,
                        'books_tax' => $bTax,
                        'portal_taxable' => $pTaxable,
                        'portal_tax' => $pTax,
                        'difference' => '0.00',
                        'itc_available' => (bool) ($p['itc_available'] ?? true),
                        'ineligibility_reason' => $p['ineligibility_reason'] ?? null,
                    ];
                }
            } else {
                // Found in books with GSTIN, but missing from GSTR-2B portal statement
                $missingInPortalSummary['count']++;
                $missingInPortalSummary['taxable_value'] = bcadd($missingInPortalSummary['taxable_value'], (string) $bill->taxable_amount, 2);
                $missingInPortalSummary['tax'] = bcadd($missingInPortalSummary['tax'], $bTax, 2);

                $records[] = [
                    'status' => 'missing_in_portal',
                    'supplier_gstin' => $supplierGstin,
                    'supplier_name' => $bill->party?->name ?? '',
                    'document_number' => $bill->supplier_bill_number,
                    'bill_id' => $bill->id,
                    'date' => $bill->date->toDateString(),
                    'books_taxable' => (string) $bill->taxable_amount,
                    'books_tax' => $bTax,
                    'portal_taxable' => '0.00',
                    'portal_tax' => '0.00',
                    'difference' => $bTax,
                    'itc_available' => false,
                    'ineligibility_reason' => 'Not reflected in GSTR-2B portal statement (CGST s. 16(2)(aa))',
                ];
            }
        }

        // Process portal documents that were not matched to any booked bill (Missing in Books)
        $missingInBooksSummary = ['count' => 0, 'taxable_value' => '0.00', 'tax' => '0.00'];
        $portalTotals = [
            'count' => count($portalDocuments),
            'taxable_value' => '0.00',
            'cgst' => '0.00',
            'sgst' => '0.00',
            'utgst' => '0.00',
            'igst' => '0.00',
            'cess' => '0.00',
            'total_tax' => '0.00',
            'itc_available_tax' => '0.00',
            'itc_ineligible_tax' => '0.00',
        ];

        foreach ($portalDocuments as $pDoc) {
            $taxable = (string) ($pDoc['taxable_amount'] ?? '0.00');
            $tax = (string) ($pDoc['total_tax'] ?? '0.00');

            $portalTotals['taxable_value'] = bcadd($portalTotals['taxable_value'], $taxable, 2);
            $portalTotals['cgst'] = bcadd($portalTotals['cgst'], (string) ($pDoc['cgst'] ?? '0.00'), 2);
            $portalTotals['sgst'] = bcadd($portalTotals['sgst'], (string) ($pDoc['sgst'] ?? '0.00'), 2);
            $portalTotals['utgst'] = bcadd($portalTotals['utgst'], (string) ($pDoc['utgst'] ?? '0.00'), 2);
            $portalTotals['igst'] = bcadd($portalTotals['igst'], (string) ($pDoc['igst'] ?? '0.00'), 2);
            $portalTotals['cess'] = bcadd($portalTotals['cess'], (string) ($pDoc['cess'] ?? '0.00'), 2);
            $portalTotals['total_tax'] = bcadd($portalTotals['total_tax'], $tax, 2);

            $isAvailable = (bool) ($pDoc['itc_available'] ?? true);
            if ($isAvailable) {
                $portalTotals['itc_available_tax'] = bcadd($portalTotals['itc_available_tax'], $tax, 2);
            } else {
                $portalTotals['itc_ineligible_tax'] = bcadd($portalTotals['itc_ineligible_tax'], $tax, 2);
            }

            $key = strtoupper(trim((string) ($pDoc['supplier_gstin'] ?? ''))).'|'.strtoupper(trim((string) ($pDoc['document_number'] ?? '')));

            if (! ($portalMatched[$key] ?? false)) {
                $missingInBooksSummary['count']++;
                $missingInBooksSummary['taxable_value'] = bcadd($missingInBooksSummary['taxable_value'], $taxable, 2);
                $missingInBooksSummary['tax'] = bcadd($missingInBooksSummary['tax'], $tax, 2);

                $records[] = [
                    'status' => 'missing_in_books',
                    'supplier_gstin' => $pDoc['supplier_gstin'] ?? '',
                    'supplier_name' => $pDoc['supplier_name'] ?? '',
                    'document_number' => $pDoc['document_number'] ?? '',
                    'bill_id' => null,
                    'date' => $pDoc['date'] ?? '',
                    'books_taxable' => '0.00',
                    'books_tax' => '0.00',
                    'portal_taxable' => $taxable,
                    'portal_tax' => $tax,
                    'difference' => bcsub('0.00', $tax, 2),
                    'itc_available' => $isAvailable,
                    'ineligibility_reason' => $pDoc['ineligibility_reason'] ?? null,
                ];
            }
        }

        return [
            'period' => $period,
            'books' => $booksTotals,
            'portal_2b' => $portalTotals,
            'comparison' => [
                'matched' => $matchedSummary,
                'tax_mismatch' => $mismatchSummary,
                'missing_in_books' => $missingInBooksSummary,
                'missing_in_portal' => $missingInPortalSummary,
                'unregistered_inward' => $unregisteredSummary,
            ],
            'records' => $records,
        ];
    }
}
