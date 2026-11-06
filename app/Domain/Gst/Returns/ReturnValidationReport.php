<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns;

/** Export gate: errors block export; warnings require CA review but do not. */
final class ReturnValidationReport
{
    public function forDataset(array $dataset): array
    {
        $errors = $dataset['validation']['errors'] ?? [];
        $warnings = $dataset['validation']['warnings'] ?? [];
        if (! is_array($errors)) {
            $errors = [['code' => 'invalid_dataset_error', 'message' => 'Dataset validation errors are malformed.', 'document_id' => 0, 'severity' => 'error']];
        } elseif (array_filter($errors, fn ($error) => ! is_array($error))) {
            $errors[] = ['code' => 'invalid_dataset_error', 'message' => 'Dataset contains a malformed validation error.', 'document_id' => 0, 'severity' => 'error'];
        }
        foreach ($dataset['b2b'] ?? [] as $document) {
            $gstin = strtoupper(trim((string) ($document['recipient']['gstin'] ?? '')));
            if ($gstin === '') {
                $errors[] = $this->issue('missing_recipient_gstin', 'B2B invoice has no recipient GSTIN.', $document['id']);

                continue;
            }
            if (! app(GstinValidator::class)->isValid($gstin)) {
                $errors[] = $this->issue('invalid_recipient_gstin', 'Recipient GSTIN has an invalid format, state code, or check digit.', $document['id']);
            } else {
                $warnings[] = ['code' => 'gstin_portal_existence_unverified', 'message' => 'GSTIN format and checksum are valid, but registration status/existence has not been verified on the GST portal.', 'document_id' => $document['id'], 'severity' => 'warning'];
            }
        }
        foreach ($dataset['b2cs'] ?? [] as $row) {
            if (! preg_match('/^\d{2}$/', (string) $row['place_of_supply'])) {
                $errors[] = $this->issue('invalid_place_of_supply', 'B2CS place of supply is invalid.', 0);
            }
        }
        if (! config('gst_returns.rules.document_issued_categories.verified')) {
            foreach ($dataset['documents_issued'] ?? [] as $row) {
                if (! in_array($row['nature_of_document'], config('gst_returns.rules.document_issued_categories.verified_values', []), true)) {
                    $errors[] = $this->issue('unverified_document_nature', 'Document-issued category is not yet verified against an official category list.', 0);
                }
            }
        }
        foreach (['b2b', 'b2cl', 'sez'] as $table) {
            foreach ($dataset[$table] ?? [] as $document) {
                if (isset($dataset['meta']['period']) && ! str_starts_with((string) $document['date'], $dataset['meta']['period'])) {
                    $errors[] = $this->issue('document_outside_period', 'Document date is outside the selected period.', $document['id']);
                }
                foreach ($document['items'] ?? [] as $line) {
                    if ($line['tax_category'] === 'taxable' && trim((string) $line['hsn_sac']) === '') {
                        $errors[] = $this->issue('missing_hsn', 'HSN/SAC is missing.', $document['id']);
                    }
                    if ($line['tax_category'] === 'taxable' && trim((string) $line['unit']) === '') {
                        $errors[] = $this->issue('missing_uqc', 'UQC is missing.', $document['id']);
                    }
                }
            }
        }
        foreach (($dataset['exports'] ?? []) as $exportType => $documents) {
            foreach ($documents as $document) {
                if (isset($dataset['meta']['period']) && ! str_starts_with((string) $document['date'], $dataset['meta']['period'])) {
                    $errors[] = $this->issue('document_outside_period', 'Document date is outside the selected period.', $document['id']);
                }
                foreach ($document['items'] ?? [] as $line) {
                    if ($line['tax_category'] === 'taxable' && trim((string) $line['hsn_sac']) === '') {
                        $errors[] = $this->issue('missing_hsn', 'HSN/SAC is missing.', $document['id']);
                    }
                    if ($line['tax_category'] === 'taxable' && trim((string) $line['unit']) === '') {
                        $errors[] = $this->issue('missing_uqc', 'UQC is missing.', $document['id']);
                    }
                }
            }
        }

        return ['valid' => count($errors) === 0, 'errors' => $errors, 'warnings' => $warnings, 'error_count' => count($errors), 'warning_count' => count($warnings)];
    }

    private function issue(string $code, string $message, int $id): array
    {
        return ['code' => $code, 'message' => $message, 'document_id' => $id, 'severity' => 'error'];
    }
}
