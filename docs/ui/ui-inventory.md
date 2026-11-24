# LaraLekh UI Inventory & Audit (Post-MS-06 Completion)

Audit date: 2026-11-24 · Branch `main` · Baseline: Pest 435 passed / 1 todo / 1419 assertions, CI green.

---

## 1. Stack & Design System Verification (Verified on `main`)

| Component | Verified state |
|---|---|
| Laravel | `laravel/framework ^13.0`, PHP `^8.4.1` (composer.lock platform 8.4.1) |
| Livewire | `livewire/livewire ^3.8` — bundled Alpine, single runtime instance |
| Tailwind CSS | `tailwindcss ^3.4.17` via CLI: `tailwindcss -i resources/css/app.css -o public/css/app.css --minify` |
| Design Tokens | Extended `tailwind.config.js` with `brand` (teal `#0F766E` primary, `navy` `#172554` secondary, `saffron` `#F59E0B` accent, `slate` neutrals, `brand-200` fix) |
| Typography | Inter Latin + Latin Ext (`400`, `500`, `600`, `700`) self-hosted `woff2` under `public/fonts/` with `@font-face` definitions and preload tags |
| Icons | Single zero-dependency SVG sprite (`resources/views/components/icon.blade.php`) exposing `<x-icon name="..."/>` with 25+ Lucide geometries |
| Blade Components | 19 design-system primitives under `resources/views/components/` (`x-icon`, `x-badge`, `x-button`, `x-card`, `x-empty-state`, `x-field`, `x-input`, `x-select`, `x-textarea`, `x-table`, `x-dialog`, `x-combobox`, `x-detail-list`, `x-kpi`, `x-money`, `x-alert`, `x-page-header`, `x-skeleton`, and `pagination.laralekh`) |
| Layout & Shell | Modern responsive application layout (`resources/views/layouts/app.blade.php`) featuring collapsible icon-rail sidebar, topbar with active company and financial year selectors, user profile menu, and mobile off-canvas drawer |
| Favicon & Logo | Hand-authored SVG "ledger/Lekh" mark, wordmark variants, and standard favicon assets |
| PDFs | DomPDF views under `resources/views/pdf/` (`invoice`, `receipt`, `correction`, `reversal`, `statement`) styled with DejaVu Sans and statutory compliance |
| Tests / CI | 435 tests passing, 1 todo, 1419 assertions; 3 GitHub Actions CI workflows active (Backend SQLite, Frontend build, Quality & security) |

---

## 2. Route & Permission Map

Two routes run outside tenant context: `/login` (guest) and `/companies` (authenticated membership selection).
All operational routes require `EnsureCompanyContext` + permission middleware.

