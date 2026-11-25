# LaraLekh — Development Changelog

Chronological record of development events, drawn from the git history and
verified records only (`git log`; `docs/validation.md`; CI runs). Entries are
ordered oldest → newest within each release era. Task IDs reference
`WORK_REGISTER.md`; historical work predates the register and has been
backfilled into it at LL-P0-002…004 with evidence.

**Historical-entry qualification:** backfilled entries record what happened
historically; they do not imply that related work is currently authorized.
Commit references, dates and subjects in this file were verified against
`git log` on 2026-11-15; statements that could not be verified are
marked unverified.

---

## 2026-11-05/06 — Steps 2–3 accounting core (historical, pre-register)

- **Summary:** session authentication with membership-checked company and
  financial-year selection (`e214ac1`, 2026-11-05); tenant-scoped GST return
  review UI foundations (`b6cb68b`, 2026-11-06); permission-checked receipt,
  allocation and customer statement screens (`d7697cc`, 2026-11-06).
- **Commits:** `e214ac1`, `b6cb68b`, `d7697cc` and related (dates per `git log`).
- **Tests:** suite validated on SQLite and MySQL (`docs/validation.md` Step 2
  section: 208 tests / 428 assertions at that point).
- **Outstanding:** documented in `docs/validation.md` historical sections.

## 2026-11-05/06 — Steps 3–4: corrections, receipts, purchases, GST returns (historical, pre-register)

- **Summary (selected commits):** sales corrections model + immutability
  (`190000_*`); receipts + allocations (`2026_11_06_200000_create_receipts`);
  compensation cess; purchase bills + supplier payment schema
  (`220000_*`, `230000_*`); purchase guards and debit-note design
  (`240000_*`); GST return snapshots + filed-record protection; audit sales
  and purchase cancellation; GSTR-1 official-template CSV export with golden
  tests; GSTR-3B reconciliation worksheet with ledger tie-outs; fail-closed
  export validation; GSTIN checksum validation; B2CL threshold verification
  against CBIC notification 12/2024; per-method permission guards on
  state-changing Livewire methods; GSTR-1 JSON exporter left pending official
  schema (`3b8331c`).
- **Tests:** full suite validated on SQLite and MySQL; series-concurrency
  harness runs recorded; GSTR golden tests.
- **Evidence:** `git log` 2026-11-06 entries; `docs/gst-rules.md`;
  `docs/gst-returns-test-inventory.md`.

## 2026-11-07 — Laravel 13 upgrade, audit, MySQL 8.4 tooling

- **Commits:** `af58222` (Laravel 13 / Pest 5 / PHPUnit 13), `76b295b`
  (activitylog v5 attribute_changes column), `dccd9bd` (full repository
  audit), `2f2d173` (Docker Compose MySQL 8.4 target), `304bd21` (docs).
- **Tests:** suite green on SQLite and MySQL 8.4; migration up/down/up;
  concurrency runs (see `docs/validation.md`, `docs/audit/laravel-audit.md`,
  `docs/mysql-test-runs.md`).
- **Outstanding:** composer audit advisories from the Laravel 11 era
  disposition pending on the new stack (→ LL-P5-004).

## 2026-11-15 — Publication, CI, protection (MS-02)

- **Summary:** repository published to GitHub; `origin` added; CI workflow
  (backend SQLite / frontend build / quality) and branch protection
  (strict required checks, admin-enforced, force-push/deletion blocked,
  conversation resolution, no reviewer count) applied; **PR #1 merged**
  (`68b569d` CI workflow; merge commit `b54a3dd`).
- **Task IDs:** LL-P0-003.
- **Tests:** Pest 348/1/971 before push (recorded in the session report);
  CI 3/3 green on PR and main.
- **Commits:** `07d10ca` (base publication), `68b569d`, merge `b54a3dd` —
  all dated 2026-11-15; publication was authorized in a preceding session.

## 2026-11-15 — UI modernization and brand (MS-03)

- **Summary:** Phase 0 UI audit (`docs/ui/ui-inventory.md`); brand tokens +
  Inter (self-hosted, OFL); logo/favicon assets; 14 design-system components
  with inline icon sprite; redesigned login + company selection; application
  shell (collapsible sidebar, header, mobile drawer); dashboard at `/` with
  permission-gated KPIs; register screens modernized; two pre-existing layout
  bugs fixed (`$slot`/`@extends` mismatch; tenant-context guard for the
  permission-backed nav link) with regression tests; 4 additional dashboard
  regression tests; independent audit fixes F1 (permission-gated financial
  HTML), F2 (receivables mirroring `SettlementService` semantics, no locking,
  excess credits separate), F3 (verbatim Inter OFL); collapsed-sidebar
  icon-rail correction with hover/focus tooltips (labels removed from layout
  instead of clipped; live Alpine state binding).
- **Task IDs:** LL-P0-002.
- **Files/modules:** layouts, 14 components, dashboard component + view,
  guest layout, brand assets, fonts, routes (`/`), login/select views,
  `AuthenticationTest`, `DashboardTest` (new), `README`, `tailwind.config.js`,
  `TenantContext` (`hasContext()` accessor), `CompanySelectionController`
  (redirect target).
- **Tests:** full suite **360 passed, 1 todo, 1018 assertions** (baseline
  348/971 + 12 new tests); `npm run build` pass; browser verification at
  1440/1366/390 including tooltips and mobile drawer.
- **Commits:** `016399c`, `ef20542` on `ui-modernization`; merged as PR #2 →
  `8344280`.
