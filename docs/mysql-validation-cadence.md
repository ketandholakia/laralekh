# MySQL 8.4 validation cadence (LL-P1-012)

The SQLite CI baseline is the fast gate, not proof of MySQL behaviour: the
posted-document immutability triggers are MySQL-only, and locking/concurrency
semantics differ between the two engines. This document defines the routine
that promotes the previously ad-hoc MySQL runs (see `docs/mysql-84.md` for the
Docker walkthrough and `docs/mysql-test-runs.md` for recorded runs) into a
documented cadence. The authoritative rule is `docs/project/DEVELOPMENT_PLAN.md`
§13 and the MySQL-validation rule at the top of the P1 phase in
`docs/project/WORK_REGISTER.md`.

## When the cadence runs

**Full cadence (all five checks below)** before any change is declared done
that touches:

- migrations or schema (any new/changed table, column, index, foreign key),
- the immutability triggers or any SQL-level guard,
- a posting or reversal path (vouchers, invoices, bills, receipts,
  corrections, payments, reversals),
- number-series allocation, locking, or financial-year handling.

**Reduced cadence (checks 1–2 only)** for application-level changes that do
not touch any of the above (screens, reports, validation messages): still run
migration up/down/up and the trigger tamper probe once per branch before
merge, so schema drift and trigger regressions are caught early.

**Skip** only for documentation-only changes.

## The five checks

Run against a dedicated, freshly created `laravix_test_*` database on MySQL
8.4 (Docker `mysql:8.4` via `docker compose up -d mysql84`, or a local 8.4
install — see `docs/mysql-84.md` for exact commands). **Never** point any of
these at development or production data; the `laravix_test_` name guard in
`tests/TestCase.php` and the concurrency tools enforces this.

1. **Migration up / down / up** — against a dedicated
   `laravix_test_migrations` database:
   `DB_CONNECTION=mysql ... php tools/verify-migrations.php`. Catches
   rollback-order problems (index/FK churn) that SQLite never exercises.
2. **Immutability-trigger verification** — confirm the trigger count matches
   the last recorded run (46 as of 2026-11-17) and probe at least one posted
   document:
   `UPDATE vouchers SET narration = 'tamper' WHERE id = <posted id>;` must be
   rejected by a trigger. Include the probe output in the run record.
3. **Relevant posting/reversal tests on MySQL** — run the suites that cover
   the changed paths against the MySQL test database (e.g. for payment-path
   work: `tests/Feature/SupplierPaymentTest.php` +
   `tests/Feature/SupplierPaymentUiTest.php`; for invoices: `PostingServiceTest`,
   `PurchaseBillTest`, receipt/correction suites). A full-suite MySQL run is
   required when the change is cross-cutting (migrations, triggers, allocator).
4. **Series-concurrency validation** — when allocator or locking behaviour is
   touched (and once per milestone regardless):
   `php tools/test-mysql-concurrency.php`,
   `php tools/test-correction-concurrency.php`,
   `php tools/test-receipt-concurrency.php`,
   `pwsh -File .\tools\run-series-concurrency.ps1 <laravix_test_db>`.
5. **Record the run** — append date, host/MySQL version, database name,
   commands and results to `docs/mysql-test-runs.md` (and the task's
   verification column in `docs/project/WORK_REGISTER.md` where applicable).

## Host notes (Windows)

- Docker Desktop's daemon must be running for the compose path; on hosts
  without the phpredis extension prefix the verify and race commands with
  `CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync`.
- If the shell exports a full application env (DB_CONNECTION=mysql,
  CACHE_STORE=redis, …), override explicitly per run: test DB commands need
  `DB_DATABASE=laravix_test_<purpose>`, and suite runs additionally need the
  array/sync overrides above. Never rely on ambient env pointing at `laravix`.
- Port 33061 is the compose host mapping; change only the host side if taken.

## Cadence status

- Full cadence last executed: **2026-11-17** (MS-04A) — migration up/down/up
  PASS, 46 triggers present, tamper probe rejected, series-concurrency harness
  PASS on MySQL 8.4.13 (`laravix_test_ms04a`, `laravix_test_migrations`,
  `laravix_test_ms04a_conc`).
- Reduced cadence last executed: **2026-11-18** (hardening/documentation pass)
  — migration up/down/up PASS, 46 triggers present, tamper probe rejected,
  supplier-payment suites 26 passed on MySQL 8.4.13
  (`laravix_test_migrations`, `laravix_test_ms04a_20261118`).
- This document is the checklist; update the "last executed" lines with every
  run.
