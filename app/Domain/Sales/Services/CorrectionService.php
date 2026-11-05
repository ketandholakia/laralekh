<?php
declare(strict_types=1);
namespace App\Domain\Sales\Services;

use App\Domain\Accounting\Models\{Ledger, LedgerGroup, Voucher, VoucherReversal};
use App\Domain\Accounting\Services\{PostingService, VoucherNumberAllocator};
use App\Domain\Sales\Models\{Invoice, SalesCorrection};
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\{DB, Validator};
use DomainException;

final class CorrectionService
{
    public function __construct(private CorrectionRules $rules, private CorrectionCalculator $calculator) {}

    public function preview(int $invoiceId, string $kind, string $treatment, array $lines): array
    {
        if (!in_array($kind, ['credit', 'debit'], true) || !in_array($treatment, ['gst', 'commercial'], true)) {
            throw new DomainException('Choose a valid correction kind and treatment.');
        }
        $invoice = Invoice::whereKey($invoiceId)->firstOrFail();
        $this->assertOriginal($invoice);
        return $this->calculator->calculate($invoice, $kind, $treatment, $lines);
    }

    public function saveDraft(?int $id, int $invoiceId, int $yearId, array $input, array $lines): SalesCorrection
    {
        return DB::transaction(function () use ($id, $invoiceId, $yearId, $input, $lines) {
            $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            $this->assertOriginal($invoice);
            $note = $id ? SalesCorrection::whereKey($id)->lockForUpdate()->firstOrFail() : new SalesCorrection;
            if ($note->exists && ($note->status !== 'draft' || $note->invoice_id !== $invoiceId || $note->financial_year_id !== $yearId)) {
                throw new DomainException('Only a draft in its original selected company/year can be edited.');
            }
            if (!$note->exists && SalesCorrection::where('request_key', $input['request_key'] ?? '')->lockForUpdate()->first()) {
                throw new DomainException('This request was already saved. Open the existing correction.');
            }
            [$data, $calculation] = $this->prepare($invoice, $yearId, $input, $lines);
            if ($note->exists) { unset($data['request_key']); }
            $note->fill($data)->save();
            $this->replaceLines($note, $calculation['lines']);
            return $note->fresh('lines');
        }, 3);
    }