- **Behavior changes (disclosed in PR #2):** `/` is the dashboard; company
  selection redirects to `/`; dashboard financial figures permission-gated.
- **Outstanding:** editors/detail views keep legacy styling (LL-P3-001…005);
  month KPIs gross on UTC boundaries (plan §8); `x-button` dead link branch.

## 2026-11-15 — Project planning (MS-04A/MS-04B preparation)

- **Summary:** established `docs/project/` — `DEVELOPMENT_PLAN.md`,
  `WORK_REGISTER.md` (38 tasks), `MILESTONES.md` (MS-01…MS-08 with MS-04
  later split into MS-04A/MS-04B), `DECISION_REGISTER.md`
  (DEC-001…DEC-022), this changelog. Recommended next milestone:
  accounting foundations completion, starting with supplier-payment tests
  (LL-P1-001).
- **Task IDs:** LL-P0-001.
- **Tests:** none (documentation-only).
- **Outstanding:** owner review of the plan and approval of milestone scope;
  decisions DEC-012…DEC-022 require owner input.

## 2026-11-15 — Planning documentation review corrections (documentation-only)

- **Summary:** owner-requested review corrections to the five planning
  documents, all inside `docs/project/`. **No application implementation
  occurred and no commit exists for this change yet.**
  - `WORK_REGISTER.md`: fixed the `Basline` → `Baseline` typo; added the
    readiness-versus-authorization rule and an interpretation table
    (`READY` + approval `Required` ≠ authorized; `IN_PROGRESS` requires
    recorded approval); documented the MS-04A/MS-04B milestone grouping in
    the P1 section; added the MySQL-validation rule (SQLite CI does not
    prove MySQL trigger behaviour) to the P1 section, LL-P1-001/002 and
    LL-P1-012; marked statements as qualified where evidence is partial.
  - `DEVELOPMENT_PLAN.md`: split phase P1's milestone description into
    MS-04A (essential reports/safety) and MS-04B (decision-gated workflows);
    rewrote §13 with explicit MySQL validation requirements (migration
    up/down/up, trigger verification, MySQL posting/reversal tests,
    series concurrency, `laravix_test_*` only, never dev/prod data); updated
    §14 open-question references to MS-04A/MS-04B and DEC IDs.
  - `MILESTONES.md`: replaced MS-04 with MS-04A/MS-04B (membership, entry
    and exit criteria, per-workflow approval, MySQL validation in exit
    criteria); MS-05 entry criterion now references MS-04B item LL-P1-009.
  - `DECISION_REGISTER.md`: documented `EVIDENCED` as a distinct concept in
    the states table (inferred from repository evidence; no separate owner
    approval implied). No decision's status changed.
  - `CHANGELOG.md`: this entry; historical entry corrections below.
- **Evidence audit:** all 15 commit references in this file verified against
  `git log` (hash, date, subject). Test-count references verified against
  `docs/validation.md` and session records.
- **Task IDs:** LL-P0-001 (documentation amendment).
- **Tests:** none (documentation-only).
- **Outstanding:** owner review; MS-04A/MS-04B remain PROPOSED and require
  separate explicit owner approval.

---

## Maintenance

Add one entry per merged PR / completed task with: date, task IDs, summary,
files, tests, results, commit, outstanding issues. Never invent entries; link
evidence (commit SHA, CI run).

## 2026-11-17 — MS-04A owner authorization recorded (documentation-only)

- **Summary:** the owner explicitly approved milestone **MS-04A
  (accounting safety and essential reports)** for implementation on
  **2026-11-17**. Authorized task IDs: **LL-P1-001, LL-P1-002, LL-P1-003,
  LL-P1-004, LL-P1-005**. **MS-04B (LL-P1-006…LL-P1-011) is explicitly NOT
  approved**; none of its policy decisions (DEC-012…DEC-017) are made or
  assumed. Approval recorded in `WORK_REGISTER.md` (LL-P1-001…005 approval
  fields and summary) and `MILESTONES.md` (MS-04A status + authorization
  record). `DECISION_REGISTER.md` was inspected: it records decisions, not
  milestone authorizations, so no entry was added there and no DEC-012…017
  status changed.
- **Task IDs:** LL-P1-001…LL-P1-005 (approval recording; statuses remain
  READY until each task actually enters implementation).
- **Implementation branch:** `feature/ms-04a-accounting-foundations`
  (created from local `main` @ `943b550`, which includes the approved
  planning documentation commit; `origin/main` is at `8344280` — the docs
  commit is preserved on the feature branch and local `main`, not pushed).
- **Tests:** none (documentation edit only).
- **Outstanding:** entry audit, then staged implementation A–E; MySQL
  validation per plan §13.

## 2026-11-17 — MS-04A implementation (Stages A–E)

- **Summary:** implemented the five owner-approved tasks on
  `feature/ms-04a-accounting-foundations` (created from `main` @ `943b550`;
  includes the approved planning documentation). Not committed yet.
  - **LL-P1-001:** `tests/Feature/SupplierPaymentTest.php` (13 tests) proving
    `PostPayment` invariants: balanced Dr supplier / Cr cash entries, SP
    series numbering, derived bill outstanding with active-allocation
    semantics, RCM payable = taxable amount, full-allocation rule, closed-year
    rejection, duplicate-post rejection, rollback consuming no number,
    tenancy isolation, reversal restoring outstanding, paise arithmetic.
    **Found and fixed a genuine defect:** `SupplierPaymentAllocationReversal`
    model class did not exist although `PostPayment::reverse()` and its
    migration reference it — the payment-reversal path would have fataled.
  - **LL-P1-002:** routes `supplier-payments.index/create/show` (behind
    `company.context` + `purchases.view/create`), `SupplierPaymentList`/
    `SupplierPaymentEditor`/`SupplierPaymentView` components + views; editor
    reuses `PostPayment` (no accounting logic in Livewire); per-method
    permission guards (`purchases.post` for posting); relations added to
    `SupplierPayment` (party, ledger) and `SupplierPaymentAllocation`
    (payment, purchaseBill) for display.
  - **LL-P1-003:** `FinancialYearManager` screen (create/close behind a new
    `years.manage` permission — `FinancialYearPermissionSeeder` registered in
    `DatabaseSeeder`); closing is permanent (no reopen); closed years already
    reject posting in `PostPayment`/`PostPurchaseBill`/`VoucherNumberAllocator`.
  - **LL-P1-004/005:** read-only `FinancialReports` service
    (`trialBalance`, `profitAndLoss`, `balanceSheet`) from posted voucher
    movements only — no locks, no writes, exact `Money` arithmetic, sums in
    application code (MySQL decimal/SQLite text convention); screens
    `TrialBalance`, `ProfitAndLoss`, `BalanceSheet` behind a new
    `reports.view` permission (`ReportingPermissionSeeder`); balance sheet
    includes retained profit and reports a reconciliation check.
- **Tests:** new suites 13 + 5 + 5 + 7 = **30 tests**; full suite
  **390 passed, 1 todo, 1162 assertions** on SQLite (baseline 360/1018);
  the same 30 tests **pass on MySQL 8.4.13** (`laravix_test_ms04a`).
- **MySQL validation:** migration up/down/up PASS (`laravix_test_migrations`);
  46 triggers present; runtime tamper probe (UPDATE posted voucher) rejected
  by trigger; series-concurrency harness PASS (`laravix_test_ms04a_conc`).
  All databases named `laravix_test_*`; dev/prod data never touched.
- **Commits:** none yet — awaiting owner authorization to commit.
- **Outstanding:** owner acceptance of MS-04A exit criteria; MS-04B remains
  NOT approved; sidebar/sidebar navigation entries for the new screens are
  minimal (register links only) pending a UI pass.

## 2026-11-17 — MS-04A audit remediation (F-01…F-04, documentation of code change)

- **Summary:** implemented the four owner-audit findings on
  `feature/ms-04a-accounting-foundations` (uncommitted). Not committed yet.
  - **F-01 (HIGH):** `SupplierPaymentEditor::persistDraft()` now derives the
    financial year from `CompanySession::financialYearId()` (the
    `SaveReceiptDraft` pattern) instead of a hardcoded `2026-27` name.
  - **F-03 (MEDIUM):** draft-stage validation aligned with `SaveReceiptDraft`:
    strictly positive amount (negative decimals rejected with an accurate
    message), payment ledger must be `CASH_BANK`, closed selected year
    rejected, payment date must fall inside the selected year, and a missing
    session year reports "Select a financial year first." instead of a 500.
    `PostPayment` remains the authoritative posting validation.
  - **F-04 (LOW):** redundant second `catch (\OverflowException)` removed.
  - **F-02 (MEDIUM):** five new tests for `SupplierPaymentView` and the
    session-FY behaviour: view renders for `purchases.view` users (403 via
    route middleware for others), foreign-company payment 404s, allocations
    render with the bill reference, reversal banner mirrors the receipt view,
    session-FY filing works for a non-2026-27 year, missing-FY validation
    error, closed-year and date-outside-year rejection.
  - **View binding fix (related to F-02):** `SupplierPaymentView::mount` now
    takes the payment id (resolved tenant-scoped inside mount/render) instead
    of route-model binding, which evaluated the company-scoped query before
    any tenant context existed and 500-ed; `ReceiptView`'s id-based pattern
    was the model.
- **Tests:** new total **36 MS-04A tests** (30 + 6 remediation tests); full
  suite **396 passed, 1 todo, 1187 assertions** on SQLite; the remediated
  suites **pass on MySQL 8.4.13** (`laravix_test_ms04a`, 36 passed /
  169 assertions). Build PASS; `git diff --check` clean.
- **Commits:** none yet — awaiting owner authorization.
- **Outstanding:** F-05 (sidebar entries) and F-06 (P&L FY default) remain
  intentionally unimplemented (audit LOW items outside this authorization);
  MS-04B remains NOT approved.

## 2026-11-17 — MS-04A owner acceptance recorded

- **Summary:** the owner formally **accepted MS-04A** (LL-P1-001…LL-P1-005)
  on 2026-11-17 following the independent acceptance audit (verdict:
  **ACCEPTANCE READY**) and the F-01…F-04 remediation, which the audit
  re-verified. Recorded in `MILESTONES.md` (MS-04A ACCEPTED) and the
  `WORK_REGISTER.md` task notes. Final evidence: full suite 396 passed /
  1 todo / 1187 assertions (SQLite); 36 MS-04A tests passed on MySQL 8.4.13
  (`laravix_test_ms04a`); migration up/down/up, trigger tamper probe and
  series concurrency recorded PASS; build and `git diff --check` clean.
  F-05 (sidebar entries) and F-06 (P&L FY default) remain documented
  follow-ups, not acceptance blockers. **MS-04B remains not approved.**
- **Task IDs:** LL-P1-001…LL-P1-005 (acceptance annotation).
- **Commits:** a single local commit on
  `feature/ms-04a-accounting-foundations`
  (`feat: implement MS-04A accounting foundations`) is authorized by this
  acceptance; **no push, no PR, no merge**.
- **Tests:** recorded as above; no new tests in this acceptance entry.
- **Outstanding:** commit authorization executed; push/PR/merge await
  separate owner instruction. MS-04B and DEC-012…017 remain open decisions.

## 2026-11-18 — Hardening/documentation pass (owner-authorized "Option A")

- **Summary:** the owner explicitly authorized four tasks on 2026-11-18 —
  **LL-P1-013** (supplier-payment draft hardening), **LL-P1-012** (MySQL
  validation cadence), **LL-P5-001** (README refresh) and **LL-P5-004**
  (composer audit re-review). All four are implemented, verified and merged.
  **MS-04B (LL-P1-006…011), DEC-012…017, F-05, F-06 and MS-05A
  were explicitly excluded and were not touched.**
  - **LL-P1-013** (new register entry, owner-approved by name in the
    authorization): `SupplierPaymentEditor::persistDraft()` now re-resolves
    the draft under a row lock and refuses to update anything that is no
    longer a draft, so a stale editor tab can no longer overwrite a posted
    payment's date/amount/ledger; `validateDraft()` now rejects a party
    without a liability (supplier) ledger at draft stage, mirroring
    `PostPayment`'s authoritative rule (customers, asset/expense-ledger
    parties and ledger-less parties are refused before anything is
    persisted). Two new UI tests prove both behaviours. UI-level only — no
    migration, trigger or allocator change.
  - **LL-P1-012:** created `docs/mysql-validation-cadence.md` — the
    documented routine (when the full vs reduced cadence runs, the five
    checks, host notes, last-executed record), cross-referenced from
    `DEVELOPMENT_PLAN.md` §13 and `docs/mysql-84.md`. Delivered as the
    checklist variant the register allowed; a MySQL CI lane would change CI
    and remains an owner decision.
  - **LL-P5-001:** README refreshed — PHP 8.4+ setup text, dependency-status
    rewritten for Laravel 13 with the clean audit result, "purchase and stock
    workflows are reserved for later steps" corrected, "Deferred to later
    steps" list updated, and a new "Purchases, supplier payments and
    financial reports" section verified against `PostPurchaseBill`,
    `PostPayment` and the seeded permissions.
  - **LL-P5-004:** `composer audit --locked` re-run via the official
    `composer-stable.phar` (Composer 2.11.2, PHP 8.5.9 host): **0 advisories,
    0 abandoned packages** on the lockfile (`laravel/framework` v13.40.0);
    disposition recorded in `docs/validation.md`.
