<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Services\InvoicePreview;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

// Creates or updates a draft sales invoice. Drafts carry no allocated number
// and no accounting effect; posting revalidates and recalculates everything.
// company_id is never accepted here: the invoice belongs to the authorized
// session company, and tenant scopes reject anything else.
final class SaveInvoiceDraft
{
    public function __construct(private InvoicePreview $preview) {}

    /**
     * @param  array<string, mixed>  $input  Validated UI input; unknown keys are ignored.
     * @param  list<array<string, mixed>>  $lines
     */
    public function handle(?int $invoiceId, array $input, array $lines): Invoice
    {
        $session = app(CompanySession::class);
        $yearId = $session->financialYearId()
            ?? throw ValidationException::withMessages(['date' => 'Select a financial year first.']);
        $company = Company::whereKey(app(TenantContext::class)->id())->firstOrFail();

        $data = [
            'party_id' => (int) ($input['party_id'] ?? 0),
            'date' => (string) ($input['date'] ?? ''),
            'due_date' => $input['due_date'] ?? null,
            'series' => (string) ($input['series'] ?? 'SI'),
            'supply_type' => (string) ($input['supply_type'] ?? 'domestic'),
            'place_of_supply' => (string) ($input['place_of_supply'] ?? ''),
            'zero_rated_mode' => (string) ($input['zero_rated_mode'] ?? 'none'),
            'zero_rated_eligible' => (bool) ($input['zero_rated_eligible'] ?? false),
            'sez_authorized' => (bool) ($input['sez_authorized'] ?? false),
            'reverse_charge' => (bool) ($input['reverse_charge'] ?? false),
        ];

        try {
            $this->assertDraftEditable($invoiceId);
            $year = FinancialYear::whereKey($yearId)->sole();
            $date = Carbon::createFromFormat('Y-m-d', $data['date'])
                ?: throw new \InvalidArgumentException('Provide a valid invoice date.');
            if ($data['due_date'] !== null && $data['due_date'] !== '') {
                if (Carbon::createFromFormat('Y-m-d', (string) $data['due_date']) === false) {
                    throw new \InvalidArgumentException('Provide a valid due date.');
                }
            } else {
                $data['due_date'] = null;
            }
            if ($year->is_closed || $date->lt($year->starts_on) || $date->gt($year->ends_on)) {
                throw new \InvalidArgumentException('The invoice date must fall inside the selected open financial year.');
            }
            $party = Party::whereKey($data['party_id'])->firstOrFail();
            $lines = $this->validatedLines($company, $date, $lines);
            // Run the same calculator the posting path uses, so a draft cannot be
            // saved with input the poster would reject.
            $this->preview->totals(
                $company, $data['date'], $data['supply_type'], $data['place_of_supply'],
                $data['reverse_charge'], $lines,
                $data['zero_rated_mode'], $data['zero_rated_eligible'], $data['sez_authorized'],
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['lines' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($invoiceId, $data, $year, $lines): Invoice {
            $invoice = $invoiceId === null
                ? new Invoice(['financial_year_id' => $year->id, 'series' => $data['series']])
                : Invoice::whereKey($invoiceId)->sole();
            $invoice->fill($data + ['financial_year_id' => $year->id, 'series' => $data['series']]);
            $invoice->save();
            // Draft lines are fully replaced on every save; posted invoices never reach here.
            $invoice->items()->delete();
            foreach ($lines as $position => $line) {
                $invoice->items()->create($line + [
                    'financial_year_id' => $year->id, 'position' => $position + 1,
                ]);
            }

            return $invoice->refresh();
        });
    }

    private function assertDraftEditable(?int $invoiceId): void
    {
        if ($invoiceId === null) {
            return;
        }
        $invoice = Invoice::whereKey($invoiceId)->first();
        if ($invoice === null) {
            throw new \InvalidArgumentException('Invoice not found.');
        }
        if ($invoice->status !== 'draft') {
            throw new \InvalidArgumentException('Posted invoices cannot be modified; issue a correction document instead.');
        }
    }

    /**
     * Validates line inputs against the tenant and the invoice date, and
     * returns canonical line payloads for persistence.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function validatedLines(Company $company, Carbon $date, array $lines): array
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('Add at least one line item.');
        }
        if (count($lines) > 500) {
            throw new \InvalidArgumentException('Too many line items.');
        }
        $canonical = [];
        foreach (array_values($lines) as $line) {
            $item = Item::whereKey((int) ($line['item_id'] ?? 0))->firstOrFail();
            $salesLedger = Ledger::whereKey((int) ($line['sales_ledger_id'] ?? 0))->firstOrFail();
            if (LedgerGroup::whereKey($salesLedger->ledger_group_id)->sole()->nature !== 'income') {
                throw new \InvalidArgumentException('The sales ledger must be an income ledger.');
            }
            $category = (string) ($line['tax_category'] ?? 'taxable');
            $rateId = $line['tax_rate_id'] ?? null;
            if ($category === 'taxable') {
                if ($rateId === null) {
                    throw new \InvalidArgumentException('Taxable lines need a GST rate selection.');
                }
                $rate = TaxRate::whereKey((int) $rateId)->firstOrFail();
                if (! $rate->is_verified || $date->lt($rate->effective_from) ||
                    ($rate->effective_to !== null && $date->gt($rate->effective_to))) {
                    throw new \InvalidArgumentException(
                        'A verified GST rate effective on the invoice date is required for '.$item->name.'.');
                }
            } else {
                $rateId = null;
            }
            $quantity = (string) ($line['quantity'] ?? '');
            $unitPrice = (string) ($line['unit_price'] ?? '');
            $discount = (string) ($line['discount'] ?? '0.00');
            if (! preg_match('/^\d{1,12}(?:\.\d{1,3})?$/D', $quantity) || bccomp($quantity, '0', 3) <= 0) {
                throw new \InvalidArgumentException('Quantity must be positive with at most three decimals.');
            }
            Money::of($unitPrice);
            Money::of($discount);
            $canonical[] = [
                'item_id' => $item->id,
                'sales_ledger_id' => $salesLedger->id,
                'tax_rate_id' => $rateId,
                'rate' => $rateId !== null ? (string) $rate->rate : '0',
                'description' => (string) ($line['description'] ?? $item->name),
                'hsn_sac' => (string) ($line['hsn_sac'] ?? $item->hsn_sac),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_category' => $category,
            ];
        }

        return $canonical;
    }
}
