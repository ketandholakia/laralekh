# LaraLekh — Decision Register

Records architectural, technical and product decisions. Decision status
concepts used in this register:

| Concept | Meaning |
|---|---|
| `PROPOSED` | Requires an owner decision; not yet approved |
| `OWNER_APPROVED` | Explicit owner approval recorded **within the decision's stated scope** |
| `OWNER_REJECTED` | Explicitly rejected by the owner |
| `SUPERSEDED` | Replaced by a later recorded decision |
| `EVIDENCED` | The decision is reflected in the existing implementation or documentation, inferred from repository evidence; **no separate owner approval is implied** |

`EVIDENCED` decisions remain in force but can be revisited by the owner.
`OWNER_APPROVED` is used **only** where the owner demonstrably approved the
decision (merged PR, explicit instruction). Implementation proposals that
still need an owner call are `PROPOSED`.

| ID | Topic | Decision | Rationale (evidence) | Approval status |
|---|---|---|---|---|
| DEC-001 | Money arithmetic | All monetary values handled as decimal strings or the `Money` value object; floats forbidden; SQLite stores text, MySQL decimal(15,2) | `app/Domain/Shared/ValueObjects/Money.php` (throws on float input); `SettlementService` comments; `MoneyCast` | EVIDENCED |
| DEC-002 | Posted-document immutability | Posted vouchers/invoices/lines/corrections/snapshots are immutable, enforced by MySQL triggers; corrections and reversals are separate immutable documents | `2026_11_05_160000_protect_posted_documents`, `README.md` ("Successful posts cannot be deleted or modified, even through SQL"), `docs/corrections-design.md` | EVIDENCED |
| DEC-003 | Tenancy model | Company-scoped everything via `TenantContext::run()` + `EnsureCompanyContext`; membership revalidated per request; roles company-scoped; company id never accepted from browser input | `app/Domain/Shared/Tenancy/*`, `README.md` "Backend usage and boundaries", `TenancyTest` | EVIDENCED |
| DEC-004 | Test/production databases | SQLite in-memory for the automated suite; MySQL 8 production target; PostgreSQL not enabled; `laravix_test_*` naming guard | `phpunit.xml`, `tests/TestCase.php:12-14`, `README.md`, `docs/mysql-84.md` | EVIDENCED |
| DEC-005 | GST product positioning | The application is a books-reconciliation and review workspace; it stores no portal credentials and files nothing; unverified rules fail closed and are flagged for CA review | `docs/gst-rules.md` (verified table + fail-closed list), `config/gst_returns.php` | EVIDENCED |
| DEC-006 | Corrections model | Corrections are separate immutable documents (GST vs commercial treatment); credit capacity capped; debit notes do not replenish capacity (MVP); reversal-of-reversal deferred | `docs/corrections-design.md` | EVIDENCED |
| DEC-007 | Settlements model | Allocations insert-only; balances derived; excess credits and advances shown separately, never absorbed; ledger is authoritative | `docs/settlements-design.md`, `SettlementService` | EVIDENCED |
| DEC-008 | Framework upgrade | Laravel 13, Pest 5, PHPUnit 13 with recorded validation | commit `af58222` (2026-11-07); `docs/validation.md` Laravel 13 section | OWNER_APPROVED (upgrade commit on owner's branch) |
| DEC-009 | CI and branch protection | GitHub Actions 3-job CI; protected `main` with strict required checks, admin-enforced, force-push/deletion blocked, conversation resolution; no reviewer count (single owner) | PR #1 merged 2026-11-15; protection API state | OWNER_APPROVED (PR #1) |
| DEC-010 | GSTR-1 JSON deferral | JSON exporter remains pending until the official GSTR-1 schema/sample is checked into `docs/gst-schemas/` | commit `3b8331c`; todo test `Gstr1JsonExporterPendingTest` | EVIDENCED |
| DEC-011 | UI modernization direction | Dashboard at `/`; company selection redirects to `/`; brand tokens (teal/navy/saffron) + self-hosted Inter (OFL) + zero-dependency icon sprite; permission-gated financial KPIs; receivables mirror `SettlementService` semantics read-only | PR #2 (disclosed in body; audited F1/F2/F3) merged 2026-11-15 | OWNER_APPROVED (PR #2) |
| DEC-012 | Inventory / COGS policy | UNDECIDED: purchases record stock movements; sales post no stock/COGS. Whether sales should move stock, and the valuation method, needs an owner decision before any stock/COGS feature | `README.md` ("Sales posting currently posts the financial voucher, not stock or cost of goods"); `PostPurchaseBill` uses `StockMovement` | PROPOSED |
| DEC-013 | Advance receipt vouchers | UNDECIDED: GST treatment already verified in `docs/settlements-design.md`; whether to build the workflow now | LL-P1-009 / LL-P2-004 | PROPOSED |
| DEC-014 | Purchase debit notes | Built upon `purchase_guards_and_debit_note_design` schema: capacity tracking against returnable quantities, proportional tax reversal, Dr. Payable / Cr. Purchase & Input GST, stock reduction for tracked items | LL-P1-006; PR #27 (merge `769b961`, 2026-11-24) | OWNER_APPROVED (PR #27) |
| DEC-015 | Zero-value invoice workflow | UNDECIDED: excluded from posting today; needs a non-accounting document design | `README.md`; LL-P1-007 | PROPOSED |
| DEC-016 | Refund of excess customer credits | Full customer credit refund workflow: `CustomerRefund` and `CustomerRefundAllocation` linked to unapplied advances (`Receipt`) and excess credits (`Invoice`); posts balanced payment voucher Dr. Customer Receivable (Sundry Debtors), Cr. Cash/Bank; statement closing reconciles with ledger balance; immutable once posted with `CustomerRefundReversal` support | LL-P1-008; PR #29 (merge `f2c634e`, 2026-11-25) | OWNER_APPROVED (PR #29) |
| DEC-017 | Sales debit-note correction / reversal-of-reversal scope | UNDECIDED: deliberately deferred in the corrections MVP | `docs/corrections-design.md`; LL-P1-011 | PROPOSED |
| DEC-018 | GSTR-2B import format | UNDECIDED: manual import needs a format decision (no portal feed exists) | LL-P2-002 | PROPOSED |
| DEC-019 | User administration expectations | Direct user creation/linking with initial password; `is_active` boolean on `company_user` pivot table (deactivated members fail `isMemberOf`); company-scoped custom roles with permission catalog and quick templates | PR #18 (merge `60765ff`, 2026-11-24); `UserManager`, `RoleManager`, `UserAndRoleManagementTest` | OWNER_APPROVED (PR #18) |
| DEC-020 | PDF customization scope | Company-level letterhead spacer (`hide_header`), custom secondary trade names, contact info lines, logo data URI, bank remittance details, footer terms, and signature designation | LL-P3-008; PR #25 (merge `af18f0e`, 2026-11-24) | OWNER_APPROVED (PR #25) |
| DEC-021 | Integration providers and credentials | UNDECIDED: IRP/GSP choice, e-way credentials, bank feed source | LL-P4-001…003 | PROPOSED |
| DEC-022 | Hosting/deployment target | UNDECIDED: environment for production readiness work | LL-P5-002 | PROPOSED |

## Notes

- Decisions DEC-001…DEC-007 are the core architecture; treat any change to
  them as requiring explicit owner approval (SUPERSEDED never applies without
  a written replacement decision).
- DEC-011 approval covers exactly the scope disclosed in PR #2; it does not
  authorize further UI redesign without a new decision.
- When a PROPOSED decision is approved, update its status to OWNER_APPROVED
  with the approval date and source, and add a CHANGELOG entry.