- **Task IDs:** LL-P1-012, LL-P1-013, LL-P5-001, LL-P5-004.
- **Files/modules:** `app/Livewire/Purchase/SupplierPaymentEditor.php`,
  `tests/Feature/SupplierPaymentUiTest.php`, `docs/mysql-validation-cadence.md`
  (new), `docs/mysql-84.md`, `docs/mysql-test-runs.md`, `docs/validation.md`,
  `README.md`, `docs/project/WORK_REGISTER.md`,
  `docs/project/DEVELOPMENT_PLAN.md` (§13 cadence cross-reference), this
  changelog.
- **Tests:** full suite **398 passed, 1 todo, 1207 assertions** on SQLite
  (baseline 396/1187 + 2 new tests); `npm run build` PASS; `git diff --check`
  clean. Reduced MySQL cadence executed (per the new cadence document):
  migration up/down/up PASS (`laravix_test_migrations`), supplier-payment
  suites **26 passed (115 assertions) on MySQL 8.4.13**
  (`laravix_test_ms04a_20261118`), 46 triggers present, tamper probe (raw
  UPDATE and DELETE on a posted voucher) rejected by triggers; direct SQL
  inserts of posted vouchers also rejected. All databases `laravix_test_*`;
  dev/prod data never touched.
- **Commits:** single commit `16c599a6b82f9a772d1e6408d8e8d7f774d035b4`
  (`feat: harden supplier payments and complete project maintenance`) on
  `feature/ms-04a-accounting-foundations`, created after the read-only
  pre-commit audit returned READY TO COMMIT. Published and merged with
  explicit owner authorization: branch pushed as a fast-forward
  (`3f6ca27..16c599a`); **PR #4** opened against `main` (containing exactly
  this one commit and the ten audited files); all three required CI checks
  (Backend tests SQLite, Frontend build, Quality and security) completed
  SUCCESS; **PR #4 merged as merge commit `8132cf8f3f0a7b7816dfb74ef1ea3b17f1828adb`**
  — post-merge CI on `main` @ `8132cf8` also green. The feature branch was
  preserved (not deleted); no squash, no rebase, no force-push.
