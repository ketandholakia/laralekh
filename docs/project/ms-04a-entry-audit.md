# MS-04A Entry Audit (2026-11-17)

Read-only audit performed before implementation, per the MS-04A authorization
(owner-approved 2026-11-17; scope LL-P1-001…LL-P1-005 only). Branch:
`feature/ms-04a-accounting-foundations` from `main` @ `943b550`.

## 1. Source files and classes inspected

| Area | Files |
|---|---|
| Supplier payments | `app/Domain/Purchase/Actions/PostPayment.php`, `app/Domain/Purchase/Models/SupplierPayment.php`, `SupplierPaymentAllocation.php`, `SupplierPaymentAllocationReversal.php` |
| Schema | `database/migrations/2026_11_06_230000_create_supplier_payments.php` (3 tables, composite tenant FKs, `restrictOnDelete`) |
| Purchase context | `app/Domain/Purchase/Actions/PostPurchaseBill.php`, `tests/Feature/PurchaseBillTest.php` (`purchaseFixture`) |
| Settlement semantics | `app/Domain/Sales/Services/SettlementService.php` (authoritative customer-side analogue) |
| Reversal engine | `app/Domain/Accounting/Services/ReversalService.php`, `VoucherNumberAllocator.php`, `PostingService::assertBalanced` |
| Tenancy/permissions | `TenantContext`, `EnsureCompanyContext`, `TenantModel` scopes; Livewire per-method guard pattern (`LivewirePermissionTest`) |
| FY handling | `app/Domain/Masters/Models/FinancialYear.php`, `CorrectionRules::openYear`, FY lock in `VoucherNumberAllocator` |
| Chart | `database/seeders/ChartOfAccountsSeeder.php` (groups: CASH_BANK/RECEIVABLES/PAYABLES/SALES/PURCHASES/INPUT_GST/OUTPUT_GST; ledgers: CASH, BANK, SUPPLIER) |

## 2. Actual behaviour and accounting invariants (verified from code)

`PostPayment::post($paymentId, array $allocations)` — inside one DB
transaction (retries ×3), all rows locked:

1. Payment must be a draft without a voucher (duplicate post → `DomainException`).
2. Payment FY must be open; payment date inside the FY (closed-year posting
   already rejected here).
3. Supplier ledger group nature must be `liability`; payment ledger group
   code must be `CASH_BANK`; amount positive (Money VO).
4. Each allocation must reference a **posted bill of the same supplier**
   (tenant-scoped lookup — a foreign-company bill resolves to null and fails
   closed); allocation positive; bill payable = `reverse_charge ?
   taxable_amount : total`; outstanding = payable − active allocations
   (`whereNotExists` on `supplier_payment_allocation_reversals`), locked read;
   allocation must not exceed outstanding.
5. Payment must be **fully allocated** (Σ allocations = payment amount) —
   partial bill coverage is fine, partially allocated payments are not.
6. Entries `Dr supplier / Cr cash`, `assertBalanced`; series `SP` number
   allocated via `VoucherNumberAllocator` (locked FY row + sequence row);
   voucher `type=payment`; allocations created with the bill's own FY key
   (cross-year settlement supported by composite FKs); payment updated with
   voucher, number, status, supplier snapshot.

`PostPayment::reverse($paymentId, $yearId, $date, $reason)` — posted only;
delegates to `ReversalService::reverse` (mirror voucher `RV`, reason 5–2000
chars, `openYear` date rules, immutable `VoucherReversal` row) and records an
immutable `SupplierPaymentAllocationReversal` row per active allocation, so
bill outstanding is restored.

**Consistency check vs customer side (`SettlementService`):** same derived-
balance philosophy (posted documents never updated; balances derived from
active allocations minus reversal rows); supplier payments are **not**
customer receipts — payable is the bill total (or taxable amount under RCM),
not invoice-less advances; no advance semantics exist for suppliers (none
implemented — no change proposed in MS-04A).

## 3. Identified gaps

- **No tests exist** for any of the above (LL-P1-001's whole point).
- **No permission checks inside `PostPayment`** — consistent with the
  existing design (`PostReceipt` likewise enforces nothing internally;
  enforcement is the Livewire per-method guard pattern, e.g. commit
  `4335328`). Stage B must guard `purchases.post` (posting) and
  `purchases.view`/`purchases.create` per action; UI tests prove 403s.
- **No draft-creation action** (`SavePaymentDraft` does not exist) — draft
  rows are simple field-only records; the Stage B Livewire editor will create
  the draft directly within tenant context and delegate everything else to
  `PostPayment` (no accounting logic duplication).
- **No routes/screens** exist for supplier payments.
- Purchase outstanding is not displayed anywhere yet (Stage B surfaces it).

## 4. Ambiguities → owner clarifications needed?

None that block Stage A: `PostPayment`'s semantics are fully determined by
code. The one product question surfaced — whether supplier **advance**
payments (unallocated funds) should ever exist — is out of MS-04A scope
(current design forbids partially allocated payments; changing it would be an
MS-04B-scale policy decision). **No owner input required to proceed.**

## 5. Proposed implementation sequence

- Stage A (LL-P1-001): `tests/Feature/SupplierPaymentTest.php` proving the
  invariants in §2, cross-checked against `SettlementService` where
  meaningful (outstanding derivation).
- Stage B (LL-P1-002): routes `supplier-payments.*` behind `company.context`
  + `can:purchases.view`; `SupplierPaymentList` + `SupplierPaymentEditor`
  Livewire components (guard `purchases.post` on post), modernized register
  UI on the design system.
- Stage C (LL-P1-003): FY create/close screen; closed FY rejects posting via
  existing guards (already enforced in `PostPayment`/`PostPurchaseBill`/
  `VoucherNumberAllocator` — screen + tests wire it up).
- Stage D (LL-P1-004): trial balance service + screen from posted voucher
  lines grouped by ledger.
- Stage E (LL-P1-005): P&L (period) and balance sheet (as-at date) from
  ledger-group classification.

## 6. Test plan

- New suites: `SupplierPaymentTest` (Stage A), UI tests inside it or a
  sibling for Stage B, `FinancialYearManagementTest` (Stage C),
  `FinancialReportsTest` (Stage D/E).
- Existing conventions: `webSession()`/`inTenantContext()`/`purchaseFixture()`
  helpers; SQLite in-memory; `laravix_test_*` guard untouched.
- Every suite run: full `vendor/bin/pest`; `npm run build` for UI stages.

## 7. MySQL validation plan (per DEVELOPMENT_PLAN.md §13)

1. DB `laravix_test_ms04a` on MySQL 8.4 (Docker `laravix-mysql84` when
   available); migration up/down/up.
2. Immutability-trigger verification on the payment voucher path.
3. `SupplierPaymentTest` + purchase/receipt suites against MySQL.
4. Series-concurrency validation if allocator behaviour is touched (no
   allocator changes planned; run the standard concurrency harness once).
5. Exact commands, database name, results recorded in the final report.
6. Never dev/prod data; `tests/TestCase.php` guard unchanged.
