# Settlements: receipts, allocations and customer statements

Receipts are posted accounting documents (Dr cash/bank, Cr customer receivable)
with their own per-year SR series. Allocations are insert-only links between a
posted receipt and posted invoices; they never create accounting entries.
Allocation undo records and receipt reversals are themselves immutable rows, so
settlement history can only grow. Invoice outstanding balances are derived, and
posted invoices are never updated with a settlement status.

## Derived balances

- Invoice outstanding = invoice total − posted credit notes + posted debit
  notes − active receipt allocations.
- A negative balance is an excess customer credit, shown separately in the
  customer view and never silently absorbed into other invoices.
- Receipt funds available = receipt amount − active allocations.
- Unallocated posted funds are customer advances, shown separately.
- Credit/debit notes reduce or increase the outstanding exactly once because
  posted notes are unique immutable documents counted directly.
- Reversed invoices cannot be allocated and are excluded from derived balances;
  their reversal voucher restores the ledger balance.
- The receivable ledger balance is authoritative. The customer statement's
  opening/closing balances come from the ledger and are shown reconciled; the
  statement lists document rows by date and never lists allocations, so nothing
  is double counted.

## Cross-year settlement

Allocations carry the invoice's own financial-year key, so a receipt posted in
one year may settle invoices from any earlier open-or-closed year of the same
company and customer. Composite foreign keys keep every link company-scoped.

## GST treatment of advances (verified 6 November 2026)

For services, an advance triggers time of supply on receipt
([CGST s. 13(2)(a)](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/acts/2017_CGST_act/active/chapter4/section13_v1.00.html)),
and the supplier must issue a receipt voucher on receiving an advance
([s. 31(3)(d)](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/acts/2017_CGST_act/active/chapter7/section31_v1.00.html)),
with provisional receipt vouchers where the amount is not determinable
([Rule 31(3)(c)](https://cbic-gst.gov.in/pdf/24092021-CGST-Rules-2017-Part-A-Rules.pdf);
the [GST Council flyer](https://gstcouncil.gov.in/sites/default/files/e-version-gst-flyers/51_GST_Flyer_Chapter6.pdf)
summarises the obligation).

This MVP deliberately does NOT implement that workflow: it issues no statutory
receipt voucher, computes no advance-period tax, and uses no 18%/12%/highest
rate fallback. An unallocated receipt is therefore an accounting record of
money held on account only; the receipt PDF and the customer view label it as
an advance whose GST treatment is handled outside the document. Receipts
allocated against invoices are pure settlements of supplies already invoiced
and have no GST consequence. A future step must add the receipt-voucher +
advance-tax workflow before unallocated advances can be treated as compliant
advance collections.

Receipt PDFs are commercial money receipts: they are not statutory receipt
vouchers and say so. Statement PDFs are informational, not statutory documents.

## Concurrency model (MySQL REPEATABLE READ)

- Receipt posting locks the receipt row, party, ledgers and the financial-year
  row, allocates the SR number inside the transaction, and validates any
  at-posting allocations before the number is used.
- Allocation locks the receipt row first (serialising against reversal and
  other allocations), then each invoice, and re-reads funds/outstanding with
  locking reads so snapshot staleness cannot over-allocate.
- Receipt reversal locks the receipt, its voucher, and every active allocation,
  swaps the original entries, and undoes all active allocations atomically.
- The generic invoice-reversal path refuses invoices with active allocations
  and refuses receipt vouchers; undo of the same allocation twice is rejected.
- Allocation-versus-reversal races are safe in either order: allocation first
  is undone by the reversal; reversal first blocks the allocation.
- Real multi-process checks: tools/test-receipt-concurrency.php.

## Unsupported (deferred, not silently mishandled)

Supplier payments/purchases, advance-tax receipt vouchers, refunds of excess
credits, write-offs, TDS/TDS adjustments, foreign currency, opening-balance
import, receipts not tied to a customer (e.g. other income), and allocation to
debit/credit notes (only invoices are allocation targets).