| Route | Name | Livewire component / controller | Permission |
|---|---|---|---|
| `/login` GET/POST | `login`, `login.store` | View + `LoginController` | guest |
| `/companies` GET/POST | `company.select`, `company.store` | `CompanySelectionController` | auth |
| `/` | `dashboard` | `App\Livewire\Dashboard` | auth, company.context |
| `/invoices` | `invoices.index` | `Sales\InvoiceList` | `invoices.view` |
| `/invoices/create` | `invoices.create` | `Sales\InvoiceEditor` | `invoices.create` |
| `/invoices/{invoice}` | `invoices.show` | `Sales\InvoiceView` | `invoices.view` |
| `/invoices/{invoice}/edit` | `invoices.edit` | `Sales\InvoiceEditor` | `invoices.create` |
| `/invoices/{invoice}/pdf` | `invoices.pdf` | `InvoicePdfController` | `invoices.view` |
| `/invoices/{invoice}/corrections/create` | `corrections.create` | `Sales\CorrectionEditor` | `corrections.create` |
| `/corrections/{c}` | `corrections.show` | `Sales\CorrectionView` | `corrections.view` |
| `/corrections/{c}/edit` | `corrections.edit` | `Sales\CorrectionEditor` | `corrections.create` |
| `/corrections/{c}/pdf` | `corrections.pdf` | `CorrectionPdfController` | `corrections.view` |
| `/vouchers/{v}/reverse` | `reversals.create` | `Accounting\ReversalEditor` | `reversals.post` |
| `/reversals/{r}` | `reversals.show` | `Accounting\ReversalView` | `corrections.view` |
| `/reversals/{r}/pdf` | `reversals.pdf` | `ReversalPdfController` | `corrections.view` |
| `/receipts` | `receipts.index` | `Sales\ReceiptList` | `receipts.view` |
| `/receipts/create` | `receipts.create` | `Sales\ReceiptEditor` | `receipts.create` |
| `/receipts/{receipt}` | `receipts.show` | `Sales\ReceiptView` | `receipts.view` |
| `/receipts/{receipt}/edit` | `receipts.edit` | `Sales\ReceiptEditor` | `receipts.create` |
| `/receipts/{receipt}/pdf` | `receipts.pdf` | `ReceiptPdfController` | `receipts.view` |
| `/customers` | `customers.index` | `Sales\CustomerList` | `receipts.view` |
| `/customers/{party}` | `customers.show` | `Sales\CustomerOutstanding` | `receipts.view` |
| `/customers/{party}/statement` | `customers.statement` | `Sales\CustomerStatement` | `receipts.view` |
| `/customers/{party}/statement/pdf` | `customers.statement.pdf` | `StatementPdfController` | `receipts.view` |
| `/purchase-bills` | `purchases.index` | `Purchase\PurchaseBillList` | `purchases.view` |
| `/purchase-bills/create` | `purchases.create` | `Purchase\PurchaseBillEditor` | `purchases.create` |
| `/purchase-bills/{bill}` | `purchases.show` | `Purchase\PurchaseBillView` | `purchases.view` |
| `/purchase-bills/{bill}/edit` | `purchases.edit` | `Purchase\PurchaseBillEditor` | `purchases.create` |
| `/supplier-payments` | `supplier-payments.index` | `Purchase\SupplierPaymentList` | `purchases.view` |
| `/supplier-payments/create` | `supplier-payments.create` | `Purchase\SupplierPaymentEditor` | `purchases.create` |
| `/supplier-payments/{payment}` | `supplier-payments.show` | `Purchase\SupplierPaymentView` | `purchases.view` |
| `/financial-years` | `financial-years.index` | `Admin\FinancialYearManager` | `admin` |
| `/reports/trial-balance` | `reports.trial-balance` | `Reports\TrialBalance` | `accounting.view` |
| `/reports/profit-and-loss` | `reports.profit-and-loss` | `Reports\ProfitAndLoss` | `accounting.view` |
| `/reports/balance-sheet` | `reports.balance-sheet` | `Reports\BalanceSheet` | `accounting.view` |
| `/items` | `items.index` | `Inventory\ItemManager` | `items.view` (+ `items.manage`) |
| `/users` | `users.index` | `Admin\UserManager` | `users.manage` |
| `/roles` | `roles.index` | `Admin\RoleManager` | `users.manage` |
| `/gst/returns` | `gst.returns.index` | `Gst\Returns\Dashboard` | `returns.view` |
| `/gst/returns/{period}` | `gst.returns.show` | `Gst\Returns\Detail` | `returns.view` |
| `/gst/returns/{period}/export/{type}` | `gst.returns.export` | `GstCsvExportController` | `returns.export` |

---

## 3. Screen-by-Screen Modernization Inventory (MS-06 Scope)

All primary transactional editors, view screens, admin utilities, and registers are standardized onto the LaraLekh Design System.

