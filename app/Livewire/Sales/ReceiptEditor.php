<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostReceipt;
use App\Domain\Sales\Actions\SaveReceiptDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Receipt draft editor: cash/bank ledger, date, payment reference, amount,
// narration, and optional allocations of the funds to customer invoices at
// posting time. Business rules live in SaveReceiptDraft and ReceiptService.
#[Layout('layouts.app')]
final class ReceiptEditor extends Component
{
    public ?int $receiptId = null;

    public string $partyQuery = '';

    public ?int $partyId = null;

    public string $partyName = '';

    public int $ledgerId = 0;

    public string $date = '';

    public string $paymentReference = '';

    public string $narration = '';

    public string $amount = '';

    /** @var array<int|string, string> invoice_id => amount */
    public array $allocations = [];

    public function mount(int|string|null $receipt = null): void
    {
        $this->authorizeAccess();
        if ($receipt !== null) {
            $draft = Receipt::findOrFail((int) $receipt);
            if ($draft->status !== 'draft') {
                $this->redirectRoute('receipts.show', $draft->id);

                return;
            }
            $this->receiptId = $draft->id;
            $this->partyId = $draft->party_id;
            $this->partyName = (string) $draft->party->name;
            $this->partyQuery = $this->partyName;
            $this->ledgerId = $draft->ledger_id;
            $this->date = $draft->date->format('Y-m-d');
            $this->paymentReference = (string) ($draft->payment_reference ?? '');
            $this->narration = (string) ($draft->narration ?? '');
            $this->amount = (string) $draft->amount;
        } else {
            $this->date = now()->toDateString();
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

    public function getPartyOptionsProperty(): array
    {
        if (mb_strlen(trim($this->partyQuery)) < 1) {
            return [];
        }
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->partyQuery)).'%';

        return Party::query()->where(fn ($q) => $q
            ->where('name', 'like', $term)->orWhere('gstin', 'like', $term))
            ->orderBy('name')->limit(8)->get(['id', 'name', 'gstin'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'gstin' => (string) $p->gstin])->all();
    }

    // Same permission names the routes use, re-checked on every state change.
    private function guard(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function selectParty(int $id): void
    {
        $this->guard('receipts.create');
        $party = Party::findOrFail($id);
        $this->partyId = $party->id;
        $this->partyName = $party->name;
        $this->partyQuery = $party->name;
    }

    public function getCashLedgersProperty(): array
    {
        return Ledger::whereHas('group', fn ($q) => $q->where('code', 'CASH_BANK'))
            ->orderBy('code')->get(['id', 'code', 'name'])->all();
    }

    /** Posted, unreversed invoices of the selected customer with outstanding balances. */
    public function getEligibleInvoicesProperty(): array
    {
        if ($this->partyId === null) {
            return [];
        }
        $settlement = app(SettlementService::class);
        $invoices = Invoice::query()
            ->where('party_id', $this->partyId)->where('status', 'posted')
            ->orderBy('date')->orderBy('id')->get();
        $rows = [];
        foreach ($invoices as $invoice) {
            if ($invoice->voucher_id === null || $settlement->invoiceReversed($invoice)) {
                continue;
            }
            $outstanding = $settlement->documentOutstanding($invoice);
            if ($outstanding->isNegative() || $outstanding->isZero()) {
                continue;
            }
            $rows[] = [
                'id' => $invoice->id, 'label' => $invoice->series.'/'.$invoice->number,
                'date' => $invoice->date->format('d-m-Y'), 'total' => (string) $invoice->total,
                'outstanding' => (string) $outstanding,
                'value' => (string) ($this->allocations[$invoice->id] ?? ''),
            ];
        }

        return $rows;
    }

    public function getAllocatedPreviewProperty(): string
    {
        $sum = Money::of('0');
        foreach ($this->allocations as $amount) {
            try {
                $sum = $sum->add(Money::of((string) $amount));
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return (string) $sum;
    }

    /** Live posting preview: exactly the entries posting will create. */
    public function getPreviewProperty(): array
    {
        if ($this->partyId === null || $this->ledgerId === 0) {
            return ['entries' => [], 'error' => null];
        }
        try {
            $ledger = Ledger::findOrFail($this->ledgerId);
            $party = Party::findOrFail($this->partyId);
            $customer = $party->ledger_id !== null ? Ledger::findOrFail($party->ledger_id) : null;

            return ['entries' => [
                ['debit' => $this->amount !== '' ? $this->amount : '0.00', 'credit' => '0.00', 'ledger' => $ledger->code.' · '.$ledger->name],
                ['debit' => '0.00', 'credit' => $this->amount !== '' ? $this->amount : '0.00',
                    'ledger' => $customer?->code.' · '.$customer?->name ?? 'customer ledger'],
            ], 'error' => null];
        } catch (\Throwable $e) {
            return ['entries' => [], 'error' => $e->getMessage()];
        }
    }

    public function save(): void
    {
        $this->guard('receipts.create');
        try {
            $receipt = app(SaveReceiptDraft::class)->handle($this->receiptId, $this->input());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('amount', $messages[0]);
            }

            return;
        }
        session()->flash('status', 'Receipt draft saved.');
        $this->redirectRoute('receipts.edit', $receipt->id);
    }

    public function post(): void
    {
        $this->guard('receipts.post');
        try {
            $receipt = app(SaveReceiptDraft::class)->handle($this->receiptId, $this->input());
            $posted = app(PostReceipt::class)->handle($receipt->id, $this->allocations);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('amount', $messages[0]);
            }

            return;
        } catch (\DomainException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }
        session()->flash('status', 'Receipt '.$posted->label().' posted.');
        $this->redirectRoute('receipts.show', $posted->id);
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'party_id' => $this->partyId, 'ledger_id' => $this->ledgerId, 'date' => $this->date,
            'payment_reference' => $this->paymentReference !== '' ? $this->paymentReference : null,
            'narration' => $this->narration !== '' ? $this->narration : null,
            'amount' => $this->amount,
        ];
    }

    public function render(): View
    {
        return view('livewire.sales.receipt-editor');
    }
}
