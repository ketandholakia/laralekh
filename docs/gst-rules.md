# GST decisions and unresolved compliance work

Research date: 5 November 2026. This MVP is not a GST filing certification.
GSTN portal manuals describe reporting; CBIC Acts and notifications determine the tax.
Some old official FAQs still show superseded thresholds, so they must not be used as current eligibility rules.

## Sources and implemented boundaries

- [GST e-waybill state master](https://docs.ewaybillgst.gov.in/apidocs/state-code.html):
  codes 01–38 retained; 25 and 28 are historical/inactive. 26 is the merged UT.
  97 (other territory) is outside this MVP. 99 means overseas in the resolver only.
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
  statutory sums payable are rounded to whole rupees. Invoice components in this
  MVP are individually rounded half-up to paise and summed. A separate utility
  handles whole-rupee statutory rounding; no automatic per-line whole-rupee rounding.
- [2025 rate-change FAQ and notification references](https://www.pib.gov.in/Pressreleaseshare.aspx?PRID=2168426&lang=2&reg=3)
  supersede the old goods schedules with Notification 9/2025-CT(R).
  [Effective-date FAQ](https://www.pib.gov.in/PressReleasePage.aspx?PRID=2163560&lang=1&reg=3)
  identifies 22 September 2025 and exceptions. Requested 0/5/12/18/28 seeds are
  historical templates, UNVERIFIED, not an authoritative current rate catalogue.
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
line amounts. No cess, inclusive pricing, TCS/TDS, advances, time-of-supply rate
transitions, mixed/composite supplies, valuation exceptions, or automatic return filing.
Rate selection uses invoice date only when it is the legally applicable tax date.
A verified rate is an accountant's input, not an automated compliance conclusion.

Registration/composition turnover thresholds, e-invoicing eligibility, B2C dynamic
QR thresholds, GSTR-1 B2CL thresholds and return schema versions are NOT encoded.
Current GSTN confirmation was insufficient to treat a single threshold as universally
applicable. These must be verified with the relevant current notifications when their
features are built. E-invoicing and GSTR-2B remain deferred.

