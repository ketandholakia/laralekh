<?php
declare(strict_types=1);
namespace App\Livewire\Sales;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{Company, Party, State};
use App\Domain\Sales\Actions\{PostInvoice, SaveInvoiceDraft};
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Services\InvoicePreview;
use App\Domain\Shared\Tenancy\{CompanySession, TenantContext};
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Draft creation/editing for the session company. This component holds no
// business rules: validation and totals live in SaveInvoiceDraft and
// InvoicePreview, posting in PostInvoice/PostingService. Posted invoices are
// redirected to the read-only view and can never be altered here.
#[Layout('layouts.app')]
final class InvoiceEditor extends Component
{
    public ?int $invoiceId = null;

    public string $partyQuery = '';
    public ?int $partyId = null;
    public string $partyName = '';

    public string $date = '';
    public string $dueDate = '';
    public string $series = 'SI';
    public string $supplyType = 'domestic';
    public string $placeOfSupply = '';
    public string $zeroRatedMode = 'none';
    public bool $zeroRatedEligible = false;
    public bool $sezAuthorized = false;
    public bool $reverseCharge = false;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public string $itemQuery = '';

    // Route parameters are resolved inside the entered tenant scope by the
    // component itself; implicit binding would run before the middleware.
    public function mount(int|string|null $invoice = null): void
    {
        $this->authorizeAccess();
        $invoiceId = $invoice !== null ? (int) $invoice : null;
        if ($invoiceId !== null) {
            $invoice = Invoice::with('items')->findOrFail($invoiceId);
            if ($invoice->status !== 'draft') {
                $this->redirectRoute('invoices.show', $invoice->id);
                return;
            }
            $this->invoiceId = $invoice->id;
            $this->partyId = $invoice->party_id;
            $this->partyQuery = (string) $invoice->party->name;
            $this->date = $invoice->date->format('Y-m-d');
            $this->dueDate = $invoice->due_date?->format('Y-m-d') ?? '';
            $this->series = $invoice->series;
            $this->supplyType = $invoice->supply_type;
            $this->placeOfSupply = $invoice->place_of_supply;
            $this->zeroRatedMode = $invoice->zero_rated_mode;
            $this->zeroRatedEligible = $invoice->zero_rated_eligible;
            $this->sezAuthorized = $invoice->sez_authorized;
            $this->reverseCharge = $invoice->reverse_charge;
            foreach ($invoice->items->sortBy('position') as $item) {
                $this->lines[] = $this->lineState(
                    $item->item_id, $item->description, $item->hsn_sac, (string) $item->quantity,
                    (string) $item->unit_price, (string) $item->discount, $item->tax_category,
                    $item->tax_rate_id, $item->sales_ledger_id, $item->rate !== null ? (string) $item->rate : '0',
                );
            }
        } else {
            $this->date = now()->toDateString();
            $this->placeOfSupply = (string) (Company::whereKey(app(TenantContext::class)->id())->value('state_code') ?? '');
        }
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('invoices.create'), 403);
    }

    // ---- lookups -----------------------------------------------------------

    public function getPartyOptionsProperty(): array
    {
        if (mb_strlen(trim($this->partyQuery)) < 1) {
            return [];
        }
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->partyQuery)).'%';
        return Party::query()->where(fn ($q) => $q
            ->where('name', 'like', $term)->orWhere('gstin', 'like', $term))
            ->orderBy('name')->limit(8)->get(['id', 'name', 'gstin', 'state_code'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'gstin' => (string) $p->gstin,
                'state_code' => (string) $p->state_code])->all();
    }

    public function selectParty(int $id): void
    {
        $party = Party::findOrFail($id);
        $this->partyId = $party->id;
        $this->partyName = $party->name;
        $this->partyQuery = $party->name;
    }

    public function getItemOptionsProperty(): array
    {
        if (mb_strlen(trim($this->itemQuery)) < 1) {
            return [];
        }
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->itemQuery)).'%';
        return Item::query()->where(fn ($q) => $q
            ->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('hsn_sac', 'like', $term))
            ->orderBy('name')->limit(8)
            ->get(['id', 'name', 'sku', 'hsn_sac', 'unit', 'sale_price', 'tax_rate_code'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'sku' => $i->sku, 'hsn_sac' => $i->hsn_sac,
                'unit' => $i->unit, 'sale_price' => (string) $i->sale_price, 'tax_rate_code' => (string) $i->tax_rate_code])->all();
    }

    // Adds a prefilled draft line from a master item; the posting path re-derives
    // every amount, so the prefill is only a convenience.
    public function addItem(int $id): void
    {
        $item = Item::findOrFail($id);
        $rateId = TaxRate::query()
            ->when($item->tax_rate_code !== null && $item->tax_rate_code !== '',
                fn ($q) => $q->where('code', $item->tax_rate_code))
            ->where('is_verified', true)
            ->orderByDesc('effective_from')->value('id');
        $this->lines[] = $this->lineState(
            $item->id, $item->name, $item->hsn_sac, '1', (string) $item->sale_price, '0.00',
            'taxable', $rateId, $this->defaultSalesLedgerId(), $rateId !== null
                ? (string) (TaxRate::find($rateId)->rate ?? '0') : '0',
        );
        $this->itemQuery = '';
    }

    public function addBlankLine(): void
    {
        $this->lines[] = $this->lineState(0, '', '', '1', '0.00', '0.00', 'taxable', null, $this->defaultSalesLedgerId(), '0');
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    private function lineState(int $itemId, string $description, string $hsn, string $quantity,
        string $unitPrice, string $discount, string $taxCategory, ?int $taxRateId,
        int $salesLedgerId, string $rate): array
    {
        return [
            'item_id' => $itemId, 'description' => $description, 'hsn_sac' => $hsn,
            'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount' => $discount,
            'tax_category' => $taxCategory, 'tax_rate_id' => $taxRateId,
            'sales_ledger_id' => $salesLedgerId, 'rate' => $rate,
        ];
    }

    public function getSalesLedgersProperty(): array
    {
        return Ledger::whereHas('group', fn ($q) => $q->where('nature', 'income'))
            ->orderBy('code')->get(['id', 'code', 'name'])->all();
    }

    private function defaultSalesLedgerId(): ?int
    {
        return Ledger::whereHas('group', fn ($q) => $q->where('nature', 'income'))
            ->orderBy('code')->value('id');
    }

    // ---- GST inputs and live totals -----------------------------------------

    public function getStatesProperty(): array
    {
        return State::where('is_active', true)->orderBy('code')->get(['code', 'name'])->all();
    }

    // Only verified rates effective on the invoice date are selectable; seeded
    // historical slabs never appear until marked verified for that date.
    public function getRateOptionsProperty(): array
    {
        $date = $this->date !== '' && Carbon::canBeCreatedFromFormat($this->date, 'Y-m-d') ? $this->date : null;
        if ($date === null) {
            return [];
        }
        return TaxRate::query()->where('is_verified', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderBy('rate')->get(['id', 'code', 'rate', 'effective_from', 'effective_to', 'legal_reference'])
            ->map(fn ($r) => ['id' => $r->id, 'label' => $r->code.' — '.((string) $r->rate).'%',
                'rate' => (string) $r->rate])->all();
    }

    /** Live preview through the domain service; errors surface as a message, not a crash. */
    public function getPreviewProperty(): array
    {
        if ($this->lines === [] || $this->placeOfSupply === '' || $this->date === '') {
            return ['lines' => [], 'totals' => null, 'error' => null];
        }
        try {
            $company = Company::whereKey(app(TenantContext::class)->id())->firstOrFail();
            $lines = array_map(fn ($line): array => [
                'unit_price' => (string) ($line['unit_price'] ?? '0'),
                'quantity' => (string) ($line['quantity'] ?? '0'),
                'discount' => (string) ($line['discount'] ?? '0'),
                'rate' => $this->rateStringFor((int) ($line['tax_rate_id'] ?? 0), (string) ($line['tax_category'] ?? 'taxable')),
                'tax_category' => (string) ($line['tax_category'] ?? 'taxable'),
            ], $this->lines);
            return $this->previewTotals($company, $lines) + ['error' => null];
        } catch (InvalidArgumentException $e) {
            return ['lines' => [], 'totals' => null, 'error' => $e->getMessage()];
        }
    }

    protected function previewTotals(Company $company, array $lines): array
    {
        return app(InvoicePreview::class)->totals(
            $company, $this->date, $this->supplyType, $this->placeOfSupply,
            $this->reverseCharge, $lines,
            $this->zeroRatedMode, $this->zeroRatedEligible, $this->sezAuthorized,
        );
    }

    private function rateStringFor(int $taxRateId, string $category): string
    {
        if ($category !== 'taxable' || $taxRateId === 0) {
            return '0';
        }
        foreach ($this->rateOptions as $option) {
            if ($option['id'] === $taxRateId) {
                return $option['rate'];
            }
        }
        // A saved draft may reference a rate outside the current date window;
        // resolve it directly so the preview stays truthful.
        return (string) (TaxRate::whereKey($taxRateId)->value('rate') ?? '0');
    }

    // ---- persistence -------------------------------------------------------

    public function save(): void
    {
        try {
            $invoice = app(SaveInvoiceDraft::class)->handle($this->invoiceId, $this->invoiceInput(), $this->lines);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('lines', $messages[0]);
            }
            return;
        }
        session()->flash('status', 'Draft saved.');
        $this->redirectRoute('invoices.edit', $invoice->id);
    }

    public function post(): void
    {
        if (!auth()->user()->can('invoices.post')) {
            $this->addError('lines', 'You are not allowed to post invoices.');
            return;
        }
        try {
            $invoice = app(SaveInvoiceDraft::class)->handle($this->invoiceId, $this->invoiceInput(), $this->lines);
            $invoice = app(PostInvoice::class)->handle($invoice->id);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->addError('lines', $messages[0]);
            }
            return;
        } catch (DomainException $e) {
            // Includes duplicate submissions: the posting transaction re-checks
            // the locked invoice status server-side before any number is used.
            $this->addError('lines', $e->getMessage());
            return;
        }
        session()->flash('status', 'Invoice '.$invoice->series.'/'.$invoice->number.' posted.');
        $this->redirectRoute('invoices.show', $invoice->id);
    }

    /** @return array<string, mixed> */
    private function invoiceInput(): array
    {
        return [
            'party_id' => $this->partyId, 'date' => $this->date, 'due_date' => $this->dueDate !== '' ? $this->dueDate : null,
            'series' => $this->series, 'supply_type' => $this->supplyType, 'place_of_supply' => $this->placeOfSupply,
            'zero_rated_mode' => $this->zeroRatedMode, 'zero_rated_eligible' => $this->zeroRatedEligible,
            'sez_authorized' => $this->sezAuthorized, 'reverse_charge' => $this->reverseCharge,
        ];
    }

    public function render(): View
    {
        return view('livewire.sales.invoice-editor');
    }
}
