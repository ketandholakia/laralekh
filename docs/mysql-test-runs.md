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
