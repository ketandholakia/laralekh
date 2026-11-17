<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Actions\PostPayment;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Purchase\Models\SupplierPaymentAllocation;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Supplier-payment editor: cash/bank ledger, date, amount and allocations of
// the funds to the supplier's posted bills. The draft row is field-only data
// created here; every accounting rule (balanced entries, series numbering,
// outstanding caps, closed years) is enforced by PostPayment — nothing is
// duplicated in this component. Drafts support later posting; posting itself
// requires the purchases.post permission on top of purchases.create.
#[Layout('layouts.app')]
final class SupplierPaymentEditor extends Component
{
    public ?int $paymentId = null;

    public string $partyQuery = '';

    public ?int $partyId = null;

    public string $partyName = '';

    public int $ledgerId = 0;

    public string $date = '';

    public string $amount = '';

    /** @var array<int|string, string> purchase_bill_id => amount */
    public array $allocations = [];

    public function mount(int|string|null $payment = null): void
    {
        $this->authorizeAccess();
        if ($payment !== null) {
            $draft = SupplierPayment::findOrFail((int) $payment);
            if ($draft->status !== 'draft') {
                $this->redirectRoute('supplier-payments.show', $draft->id);

                return;
            }
            $this->paymentId = $draft->id;
            $this->partyId = $draft->party_id;
            $this->partyName = (string) $draft->party->name;
            $this->partyQuery = $this->partyName;
            $this->ledgerId = $draft->ledger_id;
            $this->date = $draft->date->format('Y-m-d');
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
        abort_unless($user->can('purchases.create'), 403);
    }

    // Same permission names the routes use, re-checked on every state change.
    private function guard(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
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

    public function selectParty(int $id): void
    {
        $this->guard('purchases.create');
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

    /**
     * Posted bills of the selected supplier with their outstanding balances.
     * Display-only mirror of the derivation PostPayment::post enforces
     * authoritatively (payable less active allocations, reversal markers
     * excluded); the caps are re-checked there at posting time.
     */
    public function getEligibleBillsProperty(): array
    {
        if ($this->partyId === null) {
            return [];
        }
        $bills = PurchaseBill::query()
            ->where('party_id', $this->partyId)->where('status', 'posted')
            ->orderBy('date')->orderBy('id')->get();
        $rows = [];
        foreach ($bills as $bill) {
            if ($bill->voucher_id === null) {
                continue;
            }
            $paid = SupplierPaymentAllocation::query()->where('purchase_bill_id', $bill->id)
                ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('supplier_payment_allocation_reversals')
                    ->whereColumn('allocation_id', 'supplier_payment_allocations.id'))
                ->get()->reduce(fn ($s, $r) => $s->add($r->amount), Money::of('0'));
            $payable = $bill->reverse_charge ? $bill->taxable_amount : $bill->total;
            $outstanding = $payable->subtract($paid);
            if ($outstanding->isNegative() || $outstanding->isZero()) {
                continue;
            }
            $rows[] = [
                'id' => $bill->id, 'label' => $bill->supplier_bill_number,
                'date' => $bill->date->format('d-m-Y'), 'total' => (string) $payable,
                'outstanding' => (string) $outstanding,
                'value' => (string) ($this->allocations[$bill->id] ?? ''),
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
            $supplier = $party->ledger_id !== null ? Ledger::findOrFail($party->ledger_id) : null;

            return ['entries' => [
                ['debit' => $this->amount !== '' ? $this->amount : '0.00', 'credit' => '0.00',
                    'ledger' => $supplier?->code.' · '.$supplier?->name ?? 'supplier ledger'],
                ['debit' => '0.00', 'credit' => $this->amount !== '' ? $this->amount : '0.00',
                    'ledger' => $ledger->code.' · '.$ledger->name],
            ], 'error' => null];
        } catch (\Throwable $e) {
            return ['entries' => [], 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'party_id' => $this->partyId, 'ledger_id' => $this->ledgerId,
            'date' => $this->date, 'amount' => $this->amount,
        ];
    }

    /** @return array<string, list<string>> */
    private function validateDraft(): array
    {
        $errors = [];
        if ($this->partyId === null) {
            $errors['party'][] = 'Select the supplier being paid.';
        }
        if ($this->ledgerId === 0) {
            $errors['ledger'][] = 'Select the cash or bank ledger to pay from.';
        } else {
            // Mirror SaveReceiptDraft: the payment ledger must be cash/bank.
            $ledger = Ledger::find($this->ledgerId);
            if ($ledger === null || LedgerGroup::whereKey($ledger->ledger_group_id)->sole()->code !== 'CASH_BANK') {
                $errors['ledger'][] = 'Payments must use a cash or bank ledger.';
            }
        }
        // Strictly positive amount — Money accepts negative decimals, so the
        // sign is checked explicitly (posting rejects it again authoritatively).
        try {
            $amount = Money::of($this->amount === '' ? '' : $this->amount);
            if ($amount->isZero() || $amount->isNegative()) {
                $errors['amount'][] = 'A payment needs a positive amount.';
            }
        } catch (\InvalidArgumentException|\OverflowException) {
            $errors['amount'][] = 'Enter a valid positive amount (up to two decimal places).';
        }
        // Financial year comes from the authorized session selection — never a
        // hardcoded year; PostPayment stays authoritative at posting time.
        $yearId = app(CompanySession::class)->financialYearId();
        if ($yearId === null) {
            $errors['date'][] = 'Select a financial year first.';
        } else {
            try {
                $year = FinancialYear::whereKey($yearId)->sole();
            } catch (ModelNotFoundException) {
                $errors['date'][] = 'Select a financial year first.';
                $year = null;
            }
            if ($year !== null) {
                if ($year->is_closed) {
                    $errors['date'][] = 'The selected financial year is closed.';
                }
                if ($this->date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
                    $errors['date'][] = 'Enter the payment date.';
                } elseif ($year->is_closed
                    || CarbonImmutable::parse($this->date)->lt($year->starts_on)
                    || CarbonImmutable::parse($this->date)->gt($year->ends_on)) {
                    $errors['date'][] = 'The payment date must fall inside the selected financial year.';
                }
            }
        }

        return $errors;
    }

    private function persistDraft(): SupplierPayment
    {
        // validateDraft() has already confirmed the session-selected financial
        // year is present, open, and contains the payment date.
        $yearId = app(CompanySession::class)->financialYearId();
        $data = $this->input();
        if ($this->paymentId !== null) {
            $draft = SupplierPayment::findOrFail($this->paymentId);
            $draft->update($data + ['financial_year_id' => $yearId]);

            return $draft;
        }

        return SupplierPayment::create($data + ['financial_year_id' => $yearId]);
    }

    public function save(): void
    {
        $this->guard('purchases.create');
        foreach ($this->validateDraft() as $field => $messages) {
            $this->addError($field, $messages[0]);
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        $draft = $this->persistDraft();
        session()->flash('status', 'Payment draft saved.');
        $this->redirectRoute('supplier-payments.show', $draft->id);
    }

    public function post(): void
    {
        $this->guard('purchases.post');
        foreach ($this->validateDraft() as $field => $messages) {
            $this->addError($field, $messages[0]);
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        try {
            $draft = $this->persistDraft();
            $posted = app(PostPayment::class)->post($draft->id, $this->allocations);
        } catch (\DomainException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }
        session()->flash('status', 'Payment '.$posted->series.'/'.$posted->number.' posted.');
        $this->redirectRoute('supplier-payments.show', $posted->id);
    }
}
