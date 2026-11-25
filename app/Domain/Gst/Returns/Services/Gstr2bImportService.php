<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns\Services;

use App\Domain\Gst\Returns\Models\Gstr2bImport;
use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class Gstr2bImportService
{
    public function __construct(
        private readonly Gstr2bParser $parser,
        private readonly Gstr2bReconciler $reconciler,
    ) {}

    public function import(Company $company, string $period, string $content, string $filename, ?int $userId = null): Gstr2bImport
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new DomainException('Return period must use YYYY-MM.');
        }

        if ((int) $company->id !== app(TenantContext::class)->id()) {
            throw new DomainException('Company must match the active tenant context.');
        }

        $parsed = $this->parser->parse($content, $filename);
        $reconciliation = $this->reconciler->reconcile($company, $period, $parsed['documents']);

        return DB::transaction(function () use ($company, $period, $filename, $parsed, $reconciliation, $userId): Gstr2bImport {
            $import = Gstr2bImport::query()
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $data = [
                'company_id' => $company->id,
                'period' => $period,
                'source_filename' => $filename,
                'file_format' => $parsed['format'],
                'gstin' => $parsed['gstin'] ?? $company->gstin,
                'total_documents' => count($parsed['documents']),
                'summary' => $reconciliation,
                'records' => $reconciliation['records'],
                'imported_by' => $userId,
                'imported_at' => now(),
            ];

            if ($import) {
                $import->update($data);

                return $import->fresh();
            }

            return Gstr2bImport::create($data);
        });
    }
}
