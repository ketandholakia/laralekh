<?php
declare(strict_types=1);
namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\{Voucher, VoucherReversal};
use App\Domain\Sales\Models\{Invoice, SalesCorrection};
use App\Domain\Sales\Services\CorrectionRules;
use Illuminate\Support\Facades\DB;
use DomainException;

final class ReversalService
{
    public function reverse(int $voucherId, int $yearId, string $date, string $reason): VoucherReversal
    {
        return DB::transaction(function () use ($voucherId, $yearId, $date, $reason) {
            // Same first lock as note posting: a note and a reversal cannot both pass eligibility.
            $invoice = Invoice::where('voucher_id', $voucherId)->lockForUpdate()->first();
            $original = Voucher::whereKey($voucherId)->lockForUpdate()->firstOrFail();
            if ($original->status !== 'posted' || in_array($original->type, ['credit_note', 'debit_note', 'reversal'], true) ||
                $original->reverses_voucher_id || Voucher::where('reverses_voucher_id', $original->id)->exists() ||
                VoucherReversal::where('original_voucher_id', $original->id)->exists()) {
                throw new DomainException('Only an unreversed original posted voucher can be reversed.');
            }
            if ($invoice && SalesCorrection::where('invoice_id', $invoice->id)->where('status', 'posted')->exists()) {
                throw new DomainException('An invoice with posted correction notes cannot be fully reversed.');
            }
            if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) { throw new DomainException('Provide a reversal reason of 5 to 2000 characters.'); }
            app(CorrectionRules::class)->openYear($yearId, $date, $original->date->format('Y-m-d'));
            $entries = $original->lines()->orderBy('position')->with('ledger')->get()->map(fn ($line) => [
                'ledger_id' => $line->ledger_id, 'debit' => $line->credit, 'credit' => $line->debit,
                'ledger_code' => $line->ledger->code, 'ledger_name' => $line->ledger->name,
            ])->all();
            app(PostingService::class)->assertBalanced($entries);
            $number = app(VoucherNumberAllocator::class)->next($yearId, 'RV');
            $voucher = Voucher::create(['financial_year_id' => $yearId, 'type' => 'reversal', 'date' => $date,
                'series' => 'RV', 'number' => $number, 'reverses_voucher_id' => $original->id, 'narration' => $reason]);
            foreach ($entries as $index => $entry) {
                $voucher->lines()->create(['financial_year_id' => $yearId, 'position' => $index + 1,
                    'ledger_id' => $entry['ledger_id'], 'debit' => $entry['debit'], 'credit' => $entry['credit']]);
            }
            app(PostingService::class)->assertBalanced($voucher->lines()->get()->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all());
            $reversal = VoucherReversal::create(['financial_year_id' => $yearId, 'original_financial_year_id' => $original->financial_year_id,
                'original_voucher_id' => $original->id, 'voucher_id' => $voucher->id, 'date' => $date, 'reason' => $reason,
                'original_snapshot' => ['number' => $original->series.'/'.$original->number, 'date' => $original->date->format('Y-m-d'),
                    'type' => $original->type, 'supplier' => $invoice?->supplier_snapshot, 'party' => $invoice?->party_snapshot],
                'entries_snapshot' => array_map(fn ($e) => $e + [], $entries)]);
            $voucher->update(['status' => 'posted', 'posted_at' => now()]);
            $reversal->update(['status' => 'posted']);
            activity('accounting')->performedOn($reversal)->event('posted')
                ->withProperties(['original_voucher_id' => $original->id])
                ->tap(fn ($a) => $a->financial_year_id = $yearId)->log('Internal accounting reversal posted; not a GST credit note');
            return $reversal->fresh('voucher');
        }, 3);
    }
}

