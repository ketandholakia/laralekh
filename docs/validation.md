# Step 2 validation

Validated 5 November 2026 on Windows. Step 1 was validated with PHP 8.3.36;
this host currently has PHP 8.5.9 (the 8.3 install is no longer present), and
composer's platform pin (8.3.0) is unchanged. On PHP 8.5, Pest flags tests with
a `PDO::MYSQL_ATTR_SSL_CA is deprecated` notice from the stock `config/database.php`
— these are warnings, not failures; no framework or config version was changed.

| Check | Result |
| --- | --- |
| Pest on in-memory SQLite (PHP 8.5.9) | 208 tests, 428 assertions; all passed (85 flagged only by the PHP 8.5 deprecation notice) |
| Same Pest suite on MySQL 9.7.2 (isolated instance) | 208 tests, 428 assertions; all passed |
| Concurrent MySQL posting (unchanged tool) | 10 workers; 8 invoices numbered 1–8; 2 duplicates rejected |
| MySQL migrations up/rollback/up | Passed (fresh laravix_test_migrations database) |
| composer audit | Same 5 pre-existing Laravel 11 advisories; chillerlan/php-qrcode added none |
| npm audit | 0 vulnerabilities |
| Tailwind build | Passed with the new UI classes |
| Sample PDF rendering | 7 documents via tools/render-sample-pdfs.php; visually reviewed as page images |
| Sample PDF visual verdicts | All 10 pages passed after fixes (see below) |

PDF layout issues found and fixed during visual review: the grand total wrapped
between the rupee sign and the amount (now nowrap), and multi-page footers
printed "Page 1 of 1" only on the first page because the canvas page script was
registered before rendering — footers are now stamped onto completed pages
after the single render pass, giving "Page N of 4" on every page with repeated
table headers.

Two integration caveats found while building the screens: Laravel's implicit
route binding resolves before route middleware, so tenant-scoped models are
resolved manually inside the entered company context; and DomPDF embeds its
font subsets such that plain-text extraction needs space/NUL normalisation
(test helpers do this).

The isolated MySQL 9.7.2 instance used a dedicated temporary data directory on
loopback and was shut down afterwards; no existing database was touched. The
production target remains MySQL 8. Redis queues were still not exercised (no
working Redis service on this host); Sanctum remains installed for a later
API step. PDF text extraction relies on a small in-repo helper, not an external
pdftotext dependency.

## Step 1 validation (superseded details retained)

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
