# GST returns rules and verification log

Last checked: **2026-11-06**. This is an implementation record, not tax advice. Anything marked unverified blocks/flags return validation rather than being inferred.

## Verified inputs currently used

| Rule | Applied behavior | Official source and date |
|---|---|---|
| GSTR-1 B2CL invoice-value boundary | ₹2,50,000 through July 2024; ₹1,00,000 from August 2024. The boundary is applied to inter-State B2C invoice value; documents at or above configured value currently route B2CL. CA must confirm whether inclusive/exclusive is correct for each return-period portal behavior before filing. | GST Portal Returns Offline Tool 3.2.4, bundled `ReleaseNotes.txt` (release 3.2.1 says the B2CL/B2CLA invoice limit is period-dependent before/after August 2024); official download page https://www.gst.gov.in/download/returns ; GSTR-1 guide https://tutorial.gst.gov.in/userguide/returns/GSTR_1.htm . Accessed 2026-11-06. |
| Table 12 HSN digits | Minimum 4 digits where preceding-FY AATO is up to ₹5 crore; 6 digits above ₹5 crore. Since turnover is not persisted on Company, caller must provide it. If absent, taxable HSN checks fail closed. | GST Portal, FAQs > Form GSTR-1 Table-12: https://tutorial.gst.gov.in/userguide/returns/GSTR_1.htm ; Returns Offline Tool 3.2.4 `ReleaseNotes.txt`, releases 3.1.6 and 3.2.2 (including separate B2B/B2C HSN sections). Accessed 2026-11-06. |
| Table 12 HSN selection | Portal says HSN details must be selected from its database/dropdown; this application cannot verify portal-master membership offline. Reported as a CA/portal verification item. | GST Portal, FAQs > Form GSTR-1 Table-12: https://tutorial.gst.gov.in/userguide/returns/GSTR_1.htm . Accessed 2026-11-06. |
| 3B outward liability population | GSTR-1/1A liabilities auto-populate 3B Tables 3.1, 3.1.1 and 3.2. The app therefore produces a reconciliation worksheet, not a filing tool. | GST Portal, “Creation and Submission of GSTR-3B”: https://tutorial.gst.gov.in/userguide/returns/Create_and_Submit_GSTR3B.htm . Accessed 2026-11-06. |
| 3B ITC and RCM population | When GSTR-2B is generated, its ITC and reverse-charge details auto-populate 3B Tables 4 and 3.1(d), respectively. This app has no official GSTR-2B feed; its worksheet compares books and marks discrepancies for review. | Same GST Portal GSTR-3B manual above; GST Portal GSTR-2B manual: https://tutorial.gst.gov.in/userguide/returns/Manual_gstr2b.htm . Accessed 2026-11-06. |
| GSTR-3B edits | Portal auto-population does not make this an e-filing interface; values can be reviewed/edited in the portal. This app stores neither credentials nor files returns. | GST Portal GSTR-3B manual above. Accessed 2026-11-06. |
| CSV template origin | Included CSV headers are copied from the official GSTR-1 Returns Offline Tool 3.2.4 ZIP, section-wise CSV folder; the ZIP’s release notes identify 3.2.4. | https://www.gst.gov.in/download/returns and https://tutorial.gst.gov.in/offlineutilities/returns/gst_offline_tool.zip . Downloaded 2026-11-06. |

## Fail-closed / not verified

- `config/gst_returns.php` marks tax-rate validity, GSTIN checksum validation, place-of-supply legality, blocked-ITC statutory eligibility, and document-series document-nature mapping unverified. The dataset emits errors or review findings instead of choosing legal outcomes. The application only checks basic state-code shape for place of supply.
- Rule amendments, omissions, and corrections are assigned to the month of `reporting_date`, falling back to the correction date only where no reporting date exists. This is a product convention based on the stored field, not a complete statutory deadline determination; CA review required.
- B2CL vs B2CS value is compared in rupees. Current period threshold is treated as inclusive in classification; verify against the selected period’s official portal utility before filing.
- Dataset document ranges currently describe observed posted documents and cannot infer cancelled/unissued numbers because source tables have no cancellation register. CSV documents-issued export must mark this as incomplete rather than fabricate cancellation counts.
- The app has no durable AATO field, filing credentials, GSTR-2B source, GST portal HSN master, or JSON schema. CSV output is for review/import assistance only. No GSTR-1 JSON or filing is implemented.

## GSTR-3B worksheet design

Rows are categorized for reconciliation with portal-populated 3B amounts. Output tax and eligible ITC are compared to `OUTPUT_*` / `INPUT_*` ledger credit/debit movements. Reverse-charge entries remain explicitly separate. `GstCalculator::roundStatutoryPayable` is reserved for net statutory payable totals only; source transactions are not rounded to whole rupees.
