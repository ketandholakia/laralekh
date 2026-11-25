<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Models\AdvanceReceiptVoucher;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AdvanceReceiptVoucherView extends Component
{
    public int $voucherId;

    public function mount(int|string $id): void
    {
        $this->authorizeAccess();
        $this->voucherId = (int) $id;
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('receipts.view'), 403);
    }

    public function getVoucherProperty(): AdvanceReceiptVoucher
    {
        return AdvanceReceiptVoucher::with(['party', 'receipt', 'createdBy'])->findOrFail($this->voucherId);
    }

    public function render(): View
    {
        return view('livewire.sales.advance-receipt-voucher-view', [
            'voucher' => $this->voucher,
        ]);
    }
}
