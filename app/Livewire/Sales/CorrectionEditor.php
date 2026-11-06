<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Actions\PostSalesCorrection;
use App\Domain\Sales\Actions\SaveSalesCorrection;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Sales\Services\CorrectionCalculator;
use App\Domain\Sales\Services\CorrectionService;
use DomainException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
final class CorrectionEditor extends Component
{
    #[Locked]
    public ?int $noteId = null;

    #[Locked]
    public int $invoiceId;

    #[Locked]
    public string $requestKey;

    public array $form = [];

    public array $lines = [];

    public function boot(): void
    {
        app(CorrectionAccess::class)->authorize('corrections.create');
    }

    public function mount(int|string|null $invoice = null, int|string|null $correction = null): void
    {
        $note = $correction ? SalesCorrection::with('lines')->findOrFail((int) $correction) : null;
        if ($note && $note->status === 'posted') {
            $this->redirectRoute('corrections.show', $note->id);

            return;
        }
        $source = Invoice::with('items')->findOrFail($note?->invoice_id ?? (int) $invoice);
        abort_unless($source->status === 'posted', 422, 'Choose a posted invoice.');
        $this->invoiceId = $source->id;
        $this->noteId = $note?->id;
        $this->requestKey = $note?->request_key ?? (string) Str::uuid();
        $this->form = $note ? [
            'kind' => $note->kind, 'treatment' => $note->treatment, 'date' => $note->date->format('Y-m-d'),
            'reason_code' => $note->reason_code, 'reason' => $note->reason,
            'eligibility_confirmed' => $note->eligibility_confirmed, 'eligibility_evidence' => $note->eligibility_evidence ?? '',
            'reporting_date' => $note->reporting_date?->format('Y-m-d') ?? '',
            'annual_return_filed_on' => $note->annual_return_filed_on?->format('Y-m-d') ?? '',
            'delivery_address' => $note->delivery_address ?? '',
        ] : [
            'kind' => request()->query('kind') === 'debit' ? 'debit' : 'credit', 'treatment' => 'commercial',
            'date' => now()->toDateString(), 'reason_code' => 'commercial', 'reason' => '',
            'eligibility_confirmed' => false, 'eligibility_evidence' => '', 'reporting_date' => '',
            'annual_return_filed_on' => '', 'delivery_address' => $source->party_snapshot['address'] ?? '',
        ];
        $capacity = app(CorrectionCalculator::class)->remaining($source);
        foreach ($source->items as $item) {
            $saved = $note?->lines->firstWhere('invoice_item_id', $item->id);
            $this->lines[] = ['invoice_item_id' => $item->id, 'selected' => $saved !== null,
                'quantity' => $saved?->quantity ?? '0', 'amount' => $saved ? (string) $saved->taxable_amount : $capacity[$item->id]['amount']];
        }
    }

    private function selected(): array
    {
        return array_values(array_filter($this->lines, fn ($line) => ($line['selected'] ?? false) === true));
    }

    private function input(): array
    {
        return ['request_key' => $this->requestKey] + $this->form;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('corrections.create'), 403);
        $this->resetErrorBag();
        try {
            $note = app(SaveSalesCorrection::class)->handle($this->noteId, $this->invoiceId, $this->input(), $this->selected());
            $this->noteId = $note->id;
            session()->flash('status', 'Correction draft saved. No number allocated.');
        } catch (ValidationException $e) {
            $this->addError('correction', $e->validator->errors()->first());
        } catch (DomainException|InvalidArgumentException $e) {
            $this->addError('correction', $e->getMessage());
        }
    }

    public function post(): void
    {
        abort_unless(auth()->user()?->can('corrections.post'), 403);
        $this->resetErrorBag();
        if (! $this->noteId) {
            $this->addError('correction', 'Save the draft before posting.');

            return;
        }
        try {
            $note = app(PostSalesCorrection::class)->handle($this->noteId, $this->input(), $this->selected());
            $this->redirectRoute('corrections.show', $note->id);
        } catch (ValidationException $e) {
            $this->addError('correction', $e->validator->errors()->first());
        } catch (DomainException|InvalidArgumentException $e) {
            $this->addError('correction', $e->getMessage());
        }
    }

    public function render()
    {
        $source = Invoice::with('items')->findOrFail($this->invoiceId);
        $preview = null;
        $error = null;
        if ($this->selected()) {
            try {
                $preview = app(CorrectionService::class)->preview($this->invoiceId, $this->form['kind'], $this->form['treatment'], $this->selected());
            } catch (DomainException|InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return view('livewire.sales.correction-editor', ['source' => $source,
            'capacity' => app(CorrectionCalculator::class)->remaining($source), 'preview' => $preview, 'previewError' => $error]);
    }
}
