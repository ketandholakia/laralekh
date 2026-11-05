<?php
declare(strict_types=1);
namespace App\Domain\Sales\Services;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Models\Invoice;
use Carbon\CarbonImmutable;
use DomainException;

final class CorrectionRules
{
    public function date(mixed $value): CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new DomainException('Use a valid date in YYYY-MM-DD format.');
        }
        try { $date = CarbonImmutable::createFromFormat('!Y-m-d', $value); }
        catch (\Throwable) { throw new DomainException('Invalid calendar date.'); }
        if (!$date || $date->format('Y-m-d') !== $value) { throw new DomainException('Invalid calendar date.'); }
        return $date;
    }

    public function openYear(int $id, string $date, string $originalDate): FinancialYear
    {
        $year = FinancialYear::whereKey($id)->lockForUpdate()->firstOrFail();
        $day = $this->date($date);
        if ($year->is_closed || $day->lt($year->starts_on) || $day->gt($year->ends_on)) {
            throw new DomainException('Correction date must be inside the selected open financial year.');
        }
        if ($day->lt($this->date($originalDate)) || $day->gt(CarbonImmutable::today())) {
            throw new DomainException('Correction date cannot precede the original or be in the future.');
        }
        return $year;
    }

    public function eligibility(Invoice $invoice, array $data): void
    {
        if ($data['treatment'] === 'commercial') { return; }
        if (($invoice->supplier_snapshot['gst_regime'] ?? '') !== 'regular') {
            throw new DomainException('This supplier regime requires a commercial adjustment, not a GST note.');
        }
        if (empty($invoice->supplier_snapshot['gstin']) || empty($invoice->supplier_snapshot['address']) ||
            empty($invoice->party_snapshot['address'])) {
            throw new DomainException('Original supplier GSTIN and supplier/recipient addresses are required for a GST note.');
        }
        if (($invoice->party_snapshot['registration_type'] ?? '') === 'unregistered' && empty($data['delivery_address'])) {
            throw new DomainException('Provide the unregistered recipient delivery address for the GST note.');
        }
        if (!$data['eligibility_confirmed'] || mb_strlen(trim($data['eligibility_evidence'] ?? '')) < 10) {
            throw new DomainException('Document the GST eligibility review, including recipient ITC/tax-incidence conditions and annual-return status.');
        }
        $allowed = $data['kind'] === 'credit' ? ['overcharge', 'goods_return', 'deficiency'] : ['undercharge'];
        if (!in_array($data['reason_code'], $allowed, true)) {
            throw new DomainException('This reason is not supported for a GST note. Discretionary discounts require commercial treatment.');
        }
        if ($data['kind'] !== 'credit') { return; }

        // Section 34(2): reporting, not merely issuance, by the earlier deadline.
        $sourceYear = FinancialYear::whereKey($invoice->financial_year_id)->lockForUpdate()->firstOrFail();
        $deadline = CarbonImmutable::create($sourceYear->ends_on->year, 11, 30)->startOfDay();
        foreach ([$sourceYear->annual_return_filed_on, $data['annual_return_filed_on']] as $filed) {
            if ($filed) {
                $filedDate = $this->date(substr((string) $filed, 0, 10));
                if ($filedDate->gt(CarbonImmutable::today())) { throw new DomainException('Annual-return filing date cannot be in the future.'); }
                if ($filedDate->lt($deadline)) { $deadline = $filedDate; }
            }
        }
        if (!$data['reporting_date']) { throw new DomainException('Provide the intended GST credit-note reporting date.'); }
        $reporting = $this->date($data['reporting_date']);
        if ($reporting->lt($this->date($data['date']))) { throw new DomainException('Reporting cannot precede note issuance.'); }
        if ($reporting->gt($deadline) || CarbonImmutable::today()->gt($deadline)) {
            throw new DomainException('The GST credit-note reporting deadline has passed; review a commercial adjustment.');
        }
    }
}

