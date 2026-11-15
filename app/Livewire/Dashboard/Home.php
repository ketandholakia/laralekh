<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Gst\Returns\Models\GstReturnFiling;
use App\Domain\Gst\Returns\Models\GstReturnSnapshot;
use App\Domain\Masters\Models\Company;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptAllocation;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Accounting landing page for the session company. Read-only aggregates over
// the existing posted documents; every query inherits the active tenant scope
// entered by the company.context middleware. No calculations beyond simple
// sums of stored document totals happen here — GST math stays in its services.
//
// Financial aggregates are computed only for users holding the matching view
// permission; every other member sees an empty, permission-neutral page.
#[Layout('layouts.app')]
#[Title('Dashboard')]
final class Home extends Component
{
    public function render(): View
    {
        $companyId = app(CompanySession::class)->companyId();
        $user = auth()->user();
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        // Posted, uncancelled documents of the current month.
        $salesMonth = $user->can('invoices.view') ? (float) Invoice::query()->where('status', 'posted')->whereNull('cancelled_at')
            ->whereBetween('date', [$monthStart, $monthEnd])->sum('total') : null;
        $purchasesMonth = $user->can('purchases.view') ? (float) PurchaseBill::query()->where('status', 'posted')->whereNull('cancelled_at')
            ->whereBetween('date', [$monthStart, $monthEnd])->sum('total') : null;
        $receiptsMonth = $user->can('receipts.view') ? (float) Receipt::query()->where('status', 'posted')->whereDoesntHave('reversals')
            ->whereBetween('date', [$monthStart, $monthEnd])->sum('amount') : null;

        $recentInvoices = $user->can('invoices.view')
            ? Invoice::query()->where('status', 'posted')->whereNull('cancelled_at')
                ->with('party:id,name')->orderByDesc('date')->orderByDesc('id')->limit(5)->get(['id', 'number', 'series', 'date', 'total', 'status', 'party_id'])
            : collect();

        // Receivables follow the SettlementService outstanding definition.
        [$receivables, $excessCredits] = $user->can('receipts.view')
            ? $this->receivablesSummary() : [null, null];

        $latestFilings = $user->can('returns.view') ? GstReturnFiling::query()->orderByDesc('period')->orderBy('type')->limit(4)->get() : collect();
        $latestDrafts = $user->can('returns.view') ? GstReturnSnapshot::query()->where('status', 'draft')->orderByDesc('period')->limit(2)->get() : collect();

        return view('livewire.dashboard.home', [
            'company' => Company::query()->findOrFail($companyId),
            'salesMonth' => $salesMonth,
            'purchasesMonth' => $purchasesMonth,
            'receiptsMonth' => $receiptsMonth,
            'receivables' => $receivables,
            'excessCredits' => $excessCredits,
            'recentInvoices' => $recentInvoices,
            'latestFilings' => $latestFilings,
            'latestDrafts' => $latestDrafts,
            'monthLabel' => CarbonImmutable::now()->format('F Y'),
        ]);
    }

    /**
     * Company-wide receivables. Read-only, non-locking mirror of the
     * authoritative SettlementService semantics (documentOutstanding +
     * customerSummary aggregation): per posted invoice, posted credit notes
     * reduce and posted debit notes increase the balance, active (not undone)
     * receipt allocations reduce it, and invoices whose original voucher was
     * internally reversed contribute nothing. Positive balances are
     * receivables; negative ones are excess customer credits, reported
     * separately and never folded into the receivable figure. Exact decimal
     * arithmetic throughout (Money), set-based queries (no N+1), tenant scope
     * inherited from the active context.
     *
     * @return array{0: Money, 1: Money} [receivables, excessCredits]
     */
    private function receivablesSummary(): array
    {
        $invoices = Invoice::query()->where('status', 'posted')->get(['id', 'total', 'voucher_id']);

        $credits = SalesCorrection::query()->where('status', 'posted')->where('kind', 'credit')
            ->get(['invoice_id', 'total'])->groupBy('invoice_id');
        $debits = SalesCorrection::query()->where('status', 'posted')->where('kind', 'debit')
            ->get(['invoice_id', 'total'])->groupBy('invoice_id');
        $activeAllocations = ReceiptAllocation::query()->whereDoesntHave('undoneBy')
            ->get(['invoice_id', 'amount'])->groupBy('invoice_id');
        $reversedVouchers = VoucherReversal::query()
            ->whereIn('original_voucher_id', $invoices->pluck('voucher_id')->filter()->unique())
            ->pluck('original_voucher_id');

        $receivables = Money::of('0');
        $excessCredits = Money::of('0');
        foreach ($invoices as $invoice) {
            if ($invoice->voucher_id !== null && $reversedVouchers->contains($invoice->voucher_id)) {
                continue;
            }
            $outstanding = $invoice->total;
            foreach ($credits->get($invoice->id, collect()) as $note) {
                $outstanding = $outstanding->subtract($note->total);
            }
            foreach ($debits->get($invoice->id, collect()) as $note) {
                $outstanding = $outstanding->add($note->total);
            }
            foreach ($activeAllocations->get($invoice->id, collect()) as $allocation) {
                $outstanding = $outstanding->subtract($allocation->amount);
            }
            if ($outstanding->isNegative()) {
                // Excess customer credit: surfaced separately, never absorbed.
                $excessCredits = $excessCredits->add(Money::of(bcmul((string) $outstanding, '-1', 2)));
            } else {
                $receivables = $receivables->add($outstanding);
            }
        }

        return [$receivables, $excessCredits];
    }
}
