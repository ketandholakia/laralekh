# Laravix

Indian GST accounting backend. Step 1 targets PHP 8.3, Laravel 11, MySQL 8,
Livewire 3 (including Alpine), Tailwind 3, Redis, Pest, Spatie permission and
activitylog, DomPDF via its Laravel integration, and Sanctum. No application UI yet.

## Setup

Install PHP 8.3 with BCMath, PDO MySQL, mbstring, XML, curl, zip and fileinfo.
Redis connections use the phpredis extension (no additional Composer client).

```sh
composer install
cp .env.example .env
php artisan key:generate
# Set MySQL and Redis credentials in .env.
php artisan migrate --seed
npm ci
npm run build
php vendor/pestphp/pest/bin/pest
php artisan queue:work redis
```

Tests default to isolated in-memory SQLite. Set DB_CONNECTION=mysql and the other
DB variables in the process environment to run the same suite on MySQL. The test
database name must start with laravix_test_; do not use production data.
Run php tools/test-mysql-concurrency.php with that environment for ten simultaneous
posting workers. Run php tools/verify-migrations.php against the dedicated
laravix_test_migrations database to verify migration up/down/up.

## Dependency status

Laravel 11 was explicitly requested. Its security support ended on March 12, 2026:
<https://laravel.com/docs/11.x/releases>. Composer currently reports advisories
against Laravel 11. Initial dependency resolution required a one-time
`--no-security-blocking` override; project security policy is not disabled.
Run `composer audit` and upgrade the framework before exposing this app publicly.
The lockfile preserves the dependency set, not a claim of security clearance.

Only requested direct packages are installed. Pest and framework dependencies are
transitive. Alpine ships with Livewire; Tailwind's CLI handles the placeholder CSS.
There is no separate Alpine bootstrap, starter UI, or extra Redis client package.

## Backend usage and boundaries

Domain models and business services live in app/Domain/{Accounting,Sales,Purchase,
Inventory,Gst,Masters}. Shared money, casts and tenancy live in app/Domain/Shared.
Purchase and stock workflows are reserved for later steps; their foundation tables
exist. Sales posting currently posts the financial voucher, not stock or cost of goods.

After authenticating AND authorizing company membership, enter a scoped context:

```php
app(\App\Domain\Shared\Tenancy\TenantContext::class)->run($authorizedCompanyId, function () use ($invoiceId) {
    return app(\App\Domain\Accounting\Services\PostingService::class)->postSalesInvoice($invoiceId);
});
```

The context restores itself even after exceptions and selects Spatie's company
team. Future HTTP middleware and queue jobs must explicitly authorize/select a
company; there is no request-selected tenant or public posting API in this step.
When switching companies for an already loaded user, clear cached roles/permissions
relations. Roles and activity records have company scopes. Users, authentication
tokens, permission definitions, infrastructure tables and the state catalogue are
global; companies are tenant roots. Spatie's assignment pivots use company team keys.
Query-builder/raw SQL and withoutGlobalScopes are privileged operations, never
exposed to request data. Composite foreign keys enforce company/year consistency.

Seed states first. DatabaseSeeder seeds each existing company's chart and rate
templates; rerunning it preserves customised ledger names and verified rates.
For newly provisioned companies, run ChartOfAccountsSeeder and GstSlabSeeder within
their TenantContext. No demo users, credentials or businesses are seeded.

Money input must be a decimal string or Money value object. Prices are exclusive of
tax in INR, with fixed per-line discounts. The posting service recalculates draft
amounts, snapshots supplier/party details, requires verified effective-dated rates,
validates dates and ledgers, and records an activity entry in the same transaction.
Numbers are allocated only during posting, per company/year/series, with a locked
financial-year row also protecting first use of a series. Failed posts consume no
number. Successful posts cannot be deleted or modified, even through SQL, because
MySQL triggers protect vouchers, invoices and their lines. Corrections require a
future reversal/credit/debit-note action; do not disable triggers to edit history.
Zero-value invoices require a future non-accounting document workflow.

The production database target is MySQL 8 (tested on 8.4). PostgreSQL is not enabled:
its immutability triggers need a separate implementation. SQLite stores money as
text for tests; MySQL stores money in decimal(15,2).

See [GST research and assumptions](docs/gst-rules.md),
[dependency audit](docs/dependency-audit.json), and [validation notes](docs/validation.md).

## Proposed Step 2

Build authorised company/year selection and a thin Livewire sales-invoice screen
with party/item lookup, validated place of supply, line discounts, live tax totals,
draft save, posting and read-only posted detail. Render an A4 DomPDF invoice/bill of
supply from the stored snapshots, with tax breakup, HSN/SAC, amount in Indian words,
and the reviewed QR type. A payment QR is distinct from an IRP-signed e-invoice QR;
e-invoicing stays deferred. Ask before adding any QR package. Include feature tests
for authorization, tenant isolation, duplicate submission, and PDF content.