- **Post-merge baseline:** `main` @ `8132cf8` — suite 398 passed / 1 todo /
  1207 assertions (SQLite).
- **Independent review:** an independent review agent re-ran the full SQLite
  suite (exact match: 398 passed / 1 todo / 1207 assertions) and audited all
  five criteria (correctness, scope creep, documentation accuracy, code
  quality, register governance). Verdict: **ACCEPT WITH FINDINGS** — one
  MAJOR (register summary READY/PROPOSED split off by one) and one MINOR
  (editor class docblock contradicted the new draft-stage mirroring), both
  corrected immediately after the review; the affected suites were re-run
  green (26 passed / 115 assertions). No blockers; no scope creep found
  (diff touches exactly the nine declared files plus the new cadence doc).
- **Outstanding:** no other follow-ups introduced. MS-04B, DEC-012…017, F-05,
  F-06, MS-05A remain unapproved and untouched.

## 2026-11-18 — MS-06 Stage 0 shared UI foundations (PR #6)

- **Summary:** implemented the Stage 0 enabler substage of MS-06 on the
  dedicated branch `feature/ms-06-stage-0-ui-foundation` (created from
  `main` @ `8327663`) and merged it as **PR #6**:
  https://github.com/ketandholakia/laralekh/pull/6. Presentation
  infrastructure only — no accounting or domain behaviour changes.
  - **Shared components:** `x-table` (semantic, responsive, optional
    caption), `x-dialog` (native `<dialog>` with `role="dialog"`,
    `aria-modal`, labelled title, delegated open/close/backdrop handling),
    `x-combobox` (ARIA combobox/listbox/option semantics with
    ArrowUp/Down/Enter/Escape keyboard navigation), `x-textarea`
    (label/hint/error presentation), `x-detail-list` (semantic
    `dl`/`dt`/`dd` rows).
  - **Brand pagination:** `resources/views/pagination/laralekh.blade.php`
    replaces the stock Laravel/Livewire pagination styling; registered via
    `Paginator::defaultView('pagination.laralekh')` and, after review
    finding F-01, via `paginationView()` on the four register list
    components (`InvoiceList`, `ReceiptList`, `PurchaseBillList`,
    `SupplierPaymentList`) — page state, query-string filters and
    prev/next/page-number navigation preserved.
  - **Accessibility:** `x-field` gained stable `{name}-error`/`{name}-hint`
    ids; `x-input`/`x-select` gained an additive optional `:error` prop
    wiring `aria-invalid`/`aria-describedby` (existing error-bag keys
    unchanged); `x-icon` no longer leaks the icon name onto `<svg>`.
  - **Tokens/CSS:** added the missing `brand-200` token (fixes the `x-alert`
    info border); shared `@layer components` conventions for tables,
    pagination and the dialog panel (reduced-motion respecting).
  - **Independent review:** an independent read-only review of the PR
    returned **CHANGES REQUIRED** with one MAJOR finding — F-01: Livewire's
    `WithPagination` overrides `Paginator::$defaultView` while a list
    renders, so the brand pagination view was never used at runtime — plus
    F-02 (`.x-table` row-divider CSS compiled to cell-level borders). The
    owner authorized remediation of both; F-01 was fixed with
    `paginationView()` on the four list components and proven by four new
    runtime regression tests (each list rendered through `Livewire::test`
    with 11 records, asserting LaraLekh pagination markup, page-1/page-2
    content via `?page=2`, and absence of stock Livewire pagination
    markup); F-02 was corrected to `.x-table tbody tr + tr` full-width
    top borders and verified in the compiled CSS.
- **Task IDs:** LL-P3-000 (new register row, ID assigned during the
  owner-authorized documentation reconciliation; Stage 0 is the enabler
  substage of MS-06). LL-P3-001…LL-P3-005 remain READY/outstanding — the
  four Livewire list components received only the `paginationView()` method.
- **Files/modules:** `app/Providers/AppServiceProvider.php`,
  `app/Livewire/{Sales/InvoiceList,Sales/ReceiptList,
  Purchase/PurchaseBillList,Purchase/SupplierPaymentList}.php`,
  `resources/css/app.css`, `resources/views/components/{table,dialog,
  combobox,textarea,detail-list,field,icon,input,select}.blade.php`,
  `resources/views/pagination/laralekh.blade.php` (new), `tailwind.config.js`,
  `tests/Feature/DesignSystemComponentsTest.php` (new).
- **Tests:** Stage 0 component tests 14 passed / 104 assertions; full suite
  **412 passed / 1 todo / 1311 assertions** (SQLite, up from 398/1207);
  `npm run build` PASS; `git diff --check` clean. Reduced MySQL cadence
  (per `docs/mysql-validation-cadence.md`): migration up/down/up PASS
  (`laravix_test_migrations`); 46 triggers present and the immutability
  tamper probe PASS on `laravix_test_ms06_s0` (raw UPDATE/DELETE on a posted
  voucher rejected).
- **Commits:** `1da192d4d24071dca155ed194ae4e259c0b10457` (Stage 0) and
  `22fbdc0650c24458fe2c5dbc7e2bbc96495f1028` (F-01/F-02 remediation) on
  `feature/ms-06-stage-0-ui-foundation`, merged as **PR #6** (normal merge
  commit **`0e46f119061697b558d391bfd60890539d6e5519`**, 2026-11-18T13:38:19Z;
  feature branch preserved; no squash/rebase/force-push).
- **CI:** PR run `44314872756` 3/3 SUCCESS (Backend SQLite 56s, Frontend
  build 8s, Quality and security 10s); post-merge run `44315749152` on
  `main` @ `0e46f11` 3/3 SUCCESS (55s / 10s / 18s).
- **Post-merge baseline:** `main` @ `0e46f11` — suite 412 passed / 1 todo /
  1311 assertions (SQLite).
- **Outstanding:** MS-06 screen modernization (LL-P3-001…005, in the
  approved sequence) remains outstanding; GST returns detail excluded
  (separate proposal only); mobile drawer focus trap and user-menu keyboard
  management deferred; `docs/ui/ui-inventory.md` refresh deferred to MS-06
  completion. MS-04B, MS-05A, F-05, F-06 remain unapproved and untouched.

## 2026-11-24 — Planning docs reconciliation (PR #7)

