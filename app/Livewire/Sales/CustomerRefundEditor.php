<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostCustomerRefund;
use App\Domain\Sales\Actions\SaveCustomerRefundDraft;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class CustomerRefundEditor extends Component
{
    public ?int $refundId = null;

    public ?int $partyId = null;

    public string $partyName = '';

    public int $ledgerId = 0;

    public string $date = '';

    public string $paymentReference = '';

    public string $narration = '';

    /** @var array<string, string> key: "receipt_{id}" or "invoice_{id}" => amount */
    public array $allocations = [];

    public function mount(int|string|null $party = null, int|string|null $id = null): void
    {
        $this->authorizeAccess();

        if ($id !== null) {
            $refund = CustomerRefund::with(['party', 'allocations'])->findOrFail((int) $id);
            if ($refund->status !== 'draft') {
                $this->redirectRoute('customer-refunds.show', $refund->id);

                return;
            }
            $this->refundId = $refund->id;
            $this->partyId = $refund->party_id;
            $this->partyName = (string) $refund->party->name;
            $this->ledgerId = $refund->ledger_id;
            $this->date = $refund->date->format('Y-m-d');
            $this->paymentReference = (string) ($refund->payment_reference ?? '');
            $this->narration = (string) ($refund->narration ?? '');

            foreach ($refund->allocations as $alloc) {
                if ($alloc->allocation_type === 'receipt') {
                    $this->allocations['receipt_'.$alloc->receipt_id] = (string) $alloc->amount;
                } else {
                    $this->allocations['invoice_'.$alloc->invoice_id] = (string) $alloc->amount;
                }
            }
        } elseif ($party !== null) {
            $customer = Party::findOrFail((int) $party);
            $this->partyId = $customer->id;
            $this->partyName = $customer->name;
            $this->date = now()->toDateString();
        } else {
            $this->date = now()->toDateString();
        }

        if ($this->ledgerId === 0) {
            $first = Ledger::whereHas('group', fn ($q) => $q->where('code', 'CASH_BANK'))->orderBy('code')->first();
            if ($first) {
                $this->ledgerId = $first->id;
            }
        }
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('receipts.create'), 403);
    }

    private function guard(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function getCashLedgersProperty(): array
    {
        return Ledger::whereHas('group', fn ($q) => $q->where('code', 'CASH_BANK'))
            ->orderBy('code')->get(['id', 'code', 'name'])->all();
    }

    public function getRefundableItemsProperty(): array
    {
        if ($this->partyId === null) {
            return [];
        }

        $settlement = app(SettlementService::class);
        $items = [];

        // 1. Receipts with available advance funds
        $receipts = Receipt::query()
            ->where('party_id', $this->partyId)
            ->where('status', 'posted')
            ->orderBy('date')->orderBy('id')
            ->get();

        foreach ($receipts as $receipt) {
            if (ReceiptReversal::query()->where('original_receipt_id', $receipt->id)->exists()) {
                continue;
            }
            $available = $settlement->availableReceiptFunds($receipt);
            // If editing, add back what this draft had allocated
            $key = 'receipt_'.$receipt->id;
            $currentAlloc = isset($this->allocations[$key]) && is_numeric($this->allocations[$key])
                ? Money::of($this->allocations[$key]) : Money::of('0');

            if ($this->refundId !== null) {
                $priorAlloc = $receipt->allocations()->where('customer_refund_id', $this->refundId)->first();
            }

            if ($available->isPositive() || isset($this->allocations[$key])) {
                $items[] = [
                    'key' => $key,
                    'type' => 'receipt',
                    'target_id' => $receipt->id,
                    'label' => 'Receipt '.$receipt->label(),
                    'tag' => 'Unapplied Advance',
                    'date' => $receipt->date->format('d-m-Y'),
                    'total' => (string) $receipt->amount,
                    'available' => (string) $available,
                ];
            }
        }

        // 2. Invoices with excess credit (negative outstanding)
        $invoices = Invoice::query()
            ->where('party_id', $this->partyId)
            ->where('status', 'posted')
            ->orderBy('date')->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            if ($invoice->voucher_id === null || $settlement->invoiceReversed($invoice)) {
                continue;
            }
            $outstanding = $settlement->documentOutstanding($invoice);
            $key = 'invoice_'.$invoice->id;

            if ($outstanding->isNegative() || isset($this->allocations[$key])) {
                $excessCredit = $outstanding->isNegative()
                    ? Money::of(bcmul((string) $outstanding, '-1', 2))
                    : Money::of('0');

                $items[] = [
                    'key' => $key,
                    'type' => 'invoice',
                    'target_id' => $invoice->id,
                    'label' => 'Invoice '.$invoice->series.'/'.$invoice->number,
                    'tag' => 'Excess Credit',
                    'date' => $invoice->date->format('d-m-Y'),
                    'total' => (string) $invoice->total,
                    'available' => (string) $excessCredit,
                ];
            }
        }

        return $items;
    }

    public function getTotalRefundProperty(): string
    {
        $sum = Money::of('0');
        foreach ($this->allocations as $amount) {
            if (trim((string) $amount) === '') {
                continue;
            }
            try {
                $val = Money::of((string) $amount);
                if ($val->isPositive()) {
                    $sum = $sum->add($val);
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return (string) $sum;
    }

    public function saveDraft(): void
    {
        $this->guard('receipts.create');
        $refund = $this->persistDraft();
        session()->flash('status', 'Customer refund draft saved.');
        $this->redirectRoute('customer-refunds.show', $refund->id);
    }

    public function postRefund(): void
    {
        $this->guard('receipts.post');
        $draft = $this->persistDraft();

        try {
            $posted = app(PostCustomerRefund::class)->handle($draft->id, auth()->id());
            session()->flash('status', 'Customer refund '.$posted->label().' posted successfully.');
            $this->redirectRoute('customer-refunds.show', $posted->id);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['allocations' => $e->getMessage()]);
        }
    }

    private function persistDraft(): CustomerRefund
    {
        $this->validate([
            'partyId' => 'required|integer',
            'ledgerId' => 'required|integer|min:1',
            'date' => 'required|date_format:Y-m-d',
            'paymentReference' => 'nullable|string|max:60',
            'narration' => 'nullable|string|max:1000',
        ]);

        $canonicalAllocations = [];
        foreach ($this->allocations as $key => $amount) {
            if (trim((string) $amount) === '' || (float) $amount <= 0) {
                continue;
            }
            if (str_starts_with($key, 'receipt_')) {
                $targetId = (int) substr($key, 8);
                $canonicalAllocations[] = [
                    'allocation_type' => 'receipt',
                    'target_id' => $targetId,
                    'amount' => (string) $amount,
                ];
            } elseif (str_starts_with($key, 'invoice_')) {
                $targetId = (int) substr($key, 8);
                $canonicalAllocations[] = [
                    'allocation_type' => 'invoice',
                    'target_id' => $targetId,
                    'amount' => (string) $amount,
                ];
            }
        }

        if (empty($canonicalAllocations)) {
            throw ValidationException::withMessages(['allocations' => 'Enter a refund amount for at least one refundable advance or excess credit.']);
        }

        try {
            return app(SaveCustomerRefundDraft::class)->handle(
                partyId: $this->partyId,
                ledgerId: $this->ledgerId,
                date: $this->date,
                allocations: $canonicalAllocations,
                paymentReference: $this->paymentReference !== '' ? $this->paymentReference : null,
                narration: $this->narration !== '' ? $this->narration : null,
                refundId: $this->refundId,
                userId: auth()->id(),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['allocations' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        return view('livewire.sales.customer-refund-editor', [
            'cashLedgers' => $this->cashLedgers,
            'refundableItems' => $this->refundableItems,
            'totalRefund' => $this->totalRefund,
        ]);
    }
}
