<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// Creates or updates a receipt draft. Drafts allocate no number and create no
// accounting entries; company and year come from the authorized session.
final class SaveReceiptDraft
{
    public function handle(?int $receiptId, array $input): Receipt
    {
        $session = app(CompanySession::class);
        $yearId = $session->financialYearId()
            ?? throw ValidationException::withMessages(['date' => 'Select a financial year first.']);
        try {
            $year = FinancialYear::whereKey($yearId)->sole();
            $data = [
                'party_id' => (int) ($input['party_id'] ?? 0),
                'ledger_id' => (int) ($input['ledger_id'] ?? 0),
                'date' => (string) ($input['date'] ?? ''),
                'payment_reference' => ($input['payment_reference'] ?? null) !== null
                    ? mb_substr(trim((string) $input['payment_reference']), 0, 60) ?: null : null,
                'narration' => ($input['narration'] ?? null) !== null
                    ? mb_substr(trim((string) $input['narration']), 0, 2000) ?: null : null,
                'amount' => (string) ($input['amount'] ?? ''),
            ];
            $party = Party::whereKey($data['party_id'])->firstOrFail();
            if ($party->ledger_id === null) {
                throw new \DomainException('The customer needs a receivable ledger before receipts can be recorded.');
            }
            $ledger = Ledger::whereKey($data['ledger_id'])->firstOrFail();
            $group = LedgerGroup::whereKey($ledger->ledger_group_id)->sole();
            if ($group->nature !== 'asset' || $group->code !== 'CASH_BANK') {
                throw new \DomainException('Receipts must use a cash or bank ledger.');
            }
            if (Carbon::createFromFormat('Y-m-d', $data['date']) === false) {
                throw new \DomainException('Provide a valid receipt date.');
            }
            if ($year->is_closed) {
                throw new \DomainException('The selected financial year is closed.');
            }
            $amount = Money::of($data['amount']);
            if ($amount->isZero() || $amount->isNegative()) {
                throw new \DomainException('A receipt needs a positive amount.');
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($receiptId, $data, $year, $input): Receipt {
            $receipt = $receiptId === null
                ? new Receipt(['financial_year_id' => $year->id,
                    'request_key' => (string) ($input['request_key'] ?? Str::uuid())])
                : Receipt::whereKey($receiptId)->lockForUpdate()->firstOrFail();
            if ($receipt->exists && ($receipt->status !== 'draft' || $receipt->number !== null)) {
                throw new \DomainException('Only unposted draft receipts can be edited.');
            }
            if (! $receipt->exists
                && Receipt::query()->where('request_key', $receipt->request_key)->lockForUpdate()->first()) {
                throw new \DomainException('This request was already saved. Open the existing receipt.');
            }
            $receipt->fill($data + ['financial_year_id' => $year->id, 'series' => 'SR'])->save();

            return $receipt;
        });
    }
}
