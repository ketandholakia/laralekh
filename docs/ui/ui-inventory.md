# LaraLekh UI Inventory & Audit (Phase 0)

Audit date: 2026-11-15 · Branch `main` @ `b54a3ddf` · baseline: Pest 348 passed / 1 todo / 971 assertions, CI green.

## 1. Stack verification (verified, not assumed)

| Component | Verified state |
|---|---|
| Laravel | `laravel/framework ^13.0`, PHP `^8.4.1` (composer.lock platform 8.4.1) |
| Livewire | `livewire/livewire ^3.8` — Alpine bundled, `resources/js/app.js` is a comment only (no second copy loaded) |
| Tailwind | `tailwindcss ^3.4.17` via Tailwind CLI only: `tailwindcss -i resources/css/app.css -o public/css/app.css --minify`. No PostCSS, no bundler |
| Tailwind config | Stock Laravel default. `fontFamily.sans` references **Figtree, which is never actually loaded** (no CDN link, no font files). Content globs include vendor pagination + blade + js |
| CSS | `resources/css/app.css` = bare `@tailwind` directives, zero custom styles |
| Icons | **No icon library** installed |
| Blade components | **`resources/views/components/` does not exist** — every screen repeats inline utility classes |
| PDFs | 5 DomPDF views under `resources/views/pdf/` (invoice, receipt, correction, reversal, statement) — out of UI scope, reviewed only for brand consistency later |
| Favicon | `public/favicon.ico` is the **stock Laravel favicon and is not referenced** in the layout head |
| Fonts | None loaded anywhere; system default rendering |
| Tests / CI | Pest suite green (348/1/971); CI: 3 jobs on PRs + pushes to `main`, branch protection active |

## 2. Route & permission map (all routes verified)

Two screens run **outside** tenant context: `login` (guest) and `company.select`/`company.store` (auth only).
Everything else is behind `company.context` (`EnsureCompanyContext`) + `can:` permission middleware.

| Route | Name | Livewire component / controller | Permission |
|---|---|---|---|
| `/login` GET/POST | `login`, `login.store` | view + `LoginController` | guest |
| `/companies` GET/POST | `company.select`, `company.store` | `CompanySelectionController` | auth, membership-checked |
| `/` | — | redirect → `invoices.index` | invoices.view (via group) |
| `/invoices` (+create/show/edit/pdf) | `invoices.*` | `Sales\InvoiceList/Editor/View` | `invoices.view` (+`invoices.create`) |
| `/invoices/{invoice}/corrections/create` | `corrections.create` | `Sales\CorrectionEditor` | `corrections.create` |
| `/corrections/{c}` (+edit/pdf) | `corrections.*` | `Sales\CorrectionEditor/View`, PDF controller | `corrections.view`/`create` |
| `/vouchers/{v}/reverse` | `reversals.create` | `Accounting\ReversalEditor` | `reversals.post` |
| `/reversals/{r}` (+pdf) | `reversals.show`, `reversals.pdf` | `Accounting\ReversalView`, PDF controller | `corrections.view` |
| `/receipts` (+create/show/edit/pdf) | `receipts.*` | `Sales\ReceiptList/Editor/View` | `receipts.view` (+`create`) |
| `/customers` (+show/statement/pdf) | `customers.*` | `Sales\CustomerList/Outstanding/Statement` | `receipts.view` (group) |
| `/purchase-bills` (+create/show/edit) | `purchases.*` | `Purchase\PurchaseBillList/Editor/View` | `purchases.view` (+`create`) |
| `/gst/returns` (+`{period}`, csv) | `gst.returns.*` | `Gst\Returns\Dashboard/Detail`, CSV controller | `returns.view` (+`returns.export`) |

**Screens that do NOT exist** (do not invent nav entries for them): dashboard, products, inventory,
ledger/accounting screens, reports, settings, user management. Permissions that exist: `invoices.*`,
`receipts.*` (+`receipts.allocate`), `purchases.*`, `corrections.*`, `reversals.post`, `returns.*`.
`receipts.allocate` has an action (not a screen). All Livewire components declare `#[Layout('layouts.app')]`.

## 3. Screen-by-screen inventory