- **Summary:** Reconciled `DEVELOPMENT_PLAN.md`, `MILESTONES.md`, `WORK_REGISTER.md`,
  and `CHANGELOG.md` with the MS-06 Stage 0 merge (PR #6).
- **Commits:** `a858a91` merged via **PR #7** (`787158f`, 2026-11-24).
- **CI:** PR run `45334871136` 3/3 SUCCESS (Backend SQLite, Frontend build, Quality and security).

## 2026-11-24 — Sales invoice editor and view modernization (LL-P3-001, PR #8)

- **Summary:** Modernized the sales invoice editor (`invoice-editor`) and detail
  view (`invoice-view`) screens to use the LaraLekh design system and MS-06
  Stage 0 Blade components (`x-page-header`, `x-table`, `x-card`, `x-badge`,
  `x-alert`, `x-button`, `x-money`, `x-field`, `x-select`, `x-input`). Visual and
  accessibility modernization only; Livewire bindings and tests green.
- **Task ID:** LL-P3-001 (COMPLETED).
- **Files/modules:** `resources/views/livewire/sales/invoice-editor.blade.php`,
  `resources/views/livewire/sales/invoice-view.blade.php`.
- **Commits:** `d2e100f` and `63628c2` merged via **PR #8** (merge commit
  **`5783dcc`**, 2026-11-24).
- **CI:** PR run `45337493920` 3/3 SUCCESS (Backend SQLite 46s, Frontend build
  9s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `5783dcc` — full suite 412 passed / 1 todo /
  1311 assertions (SQLite).
- **Outstanding:** MS-06 screen modernization continues with LL-P3-002 (receipt
  editor + view), LL-P3-003 (purchase editor + view), LL-P3-004 (corrections
  and reversals), LL-P3-005 (customer outstanding + statement).

## 2026-11-24 — Customer receipt editor and view modernization (LL-P3-002, PR #10)

- **Summary:** Modernized the customer receipt editor (`receipt-editor`) and
  view (`receipt-view`) screens to use the LaraLekh design system and MS-06
  Stage 0 Blade components (`x-page-header`, `x-table`, `x-card`, `x-badge`,
  `x-alert`, `x-button`, `x-money`, `x-field`, `x-select`, `x-input`). Refactored
  customer lookup, payment details, existing and new allocations tables,
  undo/reversal sections, posting preview journal entries, and action controls.
  Visual and accessibility modernization only; Livewire bindings and tests green.
- **Task ID:** LL-P3-002 (COMPLETED).
- **Files/modules:** `resources/views/livewire/sales/receipt-editor.blade.php`,
  `resources/views/livewire/sales/receipt-view.blade.php`.
- **Commits:** `08d20fd` merged via **PR #10** (merge commit **`6e4df46`**,
  2026-11-24).
- **CI:** PR run `45339208919` 3/3 SUCCESS (Backend SQLite 45s, Frontend build
  10s, Quality and security 17s).
- **Post-merge baseline:** `main` @ `6e4df46` — full suite 412 passed / 1 todo /
  1311 assertions (SQLite).
- **Outstanding:** MS-06 screen modernization continues with LL-P3-003 (purchase
  editor + view), LL-P3-004 (corrections and reversals), LL-P3-005 (customer
  outstanding + statement).

## 2026-11-24 — Purchase bill editor and view modernization (LL-P3-003, PR #12)

- **Summary:** Modernized the purchase bill editor (`purchase-bill-editor`) and
  view (`purchase-bill-view`) screens to use the LaraLekh design system and
  MS-06 Stage 0 Blade components (`x-page-header`, `x-table`, `x-card`,
  `x-badge`, `x-alert`, `x-button`, `x-money`, `x-field`, `x-select`,
  `x-input`). Refactored supplier and bill metadata, inward supply line items,
  ITC eligibility badges/notes, reverse-charge indicators, totals calculation,
  and draft/post actions. Visual and accessibility modernization only; Livewire
  bindings and tests green.
- **Task ID:** LL-P3-003 (COMPLETED).
- **Files/modules:** `resources/views/livewire/purchase/purchase-bill-editor.blade.php`,
  `resources/views/livewire/purchase/purchase-bill-view.blade.php`.
- **Commits:** `55cfa08` merged via **PR #12** (merge commit **`33ca136`**,
  2026-11-24).
- **CI:** PR run `45361313393` 3/3 SUCCESS (Backend SQLite 52s, Frontend build
  10s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `33ca136` — full suite 412 passed / 1 todo /
  1311 assertions (SQLite).
- **Outstanding:** MS-06 screen modernization continues with LL-P3-004 (corrections
  and reversals), LL-P3-005 (customer outstanding + statement).

## 2026-11-24 — Correction and reversal screens modernization (LL-P3-004, PR #14)

- **Summary:** Modernized the sales correction screens (`correction-editor`,
  `correction-view`, and invoice embedded corrections section `invoice-corrections`)
  and accounting reversal screens (`reversal-editor`, `reversal-view`) to use the
  LaraLekh design system and MS-06 Stage 0 Blade components (`x-page-header`,
  `x-table`, `x-card`, `x-badge`, `x-alert`, `x-button`, `x-money`, `x-field`,
  `x-select`, `x-input`, `x-textarea`). Refactored party snapshot, reason, GST
  review, capacity indicators, line adjustments, journal preview, and audit trail
  presentation. Visual and accessibility modernization only; Livewire bindings,
  permissions, routing, and Pest tests green.
- **Task ID:** LL-P3-004 (COMPLETED).
- **Files/modules:** `resources/views/livewire/sales/correction-editor.blade.php`,
  `resources/views/livewire/sales/correction-view.blade.php`,
  `resources/views/livewire/sales/invoice-corrections.blade.php`,
  `resources/views/livewire/accounting/reversal-editor.blade.php`,
  `resources/views/livewire/accounting/reversal-view.blade.php`.
- **Commits:** `211371e` and `c21ad0d` merged via **PR #14** (merge commit
  **`fdabb40`**, 2026-11-24).
- **CI:** PR run `45366135769` 3/3 SUCCESS (Backend SQLite 43s, Frontend build
  17s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `fdabb40` — full suite 412 passed / 1 todo /
  1311 assertions (SQLite).
- **Outstanding:** MS-06 screen modernization continues with LL-P3-005 (customer
  outstanding + statement).

## 2026-11-24 — Customer outstanding and statement modernization (LL-P3-005, PR #16)

- **Summary:** Modernized the customer settlement report screens (`customer-outstanding`
  and `customer-statement`) to use the LaraLekh design system and MS-06 Stage 0
  Blade components (`x-page-header`, `x-table`, `x-card`, `x-badge`, `x-alert`,
  `x-button`, `x-field`, `x-input`). Refactored party header metadata, open
  invoices, unapplied advances, and excess credits summary KPI cards, settlement
  documents table, interactive as-of date filters, ledger statement table with
  opening/closing balance distinctions, and authoritative ledger balance reconciliation
  indicators. Visual and accessibility modernization only; Livewire bindings,
  permissions, routing, and Pest tests green.
- **Task ID:** LL-P3-005 (COMPLETED).
- **Files/modules:** `resources/views/livewire/sales/customer-outstanding.blade.php`,
  `resources/views/livewire/sales/customer-statement.blade.php`.
- **Commits:** `1b4415e` and `a7d8ac2` merged via **PR #16** (merge commit
  **`583a206`**, 2026-11-24).
