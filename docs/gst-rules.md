# GST decisions and unresolved compliance work

Research date: 5 November 2026. This MVP is not a GST filing certification.
GSTN portal manuals describe reporting; CBIC Acts and notifications determine the tax.
Some old official FAQs still show superseded thresholds, so they must not be used as current eligibility rules.

## Sources and implemented boundaries

- [GST e-waybill state master](https://docs.ewaybillgst.gov.in/apidocs/state-code.html):
  codes 01–38 retained; 25 and 28 are historical/inactive. 26 is the merged UT.
  97 (other territory) is accepted domestically and treated as inter-state. 99 means overseas in the resolver only.
  The master was visible in indexed official results; direct access returned HTTP 403.
- [IGST section 8](https://taxinformation.cbic.gov.in/content-page/explore-act/1000616/1000001):
  same supplier state and place of supply means intra-state, except SEZ.
  In applicable UTs the local component is UTGST, not SGST.
- [IGST section 16](https://taxinformation.cbic.gov.in/content-page/explore-act/1000624/1000001):
  SEZ zero rating requires authorised operations. Export/SEZ LUT and payment routes
  are explicit inputs. Eligibility, LUT validity and permitted IGST payment/refund
  routes must be verified externally; the calculator does not grant eligibility.
- [CGST section 15](https://taxinformation.cbic.gov.in/content-page/explore-act/1000284/1000001):
  MVP supports an invoice-recorded, pre-supply discount only. Post-supply discounts
  require the later credit-note workflow. The official page includes Finance Act
  2026 text marked commencement yet to be notified; no assumption is made about it.
- [GSTN registration manual](https://tutorial.gst.gov.in/userguide/registration/Apply_for_Registration_Normal_Taxpayer.htm):
  composition registration category and restrictions are explicit. This MVP does
  not decide scheme eligibility from turnover. Composition suppliers collect no
  GST on outward bills of supply; their own periodic levy is outside this calculator.
  [CGST section 10](https://taxinformation.cbic.gov.in/content-page/explore-act/1000279/1000001)
  supplies the legal rule. Inter-state outward composition supplies are rejected.
- [CGST Act section 170](https://cbic-gst.gov.in/pdf/CGST-Act-Updated-31082021.pdf):
  statutory sums payable are rounded to whole rupees. Invoice tax is calculated
  per line: CGST and SGST/UTGST are each rounded half-up to paise from half the
  rate; IGST is rounded half-up per line. Line amounts are summed without
  invoice-level recalculation. A separate utility
  handles whole-rupee statutory rounding; no automatic per-line whole-rupee rounding.
- [2025 rate-change FAQ and notification references](https://www.pib.gov.in/Pressreleaseshare.aspx?PRID=2168426&lang=2&reg=3)
  supersede the old goods schedules with Notification 9/2025-CT(R).
  [Effective-date FAQ](https://www.pib.gov.in/PressReleasePage.aspx?PRID=2163560&lang=1&reg=3)
  identifies 22 September 2025 and exceptions. Seeds include 0/5/18/40 from
  2025-09-22 and historical 5/12/18/28 through 2025-09-21. All remain UNVERIFIED,
  not an authoritative HSN-specific rate catalogue.
  No blanket expiry or HSN applicability is invented. Posting requires a verified,
  effective-dated rate. New HSN-specific rates, including applicable 40% supplies,
  must be entered after notification review. The calculator accepts valid decimal
  rates rather than hardcoding the old five slabs.
- Reverse charge is an explicit externally verified classification, not inferred
  from an unregistered recipient. For outward RCM invoices, the customer owes the
  supplier only the base; the recipient tax liability is returned separately and
  is not credited to the supplier's output tax ledgers.

## Scope assumptions

Place of supply supports ordinary goods movement, bill-to/ship-to on third-party
direction, goods without movement, and general domestic services. Special services
(property, events, transport, telecom, intermediary, etc.) require a reviewed explicit
place of supply. Export of services is not inferred merely from an overseas address.

Prices are tax-exclusive INR; quantity has up to three decimals; discounts are fixed
line amounts. Compensation cess is an explicit rate input; applicability, rate,
and valuation remain goods/notification-specific and require professional
verification. No inclusive pricing, TCS/TDS, advances, time-of-supply rate
transitions, mixed/composite supplies, valuation exceptions, or automatic return filing.
Rate selection uses invoice date only when it is the legally applicable tax date.
A verified rate is an accountant's input, not an automated compliance conclusion.

Registration/composition turnover thresholds, e-invoicing eligibility, B2C dynamic
QR thresholds, GSTR-1 B2CL thresholds and return schema versions are NOT encoded.
Current GSTN confirmation was insufficient to treat a single threshold as universally
applicable. These must be verified with the relevant current notifications when their
features are built. E-invoicing and GSTR-2B remain deferred.

## Step 4: receipts and advance payments (verified 6 November 2026)

[CGST section 13(2)(a)](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/acts/2017_CGST_act/active/chapter4/section13_v1.00.html)
makes the advance receipt the time of supply for services, and
[section 31(3)(d)](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/acts/2017_CGST_act/active/chapter7/section31_v1.00.html)
requires a receipt voucher on receiving an advance, with provisional receipt
vouchers where the amount is not determinable
([Rule 31(3)(c)](https://cbic-gst.gov.in/pdf/24092021-CGST-Rules-2017-Part-A-Rules.pdf);
the [GST Council flyer](https://gstcouncil.gov.in/sites/default/files/e-version-gst-flyers/51_GST_Flyer_Chapter6.pdf)
summarises both). This MVP does not implement that workflow: no statutory
receipt voucher is issued and no advance-period tax is computed, so unallocated
receipts are accounting records of money held on account only and are labelled
as such on the PDF and in the customer view. Receipts allocated against posted
invoices settle supplies already invoiced and carry no GST consequence. Goods
advance rules differ by notification; no goods-specific advance tax is assumed.
Refunds of excess customer credits and advance adjustments against future
invoices remain future work.

## Step 2 PDF particulars (researched 5 November 2026)

The invoice/bill-of-supply template follows
[CGST Rule 46](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/rules/cgst_rules/active/chapter6/rule46_v1.00.html)
(tax invoice) and bill-of-supply practice under Rule 54: supplier name/address/GSTIN,
consecutive serial number (series+number, max 16 characters per FY) and date,
recipient details, HSN/SAC, description/quantity, taxable value after discount,
tax rate and amounts, place of supply with state name, reverse charge indicator,
and an authorised-signatory block. Delivery address distinct from the place of
supply is not yet modelled (no ship-to fields); a ship-to feature must add it
before claiming Rule 46(m) compliance.

The optional UPI QR is a supplier-generated payment convenience QR
(`upi://pay` with payee VPA, name, amount, note), per
[GSTN's B2C dynamic QR comparison note](https://einvoice2.gst.gov.in/Documents/B2B_einvoiceQRCodevsB2CDynamicQRCode.pdf).
It is labelled as not being the IRP e-invoice QR. The Notification 14/2020
turnover thresholds that mandate the B2C dynamic QR are deliberately not
encoded; the QR here is printed only when the company configures a valid UPI ID.
Signature/digital-signature: the template prints an authorised-signatory block
only; no digital signature is applied.

## Step 3: credit/debit notes and reversals (verified 5-6 November 2026)

[Current CBIC section 34](https://taxinformation.cbic.gov.in/content-page/explore-act/1000304/1000001)
sets the credit-note declaration deadline at 30 November following the supply
financial year, or furnishing the relevant annual return, whichever is earlier.
The service checks the planned reporting date and the actual posting day, not
just the note date. It requires the reviewer to confirm annual-return status and
record evidence. A known filing date on the source financial year takes precedence
over a later date entered on a note. This does not file a return or establish that
GSTN accepted a reduction. Debit notes do not inherit this credit-note cutoff.

The current proviso restricts reduction where an applicable recipient ITC reversal
has not occurred, or tax incidence has been passed on in other cases. Eligibility
therefore needs an explicit external review. Finance Act 2026 discount amendments
on that page are marked commencement yet to be notified. This MVP supports GST
credits for overcharge, returns and deficiency, and GST debits for undercharge;
discretionary discounts use commercial treatment. It does not infer eligibility
from amounts. Special relief/extension provisions and tax-only rate corrections
are outside this implementation and need separate review.

[CBIC Rule 53(1A)](https://taxinformation.cbic.gov.in/content-page/explore-rules/1000144/1000001)
provides note particulars, including supplier/recipient details, document type,
unique serial/date, original invoice reference, taxable value/rate/tax, delivery
address for unregistered recipients, and signature. The note stores the supplied
delivery address and original party/supplier snapshots; output has a signatory
block, not an applied digital signature. Printed documents must be signed as
applicable before issue. CBIC direct HTML fetches returned 502 here; indexed
official text was cross-checked against the
[CBIC consolidated rules](https://cbic-gst.gov.in/pdf/24092021-CGST-Rules-2017-Part-A-Rules.pdf).

[GSTN's October 2025 IMS FAQ](https://tutorial.gst.gov.in/downloads/news/creative_faq_on_gstr9_for_24_25_dt_15_oct_25_v6_final.pdf)
describes recipient handling of credit notes. No IMS acceptance, ITC reversal or
return-filing status is automatically inferred or integrated in this step.

Internal reversals swap ledger entries but are explicitly not GST credit notes
or filing instructions. Commercial notes collect/reduce no GST. Neither notes
nor reversals move physical stock. The earlier Step 2 reference to Rule 54 for
ordinary bills of supply should read Rule 49; Rule 53 governs these notes.
