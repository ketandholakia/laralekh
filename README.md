# Laravix

Indian GST accounting backend with Livewire 3 sales invoices, correction notes
and internal reversals. Step 3 targets
PHP 8.3, Laravel 11, MySQL 8, Livewire 3 (including Alpine), Tailwind 3, Redis,
Pest, Spatie permission and activitylog, DomPDF via its Laravel integration,
chillerlan/php-qrcode for the invoice payment QR, and Sanctum.

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
MySQL triggers protect vouchers, invoices and their lines. Corrections use the
Step 3 reversal/credit/debit-note actions; do not disable triggers to edit history.
Zero-value invoices require a future non-accounting document workflow.

The production database target is MySQL 8 (tested on 8.4). PostgreSQL is not enabled:
its immutability triggers need a separate implementation. SQLite stores money as
text for tests; MySQL stores money in decimal(15,2).

See [GST research and assumptions](docs/gst-rules.md),
[dependency audit](docs/dependency-audit.json), and [validation notes](docs/validation.md).

## Step 2: authentication, invoice UI and PDF

Authentication is standard session login (throttled). Company selection is a
membership gate: `company_user` lists which companies a user may enter, and the
selected company/financial year live in the session, revalidated on every
request by the `company.context` middleware. No screen or action accepts a
company id from browser input; drafts always belong to the authorized session
company. Switching companies discards the year selection and clears the Spatie
role/permission relations cached on the user; `TenantContext` also keys
Spatie's permission cache per company (roles are company-scoped, and a shared
cache would freeze one company's view for every other company).

Screens: throttled login, membership-listed company/year selection, invoice
register (search, status and date filters, pagination), draft editor (party and
item lookup, explicit supply type and place of supply, verified effective-dated
rate dropdown, live tax breakup through `InvoicePreview`, which mirrors the
posting inputs exactly), read-only posted view, and the A4 PDF. Business rules
stay in `App\Domain\Sales\Actions\SaveInvoiceDraft`, `PostInvoice`, and the
Step 1 services; Livewire components hold no business logic. Posting allocates
the number inside the locking transaction; duplicate submissions fail
server-side and consume nothing; posted invoices have no mutation paths.

PDFs render from the stored supplier/party snapshots for posted documents.
Title and declarations follow the document: tax invoice, reverse charge, LUT
export/SEZ, or bill of supply (composition supplier or all non-taxable lines).
The footer with `Page N of M` is stamped after rendering via DomPDF's canvas
page script, because this DomPDF release does not substitute `{PAGE_NUM}`
inline. The optional UPI payment QR (company `upi_id`) is a supplier-generated
payment convenience QR and is labelled as not being an IRP e-invoice QR; QR
turnover thresholds and e-invoicing remain out of scope. Sample PDFs covering
the normal, inter-state, reverse charge, LUT export, composition bill of
supply, draft preview, and multi-page cases can be regenerated with
`php tools/render-sample-pdfs.php <output-directory>`.

## Step 3: corrections and reversals

From a posted invoice, authorised users can draft and post partial/full sales
credit notes and debit notes, preview the accounting/tax effect, download PDFs,
and inspect related notes and remaining creditable base amounts/quantities.
Commercial notes are the default and do not adjust GST. Statutory GST notes
require an explicit eligibility review, evidence, supported reason and, for
credits, a reporting-date/deadline check. Notes use original supply snapshots
and rates. Posted originals and corrections remain immutable.

Internal full reversals exactly swap original voucher entries. They require
separate permission and confirmation, cannot follow posted correction notes,
and cannot be repeated. They are not statutory GST credit notes. Neither
corrections nor reversals adjust physical stock.

Run migrations and `php artisan db:seed --class=CorrectionPermissionSeeder`.
Explicitly grant `corrections.view`, `corrections.create`, `corrections.post`
and, only for designated accountants, `reversals.post` through the existing
company-scoped Spatie roles. Seeding defines permissions without widening
existing roles. Posting from the editor also requires draft-create permission.
Read access to correction documents uses `corrections.view`.

See [correction design and limitations](docs/corrections-design.md),
[GST legal assumptions](docs/gst-rules.md), and [validation](docs/validation.md).
Regenerate six isolated PDF examples by setting `LARAVIX_PDF_SAMPLES=1` and
running `php vendor/pestphp/pest/bin/pest tests/Feature/CorrectionPdfTest.php`.
Output goes to ignored `output/pdf/`; no application data is used.

## Deferred to later steps

Purchases, inventory posting, GST return exports, e-invoicing (IRP QR and
reporting), GSTR-2B reconciliation, receipt/payment allocation, and a
separate zero-value document workflow remain future work. Sanctum is installed
for the later API/mobile step; this step's screens are session-only.
