<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanySelectionController;
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
                Route::get('/gst/returns', \App\Livewire\Gst\Returns\Dashboard::class)->name('gst.returns.index');
                Route::get('/gst/returns/{period}', \App\Livewire\Gst\Returns\Detail::class)->name('gst.returns.show');
            });
            Route::get('/gst/returns/{period}/csv/{filename}', \App\Http\Controllers\Gst\ReturnsCsvController::class)
                ->middleware('can:returns.export')->name('gst.returns.csv');
        Route::middleware('can:corrections.view')->group(function (): void {
            Route::get('/corrections/{correction}/pdf', \App\Http\Controllers\Sales\CorrectionPdfController::class)->name('corrections.pdf');
            Route::get('/reversals/{reversal}/pdf', \App\Http\Controllers\Accounting\ReversalPdfController::class)->name('reversals.pdf');
            Route::get('/invoices/{invoice}/corrections/create', \App\Livewire\Sales\CorrectionEditor::class)->middleware('can:corrections.create')->name('corrections.create');
            Route::get('/corrections/{correction}/edit', \App\Livewire\Sales\CorrectionEditor::class)->middleware('can:corrections.create')->name('corrections.edit');
            Route::get('/corrections/{correction}', \App\Livewire\Sales\CorrectionView::class)->name('corrections.show');
            Route::get('/vouchers/{voucher}/reverse', \App\Livewire\Accounting\ReversalEditor::class)->middleware('can:reversals.post')->name('reversals.create');
            Route::get('/reversals/{reversal}', \App\Livewire\Accounting\ReversalView::class)->name('reversals.show');
        });
        Route::get('/', fn () => redirect()->route('invoices.index'));

        Route::middleware('can:receipts.view')->group(function (): void {
            Route::get('/receipts', \App\Livewire\Sales\ReceiptList::class)->name('receipts.index');
            Route::get('/receipts/create', \App\Livewire\Sales\ReceiptEditor::class)
                ->middleware('can:receipts.create')->name('receipts.create');
            Route::get('/receipts/{receipt}/edit', \App\Livewire\Sales\ReceiptEditor::class)
                ->middleware('can:receipts.create')->name('receipts.edit');
            Route::get('/receipts/{receipt}', \App\Livewire\Sales\ReceiptView::class)->name('receipts.show');
            Route::get('/receipts/{receipt}/pdf', \App\Http\Controllers\Sales\ReceiptPdfController::class)->name('receipts.pdf');
            Route::get('/customers', \App\Livewire\Sales\CustomerList::class)->name('customers.index');
            Route::get('/customers/{party}', \App\Livewire\Sales\CustomerOutstanding::class)->name('customers.show');
            Route::get('/customers/{party}/statement', \App\Livewire\Sales\CustomerStatement::class)->name('customers.statement');
            Route::get('/customers/{party}/statement/pdf', \App\Http\Controllers\Sales\StatementPdfController::class)->name('customers.statement-pdf');
        });

        Route::middleware('can:invoices.view')->group(function (): void {
            Route::get('/invoices', \App\Livewire\Sales\InvoiceList::class)->name('invoices.index');
            Route::get('/invoices/create', \App\Livewire\Sales\InvoiceEditor::class)
                ->middleware('can:invoices.create')->name('invoices.create');
            Route::get('/invoices/{invoice}/edit', \App\Livewire\Sales\InvoiceEditor::class)
                ->middleware('can:invoices.create')->name('invoices.edit');
            Route::get('/invoices/{invoice}', \App\Livewire\Sales\InvoiceView::class)->name('invoices.show');
            Route::get('/invoices/{invoice}/pdf', \App\Http\Controllers\Sales\InvoicePdfController::class)->name('invoices.pdf');
        });
        Route::middleware('can:purchases.view')->group(function (): void {
            Route::get('/purchase-bills', \App\Livewire\Purchase\PurchaseBillList::class)->name('purchases.index');
            Route::get('/purchase-bills/create', \App\Livewire\Purchase\PurchaseBillEditor::class)->middleware('can:purchases.create')->name('purchases.create');
            Route::get('/purchase-bills/{bill}/edit', \App\Livewire\Purchase\PurchaseBillEditor::class)->middleware('can:purchases.create')->name('purchases.edit');
            Route::get('/purchase-bills/{bill}', \App\Livewire\Purchase\PurchaseBillView::class)->name('purchases.show');
        });
    });
});
