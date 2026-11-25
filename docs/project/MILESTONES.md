# LaraLekh — Milestone Register

Milestones group Works Register tasks (`WORK_REGISTER.md`) into verifiable
completion units. A milestone is COMPLETED only when its exit criteria are
demonstrably met — code presence alone is never evidence.

| ID | Name | Included tasks | Status |
|---|---|---|---|
| MS-01 | Core accounting foundations (Steps 1–4) | historical (pre-register) | COMPLETED |
| MS-02 | CI and branch protection | LL-P0-003 | COMPLETED |
| MS-03 | UI modernization and brand | LL-P0-002 (incl. dashboard, collapsed-rail correction) | COMPLETED |
| MS-04A | Accounting safety and essential reports | LL-P1-001…LL-P1-005 | ACCEPTED — owner-accepted 2026-11-17 after independent audit and F-01…F-04 remediation |
| MS-04B | Additional accounting workflows | LL-P1-006…LL-P1-011 | IN_PROGRESS (per-workflow approval; LL-P1-006 completed 2026-11-24; LL-P1-008 completed 2026-11-25; LL-P1-009 completed 2026-11-25) |
| MS-05 | GST compliance completion | LL-P2-001…LL-P2-004 | IN_PROGRESS (LL-P2-004 completed 2026-11-25) |
| MS-06 | UI completion and administration | LL-P3-001…LL-P3-008 | COMPLETED (2026-11-24) |
| MS-07 | Integrations | LL-P4-001…LL-P4-004 | PROPOSED (BLOCKED items inside) |
| MS-08 | Production readiness | LL-P5-001…LL-P5-006 | PROPOSED (LL-P5-001, LL-P5-004 completed via PR #4, 2026-11-18) |

Cross-cutting: **LL-P1-012** (MySQL migration/concurrency validation cadence)
supports MS-04A, MS-04B and every future schema change; it is not a
prerequisite for individual workflows.

---

## MS-01 — Core accounting foundations (Steps 1–4) — COMPLETED

- **Objective:** working multi-company accounting core: auth, tenancy, sales
  invoices, corrections, reversals, receipts/settlements, purchase bills, GST
  return preparation.
- **Entry criteria:** empty repository, agreed step plan (historical).
- **Exit criteria:** every area listed in `DEVELOPMENT_PLAN.md` §5 implemented
  with dedicated Pest coverage; immutability triggers in place; suite green.
- **Required automated tests:** the 29 historical feature suites ( PostingService,
  CorrectionPosting, ReceiptService, PurchaseBill, ReturnDataset/GSTR suites,
  Tenancy, Permission, Seeders, PDF tests…).
- **Required manual verification:** sample PDF visual review (`docs/validation.md`);
  MySQL concurrency and migration up/down runs (`docs/mysql-test-runs.md`).
- **Security checks:** tenancy test suite; database safety guard
  (`tests/TestCase.php` `laravix_test_*`).
- **Owner acceptance:** historical — continued step-by-step development on
  `main`; no separately recorded owner sign-off exists for this milestone
  (backfilled entry; see `CHANGELOG.md` qualification note).
- **Evidence:** repository history to `304bd21`; `docs/validation.md`;
  `docs/audit/laravel-audit.md`.

## MS-02 — CI and branch protection — COMPLETED (PR #1)

- **Objective:** automated validation and protected `main`.
- **Included tasks:** LL-P0-003.
- **Entry criteria:** test suite present.
- **Exit criteria:** three required checks (Backend SQLite, Frontend build,
  Quality & security) on every PR/push; strict up-to-date; admin-enforced;
  force-push/deletion blocked; conversation resolution required.
- **Owner acceptance:** owner instructed and approved publication (PR #1
  merged 2026-11-15).

## MS-03 — UI modernization and brand — COMPLETED (PR #2)

- **Objective:** professional brand + design system + dashboard + registers,
  with permission-faithful financial presentation.
- **Included tasks:** LL-P0-002 (and its embedded audit fixes: receivables
  mirror of `SettlementService`, permission-gated dashboard, Inter OFL).
- **Entry criteria:** approved direction (dashboard at `/`, self-hosted Inter,
  zero-dependency icon sprite).
- **Exit criteria:** suite green (360/1018); build clean; collapsed/expanded
  sidebar + tooltips verified; disclosures recorded in the PR.
- **Owner acceptance:** PR #2 merged by the owner after independent audit
  fixes (F1/F2/F3).

## MS-04A — Accounting safety and essential reports — ACCEPTED (owner-approved and accepted 2026-11-17)

**Owner acceptance record:** accepted on **2026-11-17** after the independent
acceptance audit returned **ACCEPTANCE READY**. Final evidence at acceptance:
full Pest suite **396 passed / 1 todo / 1187 assertions** (SQLite in-memory);
**36 MS-04A tests passed on MySQL 8.4.13** (`laravix_test_ms04a`); migration
up/down/up, 46 immutability triggers with a runtime tamper probe, and the
series-concurrency harness all recorded PASS; frontend build PASS;
`git diff --check` clean. Audit findings F-01…F-04 were remediated and
independently re-verified; F-05 (sidebar entries) and F-06 (P&L FY default)
remain documented follow-ups and are not acceptance blockers. MS-04B
(LL-P1-006…011) remains **not approved**.

**Implementation evidence (2026-11-17):** 30 new Pest tests
(`SupplierPaymentTest` 13, `SupplierPaymentUiTest` 5,
`FinancialYearManagementTest` 5, `FinancialReportsTest` 7) — full suite
390 passed / 1 todo / 1162 assertions on SQLite; the same 30 tests passed on
MySQL 8.4.13 (`laravix_test_ms04a`); migration up/down/up PASS
(`laravix_test_migrations`); 46 immutability triggers verified with a
runtime tamper probe (UPDATE on a posted voucher rejected with
"Immutable or invalid accounting document"); series-concurrency run PASS
(`laravix_test_ms04a_conc`). Screens: supplier-payment register/editor/view,
financial-year manager, trial balance, P&L, balance sheet.

**Owner authorization record:** approved on **2026-11-17** for exactly the
tasks LL-P1-001…LL-P1-005 (supplier-payment service tests, supplier payments
UI, financial-year management screen, trial balance, P&L and balance sheet).
MS-04B (LL-P1-006…LL-P1-011) is explicitly **not** approved and none of its
policy decisions are made. Implementation proceeds under the staged sequence
in the entry audit (Stages A–E) on a dedicated feature branch.

- **Objective:** establish confidence in the existing supplier-payment
  accounting path and deliver essential financial management and reporting
  screens.
- **Included tasks:** LL-P1-001 (supplier-payment service tests), LL-P1-002
  (supplier payments UI), LL-P1-003 (financial-year management screen),
  LL-P1-004 (trial balance), LL-P1-005 (P&L and balance sheet).
- **Entry criteria:** explicit owner approval of MS-04A; existing `PostPayment`
  and related code inspected; accounting semantics and existing settlement
  invariants documented before implementation.
- **Exit criteria:** supplier-payment service tests cover posting,
  permissions, tenancy and reversal behaviour; supplier-payment UI is tested
  and permission-protected; financial-year management enforces closed-year
  posting restrictions; trial balance reconciles; P&L and balance sheet
  reconcile to posted ledger movements; full automated suite passes; required
  MySQL validation passes (migration up/down/up, trigger verification,
  posting/reversal tests on MySQL per `DEVELOPMENT_PLAN.md` §13).
- **Required automated tests:** new service/UI tests per task; full suite.
- **Required manual verification:** reports reviewed against a seeded
  `laravix_test_*` dataset; screens reviewed at desktop + mobile widths.
- **Security checks:** permission tests per new screen; tenancy tests.
- **Owner acceptance:** owner reviews the milestone report against this exit
  criteria list.
- **Note:** approval of MS-04A does not authorize MS-04B.

## MS-04B — Additional accounting workflows — IN_PROGRESS (per-workflow approval)

- **Objective:** address additional accounting workflows only after their
  product and accounting policies have been decided.
- **Included tasks:** LL-P1-006 (purchase debit notes — COMPLETED via PR #27), LL-P1-007 (zero-value
  invoice workflow), LL-P1-008 (refund of excess customer credits), LL-P1-009
  (advance receipt voucher workflow), LL-P1-010 (inventory/COGS policy and
  stock register), LL-P1-011 (sales debit-note correction and
  reversal-of-reversal scope).
- **Entry criteria:** separate explicit owner approval of the relevant
  MS-04B work; the corresponding decisions approved before implementation of
  the affected feature — DEC-014 (purchase debit notes), DEC-015 (zero-value
  invoices), DEC-016 (credit refunds), DEC-013 (advance vouchers), DEC-012
  (inventory/COGS policy), DEC-017 (debit-note correction scope).
- **Progress:** LL-P1-006 (purchase debit notes) completed and merged via PR #27 (merge `769b961`, 2026-11-24) with 6 new Feature tests and full SQLite CI passing (445 passed, 1 todo, 1463 assertions); LL-P1-008 (customer credit refunds) completed and merged via PR #29 (merge `f2c634e`, 2026-11-25) with 6 new Feature tests, 1 Unit test and full SQLite CI passing (452 passed, 1 todo, 1547 assertions); LL-P1-009 (advance receipt vouchers) completed and merged via PR #31 (merge `90cc2aa`, 2026-11-25) with 6 new Feature tests and full SQLite CI passing (458 passed, 1 todo, 1604 assertions). MS-04B core workflow milestones completed.
- **Exit criteria:** each approved workflow has a documented accounting
  design; required service and UI tests pass; existing immutable-document and
  settlement rules remain intact; required MySQL validation passes (per
  `DEVELOPMENT_PLAN.md` §13, applied to each workflow's posting path).
- **Required automated tests:** per approved workflow; full suite.
- **Required manual verification:** per workflow, on a `laravix_test_*`
  dataset.
- **Security checks:** permission tests per screen/action; tenancy tests.
- **Owner acceptance:** per workflow — approving one MS-04B workflow does not
  authorize the others.

## MS-05 — GST compliance completion — IN_PROGRESS

- **Included tasks:** LL-P2-001…LL-P2-004.
- **Progress:** LL-P2-004 (GST treatment of advance receipts) completed and merged via PR #33 (merge `e4880a6`, 2026-11-25) with 7 new Feature tests in `GstAdvanceIntegrationTest` and full SQLite CI passing (465 passed, 1 todo, 1644 assertions); LL-P2-002 (GSTR-2B manual import and purchase reconciliation) completed and merged via PR #35 (merge `9fb309e`, 2026-11-25) with 7 new Feature tests in `Gstr2bReconciliationTest` and full SQLite CI passing (472 passed, 1 todo, 1714 assertions); LL-P2-003 (Close fail-closed GST review items) completed and merged via PR #37 (merge `a05cbca`, 2026-11-25) with 2 new Feature tests in `ReturnValidationReportTest` and full SQLite CI passing (474 passed, 1 todo, 1721 assertions), resolving Table 13 categories, blocked ITC rules, tax rate verification, and State master POS while retaining GSTIN portal status and IGST ss. 10–13 legal treatment as non-blocking CA-review warnings. Only LL-P2-001 (GSTR-1 JSON export, blocked on official schema vendoring) remains under MS-05.
- **Entry criteria:** MS-04B item LL-P1-009 (advance vouchers) approved;
  official GSTR-1 schema/sample vendored (unblocks LL-P2-001); CA engaged for
  LL-P2-003.
- **Exit criteria:** JSON export validates against the official schema; 2B
  import reconciles in the worksheet; every fail-closed item resolved or
  documented as retained CA-review.
- **Tests:** golden tests vs official samples; reconciliation tests.
- **Owner acceptance:** milestone report reviewed with CA outputs.

## MS-06 — UI completion and administration — COMPLETED (2026-11-24)

**Stage 0 — shared UI foundations (LL-P3-000) — COMPLETED (PR #6, 2026-11-18):**
delivered as a separate PR per the owner's binding decision on the approved
MS-06 implementation plan. Scope: shared design-system primitives for the
remaining screen work — `x-table`, `x-dialog` (native `<dialog>`),
`x-combobox`, `x-textarea`, `x-detail-list` — the LaraLekh pagination view
(`pagination.laralekh`, registered as the Laravel default and wired via
`paginationView()` on the four register lists after review finding F-01),
field-error accessibility wiring (stable `{name}-error`/`{name}-hint` ids,
additive `aria-invalid`/`aria-describedby`), and the missing `brand-200`
token. The independent review findings **F-01** (Livewire's `WithPagination`
overriding the default pagination view) and **F-02** (table row-divider CSS)
were remediated on the same branch (commit `22fbdc0`).

- **Evidence:** commits `1da192d` (Stage 0) and `22fbdc0` (remediation) merged
  via **PR #6** (merge commit `0e46f11`, 2026-11-18T13:38:19Z; branch
  preserved); PR CI run `44314872756` 3/3 SUCCESS; post-merge CI run
  `44315749152` 3/3 SUCCESS; final suite **412 passed / 1 todo / 1311
  assertions** (SQLite); `npm run build` PASS; reduced MySQL cadence PASS
  (migration up/down/up on `laravix_test_migrations`; 46 triggers +
  immutability tamper probe on `laravix_test_ms06_s0`). No accounting,
  domain, route, permission or schema changes.
- **Completed screen tasks:**
  - LL-P3-001 (sales invoice editor + view — COMPLETED via PR #8, merge `5783dcc`, 2026-11-24)
  - LL-P3-002 (receipt editor + view — COMPLETED via PR #10, merge `6e4df46`, 2026-11-24)
  - LL-P3-003 (purchase editor + view — COMPLETED via PR #12, merge `33ca136`, 2026-11-24)
  - LL-P3-004 (corrections and reversals — COMPLETED via PR #14, merge `fdabb40`, 2026-11-24)
  - LL-P3-005 (customer outstanding + statement — COMPLETED via PR #16, merge `583a206`, 2026-11-24)
  - LL-P3-006 (user and role management screens — COMPLETED via PR #18, merge `60765ff`, 2026-11-24)
  - LL-P3-007 (item master screens — COMPLETED via PR #20, merge `de95799`, 2026-11-24)
  - Register lists modernization (sales invoices, customer receipts, purchase bills, supplier payments — COMPLETED via PR #22, merge `f83eeed`, 2026-11-24)
  - LL-P3-008 (PDF template customization — COMPLETED via PR #25, merge `af18f0e`, 2026-11-24)
  - UI inventory audit refresh (`docs/ui/ui-inventory.md` — COMPLETED via PR #24, merge `643497c`, 2026-11-24)
- **Owner decisions carried from the approved MS-06 plan (unchanged):** GST
  returns detail excluded (separate proposal only); mobile drawer focus trap
  and user-menu keyboard management deferred. All MS-06 tasks delivered and verified.

- **Included tasks:** LL-P3-001…LL-P3-008 (plus the LL-P3-000 enabler
  substage, completed above).
- **Entry criteria:** none beyond design-system availability (merged).
- **Exit criteria:** all listed screens on the design system with unchanged
  behaviour tests; user/role administration fully permission-tested.
- **Owner acceptance:** visual review of each screen; admin flow sign-off.

## MS-07 — Integrations — PROPOSED (blocked items)

- **Included tasks:** LL-P4-001…LL-P4-004.
- **Entry criteria:** IRP/GSP + bank decisions and credentials; API scope
  decision for LL-P4-004.
- **Exit criteria:** sandbox-verified integrations; API surface (if approved)
  tested with Sanctum token auth and permission gates.
- **Owner acceptance:** per-integration.

## MS-08 — Production readiness — PROPOSED (partial progress 2026-11-18)

- **Progress annotation:** LL-P5-001 (README refresh) and LL-P5-004 (composer
  audit re-review) were completed on 2026-11-18 as part of the
  owner-authorized hardening/documentation pass — commit `16c599a`, **PR #4**
  merged as `8132cf8`, all three required CI checks green on the PR and on
  `main` after the merge. Remaining tasks: LL-P5-002 (deployment), LL-P5-003
  (backup/restore), LL-P5-005 (queue supervision) — all PROPOSED, approval
  Required; LL-P5-006 (MySQL trigger-parity lane) — PROPOSED, approval Not
  Required. The milestone itself remains PROPOSED pending the hosting
  decision (DEC-022).
- **Included tasks:** LL-P5-001…LL-P5-006.
- **Entry criteria:** owner decision on target hosting.
- **Exit criteria:** documented rehearsed deployment; proven backup/restore;
  supervised queues; audit dispositioned; README current.
- **Owner acceptance:** go-live checklist review.
