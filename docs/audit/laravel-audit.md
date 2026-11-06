# Laravix full repository audit (docs/audit/laravel-audit.md)

Audit date: 7 November 2026. Scope: the full verification checklist — test
suite reconciliation, dependency/deprecation status, authorization surfaces,
PDF/sample checks, migration integrity, concurrency tools, code style, and
runtime environment. Baseline commit: the state after `style: Pint on
LivewirePermissionTest and the SSL CA helper`.

## 1. Test suite and count reconciliation

Raw Pest summary line (SQLite, in-memory):

```
Tests:    218 deprecated,  127 passed  (954 assertions)
```

`pest --list-tests` lists exactly **345 test cases** (dataset rows counted
individually). Buckets: 218 + 127 = **345** — reconciled exactly.

Mechanics: Pest's summary line is bucketed, not a grand total. A passing test
that emits a deprecation notice is counted under "deprecated" instead of
"passed" — it did not fail. Earlier reports that read like totals ("216",
"217") were the deprecated bucket; the one-step drift 216 -> 217 was the
purchase-viewer 403 test joining the suite, and the jump from the Step 4 doc's
"290 tests" to today's 345 reflects the external purchase-bill, GST-returns
and cancellation commits (≈ +55 cases), not a counting error.

| Engine | Passed | Deprecated | Assertions | Total cases |
| --- | --- | --- | --- | --- |
| SQLite (in-memory), PHP 8.5.9 | 127 | 218 | 954 | 345 |
| MySQL 8.4.13 (Docker `mysql:8.4`) | 127 | 218 | 954 | 345 |
| MySQL 9.7.2 (host C:/tools/mysql) | 127 | 218 | 954 | 345 |

Zero failures on all three engines. Every "deprecated" flag is the single
`PDO::MYSQL_ATTR_SSL_CA is deprecated` notice from PHP 8.5; see §3 for its
source and why it remains.

## 2. Authorization audit

All state-changing public methods in `app/Livewire` (excluding
render/mount/boot) carry explicit `abort_unless(...->can(...), 403)` guards
using the same permission names their routes' middleware use:

| Component | Method | Permission |
| --- | --- | --- |
| Sales\InvoiceEditor | selectParty, addItem, addBlankLine, removeLine, save | invoices.create |
| Sales\InvoiceEditor | post | invoices.post |
| Sales\ReceiptEditor | selectParty, save | receipts.create |
| Sales\ReceiptEditor | post | receipts.post |
| Sales\ReceiptView | allocate | receipts.allocate |
| Sales\ReceiptView | undoAllocations, reverse | reversals.post |
| Sales\CorrectionEditor | save | corrections.create |
| Sales\CorrectionEditor | post | corrections.post |
| Accounting\ReversalEditor | post | reversals.post |
| Purchase\PurchaseBillEditor | addLine, save | purchases.create |
| Purchase\PurchaseBillEditor | post | purchases.post |
| Gst\Returns\Detail | generate, finalize, markFiled | returns.finalize |

`tests/Feature/LivewirePermissionTest.php` drives every method with a
view-only user and asserts an HTTP-403 abort plus no state change, and a
visibility test proves the editor post buttons are hidden from create-only
users. Blade `@can` coverage: the receipt view's allocate/undo/reverse and the
correction editor's post are wrapped; the editor save/post buttons listed
below are route-gated rather than `@can`-wrapped (post buttons now wrapped per
item 3 of the review), and every underlying method aborts 403 server-side:

- invoice-editor: party-select, addItem/removeLine/addBlankLine, save
- receipt-editor: party-select, save
- purchase-bill-editor: addLine, save
- correction-editor: save
- reversal-editor: post

## 3. Dependency and deprecation status (pre-upgrade state; see §6b)

