<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\Party;
use App\Domain\Masters\Models\State;
use App\Domain\Sales\Actions\PostAdvanceReceiptVoucher;
use App\Domain\Sales\Actions\SaveAdvanceReceiptVoucherDraft;
use App\Domain\Sales\Models\AdvanceReceiptVoucher;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AdvanceReceiptVoucherEditor extends Component
{
    public ?int $voucherId = null;

    public ?int $partyId = null;

    public string $partyName = '';

    public ?int $receiptId = null;

    public string $date = '';

    public string $placeOfSupply = '';

    public string $supplyType = 'services';

    public string $description = '';

    public bool $isRateDeterminable = true;

    public bool $isInterstateDeterminable = true;

    public string $taxRate = '18.00';

    public string $grossAmount = '';

    public string $paymentReference = '';

    public string $narration = '';

    public function mount(
        Party|int|string|null $party = null,
        int|string|null $id = null,
        int|string|null $receipt = null,
    ): void {
        $this->authorizeAccess();

        if ($id !== null) {
            $voucher = AdvanceReceiptVoucher::with(['party', 'receipt'])->findOrFail((int) $id);
            if ($voucher->status !== 'draft') {
                $this->redirectRoute('advance-vouchers.show', $voucher->id);

                return;
            }
            $this->voucherId = $voucher->id;
            $this->partyId = $voucher->party_id;
            $this->partyName = (string) $voucher->party->name;
            $this->receiptId = $voucher->receipt_id;
            $this->date = $voucher->date->format('Y-m-d');
            $this->placeOfSupply = $voucher->place_of_supply;
            $this->supplyType = $voucher->supply_type;
            $this->description = $voucher->description;
            $this->isRateDeterminable = (bool) $voucher->is_rate_determinable;
            $this->isInterstateDeterminable = (bool) $voucher->is_interstate_determinable;
            $this->taxRate = (string) $voucher->tax_rate;
            $this->grossAmount = (string) $voucher->gross_amount;
            $this->paymentReference = (string) ($voucher->payment_reference ?? '');
            $this->narration = (string) ($voucher->narration ?? '');
        } elseif ($party !== null) {
            $customer = $party instanceof Party ? $party : Party::findOrFail((int) $party);
            $this->partyId = $customer->id;
            $this->partyName = $customer->name;
            $this->placeOfSupply = $customer->state_code ?? '';
            $this->date = now()->toDateString();
            if ($receipt !== null) {
                $rec = Receipt::findOrFail((int) $receipt);
                $this->receiptId = $rec->id;
                $this->grossAmount = (string) app(SettlementService::class)->availableReceiptFunds($rec);
                $this->paymentReference = (string) ($rec->payment_reference ?? '');
            }
        } else {
            $this->date = now()->toDateString();
        }

        if ($this->placeOfSupply === '') {
            $company = Company::find(app(TenantContext::class)->id());
            $this->placeOfSupply = $company?->state_code ?? '27';
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

    public function getStatesProperty(): array
    {
        return State::orderBy('code')->get(['code', 'name'])->all();
    }

    public function getAvailableReceiptsProperty(): array
    {
        if ($this->partyId === null) {
            return [];
        }

        $settlement = app(SettlementService::class);
        $receipts = Receipt::where('party_id', $this->partyId)
            ->where('status', 'posted')
            ->orderBy('date')->orderBy('id')
            ->get();

        $out = [];
        foreach ($receipts as $r) {
            $available = $settlement->availableReceiptFunds($r);
            if ($available->isPositive() || $r->id === $this->receiptId) {
                $out[] = [
                    'id' => $r->id,
                    'label' => 'Receipt '.$r->label().' (Avail: ₹'.$available.')',
                    'available' => (string) $available,
                ];
            }
        }

        return $out;
    }

    public function getTaxPreviewProperty(): array
    {
        $rate = '0.00';
        if ($this->supplyType === 'services') {
            $rate = $this->isRateDeterminable ? $this->taxRate : '18.00';
        }

        $zero = '0.00';
        if (! is_numeric($this->grossAmount) || (float) $this->grossAmount <= 0) {
            return [
                'taxable' => $zero,
                'cgst' => $zero,
                'sgst' => $zero,
                'utgst' => $zero,
                'igst' => $zero,
                'total_tax' => $zero,
                'effective_rate' => $rate,
            ];
        }

        $gross = Money::of($this->grossAmount);
        if (bccomp($rate, '0.00', 2) <= 0) {
            return [
                'taxable' => (string) $gross,
                'cgst' => $zero,
                'sgst' => $zero,
                'utgst' => $zero,
                'igst' => $zero,
                'total_tax' => $zero,
                'effective_rate' => '0.00',
            ];
        }

        $taxable = Money::rounded(bcdiv(bcmul((string) $gross, '100', 7), bcadd('100', $rate, 7), 7));
        $totalTax = $gross->subtract($taxable);

        $company = Company::find(app(TenantContext::class)->id());
        $supplierState = $company?->state_code ?? '27';
        $isInterstate = (! $this->isInterstateDeterminable) || ($this->placeOfSupply !== $supplierState);

        $cgst = $sgst = $utgst = $igst = $zero;

        if ($isInterstate) {
            $igst = (string) $totalTax;
        } else {
            $cgstMoney = Money::rounded(bcdiv((string) $totalTax, '2', 7));
            $halfMoney = $totalTax->subtract($cgstMoney);
            $cgst = (string) $cgstMoney;

            $posState = State::where('code', $this->placeOfSupply)->first();
            if ($posState?->uses_utgst) {
                $utgst = (string) $halfMoney;
            } else {
                $sgst = (string) $halfMoney;
            }
        }

        return [
            'taxable' => (string) $taxable,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'utgst' => $utgst,
            'igst' => $igst,
            'total_tax' => (string) $totalTax,
            'effective_rate' => $rate,
        ];
    }

    public function updatedReceiptId(?string $val): void
    {
        if ($val) {
            $rec = Receipt::find((int) $val);
            if ($rec) {
                $this->grossAmount = (string) app(SettlementService::class)->availableReceiptFunds($rec);
                if (! empty($rec->payment_reference)) {
                    $this->paymentReference = (string) $rec->payment_reference;
                }
            }
        }
    }

    public function saveDraft(): AdvanceReceiptVoucher
    {
        $this->authorizeAccess();

        $this->validate([
            'partyId' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'placeOfSupply' => 'required|string|size:2',
            'description' => 'required|string|min:3|max:255',
            'grossAmount' => 'required|numeric|gt:0',
            'taxRate' => 'required|numeric|gte:0|lte:100',
        ]);

        try {
            return app(SaveAdvanceReceiptVoucherDraft::class)->handle(
                partyId: $this->partyId,
                date: $this->date,
                placeOfSupply: $this->placeOfSupply,
                grossAmount: $this->grossAmount,
                description: $this->description,
                supplyType: $this->supplyType,
                isRateDeterminable: $this->isRateDeterminable,
                isInterstateDeterminable: $this->isInterstateDeterminable,
                taxRate: $this->taxRate,
                receiptId: $this->receiptId ?: null,
                paymentReference: $this->paymentReference !== '' ? $this->paymentReference : null,
                narration: $this->narration !== '' ? $this->narration : null,
                voucherId: $this->voucherId,
                userId: auth()->id(),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['grossAmount' => $e->getMessage()]);
        }
    }

    public function postVoucher(): void
    {
        abort_unless(auth()->user()?->can('receipts.post'), 403);

        $draft = $this->saveDraft();
        $posted = app(PostAdvanceReceiptVoucher::class)->handle($draft->id, auth()->id());

        $this->redirectRoute('advance-vouchers.show', $posted->id);
    }

    public function render(): View
    {
        return view('livewire.sales.advance-receipt-voucher-editor', [
            'states' => $this->states,
            'availableReceipts' => $this->availableReceipts,
            'taxPreview' => $this->taxPreview,
        ]);
    }
}
