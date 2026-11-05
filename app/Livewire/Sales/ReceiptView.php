<?php
declare(strict_types=1);
namespace App\Livewire\Sales;

use App\Domain\Sales\Actions\{AllocateReceipt, ReverseReceipt, UndoReceiptAllocations};
use App\Domain\Sales\Models\{Receipt, ReceiptAllocation};
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Read-only posted receipt with settlement actions: allocate remaining funds
// (receipts.allocate), undo allocations or reverse the receipt (reversals.post).
#[Layout('layouts.app')]
final class ReceiptView extends Component
{
    public Receipt $receipt;

    public string $reverseReason = '';

    /** @var array<int|string, string> invoice_id => amount for new allocations */
    public array $newAllocations = [];

    /** @var list<int> allocation ids selected for undo */
    public array $undoSelection = [];

    public function mount(int|string $receiptId): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('receipts.view'), 403);
        $this->receipt = \App\Domain\Sales\Models\Receipt::findOrFail((int) $receiptId)
            ->load(['allocations.invoice', 'ledger', 'voucher', 'reversals.voucher']);
    }

    public function getAvailableFundsProperty(): string
    {
        return (string) app(SettlementService::class)->availableReceiptFunds($this->receipt->fresh());
    }

    public function getEligibleInvoicesProperty(): array
    {
        $settlement = app(SettlementService::class);
        $rows = [];
        foreach (\App\Domain\Sales\Models\Invoice::query()
            ->where('party_id', $this->receipt->party_id)->where('status', 'posted')
            ->orderBy('date')->orderBy('id')->get() as $invoice) {
            if ($invoice->voucher_id === null || $settlement->invoiceReversed($invoice)) {
                continue;
            }
            $outstanding = $settlement->documentOutstanding($invoice);
            if ($outstanding->isNegative() || $outstanding->isZero()) {
                continue;
            }
            $rows[] = ['id' => $invoice->id, 'label' => $invoice->series.'/'.$invoice->number,
                'date' => $invoice->date->format('d-m-Y'), 'outstanding' => (string) $outstanding,
                'value' => (string) ($this->newAllocations[$invoice->id] ?? '')];
        }
        return $rows;
    }

    public function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
    }

    public function allocate(): void
    {
        $this->ensurePermission('receipts.allocate');
        try {
            $count = app(AllocateReceipt::class)->handle($this->receipt->id, $this->newAllocations);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('allocations', $messages[0]);
            }
            return;
        }
        $this->newAllocations = [];
        session()->flash('status', 'Allocated to '.$count.' document(s).');
        $this->redirectRoute('receipts.show', $this->receipt->id);
    }

    public function undoAllocations(): void
    {
        $this->ensurePermission('reversals.post');
        try {
            app(UndoReceiptAllocations::class)->handle($this->receipt->id, $this->undoSelection);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('allocations', $messages[0]);
            }
            return;
        }
        session()->flash('status', 'Selected allocations undone; invoice balances restored.');
        $this->redirectRoute('receipts.show', $this->receipt->id);
    }

    public function reverse(): void
    {
        $this->ensurePermission('reversals.post');
        try {
            $yearId = app(CompanySession::class)->financialYearId();
            if ($yearId === null) {
                $this->addError('reverseReason', 'Select a financial year first.');
                return;
            }
            app(ReverseReceipt::class)->handle($this->receipt->id, $yearId, now()->toDateString(), $this->reverseReason);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('reverseReason', $messages[0]);
            }
            return;
        }
        session()->flash('status', 'Receipt reversed; amounts restored to the customer balance.');
        $this->redirectRoute('receipts.show', $this->receipt->id);
    }

    public function render(): View
    {
        return view('livewire.sales.receipt-view');
    }
}
