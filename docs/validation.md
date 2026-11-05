# Step 1 validation

Validated 5 November 2026 with PHP 8.3.36 and MySQL 8.4.13 on Windows.

| Check | Result |
| --- | --- |
| Pest on in-memory SQLite | 136 passed; 244 assertions |
| Same Pest suite on MySQL 8.4 | 136 passed; 244 assertions |
| GST calculator and place-of-supply cases | 73 passed |
| Concurrent MySQL posting | 10 simultaneous worker processes; 8 successful invoices numbered 1–8; 2 duplicate attempts rejected |
| MySQL migrations | Up, full rollback, and reapply passed |
| PHP syntax | All application, config, migration, test and tool files passed |
| Composer configuration | Strict validation passed |
| Tailwind build | Passed (no application UI) |
| npm audit | No vulnerabilities reported at installation |
| Composer audit | 5 advisory entries for Laravel 11, including critical severity; see dependency-audit.json |

The audit contains overlapping entries for one advisory; entries are not a count
of distinct vulnerabilities. Laravel 11 remains the user's explicitly requested
version, but is out of security support and is not deployment-ready. Dependency
resolution used a one-time override; no project-wide audit exclusions were added.

Tests exercise exact arithmetic, discounts, component and statutory rounding,
tax classifications, tax-effective dates, tenant and year foreign keys, company
scope cleanup, company roles, sequential numbering, duplicate posts, whole-
transaction rollback on audit failure, snapshots, ledger classification, and SQL
immutability guards. The concurrency check uses committed fixtures in an isolated
test company, starts workers behind a common barrier, then verifies database state.

Local MySQL verification used an isolated data directory and loopback test port;
it did not touch an existing application database. The test server is shut down
after verification. Reproducing integration tests requires a running MySQL 8
server and process-level DB settings for a dedicated laravix_test_ database.

Redis queues/cache are configured for phpredis. This host has no working Redis
service or PHP Redis extension, so live queue delivery was not exercised. Configure
those runtime services before using Redis in development or production. DomPDF,
Livewire, Sanctum and Spatie packages are installed and discovered; feature-specific
PDF/UI/mobile workflows are intentionally deferred.

MySQL is the production target; PostgreSQL support is not claimed. Multi-process
locking behavior is validated on MySQL, not inferred from the SQLite test results.