- Installed framework: **laravel/framework v11.57.1** (composer.lock), within
  the requested `"^11.31"`. **v11.57.1 is the newest 11.x tag** (GitHub tag
  list, 1000 tags paginated). Its shipped
  `vendor/laravel/framework/config/database.php` still references
  `PDO::MYSQL_ATTR_SSL_CA`; the `Pdo\Mysql::ATTR_SSL_CA` switch exists only in
  newer majors (verified in v13.40.0's config). **No compatible patch release
  exists within the constraint**; a framework upgrade is out of scope by
  project rule. Laravel 11's security-support window ended 12 March 2026.
- Consequence: every app boot triggers two framework-config deprecation
  notices on PHP 8.5, flagging all 345 tests as "deprecated" by Pest. The
  project's own config is clean (version-safe helper, commit 49a1e4b).
- `composer audit`: 5 advisories, all against laravel/framework 11.x
  (deployment blocker until a framework upgrade). `npm audit`: 0
  vulnerabilities.

## 4. Migration integrity

`tools/verify-migrations.php` on MySQL: **up, full rollback, reapply PASS**.
Verified on both MySQL 9.7.2 (host) and MySQL 8.4.13 (Docker `mysql:8.4`).
The audit's whitespace review of the Pint commit found `git diff -w` on
database/migrations is not empty (brace moves, import reordering survive
`-w`), but a PHP-token comparison of all 14 touched files proved zero
behavioural change (no SQL, schema, or string content differs). The review
also caught and fixed a pre-existing purchase-migration rollback bug
(`purchase_bill_items` FK support index), commit 3b515ca.

## 5. Concurrency checks (real multi-process, MySQL)

All three tools pass on MySQL 8.4.13 (Docker) and 9.7.2 (host), fresh
databases, barrier-synchronised worker processes:

- `test-mysql-concurrency.php`: 10 workers — 8 posts numbered 1–8, 2
  duplicates rejected.
- `test-correction-concurrency.php`: over-credit, duplicate reversal,
  note-versus-reversal, duplicate post rejected; four valid concurrent notes
  succeed; gapless CN/RV sequences.
- `test-receipt-concurrency.php`: duplicate receipt post, concurrent
  allocation, over-allocation, same-invoice payments, allocation-versus-
  reversal (both serialisation orders), double undo rejected; gapless SR
  numbering.

## 6. Runtime environment (documented mismatch)

- PHP: the host exposes **8.5.9** (`C:/tools/php85`); the 8.3.36 tooling
  referenced by the Step 3 report is no longer present. The composer platform
  pin remains 8.3.0; no framework/config version was changed to mask this.
- MySQL: host install is **9.7.2** (`C:/tools/mysql`); the Step 3 report's
  8.4.13 directory is gone. MySQL 8.4 verification is now Docker-based
  (`docker-compose.yml`, `docs/mysql-84.md`), exercised live on 8.4.13.
- Redis: phpredis extension absent on this host; queue delivery and
  Redis-backed caches remain unexercised (use `CACHE_STORE=array` locally).
- Docker Desktop 29.2.0 present; daemon started on demand for the 8.4 runs.

## 7. Static analysis and build

- `pint --test`: **PASS, 208 files** (the audit's own new test file was
  reformatted and the suite re-run).
- PHP syntax sweep (`php -l`) over app, tests, tools, config and migrations:
  clean. One no-op non-compound `use` statement warning was removed
  (commit under review).
- `npm run build` (Tailwind): clean; Browserslist dataset-age warning
  retained from earlier steps (cosmetic).
- No additional runtime packages were added this audit; `laravel/pint` and
  `chillerlan/php-qrcode` remain the only non-requested dev/tool deps.

## 8. PDF/sample checks (from the step deliveries, unchanged state)

- Invoice/bill-of-supply: 7 documents, all pages visually inspected (10 pages
  pass) after the grand-total wrap and page-footer fixes.
- Corrections/reversals: 6 documents / 8 pages inspected; snapshot fidelity
  tests cover master-data edits after posting.
- Receipts/statements: 5 documents / 6 pages inspected; statement reconciles
  to the receivable ledger; receipt PDFs labelled as non-statutory.

## 6b. Laravel 13 upgrade (branch `upgrade-laravel-13`, 7 November 2026)

Approved major upgrade replacing the no-major-upgrades rule (commit history on
the branch: dependency bump, schema fix, docs).

Dependency changes (composer.lock verified):

| Package | Before | After | Note |
| --- | --- | --- | --- |
| laravel/framework | v11.57.1 | **v13.40.0** | guide: framework ^13.0 |
| pestphp/pest | v3.8.x | **v5.3.5** | guide: pest ^4.0 minimum; 5.x required by pest-plugin-laravel v5 |
| pestphp/pest-plugin-laravel | v3.2.x | **v5.0.1** | requires framework ^13.23 |
| phpunit/phpunit | 11.x | **13.4.5** | guide: ^12.0 minimum; 13.x required by pest 5 |
| spatie/laravel-permission | 6.25.0 | **8.5.0** | v7 modernisation (Laravel ^12, PHP ^8.4); v8 enum contracts; registrar API used here (cacheKey, forgetCachedPermissions, set/getPermissionsTeamId, findOrCreate, teams) verified present in 8.5.0 source |
| spatie/laravel-activitylog | 4.12.3 | **5.1.4** | PHP 8.4+; fluent activity() chain intact; adds attribute_changes column (schema fix commit) |
| livewire/livewire | v3.8.11 | v3.8.11 (unchanged) | 3.8.11 already supports illuminate ^13.0 — no Livewire 4 migration needed |
| laravel/sanctum | v4.3.3 | v4.3.3 | unchanged |
| barryvdh/laravel-dompdf | v3.1.2 | v3.1.2 | unchanged |
| nesbot/carbon | 3.15.1 | 3.15.1 | Carbon 3 already in use (L12 requirement satisfied) |
| chillerlan/php-qrcode, laravel/pint | unchanged | unchanged | framework-independent |

Project PHP floor: `~8.3.0` -> `^8.4.1` (composer platform pin 8.3.0 -> 8.4.1),
forced by Pest 5 / PHPUnit 13 / activitylog 5 (all require php >= 8.4.1). Host
PHP is 8.5.9. laravel/tinker and laravel/boost are not installed, so their
guide entries do not apply.

Upgrade-guide items reviewed against this codebase — all no-ops: HasUuids
v7 (not used), DatabaseTokenRepository signature, Concurrency::run mapping,
container default-value resolution, multi-schema inspection, Blueprint
constructor, mergeIfMissing, route-name precedence, local disk root, image/SVG
rule, upsert uniqueBy validation, MySQL DELETE JOIN grammar, model booting
nested instantiation (no `new static` inside boot), polymorphic pivot names,
collection serialization eager-load restore, queue/contract additions,
pagination view names, Str factories, Js::from, polyfill globals (no
laravel/helpers). One review note: the 12->13 CSRF middleware rename
(VerifyCsrfToken -> PreventRequestForgery) requires no change here because no
test or route references the class directly.

Config decisions: `cache.serializable_classes => false` NOT added yet (the
framework default applies and the app caches only arrays/scalars; adding it is
a config-sync task, not a behaviour change); `session.serialization` kept at
`php` to avoid invalidating sessions (guide's recommended safe path; revisit
when sessions matter).

Results after the upgrade (see §1/§4/§5 for full tables):

- SQLite: `Tests: 1 todo, 344 passed (954 assertions)` — the "deprecated"
  bucket is gone entirely (was 218): Laravel 13's shipped config uses
  `Pdo\Mysql::ATTR_SSL_CA`, removing the last PHP 8.5 deprecation source.
- MySQL 8.4.13 (Docker): identical `1 todo, 344 passed (954 assertions)`.
- pest --list-tests: 345 cases = 344 passed + 1 todo. Reconciled.
- Migration up/rollback/up and all three race tools PASS on 8.4.13.
- composer audit: **0 advisories** (was 5 against laravel/framework 11.x).
  npm audit: 0 vulnerabilities (unchanged).

## 9. Open blockers (blockers 1–2 resolved by the §6b upgrade)

1. Laravel 11 security advisories (5, one critical) — upgrade path blocked by
   the no-major-upgrade rule; explicit decision required.
2. Framework-vendor `PDO::MYSQL_ATTR_SSL_CA` deprecation noise on PHP 8.5
   (inseparable from blocker 1).
3. PHP 8.3 / MySQL 8.4-as-host-install absent; 8.4 now covered via Docker,
   PHP 8.3 unverified on this host (platform pin held at 8.3.0).
4. Redis/PostgreSQL unvalidated (phpredis absent; PostgreSQL triggers never
   implemented — production target is MySQL 8).
5. Advance-tax receipt-voucher workflow, refunds of excess credits, supplier
   payments on the receipt side, and GST return filing remain deferred per
   design docs.
