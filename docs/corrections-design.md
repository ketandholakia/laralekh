# Sales corrections: storage and scope

Corrections are new documents. Originals and the existing immutability triggers
remain unchanged. Each note belongs to its own posting year; explicit source-year
foreign keys permit a later-year note against an earlier-year invoice without
permitting a cross-company or mismatched source-line reference.

GST credit/debit notes and commercial credit/debit notes share a document model
with an explicit treatment. Internal voucher reversals use separate records and
are never presented as GST notes. Posted notes, their lines and reversal metadata
have additional SQL immutability triggers. The original-voucher reference is
unique both on reversal records and on reversing vouchers.

Credit capacity is the original invoice line less all posted credit notes,
including commercial credits. Debit notes do not replenish original-invoice
credit capacity in this MVP; correcting a debit note is a later workflow.
Amounts and affected quantities are independently capped. A zero affected
quantity denotes a value-only adjustment, not a physical stock movement.

Original rounded tax components are allocated cumulatively across partial credits.
Commercial credits consume the corresponding tax allocation but do not post it.
This prevents a later GST credit from reclaiming tax attributable to an earlier
commercial concession and ensures partial paise allocations reconcile exactly.
Debit notes reuse the original rate and supply classification. No current rate
master lookup is used for corrections.

All corrections and reversals against a sales voucher lock the original invoice
first. This serializes credit-capacity checks with reversal checks. A full
reversal is permitted only where no posted correction exists; a reversed invoice
cannot subsequently be credited/debited. Reversal-of-reversal and reversal of note
vouchers are deferred, avoiding ambiguous correction chains. Existing drafts can
remain visible but cannot post once their original has been reversed.

GST credit eligibility requires documented external review and a planned reporting
date. This is not return filing or an assertion that a reduction has been accepted.
Financial corrections do not adjust physical inventory.

# Validation refinements

MySQL repeatable-read snapshots require current locking reads for posted-credit
capacity, reversal exclusion and number-sequence existence, even after the
source-invoice/year locks are acquired. Real multi-process races cover these
paths. InnoDB rollback also restores FK-supporting indexes before dropping new
unique indexes. Original posted-document triggers remain unchanged.