- **CI:** PR run `45369248684` 3/3 SUCCESS (Backend SQLite 33s, Frontend build
  15s, Quality and security 14s).
- **Post-merge baseline:** `main` @ `583a206` — full suite 412 passed / 1 todo /
  1311 assertions (SQLite).
- **Outstanding:** Core screen modernization (LL-P3-001…005) is completed; remaining
  MS-06 tasks are gated LL-P3-006 (user/role management), LL-P3-007 (item master),
  LL-P3-008 (PDF customization), and register lists modernization.

## 2026-11-24 — Company-scoped user and role management screens (LL-P3-006, PR #18)

- **Summary:** Implemented company-scoped user administration and role management
  screens (`UserManager` at `/admin/users` and `RoleManager` at `/admin/roles`)
  built with the LaraLekh design system and MS-06 Stage 0 Blade components
  (`x-page-header`, `x-card`, `x-table`, `x-badge`, `x-button`, `x-field`, `x-input`,
  `x-alert`). Features direct user creation/linking with initial password, company-scoped
  role assignment, password resets, active-company deactivation via a new `is_active`
  boolean column on `company_user` (preventing self-deactivation and denying access via
  `isMemberOf`), granular company-scoped role creation and editing with permission catalog
  checkboxes, role templates (`sales_clerk`, `accountant`, `auditor`, `admin`), and deletion
  guards preventing removal of roles with active user assignments. Navigation sidebar and
  user dropdown updated with permission-gated Administration links. Tenancy isolation
  preserved: `Role` global scope fails safe (`1 = 0`) outside active tenant context while
  keeping writes strictly guarded.
- **Task ID:** LL-P3-006 (COMPLETED).
- **Files/modules:** `app/Domain/Masters/Models/Company.php`, `app/Domain/Shared/Models/Role.php`,
  `app/Livewire/Admin/UserManager.php`, `app/Livewire/Admin/RoleManager.php`,
  `app/Models/User.php`, `database/migrations/2026_11_24_160000_add_is_active_to_company_user_table.php`,
  `database/seeders/DatabaseSeeder.php`, `database/seeders/UserPermissionSeeder.php`,
  `resources/views/layouts/app.blade.php`, `resources/views/livewire/admin/user-manager.blade.php`,
  `resources/views/livewire/admin/role-manager.blade.php`, `routes/web.php`,
  `tests/Feature/UserAndRoleManagementTest.php`.
- **Commits:** `1f3faf7`, `beb3252`, `510e573`, `4bca0aa` merged via **PR #18** (merge commit
  **`60765ff`**, 2026-11-24).
- **CI:** PR run `45379702394` 3/3 SUCCESS (Backend SQLite 41s, Frontend build 10s, Quality and security 9s).
- **Post-merge baseline:** `main` @ `60765ff` — full suite 425 passed / 1 todo / 1353 assertions (SQLite).
- **Outstanding:** MS-06 tasks remaining are LL-P3-007 (item master), LL-P3-008 (PDF customization), and register lists modernization.

## 2026-11-24 — Company-scoped item master screens and catalog management (LL-P3-007, PR #20)

- **Summary:** Implemented company-scoped item master administration (`ItemManager` at `/items`)
  built with the LaraLekh design system and MS-06 Stage 0 Blade components (`x-page-header`,
  `x-card`, `x-table`, `x-badge`, `x-button`, `x-field`, `x-input`, `x-alert`). Features goods
  and service item creation/editing with company-scoped SKU uniqueness validation, HSN/SAC
  format checks, unit selection (UQC), default tax rate linkage (`tax_rate_code`), stock tracking
  toggle (auto-disabled for services), keyword search (name/SKU/HSN), kind/stock filters, KPI summary
  counters, and transaction deletion protection (`isUsed()` check across invoices, purchase bills,
  and stock movements). Seeded `items.view` and `items.manage` permissions via `ItemPermissionSeeder`,
  updated `RoleManager` permission catalog and templates, and added `Inventory` navigation sidebar item.
- **Task ID:** LL-P3-007 (COMPLETED).
- **Files/modules:** `app/Domain/Inventory/Models/Item.php`, `app/Livewire/Admin/RoleManager.php`,
  `app/Livewire/Inventory/ItemManager.php`, `database/seeders/DatabaseSeeder.php`,
  `database/seeders/ItemPermissionSeeder.php`, `resources/views/components/icon.blade.php`,
  `resources/views/layouts/app.blade.php`, `resources/views/livewire/inventory/item-manager.blade.php`,
  `routes/web.php`, `tests/Feature/ItemMasterTest.php`.
- **Commits:** `2fa4f48` merged via **PR #20** (merge commit **`de95799`**, 2026-11-24).
- **CI:** PR run `45394688229` 3/3 SUCCESS (Backend SQLite 1m3s, Frontend build 9s, Quality and security 15s).
- **Post-merge baseline:** `main` @ `de95799` — full suite 435 passed / 1 todo / 1419 assertions (SQLite).
- **Outstanding:** MS-06 tasks remaining are register lists modernization and LL-P3-008 (PDF customization).

## 2026-11-24 — Transaction register lists modernization (PR #22)

- **Summary:** Modernized the four primary transaction register views (`InvoiceList`, `ReceiptList`,
  `PurchaseBillList`, and `SupplierPaymentList`) to the LaraLekh design system (MS-06 Stage 0).
  Adopted standard root `<div class="space-y-6">`, `<x-card class="p-4">` wrapper for search/filter
  controls, semantic `<x-table caption="...">` with `<thead>` and `<tbody>` leveraging design-system
  CSS hooks, `<x-empty-state>` for empty rows, and design-system `<x-badge>` and `<x-money>` helpers,
  while retaining all Livewire reactive models, URL query parameters, authorization checks, and
  LaraLekh pagination intact.
- **Task ID:** MS-06 Register Lists Modernization (COMPLETED).
- **Files/modules:** `resources/views/livewire/purchase/purchase-bill-list.blade.php`,
  `resources/views/livewire/purchase/supplier-payment-list.blade.php`,
  `resources/views/livewire/sales/invoice-list.blade.php`,
  `resources/views/livewire/sales/receipt-list.blade.php`.
- **Commits:** `b24fc5c` merged via **PR #22** (merge commit **`f83eeed`**, 2026-11-24).
- **CI:** PR run `45411552271` 3/3 SUCCESS (Backend SQLite 47s, Frontend build 8s, Quality and security 13s).
- **Post-merge baseline:** `main` @ `f83eeed` — full suite 435 passed / 1 todo / 1419 assertions (SQLite).
- **Outstanding:** MS-06 task LL-P3-008 (PDF template customization) and ui-inventory refresh.

## 2026-11-24 — Company PDF template customization (LL-P3-008, PR #25)

