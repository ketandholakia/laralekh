<?php
declare(strict_types=1);
namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\{Ledger, LedgerGroup, Voucher};
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Gst\Services\{GstCalculator, PlaceOfSupplyResolver};
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\{Company, FinancialYear, Party};
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use DomainException;

final class PostingService
{
    public function __construct(
        private GstCalculator $calculator,
        private PlaceOfSupplyResolver $placeOfSupply,
        private VoucherNumberAllocator $numbers,
    ) {}

    public function postSalesInvoice(int $invoiceId): Voucher
    {
        return DB::transaction(function () use ($invoiceId): Voucher {
            $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->sole();
            if ($invoice->status !== 'draft' || $invoice->voucher_id !== null || $invoice->number !== null) {
                throw new DomainException('Invoice is already posted or has an assigned number.');
            }
            $year = FinancialYear::whereKey($invoice->financial_year_id)->lockForUpdate()->sole();
            if ($year->is_closed || $invoice->date->lt($year->starts_on) || $invoice->date->gt($year->ends_on)) {
                throw new DomainException('Invoice date must be inside an open financial year.');
            }
            $company = Company::whereKey(app(TenantContext::class)->id())->lockForUpdate()->sole();
            $party = Party::whereKey($invoice->party_id)->lockForUpdate()->sole();
            $partyLedger = $this->ledger($party->ledger_id, 'asset');
            // place_of_supply is an explicitly reviewed location, not necessarily the billing state.
            $supply = $this->placeOfSupply->resolve($company->state_code, $invoice->place_of_supply, $invoice->supply_type);
            $items = $invoice->items()->orderBy('position')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw new DomainException('Cannot post an invoice without items.');
            }

            $totals = array_fill_keys(['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'cess', 'total'], Money::of('0'));
            $credits = [];
            $recipientTax = Money::of('0');
            $recipientCess = Money::of('0');
            foreach ($items as $line) {
                Item::whereKey($line->item_id)->sole(); // Tenant validation also covers persisted drafts.
                $salesLedger = $this->ledger($line->sales_ledger_id, 'income');
                $rate = '0';
                if ($line->tax_category === 'taxable') {
                    $taxRate = TaxRate::whereKey($line->tax_rate_id)->lockForUpdate()->first();
                    if (!$taxRate || !$taxRate->is_verified || $invoice->date->lt($taxRate->effective_from) ||
                        ($taxRate->effective_to && $invoice->date->gt($taxRate->effective_to))) {
                        throw new DomainException('A verified GST rate effective on the tax date is required.');
                    }
                    $rate = $taxRate->rate;
                }
                $result = $this->calculator->calculate(
                    unitPrice: $line->unit_price, quantity: $line->quantity, rate: $rate, supply: $supply,
                    discount: $line->discount, category: $line->tax_category, reverseCharge: $invoice->reverse_charge,
                    regime: $company->gst_regime, zeroRatedMode: $invoice->zero_rated_mode,
                    zeroRatedEligible: $invoice->zero_rated_eligible, sezAuthorized: $invoice->sez_authorized,
                    cessRate: $taxRate->cess_rate ?? '0',
                );
                $line->fill($result->amounts() + ['rate' => $rate, 'recipient_tax' => $result->recipientTax,
                    'recipient_cess' => $result->recipientCess])->save();
                foreach ($result->amounts() as $field => $amount) {
                    $totals[$field] = $totals[$field]->add($amount);
                }
                $recipientTax = $recipientTax->add($result->recipientTax);
                $recipientCess = $recipientCess->add($result->recipientCess);
                $credits[$salesLedger->id] = ($credits[$salesLedger->id] ?? Money::of('0'))->add($result->taxableAmount);
            }
            if ($totals['total']->isZero()) {
                throw new DomainException('Zero-value invoices need a separate non-accounting document workflow.');
            }
            foreach (['cgst', 'sgst', 'utgst', 'igst', 'cess'] as $tax) {
                if (!$totals[$tax]->isZero()) {
                    $ledger = Ledger::where('code', $tax === 'cess' ? 'OUTPUT_CESS' : 'OUTPUT_'.strtoupper($tax))->sole();
                    $this->ledger($ledger->id, 'liability');
                    $credits[$ledger->id] = ($credits[$ledger->id] ?? Money::of('0'))->add($totals[$tax]);
                }
            }
            $entries = [['ledger_id' => $partyLedger->id, 'debit' => $totals['total'], 'credit' => Money::of('0')]];
            foreach ($credits as $ledgerId => $amount) {
                if (!$amount->isZero()) {
                    $entries[] = ['ledger_id' => $ledgerId, 'debit' => Money::of('0'), 'credit' => $amount];
                }
            }
            $this->assertBalanced($entries);
            $number = $this->numbers->next($year->id, $invoice->series);
            $voucher = Voucher::create([
                'financial_year_id' => $year->id, 'type' => 'sales', 'date' => $invoice->date,
                'series' => $invoice->series, 'number' => $number, 'status' => 'draft',
                'narration' => 'Sales invoice '.$invoice->series.'/'.$number,
            ]);
            foreach ($entries as $position => $entry) {
                $voucher->lines()->create($entry + ['financial_year_id' => $year->id, 'position' => $position + 1]);
            }
            // Validate persisted values too; no reliance on floating point database SUM.
            $this->assertBalanced($voucher->lines()->get()->map(fn ($line) => [
                'debit' => $line->debit, 'credit' => $line->credit,
            ])->all());
            $invoice->fill($totals + [
                'voucher_id' => $voucher->id, 'number' => $number, 'status' => 'posted',
                'recipient_tax' => $recipientTax,
                'recipient_cess' => $recipientCess,
                'supplier_snapshot' => $company->only(['name', 'gstin', 'state_code', 'address', 'gst_regime']),
                'party_snapshot' => $party->only(['name', 'gstin', 'state_code', 'address', 'registration_type']),
            ])->save();
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);
            activity('accounting')->performedOn($voucher)->event('posted')
                ->withProperties(['invoice_id' => $invoice->id, 'total' => (string) $totals['total']])
                ->tap(function ($activity) use ($year): void { $activity->financial_year_id = $year->id; })
                ->log('Sales invoice posted');
            return $voucher->load('lines');
        }, 3);
    }

    public function assertBalanced(array $entries): void
    {
        $debits = $credits = Money::of('0');
        if (count($entries) < 2) {
            throw new DomainException('A voucher needs at least two entries.');
        }
        foreach ($entries as $entry) {
            $debit = $entry['debit'] instanceof Money ? $entry['debit'] : Money::of($entry['debit']);
            $credit = $entry['credit'] instanceof Money ? $entry['credit'] : Money::of($entry['credit']);
            if ($debit->isNegative() || $credit->isNegative() || ($debit->isZero() === $credit->isZero())) {
                throw new DomainException('Each entry must have exactly one positive debit or credit.');
            }
            $debits = $debits->add($debit);
            $credits = $credits->add($credit);
        }
        if (!$debits->equals($credits)) {
            throw new DomainException('Voucher debits do not equal credits.');
        }
    }

    private function ledger(int $id, string $nature): Ledger
    {
        $ledger = Ledger::whereKey($id)->lockForUpdate()->sole();
        $group = LedgerGroup::whereKey($ledger->ledger_group_id)->sole();
        if ($group->nature !== $nature) {
            throw new DomainException('Ledger has an inappropriate accounting nature.');
        }
        return $ledger;
    }
}
