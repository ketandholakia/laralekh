<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Purchase\Models\SupplierPayment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Read-only posted/draft supplier payment with its allocations.
#[Layout('layouts.app')]
final class SupplierPaymentView extends Component
{
    public ?int $paymentId = null;

    // The id is resolved inside mount/render so the tenant-scoped lookup runs
    // after the company.context middleware (route-model binding would evaluate
    // the company-scoped query before any tenant context exists).
    public function mount(int|string|null $payment = null): void
    {
        abort_unless(auth()->user()?->can('purchases.view'), 403);
        $this->paymentId = $payment !== null ? (int) $payment : null;
    }

    public function render(): View
    {
        $payment = SupplierPayment::findOrFail($this->paymentId)->load('party', 'allocations');
        // Mirror the receipt-view reversal banner: the payment's status stays
        // 'posted' after reversal, so the immutable reversal record carries it.
        $reversal = $payment->voucher_id !== null
            ? \App\Domain\Accounting\Models\VoucherReversal::query()
                ->where('original_voucher_id', $payment->voucher_id)->with('voucher')->first()
            : null;

        return view('livewire.purchase.supplier-payment-view', [
            'payment' => $payment, 'reversal' => $reversal,
        ]);
    }
}