- **Summary:** Implemented company-level PDF template and letterhead customization (`PdfCustomizer` at `/admin/pdf-customizer`).
  Features letterhead margin reservation for pre-printed stationery (`hide_header`), custom secondary trade names, contact info lines,
  company logo (data URI / base64), bank account remittance details on invoices (bank name, A/C number, IFSC, branch), custom footer
  terms and conditions, and configurable signature line designation (`signature_label`), with live side-by-side A4 document preview.
  Stored in `companies.pdf_settings` (nullable JSON). Integrated into `InvoicePdfController`, `ReceiptDocuments`, and DomPDF views
  (`pdf.invoice`, `pdf.receipt`) while preserving all statutory tax, GSTIN, HSN, and ledger document particulars strictly unchanged.
- **Task ID:** LL-P3-008 (COMPLETED). Completes milestone **MS-06 (UI completion and administration)**.
- **Files/modules:** `app/Domain/Masters/Models/Company.php`, `app/Domain/Sales/Services/ReceiptDocuments.php`,
  `app/Http/Controllers/Sales/InvoicePdfController.php`, `app/Livewire/Admin/PdfCustomizer.php`,
  `database/migrations/2026_11_24_200000_add_pdf_settings_to_companies_table.php`,
  `resources/views/components/icon.blade.php`, `resources/views/layouts/app.blade.php`,
  `resources/views/livewire/admin/pdf-customizer.blade.php`, `resources/views/pdf/invoice.blade.php`,
  `resources/views/pdf/receipt.blade.php`, `routes/web.php`, `tests/Feature/PdfCustomizationTest.php`.
