<?php
declare(strict_types=1);
namespace App\Livewire\Sales;

use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Read-only presentation of an invoice (draft or posted). Posted documents are
// immutable at the database level; this screen offers no mutations at all.
#[Layout('layouts.app')]
final class InvoiceView extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('invoices.view'), 403);
        $this->invoice = $invoice->load(['items' => fn ($q) => $q->orderBy('position'), 'party', 'voucher']);
    }

    public function render(): View
    {
        return view('livewire.sales.invoice-view');
    }
}
