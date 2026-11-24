<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Purchase\Actions\PostPurchaseDebitNote;
use App\Domain\Purchase\Actions\SavePurchaseDebitNoteDraft;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\PurchaseDebitNote;
use App\Domain\Purchase\Models\PurchaseDebitNoteLine;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
final class PurchaseDebitNoteEditor extends Component
{
    #[Locked]
    public int $billId;

    #[Locked]
    public ?int $debitNoteId = null;

    public string $date = '';

    public string $reason = '';

    /** @var array<int, array{selected: bool, bill_item_id: int, description: string, original_quantity: string, prior_debited_quantity: string, max_quantity: string, quantity: string, unit_price: string, taxable_amount: string, total: string}> */
    public array $lines = [];

    public function boot(): void
    {
        $company = app(CompanySession::class)->companyId();
        abort_if($company === null, 403);
        abort_unless(auth()->user()->isMemberOf($company) && auth()->user()->can('purchases.create'), 403);
    }

    public function mount(int|string|null $bill = null, ?int $debitNote = null): void
    {
        if ($debitNote !== null) {
            $dn = PurchaseDebitNote::with(['bill.items', 'lines'])->findOrFail($debitNote);
            if ($dn->status === 'posted') {
                $this->redirect(route('purchase-debit-notes.show', $dn));

                return;
            }
            $this->debitNoteId = $dn->id;
            $this->billId = $dn->purchase_bill_id;
            $this->date = $dn->date->format('Y-m-d');
            $this->reason = $dn->reason;
            $billModel = $dn->bill;
        } else {
            $this->billId = (int) $bill;
            $billModel = PurchaseBill::with('items')->findOrFail($this->billId);
            abort_unless($billModel->status === 'posted', 400, 'Debit notes can only be issued against posted purchase bills.');
            $today = now()->format('Y-m-d');
            $this->date = $today >= $billModel->date->format('Y-m-d') ? $today : $billModel->date->format('Y-m-d');
            $this->reason = 'Goods returned / price reduction';
        }

        // Initialize line states
        $existingLines = $this->debitNoteId !== null
            ? PurchaseDebitNoteLine::where('purchase_debit_note_id', $this->debitNoteId)->get()->keyBy('purchase_bill_item_id')
            : collect();

        foreach ($billModel->items as $item) {
            $priorDebited = (string) PurchaseDebitNoteLine::where('purchase_bill_item_id', $item->id)
                ->where('purchase_debit_note_id', '!=', $this->debitNoteId ?? 0)
                ->sum('quantity');

            $maxQty = bcsub((string) $item->quantity, $priorDebited, 3);
            $existing = $existingLines->get($item->id);

            $currentQty = $existing ? (string) $existing->quantity : '0';
            $isSelected = $existing !== null || (bccomp($maxQty, '0', 3) > 0 && $this->debitNoteId === null);

            $this->lines[$item->id] = [
                'selected' => $isSelected && bccomp($maxQty, '0', 3) > 0,
                'bill_item_id' => $item->id,
                'description' => $item->description,
                'original_quantity' => (string) $item->quantity,
                'prior_debited_quantity' => $priorDebited,
                'max_quantity' => $maxQty,
                'quantity' => $currentQty !== '0' ? $currentQty : (bccomp($maxQty, '0', 3) > 0 ? $maxQty : '0'),
                'unit_price' => (string) $item->unit_price,
                'taxable_amount' => '0.00',
                'total' => '0.00',
            ];
        }

        $this->recalculateLines();
    }

    public function updatedLines(): void
    {
        $this->recalculateLines();
    }

    private function recalculateLines(): void
    {
        $bill = PurchaseBill::with('items')->find($this->billId);
        if (! $bill) {
            return;
        }

        $items = $bill->items->keyBy('id');
        foreach ($this->lines as $id => $line) {
            $item = $items->get($id);
            if (! $item) {
                continue;
            }

            $qty = is_numeric($line['quantity']) ? (string) $line['quantity'] : '0';
            if (! $line['selected'] || bccomp($qty, '0', 3) <= 0) {
                $this->lines[$id]['taxable_amount'] = '0.00';
                $this->lines[$id]['total'] = '0.00';

                continue;
            }

            $ratio = bcdiv($qty, (string) $item->quantity, 8);
            $taxable = bcmul((string) $item->taxable_amount, $ratio, 2);
            $totalTax = bcadd(
                bcadd((string) $item->cgst, (string) $item->sgst, 2),
                bcadd((string) $item->utgst, bcadd((string) $item->igst, (string) $item->cess, 2), 2),
                2
            );
            $proratedTax = bcmul($totalTax, $ratio, 2);
            $this->lines[$id]['taxable_amount'] = $taxable;
            $this->lines[$id]['total'] = bcadd($taxable, $proratedTax, 2);
        }
    }

    public function saveDraft(): void
    {
        $this->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $payloadLines = [];
        foreach ($this->lines as $line) {
            if ($line['selected'] && bccomp((string) $line['quantity'], '0', 3) > 0) {
                $payloadLines[] = [
                    'purchase_bill_item_id' => $line['bill_item_id'],
                    'quantity' => $line['quantity'],
                    'description' => $line['description'],
                ];
            }
        }

        if (empty($payloadLines)) {
            $this->addError('lines', 'Please select at least one item with a return quantity greater than zero.');

            return;
        }

        try {
            $debitNote = app(SavePurchaseDebitNoteDraft::class)->handle(
                $this->debitNoteId,
                $this->billId,
                ['date' => $this->date, 'reason' => $this->reason],
                $payloadLines
            );
            $this->debitNoteId = $debitNote->id;
            session()->flash('status', 'Purchase debit note draft saved.');
            $this->redirect(route('purchase-debit-notes.show', $debitNote));
        } catch (DomainException $e) {
            $this->addError('lines', $e->getMessage());
        }
    }

    public function post(): void
    {
        abort_unless(auth()->user()->can('purchases.post'), 403);

        $this->saveDraft();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $posted = app(PostPurchaseDebitNote::class)->handle($this->debitNoteId);
            session()->flash('status', 'Purchase debit note PDN/'.$posted->number.' posted successfully.');
            $this->redirect(route('purchase-debit-notes.show', $posted));
        } catch (DomainException $e) {
            $this->addError('post', $e->getMessage());
        }
    }

    public function render(): View
    {
        $bill = PurchaseBill::with(['items', 'party'])->findOrFail($this->billId);

        $totalTaxable = '0.00';
        $totalAmount = '0.00';
        foreach ($this->lines as $line) {
            if ($line['selected']) {
                $totalTaxable = bcadd($totalTaxable, $line['taxable_amount'], 2);
                $totalAmount = bcadd($totalAmount, $line['total'], 2);
            }
        }

        return view('livewire.purchase.purchase-debit-note-editor', [
            'bill' => $bill,
            'totalTaxable' => $totalTaxable,
            'totalAmount' => $totalAmount,
        ]);
    }
}
