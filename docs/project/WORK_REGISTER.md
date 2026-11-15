# LaraLekh — Works Register

Authoritative task register. IDs are stable (`LL-P<phase>-<n>`); never reuse or
renumber. Statuses: `PROPOSED · READY · IN_PROGRESS · BLOCKED · IN_REVIEW ·
COMPLETED · DEFERRED · CANCELLED`. Owner Approval states: `Required /
Approved / Rejected / Not Required`. Nothing is marked APPROVED or COMPLETED
without recorded evidence (commit, PR, or test run).

Baseline: `main` @ `8344280` (PR #2 merged), suite 360 passed / 1 todo /
1018 assertions.

## Readiness versus implementation authorization

**Task readiness and implementation authorization are separate controls.**
A task marked `READY` with `Owner Approval: Required` may be technically
ready, but **must not enter `IN_PROGRESS` until explicit owner authorization
is recorded** (in this register and the `DECISION_REGISTER.md` where a policy
decision is involved).

| Status | Meaning | Owner approval | Can implementation start? |
|---|---|---|---|
| PROPOSED | Work identified; scope/design may need refinement | Not yet requested/decided | No |
| READY | Prerequisites and technical preparation complete | **Required → not yet recorded** | **No — wait for approval** |
| READY | Prerequisites complete | Not Required | Yes, on normal instruction |
| IN_PROGRESS | Implementation underway | Must already be recorded | — |
| BLOCKED / DEFERRED | External dependency or explicit deferral | Revisit when unblocked | No |
| COMPLETED | Done, with recorded evidence (commit/PR/tests) | — | — |

Milestone grouping: P1 tasks are delivered across two milestones —
**MS-04A** (accounting safety and essential reports: LL-P1-001…005) and
**MS-04B** (additional accounting workflows, each gated on its own policy
decision: LL-P1-006…011). LL-P1-012 is a cross-cutting validation task.
See `MILESTONES.md`. Approval of MS-04A does **not** authorize MS-04B.

## Register

### P0 — Planning and documentation

| ID | Work item | Description | Category | Priority | Status | Deps | Owner approval | Acceptance criteria | Verification | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| LL-P0-001 | Establish project documentation | Create `docs/project/` with the master plan, works register, milestones, decision register and changelog, reflecting the actual codebase | documentation | Medium | COMPLETED | — | Not Required | Five documents exist, internally consistent, IDs unique | This document set (created 2026-11-15) | Revisit with every merged PR |
| LL-P0-002 | UI modernization and brand | Brand tokens, Inter + OFL, logo/favicon, design-system components, shell, dashboard, register screens, collapsed-rail correction | UI | High | COMPLETED | — | Approved (PR #2 merged 2026-11-15) | Merged PR #2; suite green | PR #2, CI runs on `016399c`/`ef20542` and on `main` `8344280` | Editors/detail views intentionally deferred (LL-P3-001…005) |
| LL-P0-003 | CI and branch protection | GitHub Actions (backend/frontend/quality) + protected `main` | infrastructure | High | COMPLETED | — | Approved (PR #1 merged 2026-11-15) | 3 required checks; strict up-to-date; force-push/deletion blocked | Protection API read; CI run 43687453646 | — |
| LL-P0-004 | Laravel 13 / Pest 5 / PHPUnit 13 upgrade | Framework and test-stack upgrade with validation | infrastructure | High | COMPLETED | — | Approved (commit `af58222`, 2026-11-07) | Suite green; validation recorded | `docs/validation.md` Laravel 13 section; `docs/audit/laravel-audit.md` | composer audit re-review still open (LL-P5-004) |

### P1 — Accounting foundations completion (delivered as MS-04A essential work and MS-04B decision-gated workflows — see `MILESTONES.md`)

> **MySQL validation rule:** tasks that touch posted-document immutability,
> triggers, migrations, number-series concurrency/locking, or posting paths
> that depend on MySQL-specific behaviour require MySQL validation
> (migration up/down/up, trigger verification, relevant posting/reversal tests
> against MySQL, series-concurrency validation where applicable) in addition
> to the SQLite CI baseline. SQLite passing does **not** demonstrate MySQL
> trigger behaviour. Tests must use a `laravix_test_*` database and never
> target development or production data.

| ID | Work item | Description | Category | Priority | Status | Deps | Owner approval | Acceptance criteria | Verification | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| LL-P1-001 | Supplier-payment service tests | `PostPayment`/`SupplierPayment` exist with no tests; write service tests covering posting, allocation semantics, permissions, tenancy and reversal interaction before any exposure. **MySQL validation applies** (posting path) | testing | Critical | READY | — | Required | Full coverage of posting edge cases; suite green; MySQL run recorded per the P1 validation rule | New Pest tests on SQLite and MySQL; full suite | Highest correctness risk in the codebase (untested accounting path). READY ≠ authorized — see the readiness rule above |
| LL-P1-002 | Supplier payments UI | Record/list supplier payments against purchase bills, reusing `PostPayment`; permission-gated. **MySQL validation applies** (posting path) | feature | High | READY | LL-P1-001 | Required | Screens render for `purchases.view/create/post`; unauthorized users get 403; payments reduce purchase outstanding correctly; MySQL run recorded | UI Pest tests; suite | Outstanding-balance semantics for purchases need the same derived approach as customer settlements |
| LL-P1-003 | Financial-year management screen | Create/close financial years per company with guards (no posting into closed years) | feature | High | READY | — | Required | FY create/close works for members with an admin role; closed years reject posting | UI + service tests | FYs currently only created via seeders/tests/code |
| LL-P1-004 | Trial balance report | Debit/credit totals per ledger for a period, from posted vouchers only | feature | High | READY | — | Required | TB debits equal credits; respects tenant scope; period filters | Service + UI tests | Reuse ledger movement aggregation patterns from `SettlementService::ledgerBalance` (read-only variant) |
| LL-P1-005 | Profit & loss and balance sheet reports | P&L for a period and balance sheet as at a date from the chart of accounts and posted vouchers | feature | High | READY | LL-P1-004 | Required | Reports reconcile to ledger totals; empty state for new companies | Service + UI tests | Depends on ledger-group classification in the seeded chart |
| LL-P1-006 | Purchase debit-note workflow | Workflow for the designed purchase debit-note storage (`purchase_guards_and_debit_note_design` migration) | feature | Medium | PROPOSED | — | Required | Mirrors sales-correction rigour: capacity, immutability, GST treatment decided | Service + UI tests | Design exists in schema; scope needs owner approval |
| LL-P1-007 | Zero-value invoice document workflow | Non-accounting document workflow for zero-value invoices (explicitly excluded from posting today) | feature | Medium | PROPOSED | — | Required | Zero-value documents recorded without accounting postings; GST documents list unaffected | Tests | README: "Zero-value invoices require a future non-accounting document workflow" |
| LL-P1-008 | Refund of excess customer credits | Workflow to refund excess credits/advances without disturbing immutable history | feature | Medium | PROPOSED | — | Required | Refund posts a mirrored voucher; credits/advances reduce accordingly; statements reconcile | Service + UI tests | Excess credits currently displayed separately only |
| LL-P1-009 | Advance receipt voucher workflow | GST-correct advance receipts (time of supply, receipt voucher rules per `docs/settlements-design.md` § GST treatment of advances) | feature | High | PROPOSED | — | Required | Advance vouchers/records exist; later allocation converts advance to settlement; GST documents unaffected until supply | Service tests; CA review sign-off | Legal treatment already verified in docs — implementation design needed |
| LL-P1-010 | Inventory/COGS policy and stock register | Decide whether sales post stock/COGS; add valuation + stock register screens | feature | Medium | PROPOSED | DEC-012 | Required | Policy recorded in `DEC-012`; implementation matches it | Tests per decision | Purchases already record stock movements; sales do not |
| LL-P1-011 | Sales debit-note correction & reversal-of-reversal scope | Extend corrections where MVP deliberately defers: debit-note correction, reversal-of-reversal | feature | Medium | PROPOSED | — | Required | Scope approved and documented in `docs/corrections-design.md`; implemented with capacity guards | Service tests | `docs/corrections-design.md` marks these as later workflows |
| LL-P1-012 | MySQL migration/concurrency validation cadence | Cross-cutting validation task (not a prerequisite for individual MS-04B workflows): promote migration up/down/up, immutability-trigger verification, and series-concurrency runs from ad-hoc docs to a documented routine (checklist or CI job on MySQL) | infrastructure | Medium | READY | — | Not Required | Documented cadence executed for each schema change; covers the P1 MySQL validation rule above | `docs/mysql-84.md`, `docs/mysql-test-runs.md` records | Requires local MySQL 8.4 (Docker path exists) |

### P2 — GST compliance completion

| ID | Work item | Description | Category | Priority | Status | Deps | Owner approval | Acceptance criteria | Verification | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| LL-P2-001 | GSTR-1 JSON export | Implement `Gstr1JsonExporter` against the official schema/sample | feature | Medium | BLOCKED | LL-P2-003 | Required | Export validates against the official schema; golden test un-todo'd | Existing todo test in `Gstr1JsonExporterPendingTest` | Blocked: schema/sample must be vendored into `docs/gst-schemas/` (decision `3b8331c`) |
| LL-P2-002 | GSTR-2B import and reconciliation enhancement | Define a manual GSTR-2B import format and compare portal ITC/liabilities against books | feature | High | PROPOSED | LL-P2-003 | Required | Imported 2B data reconciles in the 3B worksheet; discrepancies flagged | Golden tests | No portal feed exists by design |
| LL-P2-003 | Close fail-closed GST review items | GSTIN portal existence, blocked-ITC statutory eligibility, Table 13 ranges, transaction-level place-of-supply treatment — resolve with CA/portal evidence | documentation | High | READY | — | Required | Each item resolved or explicitly retained as CA-review with rationale | Update `docs/gst-rules.md` + `config/gst_returns.php` | Items block/flag exports today by design |
| LL-P2-004 | GST treatment of advance receipts | Integrate advance receipt vouchers with GST time-of-supply handling once LL-P1-009 exists | feature | Medium | READY | LL-P1-009 | Required | Advances reflected correctly in GSTR-1 documents or explicitly out of scope with CA sign-off | Tests + CA review | Legal sources already cited in `docs/settlements-design.md` |

### P3 — UI modernization completion and administration

| ID | Work item | Description | Category | Priority | Status | Deps | Owner approval | Acceptance criteria | Verification | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| LL-P3-001 | Invoice editor + view modernization | Apply the design system to `invoice-editor`/`invoice-view`; visual only | UI | Medium | READY | — | Not Required | All existing Livewire bindings/tests unchanged and green | Suite + visual review | Highest-traffic screen |
| LL-P3-002 | Receipt editor + view modernization | Same treatment for receipt screens | UI | Medium | READY | — | Not Required | As above | Suite + visual review | — |
| LL-P3-003 | Purchase editor + view modernization | Same treatment for purchase screens | UI | Medium | READY | — | Not Required | As above | Suite + visual review | — |
| LL-P3-004 | Correction/reversal screens modernization | Correction editor/view, reversal editor/view | UI | Medium | READY | — | Not Required | As above | Suite + visual review | — |
| LL-P3-005 | Customer outstanding/statement modernization | Outstanding + statement screens on the design system | UI | Medium | READY | — | Not Required | As above | Suite + visual review | — |
| LL-P3-006 | User & role management screens | Company-scoped user administration: create users, assign company-scoped roles, deactivate; expectations for invitations/password reset need owner input | feature | High | READY | — | Required | Only admins manage users/roles; roles remain company-scoped; suite green | `LivewirePermissionTest`-style tests | Roles are company-scoped today; no screens exist |
| LL-P3-007 | Item master screens | Create/maintain items (SKU, HSN/SAC, rates linkage) | feature | Medium | READY | — | Not Required | Items manageable by authorized users; invoice line lookup unchanged | UI tests | Items currently created only via seeders/tests |
| LL-P3-008 | PDF template customization | Letterhead/logo/footer options per company | feature | Low | PROPOSED | — | Required | Customization renders in DomPDF output; statutory content unchanged | PDF sample visual review | Scope needs owner decision |

### P4 — Integrations (blocked/deferred)

| ID | Work item | Description | Category | Priority | Status | Deps | Owner approval | Acceptance criteria | Verification | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| LL-P4-001 | E-invoicing (IRP) integration | IRP registration for eligible invoices | feature | Medium | BLOCKED | LL-P2-003 | Required | Signed invoices accepted by the chosen IRP/GSP in sandbox | Sandbox tests | Blocked: provider choice + credentials |
| LL-P4-002 | E-way bill integration | E-way bill generation from invoices | feature | Medium | BLOCKED | LL-P2-003 | Required | E-way JSON accepted in sandbox | Sandbox tests | Blocked: credentials |
| LL-P4-003 | Banking and bank reconciliation | Bank ledger + statement import + matching | feature | Medium | BLOCKED | — | Required | Imported statements reconcile to the bank ledger | Tests | Blocked: bank/format decision |
| LL-P4-004 | API / mobile foundations | Sanctum is installed and reserved for a later step; token-authenticated API surface | feature | Low | DEFERRED | — | Required | Deferred until core screens complete | — | README records this intent |

### P5 — Production readiness

| ID | Work item | Description | Category | Priority | Status | Deps | Owner approval | Acceptance criteria | Verification | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| LL-P5-001 | README refresh | Update stale sections: "purchase and stock workflows are reserved for later steps" (purchases implemented), the "Deferred to later steps" list, and PHP 8.3 setup text (stack is PHP 8.4+/Laravel 13) | documentation | High | READY | — | Not Required | README matches the actual codebase | Review | Also fold in the CI/contribution section already present |
| LL-P5-002 | Deployment pipeline and production environment | Reproducible deployment (migrations, seeders, assets, queue workers) | infrastructure | High | PROPOSED | — | Required | A documented, rehearsed deployment path exists | Rehearsal on a test host | Target hosting needs owner decision |
| LL-P5-003 | Backup and restore strategy | Backup schedule, restore runbook, tested against a `laravix_test_*` database | infrastructure | Critical | PROPOSED | — | Required | Restore proven end-to-end | Restore rehearsal | Accounting data loss is unrecoverable without this |
| LL-P5-004 | composer audit re-review on Laravel 13 | The recorded advisories predate the Laravel 13 upgrade; re-run `composer audit` and disposition findings | security | Medium | READY | — | Not Required | Findings dispositioned in `docs/validation.md` | `composer audit` output | — |
| LL-P5-005 | Queue supervision and monitoring | `queue:work redis` is documented but unsupervised; add supervision/monitoring approach | infrastructure | Medium | PROPOSED | LL-P5-002 | Required | Queued jobs run reliably in production shape | Ops checklist | — |
| LL-P5-006 | SQLite/MySQL trigger parity tests | Immutability triggers are MySQL-only; add a MySQL-run test lane or documented coverage note | testing | Medium | PROPOSED | — | Not Required | Posted-document immutability covered by automated tests on MySQL | MySQL test lane | LL-P1-012 provides the MySQL cadence |

## Summary

**Total tasks: 38**

By status: COMPLETED 4 · READY 17 · PROPOSED 12 · BLOCKED 4 · DEFERRED 1 ·
IN_PROGRESS 0 · IN_REVIEW 0 · CANCELLED 0

By priority: Critical 2 · High 13 · Medium 21 · Low 2

Tasks requiring owner approval before work starts ("Required") — **24**:
LL-P1-001…LL-P1-011, LL-P2-001…LL-P2-004, LL-P3-006, LL-P3-008,
LL-P4-001…LL-P4-004, LL-P5-002, LL-P5-003, LL-P5-005.

"Not Required" — **11**: LL-P0-001, LL-P1-012, LL-P3-001…LL-P3-005,
LL-P3-007, LL-P5-001, LL-P5-004, LL-P5-006. These may start on normal
instruction.

Approved — **3**: LL-P0-002, LL-P0-003, LL-P0-004 (historical evidence
recorded in the task rows: merged PRs / pushed commits).

> Maintenance rules: keep this table the single source of task truth; when a
> task moves, update its Status here and add a CHANGELOG entry with evidence.
> `READY` never implies authorization — record owner approval first. MySQL
> validation follows the P1 rule at the top of the phase section.