| # | Screen | Blade View | Design System Implementation |
|---|---|---|---|
| 1 | Main layout & shell | `layouts/app.blade.php` | Collapsible sidebar rail, tooltip labels, top bar with company/FY switcher, brand wordmark, mobile off-canvas drawer |
| 2 | Login | `auth/login.blade.php` | Centered brand card, `<x-input>`, `<x-button variant="primary">`, styled validation messages |
| 3 | Company selector | `company/select.blade.php` | Company card grid, GSTIN/state metadata display, active state highlights |
| 4 | Dashboard | `livewire/dashboard.blade.php` | KPI summary cards (`<x-kpi>`), quick actions, recent activity feeds |
| 5 | Sales Invoice list | `livewire/sales/invoice-list.blade.php` | `<x-page-header>`, `<x-card class="p-4">` filter bar, `<x-table caption="...">`, `<x-badge>`, `<x-empty-state>` |
| 6 | Sales Invoice editor | `livewire/sales/invoice-editor.blade.php` | `<x-page-header>`, `<x-card>`, `<x-combobox>` for party & item lookup, line item calculations, totals aside |
| 7 | Sales Invoice view | `livewire/sales/invoice-view.blade.php` | Status banner, document metadata, `<x-table caption="...">` for lines, tax breakout, PDF trigger |
| 8 | Customer Receipt list | `livewire/sales/receipt-list.blade.php` | `<x-page-header>`, `<x-card>` filter bar, `<x-table>`, `<x-empty-state>`, LaraLekh pagination |
| 9 | Customer Receipt editor | `livewire/sales/receipt-editor.blade.php` | `<x-page-header>`, party combobox, settlement allocation table with balance checks, `<x-card>` |
| 10 | Customer Receipt view | `livewire/sales/receipt-view.blade.php` | `<x-page-header>`, allocation breakdown table, voucher reference, action triggers |
| 11 | Purchase Bill list | `livewire/purchase/purchase-bill-list.blade.php` | `<x-page-header>`, `<x-card>` search card, `<x-table>`, `<x-empty-state>`, `<x-money>` |
| 12 | Purchase Bill editor | `livewire/purchase/purchase-bill-editor.blade.php` | `<x-page-header>`, party combobox, line items table, ITC category selectors, totals aside |
| 13 | Purchase Bill view | `livewire/purchase/purchase-bill-view.blade.php` | `<x-page-header>`, supplier details card, line item breakdown, totals summary |
| 14 | Supplier Payment list | `livewire/purchase/supplier-payment-list.blade.php` | `<x-page-header>`, filter card, `<x-table>`, `<x-empty-state>`, `<x-badge>` |
| 15 | Supplier Payment editor | `livewire/purchase/supplier-payment-editor.blade.php` | `<x-page-header>`, supplier combobox, unpaid bill allocation table, draft validation guards |
| 16 | Supplier Payment view | `livewire/purchase/supplier-payment-view.blade.php` | `<x-page-header>`, payment details, allocated bills table, voucher linkage |
| 17 | Customer list & outstanding | `livewire/sales/customer-list.blade.php`, `customer-outstanding.blade.php` | `<x-page-header>`, aging summaries, unallocated receipts card, invoice list |
| 18 | Customer statement | `livewire/sales/customer-statement.blade.php` | Running balance ledger table, period date range picker, running debits/credits |
| 19 | Sales Corrections | `livewire/sales/correction-editor.blade.php`, `correction-view.blade.php`, `invoice-corrections.blade.php` | Capacity validation, line delta editor, credit note representation |
| 20 | Voucher Reversals | `livewire/accounting/reversal-editor.blade.php`, `reversal-view.blade.php` | Reason selector, journal reversal preview, immutable audit log |
| 21 | Financial Year Manager | `livewire/admin/financial-year-manager.blade.php` | Financial year creation card, closed/active status badges, company context safeguards |
| 22 | User Manager | `livewire/admin/user-manager.blade.php` | Modal-driven user creation (`<x-dialog>`), company role assignment, active/deactivate toggle |
| 23 | Role Manager | `livewire/admin/role-manager.blade.php` | Company-scoped role definition, template presets (Sales, Purchase, Viewer, Admin), granular permission checkboxes |
| 24 | Item Master Manager | `livewire/inventory/item-manager.blade.php` | Goods/Service catalog, SKU uniqueness, HSN/SAC validation, tax rates, stock tracking toggle, usage deletion protection |
| 25 | GST Returns Dashboard & Detail | `livewire/gst/returns/dashboard.blade.php`, `detail.blade.php` | Period cards, GSTR-1 & GSTR-3B table summaries, CSV export actions |
| 26 | Financial Reports | `trial-balance.blade.php`, `profit-and-loss.blade.php`, `balance-sheet.blade.php` | Formatted ledger tables, grouping hierarchy, zero-balance toggles, date filters |

---

## 4. Phase 0 Gaps Resolution Matrix

| # | Gap Identified in Phase 0 | Resolution in MS-06 |
|---|---|---|
| 1 | No brand identity, logo, favicon, or brand typography | Resolved via custom SVG mark/wordmark, Inter latin/ext fonts, and full favicon bundle (PR #2). |
| 2 | No design tokens in `tailwind.config.js`; no reusable Blade components | Resolved: `brand` tokens configured; 19 modular Blade components implemented (PR #2, PR #6). |
| 3 | Shell was top bar only; no company context display or user menu | Resolved: Collapsible sidebar rail + header with live company & financial year context and user menu (PR #2). |
| 4 | No dashboard (route `/` redirected to invoices) | Resolved: `/` now routes to interactive KPI and activity Dashboard (PR #2). |
| 5 | Accessibility gaps (focus rings, ARIA comboboxes/dialogs, captions) | Resolved: Native `<dialog>` modal (`<x-dialog>`), ARIA-compliant combobox (`<x-combobox>`), focus-visible styling, stable error/hint descriptions. |
| 6 | No Indian number formatting helper for currency values | Resolved: `<x-money>` Blade component formats amounts in Indian lakhs/crores system with tabular numerals. |
| 7 | Stock Laravel favicon active | Resolved: LaraLekh branded vector and multi-size favicons installed and linked. |
