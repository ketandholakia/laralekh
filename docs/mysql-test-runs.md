# MySQL concurrency verification

Verified 2026-11-06 against the repository's local MySQL 8.4.13 instance on `127.0.0.1:33360`.

```powershell
pwsh -File .\local-mysql.ps1
$bin = "$env:LOCALAPPDATA/laravix/mysql-8.4.13-winx64/bin"
& "$bin/mysql.exe" --no-defaults --host=127.0.0.1 --port=33360 --user=root -e "CREATE DATABASE IF NOT EXISTS laravix_test_migration_scan5"
pwsh -File .\tools\run-series-concurrency.ps1 laravix_test_migration_scan5
```

Output:

```text
PASS: parallel sales and purchase postings are sequential; failed posts rolled back allocation without gaps.
```

This ran both sales and purchase parallel-post races, plus injected posting failures to verify sequence allocations roll back without gaps. The dedicated database was freshly created for this run.

## Full Pest suite: SQLite and MySQL

`tools/test-pest-both-databases.ps1` runs the full suite first on in-memory SQLite, then on a separately named dedicated MySQL database. Verified 2026-11-06 with:

```powershell
pwsh -File .\tools\test-pest-both-databases.ps1 laravix_test_pest_part0_20261106
```

Both runs reported `205 deprecated, 127 passed (889 assertions)`. The initial MySQL run exposed a snapshot-hash portability issue caused by MySQL JSON key normalization. Hashing now uses recursive canonical JSON key ordering; the rerun passed on both databases. Existing PHP/Pest deprecation warnings remain, with no failed tests.

After document-cancellation support, the suite was rerun on 2026-11-06 using `laravix_test_cancel_20261106`. Both backends reported `210 deprecated, 127 passed (924 assertions)`. One initial regression (document-issued groups with no cancelled key) was corrected before the passing run. The pre-existing `DomainException` import warning and PHPUnit/Pest deprecation notices remain.

## Reduced cadence run — 18 November 2026 (hardening/documentation pass)

Docker `mysql:8.4` (8.4.13, container `laravix-mysql84`, port 33061), per
`docs/mysql-validation-cadence.md`. The branch changed no migrations, triggers
or allocator code (UI-level draft hardening + documentation), so the reduced
cadence applied:

| Check | Result |
| --- | --- |
| Migration up / rollback / up (`laravix_test_migrations`, `php tools/verify-migrations.php`) | PASS |
| Supplier-payment suites on MySQL (`laravix_test_ms04a_20261118`) | 26 passed (115 assertions) — includes the two new LL-P1-013 draft-hardening tests |
| Trigger count | 46 (unchanged from the 2026-11-17 record) |
| Tamper probe (raw UPDATE and DELETE on a posted voucher) | Both rejected with `Immutable or invalid accounting document`; narration unchanged — PASS |

Probe extras observed: inserting a voucher with `status='posted'` directly via
SQL is also rejected by the BEFORE INSERT trigger; only the
draft → balanced-lines-update path can produce a posted voucher. Series
concurrency was not re-run (no allocator change; last full-cadence run
2026-11-17, MS-04A).
