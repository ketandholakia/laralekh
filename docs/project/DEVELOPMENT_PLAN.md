# LaraLekh — Master Development Plan

Status: planning document, established 2026-11-15 from repository state at `main`
@ `83442805e205765a4e4409844bdca22734a53a28` (PR #2 merged). This plan describes
what exists in the codebase today and what is proposed next. Facts are drawn
from code, tests and the documents in `docs/`; proposals are clearly marked and
require owner approval (see `DECISION_REGISTER.md`).

Related registers: `WORK_REGISTER.md` (tasks `LL-*`), `MILESTONES.md` (`MS-*`),
`DECISION_REGISTER.md` (`DEC-*`), `CHANGELOG.md`.

---

## 1. Product overview

**LaraLekh** — *Smart Accounts. Simplified Business.* Indian GST accounting and
business management software: a multi-company, tenant-isolated accounting
backend with a Livewire UI that posts immutable accounting vouchers, produces
GST-compliant sales documents and notes, reconciles customer settlements, and
prepares GSTR-1/GSTR-3B review exports. It is deliberately a **reconciliation
and review workspace** — it does not file returns or hold portal credentials
(`docs/gst-rules.md`).

## 2. Product vision and target users

- Accountants (CAs), traders, retailers and small Indian businesses that need
  trustworthy books with GST-aware documents.
- Design principle: correctness over convenience — legal/CA-review items fail
  closed rather than being guessed (`DEC-010`).
- Single-owner SaaS-style product, multi-company per user, multi-user per
  company (company-scoped Spatie roles).

## 3. Product positioning

Professional, modern accounting SaaS built on Laravel/Livewire — not a generic
admin template. Brand identity (teal/navy/saffron, Inter, "Lekh ledger" logo)
was established in PR #2. The differentiator is the audited posting engine:
locked series numbering, SQL immutability triggers on posted documents, and
exact decimal money handling.

## 4. Technology stack and architecture

| Layer | Choice | Evidence |
|---|---|---|
| Backend | Laravel 13, PHP 8.4+ (`composer.json` `^8.4.1`) | `composer.lock` platform pin |
| UI | Livewire 3 (Alpine bundled), Blade, Tailwind CSS 3 via Tailwind CLI | `package.json`, no bundler |
| Database | MySQL 8 (8.4 tested); SQLite in-memory for tests | `docs/mysql-84.md`, `phpunit.xml` |
| Auth | Session-based; Sanctum installed but unused (future API step) | `config/sanctum.php` |
| Permissions | Spatie Permission, company-team scoped | `config/permission.php`, `Role` model |
| Auditing | Spatie Activitylog v5 | migration `attribute_changes` |
| PDF | DomPDF via barryvdh/laravel-dompdf; chillerlan/php-qrcode | `composer.json` |
| Testing | Pest 5 / PHPUnit 13; CI: GitHub Actions, 3 jobs + branch protection | `.github/workflows/ci.yml` |

Architecture: domain modules under `app/Domain/{Accounting,Sales,Purchase,
Inventory,Gst,Masters,Shared}`; HTTP surface intentionally thin (9 controllers,
20 Livewire components); all tenant-scoped queries flow through
`TenantContext` + `EnsureCompanyContext`; posted documents are immutable via
MySQL triggers; money is a decimal-string `Money` value object (never float).

## 5. Existing implemented functionality (implemented and tested)

Evidence: dedicated test files; full suite currently **412 passed / 1 todo /
1311 assertions** on SQLite (CI-verified on PR #6 and on merged `main` @
`0e46f11`).

| Area | Status detail |
|---|---|
| Authentication | Session login, throttling, logout (`AuthenticationTest`) |
| Company selection/switching | Membership-checked, session-backed (`CompanySelectionController`, `EnsureCompanyContext`) |
| Tenancy | `TenantContext`, `EnsureCompanyContext`, `TenantModel` global scopes, company-scoped roles, composite FKs (`TenancyTest`) |
| Chart of accounts & GST slabs | Per-company seeders, idempotent (`SeedersTest`) |
| Sales invoices | Draft→post, per-series numbering (locked FY row, failed posts consume no number), immutability triggers, PDF, cancellation (`PostingServiceTest`, `SalesUiTest`, `InvoicePdfTest`, `DocumentCancellationTest`) |
| Sales corrections | GST + commercial credit/debit notes, capacity caps, cumulative paise allocation, own series/PDF (`CorrectionPostingTest`, `CorrectionValidationTest`, `CorrectionPdfTest`) |
| Internal reversals | Voucher reversal for invoices/receipts, immutable reversal rows (`ReversalService` tests, `ReversalEditor/View`) |
| Receipts & allocations | Draft→post, per-invoice allocations, cross-year settlement, allocation undo, receipt reversal, advances, excess credits (`ReceiptServiceTest`, `ReceiptUiTest`) |
| Customer outstanding & statements | Derived balances reconciled to the receivable ledger (`CustomerOutstanding`, `CustomerStatement`) |
| Purchase bills | Draft→post, per-method permission guards, stock movement on purchase receipt, cancellation guards (`PurchaseBillTest`) |
| GST calculations | CGST/SGST/UTGST/IGST/compensation-cess, place-of-supply resolution, verified-rate-only policy (`GstCalculator`, return edge-case tests) |
| GSTR-1 preparation | Dataset builder with B2B/B2CL/B2CS/SEZ/EXP/CDNR/CDNUR classification, HSN summaries (split B2B/B2C), validation report, official Offline Tool 3.2.4 CSV exports + golden tests, review-summary CSV (`ReturnDatasetBuilderTest`, `OfficialGstr1Csv*Test`) |
| GSTR-3B preparation | Reconciliation worksheet at tax-head precision, statutory rounding only on combined payable, ledger tie-out (`Gstr3bSummaryTest`) |
| Return lifecycle | Immutable versioned snapshots (SHA-256), filing markers (ARN/date), filed-record protection, backdating monitor (`GstReturnSnapshotTest`, `ReturnValidationReportTest`) |
| Dashboard | Permission-gated financial KPIs with SettlementService-faithful receivables (`DashboardTest`, 12 scenarios) |
| UI/brand | Design system (19 Blade components — the 14 from PR #2 plus the MS-06 Stage 0 primitives `x-table`/`x-dialog`/`x-combobox`/`x-textarea`/`x-detail-list` and the LaraLekh pagination view, PR #6), sidebar shell with icon rail + tooltips, guest layout, Inter fonts + OFL, logo/favicon assets (PR #2) |
| CI & protection | GitHub Actions (backend/frontend/quality), branch protection with required checks (`ci.yml`, protection API) |

## 6. Partially implemented functionality

| Area | Present | Missing | Register |
|---|---|---|---|
| Supplier payments | Schema, models, `PostPayment` action | No UI, no route, **no tests** — highest correctness risk in the codebase | LL-P1-001, LL-P1-002 |
| Inventory/stock | `StockMovement` model; purchase posting records movements | Sales posting moves no stock and there is no COGS, valuation, stock register or screens; policy undecided | LL-P1-010 (`DEC-012` proposed) |
| GSTR-1 JSON export | `Gstr1JsonExporter` service + todo test | Blocked: official JSON schema/sample must be checked into `docs/gst-schemas/` first (commit `3b8331c`) | LL-P2-001 |
| GSTR-2B | Books-vs-portal reconciliation worksheet | No portal feed/import; discrepancy handling needs a format decision | LL-P2-002 |
| Financial reports | Ledger-balance helpers inside `SettlementService` | No trial balance, P&L or balance sheet screens | LL-P1-004, LL-P1-005 |
| User/role management | Company-scoped roles/permissions at engine level | No user admin screens, invitations, or password reset | LL-P3-006 |
| Item master | `Item` model used by invoice lines | No item management screens | LL-P3-007 |
| Financial years | Created via seeders/code; locked-number protection exists | No FY management screen (create/close/reopen) | LL-P1-003 |
| PDF/document output | Fixed professional templates for invoice/receipt/correction/reversal/statement | No customization (letterhead, logo) | LL-P3-008 |

## 7. Missing functionality (not started)

- E-invoicing (IRP) integration and e-way bills — external credentials and
  owner decisions required (LL-P4-001, LL-P4-002).
- Banking, bank feeds and bank reconciliation (LL-P4-003).
- Backup/restore strategy and deployment pipeline (LL-P5-002, LL-P5-003).
- API/mobile surface — Sanctum installed and explicitly reserved for a later
  step (LL-P4-004, deferred).
- Purchase debit notes and purchase-side document workflow (LL-P1-006).
- Zero-value invoice document workflow (LL-P1-007), refund of excess customer
  credits (LL-P1-008), advance receipt vouchers (LL-P1-009), sales debit-note
  correction and reversal-of-reversal scope (LL-P1-011).
- README "Backend usage and boundaries" is stale: it still says purchase and
  stock workflows are "reserved for later steps" although purchases are
  implemented (LL-P5-001).

## 8. Known limitations and technical debt

1. **SQLite/MySQL trigger parity** — immutability triggers exist on MySQL only;
   tests on SQLite cannot exercise them (LL-P5-006).
2. **Month KPI boundaries** — dashboard month windows use UTC
   (`CarbonImmutable::now()`), not a business timezone (documented PR #2
   limitation).
3. **Gross month KPIs** — credit notes do not reduce the "Sales" KPI (labelled
   gross; refinement proposed alongside reporting work).
4. **UI coverage** — editors and document detail views retain legacy inline
   styling; only registers/dashboard/shell were modernized (LL-P3-001…005).
5. **composer audit** — historical advisories existed in the Laravel 11 era;
   re-review needed on the Laravel 13 stack (LL-P5-004).
6. **PostgreSQL not enabled** — immutability triggers need a separate
   implementation if ever required (explicitly out of scope).
7. **Stale documentation** — README boundaries section (above) and the
   "Deferred to later steps" list predate the purchase/GST implementations.
8. **Unlockable corrections** — a reversed invoice cannot be credited/debited
   and debit notes do not replenish credit capacity (deliberate MVP scope,
   `docs/corrections-design.md`; revisit via LL-P1-011).

## 9. Security and compliance considerations

- Posted-document immutability enforced in SQL triggers (MySQL) — do not
  disable; editing history is forbidden by design.
- Tenant isolation: membership revalidated per request; composite FKs;
  `withoutGlobalScopes`/raw SQL treated as privileged (`TenancyTest`).
- Fail-closed GST validation: unverified rules block export and are marked for
  CA review (`docs/gst-rules.md`) — the app stores no portal credentials and
  files nothing.
- No secrets in the repository; `.env` gitignored; fonts carry their OFL.
- Test database guard: non-SQLite test databases must be named `laravix_test_*`
  (`tests/TestCase.php`) — never weaken.

## 10. Development principles

1. Accounting correctness first: exact decimal arithmetic (`Money`), derived
   balances, immutable posted history.
2. Fail closed on anything legally unverifiable; mark CA-review items.
3. Reuse domain services; never duplicate accounting rules in UI code.
4. Every feature lands with Pest coverage; CI must stay green.
5. UI changes respect permission gating end-to-end (compute + render).
6. No new dependencies without owner approval; prefer open-source, vendored,
   or zero-dependency approaches.
7. Documentation reflects reality: update this plan when code changes.

## 11. Feature roadmap (phases)

Phase numbering matches the Works Register (`LL-P<phase>-<n>`).

### P0 — Planning and documentation — COMPLETED
- **Objective:** establish trustworthy project management documentation.
- **Scope:** this document set; repository inspection; registers.
- **Deliverables:** `docs/project/*` (five files).
- **Dependencies:** none.
- **Acceptance criteria:** registers consistent with the codebase; IDs unique;
  statuses honest; no invented approvals.
- **Required tests:** none (documentation-only).
- **Risks:** drift — mitigate by updating registers with each merged PR.
- **Status:** COMPLETED (2026-11-15; amended the same day after owner review
  — MS-04 split, readiness/authorization rule, MySQL validation requirements).

### P1 — Accounting foundations completion — PROPOSED (recommended next)

Delivered across two milestones so that essential accounting safety work is
not gated on optional workflow decisions. Approval of MS-04A does **not**
authorize MS-04B.

#### MS-04A — Accounting safety and essential reports (LL-P1-001…005)
- **Objective:** establish confidence in the existing supplier-payment
  accounting path and deliver essential financial management and reporting
  screens.
- **Scope:** LL-P1-001 (supplier-payment service tests), LL-P1-002 (supplier
  payments UI), LL-P1-003 (financial-year management screen), LL-P1-004
  (trial balance), LL-P1-005 (P&L and balance sheet).
- **Entry criteria:** explicit owner approval of MS-04A; existing
  `PostPayment` code inspected; accounting semantics and existing settlement
  invariants documented before implementation.
- **Exit criteria:** supplier-payment tests cover posting, permissions,
  tenancy and reversal behaviour; supplier-payment UI is tested and
  permission-protected; closed years reject posting; trial balance
  reconciles; P&L/balance sheet reconcile to posted ledger movements; full
  suite passes; required MySQL validation passes.
- **Risks:** supplier-payment code exists untested — behaviour must be
  verified against `SettlementService` semantics before exposure.
- **Status:** PROPOSED — awaiting owner approval.

#### MS-04B — Additional accounting workflows (LL-P1-006…011)
- **Objective:** address additional accounting workflows only after their
  product and accounting policies have been decided.
- **Scope:** LL-P1-006 (purchase debit notes), LL-P1-007 (zero-value
  invoices), LL-P1-008 (credit refunds), LL-P1-009 (advance receipt
  vouchers), LL-P1-010 (inventory/COGS policy and stock register), LL-P1-011
  (sales debit-note correction / reversal-of-reversal scope).
- **Entry criteria:** separate explicit owner approval of the relevant
  MS-04B work; the corresponding decisions (DEC-012…DEC-017) approved before
  implementation of the affected feature.
- **Exit criteria:** each approved workflow has a documented accounting
  design; service and UI tests pass; immutable-document and settlement rules
  remain intact; required MySQL validation passes.
- **Cross-cutting:** LL-P1-012 (MySQL validation cadence) applies to both
  milestones and is not a prerequisite for individual MS-04B workflows.
- **Status:** PROPOSED — per-workflow approval.

### P2 — GST compliance completion — PROPOSED
- **Objective:** finish GST output fidelity and CA-review closure.
- **Scope:** GSTR-1 JSON export once the official schema/sample is vendored;
  GSTR-2B import + reconciliation enhancement; resolution of fail-closed
  review items; GST treatment of advance receipts.
- **Dependencies:** LL-P2-001 blocked on schema (LL-P2-003/CA);
  LL-P2-004 depends on LL-P1-009.
- **Acceptance criteria:** exports validate against the official schema;
  every fail-closed item is either resolved or explicitly documented as
  CA-review; no rule is guessed.
- **Required tests:** golden tests against official samples; reconciliation
  tests.
- **Risks:** regulatory changes; portal-only data cannot be verified offline.
- **Status:** PROPOSED.

### P3 — UI modernization completion and administration — PROPOSED (Stage 0 completed)

- **Objective:** bring the remaining screens to the new design system and add
  administration screens.
- **MS-06 Stage 0 — shared UI foundations (LL-P3-000) — COMPLETED (PR #6,
  merge `0e46f11`, 2026-11-18):** delivered as a separate PR per the owner's
  binding decision on the approved MS-06 implementation plan. Scope:
  `x-table`, `x-dialog`, `x-combobox`, `x-textarea`, `x-detail-list`, the
  LaraLekh pagination view (`pagination.laralekh`, registered as the Laravel
  default and wired via `paginationView()` on the four register lists after
  review finding F-01), field-error accessibility wiring, and the
  `brand-200` token fix; review corrections F-01/F-02 included. Suite
  baseline on merged `main`: 412 passed / 1 todo / 1311 assertions;
  post-merge CI run `44315749152` 3/3 SUCCESS. The four Livewire list
  components (sales invoices, customer receipts, purchase bills, supplier payments)
  had their screen modernization completed via PR #22 (merge `f83eeed`, 2026-11-24).
  PDF customization (LL-P3-008) completed via PR #25 (merge `af18f0e`, 2026-11-24).
  UI inventory audit refreshed via PR #24 (merge `643497c`, 2026-11-24).
- **Scope (status):** MS-06 is fully completed. All editor, view, statement, master,
  admin, register list screens, and PDF customization are delivered.
- **Owner decisions from the approved MS-06 plan (unchanged):** Stage 0 as a
  separate PR (executed); brand-styled pagination replaces the stock view
  (executed); per-field error attribution via additive keys (component wiring
  shipped; editor adoption lands with each screen task); GST returns detail
  excluded (separate proposal only); mobile drawer focus trap and user-menu
  keyboard management deferred; `docs/ui/ui-inventory.md` refresh at MS-06
  completion.
- **Acceptance criteria:** visual-only changes for existing flows (behaviour
  tests unchanged and green); admin screens fully permission-tested.
- **Status:** Stage 0 COMPLETED; LL-P3-001…005 remain READY with Owner
  Approval "Not Required" — READY is not implementation authorization.

### P4 — Integrations — PROPOSED/BLOCKED
- **Objective:** external statutory and banking integrations.
- **Scope:** IRP e-invoicing, e-way bills, banking/bank reconciliation,
  Sanctum API foundations.
- **Dependencies:** IRP/GSP credentials and identifiers, bank-feed decision,
  owner approval. **Blocked** until provided (LL-P4-001…003); API work
  deferred (LL-P4-004).
- **Status:** BLOCKED/DEFERRED.

### P5 — Production readiness — PROPOSED
- **Objective:** make the application deployable and operable.
- **Scope:** deployment pipeline, production configuration, backup/restore,
  queue supervision/monitoring, composer audit re-review, README refresh,
  trigger-parity tests.
- **Acceptance criteria:** a reproducible deployment path documented and
  rehearsed; backup/restore tested end-to-end on a `laravix_test_*` database.
- **Status:** PROPOSED.

## 12. Milestones and dependencies

See `MILESTONES.md`. Summary: MS-01..MS-03 COMPLETED (core accounting Steps
1–4; CI & protection; UI modernization). Phase P1 is delivered across two
milestones: **MS-04A** (accounting safety and essential reports — the
recommended next milestone) and **MS-04B** (additional accounting workflows,
each gated on its own policy decision; approval of MS-04A does not authorize
MS-04B). MS-05 depends on MS-04B item LL-P1-009; MS-06 is independent of
MS-04A/MS-04B/MS-05 — its Stage 0 enabler substage (LL-P3-000) is completed
via PR #6 (merge `0e46f11`, 2026-11-18) while the remaining MS-06 screens
stay PROPOSED/READY as registered; MS-07 is blocked on external credentials;
MS-08 is last.

## 13. Testing and release strategy

- Every PR runs CI: backend Pest on SQLite in-memory, frontend build, quality
  (composer validate, platform reqs, syntax, whitespace) — branch protection
  enforces all three. **The SQLite CI baseline does not exercise the MySQL
  immutability triggers; it is the fast gate, not proof of MySQL behaviour.**
- Pre-merge locally: full `vendor/bin/pest`, `npm run build`,
  `git diff --check`.
- **MySQL validation is required for accounting changes involving
  posted-document immutability, triggers, migrations, number-series
  concurrency/locking, or posting paths that depend on MySQL-specific
  behaviour.** At minimum: (1) migration up/down/up on MySQL 8.4; (2)
  immutability-trigger verification; (3) the relevant posting and reversal
  tests run against MySQL; (4) number-series concurrency validation where
  applicable; (5) tests use a `laravix_test_*` database; (6) tests never
  target development or production data. Tooling and records:
  `docs/mysql-84.md`, `docs/mysql-test-runs.md`; the documented routine
  cadence is `docs/mysql-validation-cadence.md` (LL-P1-012).
- Releases: no release process exists yet — defined in MS-08.

## 14. Open questions requiring owner decisions

| # | Question | Register |
|---|---|---|
| 1 | Approve MS-04A scope (supplier-payment tests + UI, FY screen, trial balance, P&L/BS) | MS-04A / LL-P1-001…005 |
| 2 | Approve MS-04B workflows and their priority order (purchase debit notes, zero-value invoices, credit refunds, advance vouchers) | MS-04B / LL-P1-006…009 |
| 3 | Inventory & COGS policy: should sales post stock/COGS? Stock valuation method? | DEC-012 / LL-P1-010 |
| 4 | Sales debit-note correction & reversal-of-reversal scope extension? | DEC-017 / LL-P1-011 |
| 5 | Vendor the official GSTR-1 JSON schema/sample to unblock JSON export? | LL-P2-001 |
| 6 | GSTR-2B: manual import format acceptable? | DEC-018 / LL-P2-002 |
| 7 | CA engagement to close fail-closed GST items | LL-P2-003 |
| 8 | User & role management: invitation flow / password reset expectations? | DEC-019 / LL-P3-006 |
| 9 | PDF customization scope (letterhead, logo, footer text)? | DEC-020 / LL-P3-008 |
| 10 | IRP/GSP provider choice and credentials for e-invoicing/e-way | DEC-021 / LL-P4-001/002 |
| 11 | Target hosting/deployment environment for production readiness | DEC-022 / LL-P5-002 |
