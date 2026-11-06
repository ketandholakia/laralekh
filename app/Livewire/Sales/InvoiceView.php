<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

// Read-only presentation of an invoice (draft or posted). Posted documents are
// immutable at the database level; this screen offers no mutations at all.
#[Layout('layouts.app')]
final class InvoiceView extends Component
{
    #[Locked]
    public int $invoiceId;

    // Route parameters are not implicitly bound: that would resolve the model
    // before the company.context middleware enters the tenant scope.
    public function mount(int|string|null $invoice = null, int|string|null $invoiceId = null): void
    {
        $this->invoiceId = (int) ($invoice ?? $invoiceId);
    }

    public function boot(): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('invoices.view'), 403);
    }

    public function render(): View
    {
        return view('livewire.sales.invoice-view', ['invoice' => Invoice::findOrFail($this->invoiceId)
            ->load(['items' => fn ($q) => $q->orderBy('position'), 'party', 'voucher'])]);
    }
}
