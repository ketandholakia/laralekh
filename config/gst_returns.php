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
        'tax_rate_verification' => ['verified' => false, 'source' => null],
        'place_of_supply' => ['verified' => false, 'source' => null],
        'blocked_itc_rules' => ['verified' => false, 'source' => null],
        'document_issued_categories' => ['verified' => false, 'verified_values' => ['Invoices for outward supply'], 'source' => 'GST Portal, Creation of Outward Supplies Return in GSTR-1, Table 13 manual; template row in official Offline Tool 3.2.4 docs.csv. Other category labels/series handling require official confirmation.'],
    ],
    'csv_templates' => [
        'source_url' => 'https://www.gst.gov.in/download/returns',
        'tool_version' => '3.2.4',
        'directory' => base_path('docs/gst-templates/official-offline-tool-v3.2.4'),
    ],
];
