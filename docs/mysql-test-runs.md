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