| # | Screen | View(s) | Current state |
|---|---|---|---|
| 1 | Main layout | `layouts/app.blade.php` | Top-header-only shell: brand link, 4 nav links, logout. No sidebar, no company display, no user menu. `{{ $slot }}` + `@yield('content')` (both conventions supported). TenantContext-guarded GST link |
| 2 | Login | `auth/login.blade.php` | Functional card form; no brand moment, no password visibility toggle, no loading state. Has remember-me; **no forgot-password route exists** |
| 3 | Company selection | `company/select.blade.php` | Single dropdown + Continue. No company metadata (GSTIN/address), no user identification, no selected-state styling |
| 4 | GST returns dashboard | `livewire/gst/returns/dashboard.blade.php` | **Best current pattern**: eyebrow + 3xl title header, workflow badge, card section. This is the local standard to promote everywhere |
| 5 | GST return detail | `livewire/gst/returns/detail.blade.php` | Dense single-line markup; KPI-ish articles, CSV export buttons, snapshot list. Contains the LaraLekh-only CSV note |
| 6 | Invoice list | `livewire/sales/invoice-list.blade.php` | Title + New button, 4 filter fields in a card, table w/ inline status badge, `₹` totals, pagination, empty state |
| 7 | Invoice editor | `livewire/sales/invoice-editor.blade.php` | Alpine `x-data` party/item autocomplete dropdowns, card sections, line items. Largest form (218 lines) |
| 8 | Invoice/receipt/correction/reversal views | `sales/invoice-view`, `receipt-view`, `correction-view`, `accounting/reversal-view` | Read-only documents with action buttons |
| 9 | Receipts | `receipt-list/editor/view` | Same patterns as invoices |
| 10 | Customers | `customer-list`, `customer-outstanding`, `customer-statement` | Compact list (32 lines) + outstanding + statement with running balance |
| 11 | Purchases | `purchase/*` (3 views) | Same patterns as sales |

### Current visual language (consistent but uncomponentized)
- Palette: `slate` neutrals + `indigo-600/700` actions, `green`/`amber`/`red` statuses
- Cards: `bg-white rounded-lg border border-slate-200 p-4/5`; tables: `bg-slate-50` header, `border-t` rows
- Status badges: inline `rounded-full px-2 py-0.5 text-xs` triplets repeated per view
- Buttons: inline `rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700`
- Money: inline `₹{{ ... }}` with `font-mono`; dates `d-m-Y`; numbers unformatted (no thousands separator)
- Two competing page-header styles: plain `text-xl` h1 (most screens) vs GST's eyebrow+3xl pattern
- Forms: repeated inline input classes (`rounded border-slate-300 ... focus:outline-none`), `@error` paragraphs
- Focus states: mostly `focus:outline-none` + color change — **not keyboard-visible enough**; no skip links, no aria-current on nav, no focus-visible rings

## 4. Gaps vs. target product (drives the phases)

1. No brand identity: no logo, no favicon integration, no fonts, generic indigo/slate look
2. No design tokens in `tailwind.config.js`; no reusable Blade components → heavy duplication
3. Shell is a top bar only; no sidebar, no company context display, no user menu
4. No dashboard (route absent); `/` redirects to invoices
5. Accessibility: weak focus visibility, no aria-current, unlabeled icon-less controls, tables lack `<caption>`/scope polish
6. No Indian number formatting helper (lakh/crore grouping) for financial values
7. Stock Laravel favicon live; brand favicon absent

## 5. Proposed design direction (summary — full rationale in chat)

- **Tokens**: extend `tailwind.config.js` with a `brand` palette mapping 1:1 to the target colors
  (teal `#0F766E` primary, navy `#172554` secondary, saffron `#F59E0B` accent, slate neutrals kept).
  The palette intentionally aligns with Tailwind's default scale for incremental adoption.
- **Typography**: Inter with system fallback; self-hosted `woff2` in `public/fonts` (no external CDN),
  loaded via `@font-face` + preload; tabular numerals for financial columns.
- **Icons**: single zero-dependency inline SVG sprite (Lucide icon geometry) exposed through one
  `<x-icon name="...">` Blade component — no new packages, one consistent icon style.
- **Components**: small Blade component set (`x-button`, `x-input`, `x-card`, `x-badge`, `x-kpi`,
  `x-empty-state`, `x-page-header`…) replacing the repeated inline patterns; GST dashboard's
  eyebrow-header pattern promoted as the standard page header.
- **Shell**: collapsible sidebar (permission-aware, grouped) + header with logo, current company,
  user menu; mobile drawer. Nav entries strictly limited to existing routes.
- **Dashboard (needs approval)**: new Livewire page fed by existing models/services with
  permission-aware KPI cards and empty states; propose making it the `/` landing.
- **Logo**: hand-authored SVG "ledger/Lekh" mark (bound-record motif) + wordmark; light/dark/icon
  variants + favicon set replacing the stock Laravel icon.
