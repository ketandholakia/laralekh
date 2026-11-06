# Validation history

Latest: [Step 4 validation](#step-4-validation-6-november-2026) used PHP 8.5.9
and MySQL 9.7.2 on this host; the Step 3 PHP 8.3/MySQL 8.4 tooling is not
reproducible here any more (see the Step 4 section for the verified mismatch).

## Step 2 validation (historical)

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

## Step 4 validation (6 November 2026)

Baseline was independently verified before changes: 265 tests / 629 assertions
on SQLite and MySQL. The Step 3 report's tooling paths were NOT reproducible on
this host: `C:/Users/Admin/AppData/Local/laravix/php83/php.exe` (PHP 8.3.36)
and the recovered MySQL 8.4.13 directory are absent; the host has PHP 8.5.9
(`C:/tools/php85`) and MySQL 9.7.2 (`C:/tools/mysql`). All Step 4 verification
therefore used PHP 8.5.9 and an isolated MySQL 9.7.2 instance (temp data
directory, loopback port 33079, shut down afterwards; no application database
touched). The composer platform pin remains 8.3.0; no framework version was
changed. On PHP 8.5, Pest flags deprecation notices from the stock
`config/database.php`; flagged tests still pass.

| Check | Result |
| --- | --- |
| Pest on in-memory SQLite | 290 tests, 747 assertions; all passed (167 PHP 8.5 deprecation flags) |
| Same suite on MySQL 9.7.2 | 290 tests, 747 assertions; all passed |
| Receipt races, real MySQL workers (5 fresh-DB runs) | duplicate post, concurrent allocation, over-allocation, same-invoice payments, allocation-versus-reversal (both orderings), double undo rejected; gapless SR numbering |
| Existing races after receipts | Invoice posting and correction/reversal race tools still PASS |
| Migration up/rollback/up (MySQL) | Passed |
| Identifier audit (MySQL) | Fixed: `receipt_allocation_reversals` tenant FK auto-name exceeded MySQL's 64-char identifier limit; explicit short name now used and verified present |
| composer audit | Same 5 pre-existing Laravel 11 advisories; no new packages |
| npm audit | 0 vulnerabilities |
| Receipt/statement PDF samples | 5 documents / 6 pages visually inspected; all pages pass |

New tests cover partial/multiple payments, multi-invoice allocations,
advances, later allocation, cross-year settlement, corrections interacting
with receipts, over-allocation and foreign-customer refusals, allocation undo,
exact receipt reversal, generic-reversal guards, duplicate posts, rollback
consuming no number, statement reconciliation/as-of rows, and PDF content.

Race testing exposed two real defects fixed during this step: allocation did
not lock the receipt row (so it did not serialise against reversal), and the
worker/tool initially ran the allocation decision outside a transaction; the
shipped `AllocateReceipt` action always transacted. The allocation-versus-
reversal race is correct in either serialisation order and is asserted as such.

Advance-payment GST treatment was re-verified against current CBIC sources
(sections 13(2)(a) and 31(3)(d), Rule 31(3)(c)); this MVP does not issue
statutory receipt vouchers or compute advance tax — see
[settlement design](settlements-design.md). Receipts allocated against invoices
have no GST consequence. Redis and PostgreSQL remain unvalidated. Windows
helper scripts used locally remain ignored.

## Step 3 validation (6 November 2026)

Baseline was independently verified before changes: 208 tests / 428 assertions.
The PHP 8.3 runtime was found at
`C:/Users/Admin/AppData/Local/laravix/php83/php.exe` (8.3.36), and the isolated
MySQL 8.4.13 tooling/data directory was recovered under the same `laravix`
directory. These results supersede Step 2's runtime mismatch: Step 3 used
PHP 8.3 and MySQL 8, not the host's default versions or MySQL 9.7.

| Check | Result |
| --- | --- |
| Full Pest suite, SQLite | 265 passed / 629 assertions |
| Full Pest suite, MySQL 8.4.13 | 265 passed / 629 assertions |
| Correction concurrency, real MySQL workers | Over-credit, duplicate reversal, note-versus-reversal, duplicate post rejected; four valid simultaneous notes succeed; CN/RV sequences gapless |
| Existing invoice concurrency | Ten workers: eight posts numbered 1-8 and two duplicate rejections |
| Isolated migration database | Up, full rollback, up passed |
| Tailwind build | Passed; existing Browserslist dataset age warning remains |
| PHP syntax and diff whitespace | Passed for application, migrations, tests and tools |
| PDF examples | Six PDFs / eight pages rendered by DomPDF and all pages visually inspected using Poppler |

New tests cover credit/debit directions, full/partial/multiple notes, paise
allocation, original rates after master changes, IGST, UTGST, export/SEZ LUT
and payment modes, RCM, exempt/nil supplies, composition commercial notes,
reporting cutoff, closed years, cross-year corrections, foreign references,
membership/permissions, immutable SQL guards, exact swapped reversals, and
complete transaction rollback including sequence and audit failures.

MySQL race tests exposed repeatable-read snapshots established before lock
acquisition. Capacity, reversal exclusion and series creation now use current
locking reads; source-invoice locks serialize note/reversal decisions. Migration
rollback exposed InnoDB replacing implicit FK indexes with new unique indexes;
rollback now restores supporting indexes before dropping the unique indexes.
No existing immutability trigger was disabled or weakened.

Reproduce with process-level DB_CONNECTION=mysql, DB_HOST=127.0.0.1,
DB_PORT=33360, DB_DATABASE=laravix_test_suite, DB_USERNAME=root and the local
test password, then run Pest and `php tools/test-correction-concurrency.php`.
The concurrency tool insists on a `laravix_test_` database and uses a new company
per run. `php tools/test-mysql-concurrency.php` checks original invoice posting.
For `php tools/verify-migrations.php`, use the separate
`laravix_test_migrations` database; never point this rollback check at application
data. Windows helper scripts used locally remain ignored.

PDF samples in ignored `output/pdf/`: full credit, partial credit, debit,
commercial draft, 48-line/three-page credit and internal reversal. Repeated
headers, all page numbers, signatory space, exact amounts, words and warnings
were checked; no clipping or overlap was observed. Poppler emitted a system
Symbol-font warning, but these documents use embedded DejaVu and rendered
correctly. PDF tests check original snapshot preservation after master edits,
tenant access, repeated headers, document text and previews not allocating numbers.
Documents contain signatory space, not a digital signature.

No packages were added. Laravel 11 was neither upgraded nor its existing
security advisories suppressed; the retained dependency audit remains a
deployment blocker. Live Redis delivery and PostgreSQL are still unvalidated.
GST eligibility/ITC reversal, actual return filing, and applicability of future
notifications require external review; see gst-rules.md. Physical stock,
tax-only corrections, credits against debit-note increments and statutory
correction of an internal reversal remain unsupported.

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
