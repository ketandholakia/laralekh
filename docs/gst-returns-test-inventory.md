# GST returns tests introduced in Step 3

Inventory of the Step 3 feature tests, with Part 0.4 additions noted.

## Dataset, classification, tenancy and edge cases

`tests/Feature/ReturnDatasetBuilderTest.php`
- `classifies posted outward invoices into return dataset tables using period rules`
- `fails closed if turnover is missing for HSN validation and refuses composition returns`
- `keeps return dataset access tenant scoped`
- `never includes another tenant company invoices in the return dataset` *(Part 0.4)*

`tests/Feature/ReturnDatasetEdgeCasesTest.php`
- `uses a strict B2CL boundary and routes the boundary invoice to B2CS`
- `classifies purchase reverse-charge bills and eligible input tax by head`
- `classifies exports with and without payment and SEZ supplies separately`
- `reports a correction in its later reporting period instead of the source invoice period`

## GSTR-3B and ledger invariants

`tests/Feature/Gstr3bSummaryTest.php`
- `builds the 3B books reconciliation and ties output and ITC to tax ledgers` — asserts GSTR-1 net output tax agrees with `OUTPUT_*` ledger movements.
- `fails loudly when GSTR-1 output tax does not tie to OUTPUT ledgers`
- `ties GSTR-3B eligible ITC to INPUT ledger movements and rejects mismatches` *(Part 0.4)*

## Snapshots and filing

`tests/Feature/GstReturnSnapshotTest.php`
- `finalizes immutable snapshots and supersedes on regeneration` — covers finalized and superseded payload/update/delete protections and filing-marker values.
- `warns when a document is posted after filing into an already filed period`
- `blocks finalizing a snapshot with validation errors`

## Validation and CSVs

`tests/Feature/ReturnValidationReportTest.php`
- `reports missing or invalid B2B recipient GSTIN and blocks unverified checksum results`
- `reports documents outside period and missing HSN or UQC`

`tests/Feature/OfficialGstr1CsvExporterTest.php`
- `uses the exact official Offline Tool CSV headers and column order`
- `blocks CSV export when required recipient GSTIN checksum verification is not configured`
- `blocks CSV export for datasets with validation errors`

`tests/Feature/OfficialGstr1CsvGoldenTest.php`
- `matches fixed golden CSV output for every exported GSTR-1 section`

## UI and intentionally pending JSON

`tests/Feature/GstReturnsUiTest.php`
- `shows the return period detail only for users with returns view permission`
- `shows tenant-scoped return periods on the dashboard and blocks users without view access`
- `blocks dashboard access for users without returns view permission`
- `requires finalize permission for snapshot actions`

`tests/Feature/Gstr1JsonExporterPendingTest.php`
- `exports GSTR-1 JSON conforming to the official schema and sample` — pending until an official schema/sample is provided.
