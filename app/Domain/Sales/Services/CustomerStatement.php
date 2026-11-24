<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\CustomerRefund;
use App\Domain\Sales\Models\CustomerRefundReversal;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\ReceiptReversal;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Carbon;

// As-of customer statement. Opening and closing balances come from the
// customer's receivable ledger (authoritative); the dated rows list documents
// whose document date falls inside the window. Allocations are never rows, so
// nothing is double counted. Backdated entries appear on their document date;
// the statement is a faithful as-of view, not a creation-date audit.
final class CustomerStatement
{
    private const KIND_ORDER = ['invoice' => 1, 'debit_note' => 2, 'receipt_reversal' => 3,
        'customer_refund' => 4, 'receipt' => 5, 'credit_note' => 6, 'invoice_reversal' => 7,
        'customer_refund_reversal' => 8];

    public function __construct(private SettlementService $settlement) {}

    /**
     * @return array{party: Party, from: string, as_of: string, opening: Money,
     *     rows: list<array<string, mixed>>, closing: Money, ledger_as_of: Money}
     */
    public function build(int $partyId, string $from, string $asOf): array
    {
        app(TenantContext::class)->id();
        $party = Party::query()->whereKey($partyId)->firstOrFail();
        $start = Carbon::createFromFormat('Y-m-d', $from)
            ?? throw new DomainException('Provide a valid statement start date.');
        $end = Carbon::createFromFormat('Y-m-d', $asOf)
            ?? throw new DomainException('Provide a valid as-of date.');
        if ($end->lt($start)) {
            throw new DomainException('The as-of date cannot precede the statement start date.');
        }
        $opening = $this->settlement->ledgerBalance($party->ledger_id, $from);

        $rows = [];
        foreach ($this->invoices($partyId, $from, $asOf) as $row) {
            $rows[] = $row;
        }
        usort($rows, function (array $a, array $b): int {
            return [$a['date'], self::KIND_ORDER[$a['kind']], $a['id']]
                <=> [$b['date'], self::KIND_ORDER[$b['kind']], $b['id']];
        });
        $running = $opening;
        foreach ($rows as $index => $row) {
            $running = $running->add(Money::of($row['debit']))->subtract(Money::of($row['credit']));
            $rows[$index]['balance'] = (string) $running;
        }
        $ledgerAsOf = $this->settlement->ledgerBalance($party->ledger_id, null, $asOf);

        return [
            'party' => $party, 'from' => $from, 'as_of' => $asOf,
            'opening' => $opening, 'rows' => $rows, 'closing' => $running,
            'ledger_as_of' => $ledgerAsOf,
        ];
    }

    /** Debits increase what the customer owes; credits decrease it. */
    private function invoices(int $partyId, string $from, string $asOf): array
    {
        $rows = [];
        foreach (Invoice::query()->where('party_id', $partyId)->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())->orderBy('id')->get() as $invoice) {
            $rows[] = $this->row('invoice', $invoice->id, $invoice->date->format('Y-m-d'),
                'Invoice '.$invoice->series.'/'.$invoice->number, (string) $invoice->total, '0.00');
        }
        foreach (SalesCorrection::query()->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())->whereIn('invoice_id', $this->invoiceIds($partyId))
            ->orderBy('id')->get() as $note) {
            $rows[] = $this->row($note->kind === 'credit' ? 'credit_note' : 'debit_note', $note->id,
                $note->date->format('Y-m-d'), ucfirst($note->label()).' '.$note->series.'/'.$note->number
                    .' (invoice '.$note->invoice->series.'/'.$note->invoice->number.')',
                $note->kind === 'credit' ? '0.00' : (string) $note->total,
                $note->kind === 'credit' ? (string) $note->total : '0.00');
        }
        foreach (Receipt::query()->where('party_id', $partyId)->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())->orderBy('id')->get() as $receipt) {
            $rows[] = $this->row('receipt', $receipt->id, $receipt->date->format('Y-m-d'),
                'Receipt '.$receipt->label(), '0.00', (string) $receipt->amount);
        }
        foreach (ReceiptReversal::query()->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())->orderBy('id')->get() as $reversal) {
            $rows[] = $this->row('receipt_reversal', $reversal->id, $reversal->date->format('Y-m-d'),
                'Receipt reversal (of '.$reversal->original_snapshot['number'].')',
                (string) $reversal->original_snapshot['amount'], '0.00');
        }
        foreach (VoucherReversal::query()->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())->orderBy('id')->get() as $reversal) {
            $invoice = Invoice::query()->where('voucher_id', $reversal->original_voucher_id)->first();
            if ($invoice === null || $invoice->party_id !== $partyId) {
                continue; // not a customer invoice reversal
            }
            // The reversal swaps the original entries; the customer-side credit
            // is the first swapped entry and equals the reversed invoice total.
            $rows[] = $this->row('invoice_reversal', $reversal->id, $reversal->date->format('Y-m-d'),
                'Invoice reversal (of '.$reversal->original_snapshot['number'].')',
                '0.00', (string) ($reversal->entries_snapshot[0]['credit'] ?? '0.00'));
        }
        foreach (CustomerRefund::query()->where('party_id', $partyId)->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())->orderBy('id')->get() as $refund) {
            $rows[] = $this->row('customer_refund', $refund->id, $refund->date->format('Y-m-d'),
                'Customer refund '.$refund->label().($refund->payment_reference ? ' (ref '.$refund->payment_reference.')' : ''),
                (string) $refund->amount, '0.00');
        }
        foreach (CustomerRefundReversal::query()->where('status', 'posted')
            ->where('date', '>=', $from)
            ->where('date', '<', Carbon::parse($asOf)->addDay()->toDateString())
            ->whereHas('refund', fn ($q) => $q->where('party_id', $partyId))
            ->orderBy('id')->get() as $reversal) {
            $rows[] = $this->row('customer_refund_reversal', $reversal->id, $reversal->date->format('Y-m-d'),
                'Customer refund reversal (of '.($reversal->original_snapshot['number'] ?? '').')',
                '0.00', (string) ($reversal->original_snapshot['amount'] ?? '0.00'));
        }

        return $rows;
    }

    private function invoiceIds(int $partyId): array
    {
        return Invoice::query()->where('party_id', $partyId)->pluck('id')->all();
    }

    private function row(string $kind, int $id, string $date, string $description, string $debit, string $credit): array
    {
        return ['kind' => $kind, 'id' => $id, 'date' => $date, 'description' => $description,
            'debit' => $debit, 'credit' => $credit];
    }
}
