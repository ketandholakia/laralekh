<?php

use App\Http\Controllers\Accounting\ReversalPdfController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanySelectionController;
use App\Http\Controllers\Gst\ReturnsCsvController;
use App\Http\Controllers\Sales\CorrectionPdfController;
use App\Http\Controllers\Sales\InvoicePdfController;
use App\Http\Controllers\Sales\ReceiptPdfController;
use App\Http\Controllers\Sales\StatementPdfController;
use App\Livewire\Accounting\ReversalEditor;
use App\Livewire\Accounting\ReversalView;
use App\Livewire\Gst\Returns\Dashboard;
use App\Livewire\Gst\Returns\Detail;
use App\Livewire\Purchase\PurchaseBillEditor;
use App\Livewire\Purchase\PurchaseBillList;
use App\Livewire\Purchase\PurchaseBillView;
use App\Livewire\Sales\CorrectionEditor;
use App\Livewire\Sales\CorrectionView;
use App\Livewire\Sales\CustomerList;
use App\Livewire\Sales\CustomerOutstanding;
use App\Livewire\Sales\CustomerStatement;
use App\Livewire\Sales\InvoiceEditor;
use App\Livewire\Sales\InvoiceList;
use App\Livewire\Sales\InvoiceView;
use App\Livewire\Sales\ReceiptEditor;
use App\Livewire\Sales\ReceiptList;
use App\Livewire\Sales\ReceiptView;
use Illuminate\Support\Facades\Route;

// Authentication and company selection are the only screens reachable without
// an entered tenant context. Every other screen runs behind the company.context
// middleware, which revalidates membership on every request and never accepts a
// company id from browser input.
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/companies', [CompanySelectionController::class, 'index'])->name('company.select');
    Route::post('/companies', [CompanySelectionController::class, 'store'])->name('company.store');

    Route::middleware('company.context')->group(function (): void {
        Route::middleware('can:returns.view')->group(function (): void {
            Route::get('/gst/returns', Dashboard::class)->name('gst.returns.index');
            Route::get('/gst/returns/{period}', Detail::class)->name('gst.returns.show');
        });
        Route::get('/gst/returns/{period}/csv/{filename}', ReturnsCsvController::class)
            ->middleware('can:returns.export')->name('gst.returns.csv');
        Route::middleware('can:corrections.view')->group(function (): void {
            Route::get('/corrections/{correction}/pdf', CorrectionPdfController::class)->name('corrections.pdf');
            Route::get('/reversals/{reversal}/pdf', ReversalPdfController::class)->name('reversals.pdf');
            Route::get('/invoices/{invoice}/corrections/create', CorrectionEditor::class)->middleware('can:corrections.create')->name('corrections.create');
            Route::get('/corrections/{correction}/edit', CorrectionEditor::class)->middleware('can:corrections.create')->name('corrections.edit');
            Route::get('/corrections/{correction}', CorrectionView::class)->name('corrections.show');
            Route::get('/vouchers/{voucher}/reverse', ReversalEditor::class)->middleware('can:reversals.post')->name('reversals.create');
            Route::get('/reversals/{reversal}', ReversalView::class)->name('reversals.show');
        });
        Route::get('/', \App\Livewire\Dashboard\Home::class)->name('dashboard');

        Route::middleware('can:receipts.view')->group(function (): void {
            Route::get('/receipts', ReceiptList::class)->name('receipts.index');
            Route::get('/receipts/create', ReceiptEditor::class)
                ->middleware('can:receipts.create')->name('receipts.create');
            Route::get('/receipts/{receipt}/edit', ReceiptEditor::class)
                ->middleware('can:receipts.create')->name('receipts.edit');
            Route::get('/receipts/{receipt}', ReceiptView::class)->name('receipts.show');
            Route::get('/receipts/{receipt}/pdf', ReceiptPdfController::class)->name('receipts.pdf');
            Route::get('/customers', CustomerList::class)->name('customers.index');
            Route::get('/customers/{party}', CustomerOutstanding::class)->name('customers.show');
            Route::get('/customers/{party}/statement', CustomerStatement::class)->name('customers.statement');
            Route::get('/customers/{party}/statement/pdf', StatementPdfController::class)->name('customers.statement-pdf');
        });

        Route::middleware('can:invoices.view')->group(function (): void {
            Route::get('/invoices', InvoiceList::class)->name('invoices.index');
            Route::get('/invoices/create', InvoiceEditor::class)
                ->middleware('can:invoices.create')->name('invoices.create');
            Route::get('/invoices/{invoice}/edit', InvoiceEditor::class)
                ->middleware('can:invoices.create')->name('invoices.edit');
            Route::get('/invoices/{invoice}', InvoiceView::class)->name('invoices.show');
            Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
        });
        Route::middleware('can:purchases.view')->group(function (): void {
            Route::get('/purchase-bills', PurchaseBillList::class)->name('purchases.index');
            Route::get('/purchase-bills/create', PurchaseBillEditor::class)->middleware('can:purchases.create')->name('purchases.create');
            Route::get('/purchase-bills/{bill}/edit', PurchaseBillEditor::class)->middleware('can:purchases.create')->name('purchases.edit');
            Route::get('/purchase-bills/{bill}', PurchaseBillView::class)->name('purchases.show');

            // Supplier payments settle posted purchase bills. Posting is a
            // purchases.post action enforced again per Livewire method.
            Route::get('/supplier-payments', \App\Livewire\Purchase\SupplierPaymentList::class)->name('supplier-payments.index');

            Route::get('/supplier-payments/create', \App\Livewire\Purchase\SupplierPaymentEditor::class)
                ->middleware('can:purchases.create')->name('supplier-payments.create');
            Route::get('/supplier-payments/{payment}', \App\Livewire\Purchase\SupplierPaymentView::class)->name('supplier-payments.show');
        });

        // Item master management.
        Route::get('/items', \App\Livewire\Inventory\ItemManager::class)->name('items.index');

        // Company-scoped financial-year administration (create/close) — its own
        // years.manage permission, independent of purchases.view.
        Route::get('/financial-years', \App\Livewire\Masters\FinancialYearManager::class)->name('financial-years.index');

        // Company-scoped user and role administration.
        Route::get('/admin/users', \App\Livewire\Admin\UserManager::class)->name('admin.users.index');
        Route::get('/admin/roles', \App\Livewire\Admin\RoleManager::class)->name('admin.roles.index');

        // Financial reports from posted voucher movements (read-only).
        Route::get('/reports/trial-balance', \App\Livewire\Accounting\TrialBalance::class)->name('reports.trial-balance');
        Route::get('/reports/profit-and-loss', \App\Livewire\Accounting\ProfitAndLoss::class)->name('reports.profit-and-loss');
        Route::get('/reports/balance-sheet', \App\Livewire\Accounting\BalanceSheet::class)->name('reports.balance-sheet');
    });
});