    public function post(int $id): SalesCorrection
    {
        return DB::transaction(function () use ($id) {
            $sourceId = SalesCorrection::whereKey($id)->firstOrFail()->invoice_id;
            $invoice = Invoice::whereKey($sourceId)->lockForUpdate()->firstOrFail();
            $note = SalesCorrection::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($note->status !== 'draft' || $note->invoice_id !== $sourceId || $note->voucher_id) {
                throw new DomainException('Correction is already posted or its source changed.');
            }
            $this->assertOriginal($invoice);
            $input = $note->toArray();
            foreach (['date', 'reporting_date', 'annual_return_filed_on'] as $field) {
                $input[$field] = $note->$field?->format('Y-m-d');
            }
            $lines = $note->lines()->orderBy('position')->lockForUpdate()->get()->map(fn ($line) => [
                'invoice_item_id' => $line->invoice_item_id, 'quantity' => $line->quantity, 'amount' => (string) $line->taxable_amount,
            ])->all();
            [$data, $calculation] = $this->prepare($invoice, $note->financial_year_id, $input, $lines);
            $note->fill($data)->save();
            $this->replaceLines($note, $calculation['lines']);
            $entries = $this->entries($invoice, $note, $calculation);
            app(PostingService::class)->assertBalanced($entries);
            $number = app(VoucherNumberAllocator::class)->next($note->financial_year_id, $note->series);
            $voucher = Voucher::create(['financial_year_id' => $note->financial_year_id, 'type' => $note->kind.'_note',
                'date' => $note->date, 'series' => $note->series, 'number' => $number, 'narration' => $note->label().': '.$note->reason]);
            foreach ($entries as $index => $entry) {
                $voucher->lines()->create($entry + ['financial_year_id' => $note->financial_year_id, 'position' => $index + 1]);
            }
            app(PostingService::class)->assertBalanced($voucher->lines()->get()->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all());
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);
            $note->update(['voucher_id' => $voucher->id, 'number' => $number, 'status' => 'posted']);
            activity('accounting')->performedOn($note)->event('posted')
                ->withProperties(['original_invoice_id' => $invoice->id, 'treatment' => $note->treatment])
                ->tap(fn ($a) => $a->financial_year_id = $note->financial_year_id)->log('Sales correction posted');
            return $note->fresh(['lines', 'voucher']);
        }, 3);
    }

    private function assertOriginal(Invoice $invoice): void
    {
        if ($invoice->status !== 'posted' || !$invoice->voucher_id) { throw new DomainException('Corrections require a posted original invoice.'); }
        if (VoucherReversal::where('original_voucher_id', $invoice->voucher_id)->lockForUpdate()->first() ||
            Voucher::where('reverses_voucher_id', $invoice->voucher_id)->lockForUpdate()->first()) {
            throw new DomainException('The original voucher is reversed; further notes are not allowed.');
        }
    }

    private function prepare(Invoice $invoice, int $yearId, array $input, array $lines): array
    {
        $data = Validator::make($input, [
            'kind' => 'required|in:credit,debit', 'treatment' => 'required|in:gst,commercial',
            'date' => 'required|string', 'reason_code' => 'required|in:overcharge,goods_return,deficiency,undercharge,commercial',
            'reason' => 'required|string|min:5|max:2000', 'request_key' => 'required|uuid',
            'eligibility_confirmed' => 'sometimes|boolean', 'eligibility_evidence' => 'nullable|string|max:2000',
            'reporting_date' => 'nullable|string', 'annual_return_filed_on' => 'nullable|string',
            'delivery_address' => 'nullable|string|max:2000',
        ])->validate();
        $data += ['eligibility_confirmed' => false, 'eligibility_evidence' => null, 'reporting_date' => null,
            'annual_return_filed_on' => null, 'delivery_address' => null];
        $data['eligibility_confirmed'] = (bool) $data['eligibility_confirmed'];
        foreach (['reporting_date', 'annual_return_filed_on'] as $field) {
            $data[$field] = empty($data[$field]) ? null : $this->rules->date($data[$field])->format('Y-m-d');
        }
        $this->rules->openYear($yearId, $data['date'], $invoice->date->format('Y-m-d'));
        $this->rules->eligibility($invoice, $data);
        $calculation = $this->calculator->calculate($invoice, $data['kind'], $data['treatment'], $lines);
        $data += ['financial_year_id' => $yearId, 'original_financial_year_id' => $invoice->financial_year_id,
            'invoice_id' => $invoice->id, 'series' => ($data['treatment'] === 'commercial' ? 'C' : '').($data['kind'] === 'credit' ? 'CN' : 'DN'),
            'supplier_snapshot' => $invoice->supplier_snapshot, 'party_snapshot' => $invoice->party_snapshot,
            'original_snapshot' => ['invoice_id' => $invoice->id, 'number' => $invoice->series.'/'.$invoice->number,
                'date' => $invoice->date->format('Y-m-d'), 'place_of_supply' => $invoice->place_of_supply,
                'supply_type' => $invoice->supply_type, 'reverse_charge' => $invoice->reverse_charge, 'zero_rated_mode' => $invoice->zero_rated_mode],
        ];
        return [$data + $calculation['totals'], $calculation];
    }

    private function replaceLines(SalesCorrection $note, array $rows): void
    {
        $note->lines()->delete();
        foreach ($rows as $row) {
            $note->lines()->create($row + ['financial_year_id' => $note->financial_year_id,
                'original_financial_year_id' => $note->original_financial_year_id, 'invoice_id' => $note->invoice_id]);
        }
    }

    private function entries(Invoice $invoice, SalesCorrection $note, array $calculation): array
    {
        $original = $invoice->voucher->lines()->with('ledger')->get();
        $partyLines = $original->filter(fn ($l) => !$l->debit->isZero());
        if ($partyLines->count() !== 1) { throw new DomainException('Original sales voucher does not have one party debit.'); }
        $partyId = $partyLines->first()->ledger_id;
        $this->assertLedger($partyId, 'asset');
        $amounts = [];
        foreach ($calculation['lines'] as $line) {
            $id = $line['sales_ledger_id'];
            $this->assertLedger($id, 'income');
            if (!$original->contains(fn ($l) => $l->ledger_id === $id && !$l->credit->isZero())) {
                throw new DomainException('Sales ledger is absent from the original voucher.');
            }
            $amounts[$id] = ($amounts[$id] ?? Money::of('0'))->add($line['taxable_amount']);
        }
        foreach (['cgst', 'sgst', 'utgst', 'igst'] as $tax) {
            $amount = $calculation['totals'][$tax];
            if ($amount->isZero()) { continue; }
            $line = $original->first(fn ($l) => $l->ledger->code === 'OUTPUT_'.strtoupper($tax));
            if (!$line) { throw new DomainException('Original output tax ledger cannot be resolved; review its classification.'); }
            $this->assertLedger($line->ledger_id, 'liability');
            $amounts[$line->ledger_id] = ($amounts[$line->ledger_id] ?? Money::of('0'))->add($amount);
        }
        $credit = $note->kind === 'credit'; $zero = Money::of('0');
        $entries = [['ledger_id' => $partyId, 'debit' => $credit ? $zero : $note->total, 'credit' => $credit ? $note->total : $zero]];
        foreach ($amounts as $id => $amount) {
            if (!$amount->isZero()) { $entries[] = ['ledger_id' => $id, 'debit' => $credit ? $amount : $zero, 'credit' => $credit ? $zero : $amount]; }
        }
        return $entries;
    }

    private function assertLedger(int $id, string $nature): void
    {
        $ledger = Ledger::whereKey($id)->lockForUpdate()->firstOrFail();
        if (LedgerGroup::whereKey($ledger->ledger_group_id)->firstOrFail()->nature !== $nature) {
            throw new DomainException('An original ledger has an incompatible accounting nature.');
        }
    }
}