- **Commits:** `7fb0ca4`, `c336873` merged via **PR #25** (merge commit **`af18f0e`**, 2026-11-24).
- **CI:** PR run `45419445644` 3/3 SUCCESS (Backend SQLite 1m4s, Frontend build 13s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `af18f0e` — full suite 439 passed / 1 todo / 1436 assertions (SQLite).
- **Outstanding:** MS-06 is fully completed. Next on roadmap: MS-04B (additional accounting workflows) and MS-05 (GST compliance completion).

## 2026-11-24 — Purchase debit-note workflow (LL-P1-006, PR #27)

- **Summary:** Implemented the full purchase debit-note workflow utilizing the existing `purchase_debit_notes` and `purchase_debit_note_lines` database schema (`2026_11_06_240000_purchase_guards_and_debit_note_design.php`). Features draft creation and editing (`PurchaseDebitNoteEditor` at `/purchase-bills/{bill}/debit-notes/create` and `/purchase-debit-notes/{id}/edit`), returnable item capacity enforcement (`max_returnable = quantity - sum(prior_debits)`), proportional tax calculation across CGST, SGST, UTGST, IGST, and Cess, and atomic debit note posting (`PostPurchaseDebitNote`). The posting action allocates standard voucher numbers (`PDN/1`) via `VoucherNumberAllocator`, posts balanced general ledger vouchers (`type: 'debit_note'`: Dr. Supplier Payable / Creditor, Cr. Purchase/Expense Ledger, Cr. Input GST Ledgers), records negative `StockMovement` records for tracked goods, and locks the debit note into immutable `posted` state. Integrated debit note inspection (`PurchaseDebitNoteView` at `/purchase-debit-notes/{id}`) and "Issue Debit Note" actions into `PurchaseBillView`.
- **Task ID:** LL-P1-006 (COMPLETED). Advances milestone **MS-04B (Additional accounting workflows)**.
- **Files/modules:** `app/Domain/Purchase/Actions/PostPurchaseDebitNote.php`,
  `app/Domain/Purchase/Actions/SavePurchaseDebitNoteDraft.php`,
  `app/Domain/Purchase/Models/PurchaseBill.php`,
  `app/Domain/Purchase/Models/PurchaseBillItem.php`,
  `app/Domain/Purchase/Models/PurchaseDebitNote.php`,
  `app/Domain/Purchase/Models/PurchaseDebitNoteLine.php`,
  `app/Livewire/Purchase/PurchaseBillView.php`,
  `app/Livewire/Purchase/PurchaseDebitNoteEditor.php`,
  `app/Livewire/Purchase/PurchaseDebitNoteView.php`,
  `resources/views/livewire/purchase/purchase-bill-view.blade.php`,
  `resources/views/livewire/purchase/purchase-debit-note-editor.blade.php`,
  `resources/views/livewire/purchase/purchase-debit-note-view.blade.php`,
  `routes/web.php`, `tests/Feature/PurchaseDebitNoteTest.php`.
- **Commits:** `88f2236` merged via **PR #27** (merge commit **`769b961`**, 2026-11-24).
- **CI:** PR run `45507207230` 3/3 SUCCESS (Backend SQLite 48s, Frontend build 9s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `769b961` — full suite 445 passed / 1 todo / 1463 assertions (SQLite).
- **Outstanding:** MS-04B remaining workflows are LL-P1-008 (customer credit refund) and LL-P1-009 (advance receipt voucher).

## 2026-11-25 — Customer credit refund workflow (LL-P1-008, PR #29)

- **Summary:** Implemented the full customer credit refund workflow enabling disbursement of excess customer credits (resulting from credit notes posted after invoice settlement) and unapplied receipt advances back to the customer. Features new schema `2026_11_25_100000_create_customer_refunds.php` creating `customer_refunds`, `customer_refund_allocations`, and `customer_refund_reversals` with database immutability triggers guarding posted documents. Implemented domain models `CustomerRefund`, `CustomerRefundAllocation`, and `CustomerRefundReversal` and domain actions `SaveCustomerRefundDraft`, `PostCustomerRefund` (balanced general ledger voucher `type: 'payment'`: Dr. Customer Receivable / Sundry Debtors, Cr. Cash/Bank), and `ReverseCustomerRefund` (balanced reversal voucher `type: 'receipt'`: Dr. Cash/Bank, Cr. Customer Receivable). Integrated into `SettlementService` (`availableReceiptFunds` and `documentOutstanding` deduct active refunds and restore capacity upon reversal) and `CustomerStatement` (debit refund rows and credit reversal rows with exact statement closing to ledger tie-out). Built Livewire components `CustomerRefundEditor` (at `/customers/{party}/refunds/create` and `/customer-refunds/{id}/edit`), `CustomerRefundView` (at `/customer-refunds/{id}` with reversal modal), and added "Refund credit" button to `CustomerOutstanding`. Added `isPositive()` and instance pass-through to `Money` value object.
- **Task ID:** LL-P1-008 (COMPLETED). Advances milestone **MS-04B (Additional accounting workflows)**.
- **Files/modules:** `app/Domain/Sales/Actions/PostCustomerRefund.php`,
  `app/Domain/Sales/Actions/ReverseCustomerRefund.php`,
  `app/Domain/Sales/Actions/SaveCustomerRefundDraft.php`,
  `app/Domain/Sales/Models/CustomerRefund.php`,
  `app/Domain/Sales/Models/CustomerRefundAllocation.php`,
  `app/Domain/Sales/Models/CustomerRefundReversal.php`,
  `app/Domain/Sales/Services/CustomerStatement.php`,
  `app/Domain/Sales/Services/SettlementService.php`,
  `app/Domain/Shared/ValueObjects/Money.php`,
  `app/Livewire/Sales/CustomerRefundEditor.php`,
  `app/Livewire/Sales/CustomerRefundView.php`,
  `database/migrations/2026_11_25_100000_create_customer_refunds.php`,
  `resources/views/livewire/sales/customer-outstanding.blade.php`,
  `resources/views/livewire/sales/customer-refund-editor.blade.php`,
  `resources/views/livewire/sales/customer-refund-view.blade.php`,
  `routes/web.php`, `tests/Feature/CustomerCreditRefundTest.php`, `tests/Unit/MoneyTest.php`.
- **Commits:** `3e30fec`, `b246f06`, `252c430`, `854a099`, `0174fb2` merged via **PR #29** (merge commit **`f2c634e`**, 2026-11-25).
- **CI:** PR run `45515163936` 3/3 SUCCESS (Backend SQLite 53s, Frontend build 8s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `f2c634e` — full suite 452 passed / 1 todo / 1547 assertions (SQLite).
- **Outstanding:** MS-04B remaining workflow is LL-P1-009 (advance receipt voucher).

## 2026-11-25 — Statutory advance receipt voucher workflow (LL-P1-009, PR #31)

- **Summary:** Implemented the statutory advance receipt voucher workflow pursuant to CGST Act Section 31(3)(d) and Rule 50 for customer advance collections. Features new migration `2026_11_25_200000_create_advance_receipt_vouchers.php` creating `advance_receipt_vouchers` with database immutability triggers guarding posted documents. Implemented domain model `AdvanceReceiptVoucher` and domain actions `SaveAdvanceReceiptVoucherDraft` and `PostAdvanceReceiptVoucher`. Implemented statutory Rule 50 provisos: automated 18% tax rate fallback if tax rate is not determinable (Rule 50 proviso (i)), inter-state IGST treatment if destination or nature of supply is not determinable (Rule 50 proviso (ii)), and 0% tax exemption for advances against goods per Notification No. 66/2017-CT. Features sequential series numbering (`ARV/1`) via `VoucherNumberAllocator`, snapshots of supplier and recipient particulars, Livewire components `AdvanceReceiptVoucherEditor` (at `/customers/{party}/advance-vouchers/create` and `/advance-receipt-vouchers/{id}/edit`) with real-time tax calculation preview, `AdvanceReceiptVoucherView` (at `/advance-receipt-vouchers/{id}`), and integrated "Issue Statutory Advance Voucher" action into `ReceiptView`.
- **Task ID:** LL-P1-009 (COMPLETED). Completes statutory advance voucher workflow under milestone **MS-04B (Additional accounting workflows)**.
- **Files/modules:** `app/Domain/Sales/Actions/PostAdvanceReceiptVoucher.php`,
  `app/Domain/Sales/Actions/SaveAdvanceReceiptVoucherDraft.php`,
  `app/Domain/Sales/Models/AdvanceReceiptVoucher.php`,
  `app/Domain/Sales/Models/Receipt.php`,
  `app/Livewire/Sales/AdvanceReceiptVoucherEditor.php`,
  `app/Livewire/Sales/AdvanceReceiptVoucherView.php`,
  `app/Livewire/Sales/ReceiptView.php`,
  `database/migrations/2026_11_25_200000_create_advance_receipt_vouchers.php`,
  `resources/views/livewire/sales/advance-receipt-voucher-editor.blade.php`,
  `resources/views/livewire/sales/advance-receipt-voucher-view.blade.php`,
  `resources/views/livewire/sales/receipt-view.blade.php`,
  `routes/web.php`, `tests/Feature/AdvanceReceiptVoucherTest.php`.
- **Commits:** `b30d5ee`, `e491239`, `458e7b7`, `a611661` merged via **PR #31** (merge commit **`90cc2aa`**, 2026-11-25).
- **CI:** PR run `45518745281` 3/3 SUCCESS (Backend SQLite 1m3s, Frontend build 8s, Quality and security 14s).
- **Post-merge baseline:** `main` @ `90cc2aa` — full suite 458 passed / 1 todo / 1604 assertions (SQLite).
- **Outstanding:** MS-04B core workflows completed. Next on roadmap: MS-05 (GST compliance completion) and MS-08 (Production readiness).

## 2026-11-25 — GST treatment of advance receipts in outward returns (LL-P2-004, PR #33)

- **Summary:** Integrated statutory advance receipt vouchers (`AdvanceReceiptVoucher`, series `ARV`, delivered under LL-P1-009) into the outward supplies return preparation pipeline. In `ReturnDatasetBuilder`, posted advance receipt vouchers for the period are queried, validated for active place-of-supply state codes, and tracked under `advances.received`. Taxable advance receipts for services are aggregated into GSTR-1 Table 11A (`advances.table_11a`), separated by supply nature (intra-state CGST/SGST vs. inter-state IGST) in addition to place of supply and tax rate per Table 11A(1) and 11A(2) statutory structure. Accurately respects Rule 50 provisos (18% undetermined rate fallback and inter-state IGST fallback for undetermined destination). Respects statutory tax exemption on goods advances under Notification No. 66/2017-CT, keeping them tracked in received records while excluding them from Table 11A outward tax liabilities. In Table 13 (`documents_issued`), statutory advance vouchers are integrated under document category `Receipt voucher` with serial range and counts. Integrated Table 11A summaries into `ReturnReviewCsvExporter` and the Livewire `Detail` return review screen with place of supply, rate, gross advance, taxable value, and component taxes breakdown.
- **Task ID:** LL-P2-004 (COMPLETED). Advances milestone **MS-05 (GST compliance completion)**.
- **Files/modules:** `app/Domain/Gst/Returns/ReturnDatasetBuilder.php`,
  `app/Domain/Gst/Returns/ReturnReviewCsvExporter.php`,
  `resources/views/livewire/gst/returns/detail.blade.php`,
  `tests/Feature/GstAdvanceIntegrationTest.php`.
- **Commits:** `7360b86`, `110c9bf` merged via **PR #33** (merge commit **`e4880a6`**, 2026-11-25).
- **CI:** PR run `45549304670` 3/3 SUCCESS (Backend SQLite 49s, Frontend build 11s, Quality and security 10s).
- **Post-merge baseline:** `main` @ `e4880a6` — full suite 465 passed / 1 todo / 1644 assertions (SQLite).
- **Outstanding:** MS-05 remaining tasks are LL-P2-001 (GSTR-1 JSON export; blocked on schema vendoring), LL-P2-002 (GSTR-2B manual import & reconciliation), and LL-P2-003 (Close fail-closed GST review items).
