<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Masters\Models\Party;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Domain\Purchase\Actions\SavePurchaseBillDraft;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class PurchaseBillEditor extends Component
{
    public ?int $billId = null;

    public array $data = ['party_id' => '', 'supplier_bill_number' => '', 'supplier_bill_date' => '', 'date' => '', 'reverse_charge' => false];

    public array $lines = [];

    public function boot(): void
    {
        $company = app(CompanySession::class)->companyId();
        abort_if($company === null, 403);
        abort_unless(auth()->user()->isMemberOf($company) && auth()->user()->can('purchases.create'), 403);
    }

    public function mount(int|string|null $bill = null): void
    {
        if ($bill) {
            $model = PurchaseBill::with('items')->findOrFail((int) $bill);
            abort_if($model->status !== 'draft', 403);
            $this->billId = $model->id;
            // Livewire public properties must hold plain scalars: stringify
            // dates and Money-cast fields instead of keeping model casts.
            $this->data = collect($model->only(['party_id', 'supplier_bill_number', 'supplier_bill_date', 'date', 'reverse_charge']))
                ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all();
            $this->lines = $model->items->map(fn ($l) => collect($l->only(['item_id', 'purchase_ledger_id', 'tax_rate_id', 'description', 'hsn_sac', 'quantity', 'unit_price', 'discount', 'tax_category', 'itc_eligibility', 'itc_reason']))
                ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (is_object($value) ? (string) $value : $value))->all())->all();
        } else {
            $this->data['date'] = now()->toDateString();
            $this->data['supplier_bill_date'] = now()->toDateString();
        }
        if (! $this->lines) {
            $this->lines = [['item_id' => '', 'purchase_ledger_id' => '', 'tax_rate_id' => '', 'description' => '', 'hsn_sac' => '', 'quantity' => '1', 'unit_price' => '0.00', 'discount' => '0.00', 'tax_category' => 'taxable', 'itc_eligibility' => 'eligible', 'itc_reason' => '']];
        }
    }

    public function addLine(): void
    {
        abort_unless(auth()->user()?->can('purchases.create'), 403);
        $this->lines[] = ['item_id' => '', 'purchase_ledger_id' => '', 'tax_rate_id' => '', 'description' => '', 'hsn_sac' => '', 'quantity' => '1', 'unit_price' => '0.00', 'discount' => '0.00', 'tax_category' => 'taxable', 'itc_eligibility' => 'eligible', 'itc_reason' => ''];
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('purchases.create'), 403);
        $bill = app(SavePurchaseBillDraft::class)->handle($this->billId, $this->data, $this->lines);
        $this->billId = $bill->id;
        session()->flash('status', 'Purchase bill draft saved.');
    }

    public function post(): void
    {
        abort_unless(auth()->user()?->can('purchases.post'), 403);
        // Inline the draft save so posting needs purchases.post only.
        $bill = app(SavePurchaseBillDraft::class)->handle($this->billId, $this->data, $this->lines);
        $this->billId = $bill->id;
        $bill = app(PostPurchaseBill::class)->handle($this->billId);
        $this->redirectRoute('purchases.show', $bill->id);
    }

    public function render(): View
    {
        return view('livewire.purchase.purchase-bill-editor', ['parties' => Party::orderBy('name')->get(), 'ledgers' => Ledger::orderBy('name')->get(), 'rates' => TaxRate::orderBy('effective_from', 'desc')->get()]);
    }
}
