<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Actions\PostCustomerRefund;
use App\Domain\Sales\Actions\ReverseCustomerRefund;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Shared\Tenancy\CompanySession;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class CustomerRefundView extends Component
{
    public CustomerRefund $refund;

    public bool $showReverseModal = false;

    public string $reversalReason = '';

    public string $reversalDate = '';

    public function mount(int|string $id): void
    {
        $this->authorizeAccess();
        $this->refund = CustomerRefund::with([
            'party', 'ledger', 'voucher.lines.ledger', 'allocations.receipt', 'allocations.invoice', 'reversals.voucher',
        ])->findOrFail((int) $id);
        $this->reversalDate = now()->toDateString();
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('receipts.view'), 403);
    }

    public function postRefund(): void
    {
        abort_unless(auth()->user()?->can('receipts.post'), 403);
        if ($this->refund->status !== 'draft') {
            return;
        }

        try {
            app(PostCustomerRefund::class)->handle($this->refund->id, auth()->id());
            session()->flash('status', 'Customer refund posted.');
            $this->refund = $this->refund->fresh([
                'party', 'ledger', 'voucher.lines.ledger', 'allocations.receipt', 'allocations.invoice', 'reversals',
            ]);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage()]);
        }
    }

    public function reverseRefund(): void
    {
        abort_unless(auth()->user()?->can('receipts.reverse'), 403);
        $this->validate([
            'reversalReason' => 'required|string|min:5|max:2000',
            'reversalDate' => 'required|date_format:Y-m-d',
        ]);

        $year = FinancialYear::query()
            ->where('starts_on', '<=', $this->reversalDate)
            ->where('ends_on', '>=', $this->reversalDate)
            ->first();

        if (! $year || $year->is_closed) {
            throw ValidationException::withMessages(['reversalDate' => 'Reversal date must fall inside an open financial year.']);
        }

        try {
            app(ReverseCustomerRefund::class)->handle(
                refundId: $this->refund->id,
                yearId: $year->id,
                date: $this->reversalDate,
                reason: $this->reversalReason,
                userId: auth()->id(),
            );
            session()->flash('status', 'Customer refund reversed.');
            $this->showReverseModal = false;
            $this->refund = $this->refund->fresh([
                'party', 'ledger', 'voucher.lines.ledger', 'allocations.receipt', 'allocations.invoice', 'reversals.voucher',
            ]);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['reversalReason' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        return view('livewire.sales.customer-refund-view', [
            'refund' => $this->refund,
            'isReversed' => $this->refund->isReversed(),
        ]);
    }
}
