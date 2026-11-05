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

        Route::middleware('can:invoices.view')->group(function (): void {
            Route::get('/invoices', \App\Livewire\Sales\InvoiceList::class)->name('invoices.index');
            Route::get('/invoices/create', \App\Livewire\Sales\InvoiceEditor::class)
                ->middleware('can:invoices.create')->name('invoices.create');
            Route::get('/invoices/{invoice}/edit', \App\Livewire\Sales\InvoiceEditor::class)
                ->middleware('can:invoices.create')->name('invoices.edit');
            Route::get('/invoices/{invoice}', \App\Livewire\Sales\InvoiceView::class)->name('invoices.show');
            Route::get('/invoices/{invoice}/pdf', \App\Http\Controllers\Sales\InvoicePdfController::class)->name('invoices.pdf');
        });
    });
});
