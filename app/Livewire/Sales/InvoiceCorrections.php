<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Services\CorrectionCalculator;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class InvoiceCorrections extends Component
{
    #[Locked]
    public int $invoiceId;

    public function boot(): void
    {
        app(CorrectionAccess::class)->authorize();
    }

    public function mount(int $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function render()
    {
        $invoice = Invoice::findOrFail($this->invoiceId);

        return view('livewire.sales.invoice-corrections', ['source' => $invoice, 'notes' => $invoice->corrections()->orderByDesc('id')->get(),
            'capacity' => app(CorrectionCalculator::class)->remaining($invoice),
            'reversal' => VoucherReversal::where('original_voucher_id', $invoice->voucher_id)->first()]);
    }
}
