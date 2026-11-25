<?php

return [
    'schema_version' => null,
    'rules' => [
        'b2cl_threshold' => [
            'value' => null,
            'versions' => [
                ['from' => '2017-07', 'to' => '2024-07', 'value' => 250000, 'verified' => true],
                ['from' => '2024-08', 'to' => null, 'value' => 100000, 'verified' => true],
            ],
            'source' => 'GST Portal, GSTR-1 offline tool ReleaseNotes.txt (release 3.2.1); GST Portal GSTR-1 FAQ; current threshold applicability by return period as at 2026-11-06.',
        ],
        'hsn_digits' => [
            'verified' => true,
            'bands' => [['max_turnover_rupees' => 50000000, 'digits' => 4], ['max_turnover_rupees' => null, 'digits' => 6]],
            'source' => 'GST Portal, FAQs > Form GSTR-1 Table-12, accessed 2026-11-06; GSTR-1 Returns Offline Tool ReleaseNotes.txt releases 3.1.6 and 3.2.2.',
            'note' => 'From May 2025, HSN is selected from the GST Portal database; this application cannot establish database membership from the source ledgers.',
        ],
        'tax_rate_verification' => [
            'verified' => true,
            'source' => 'Enforced per-line via effective-dated TaxRate records with is_verified = true in SaveInvoiceDraft, PostingService, and PostPurchaseBill; unverified or expired rates are rejected.',
        ],
        'place_of_supply' => [
            'verified' => true,
            'ca_review_required' => true,
            'source' => 'Active 2-digit State/UT master validation in ReturnDatasetBuilder and PlaceOfSupplyService; transaction-level legal determination under IGST Act ss. 10-13 is retained as CA review.',
        ],
        'blocked_itc_rules' => [
            'verified' => true,
            'source' => 'CGST Act Section 17(5) & Section 16(2)(aa); GSTR-3B Table 4(B) instructions. Enforced via purchase line-level itc_eligibility (eligible/blocked/review) and GSTR-2B reconciliation; unreviewed lines emit CA-review warnings.',
        ],
        'document_issued_categories' => [
            'verified' => true,
            'verified_values' => [
                'Invoices for outward supply',
                'Invoices for inward supply from unregistered person',
                'Revised Invoice',
                'Debit Note',
                'Credit Note',
                'Receipt voucher',
                'Receipt Voucher',
                'Payment Voucher',
                'Refund voucher',
                'Refund Voucher',
                'Delivery Challan for job work',
                'Delivery Challan for supply on approval',
                'Delivery Challan in case of liquid gas',
                'Delivery Challan in cases other than by way of supply (excluding at S no. 9 to 11)',
            ],
            'source' => 'Form GSTR-1 Table 13 (Rule 59(1), CGST Rules 2017) & GST Returns Offline Tool 3.2.4 docs.csv (docs/gst-templates/official-offline-tool-v3.2.4/docs.csv).',
        ],
        'gstin_portal_existence' => [
            'verified' => false,
            'retained_as_ca_review' => true,
            'source' => 'Format, state code, and Mod-36 checksum verified offline via GstinValidator; live portal registration status requires external GSP/IRP API credentials (excluded by DEC-005). Retained as non-blocking CA-review warning.',
        ],
    ],
    'csv_templates' => [
        'source_url' => 'https://www.gst.gov.in/download/returns',
        'tool_version' => '3.2.4',
        'directory' => base_path('docs/gst-templates/official-offline-tool-v3.2.4'),
    ],
];
