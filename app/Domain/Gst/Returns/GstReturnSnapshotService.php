<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns;

use App\Domain\Gst\Returns\Models\GstReturnSnapshot;
use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class GstReturnSnapshotService
{
    public function generate(Company $company, string $period, string $type, array $payload, ?string $schemaVersion = null): GstReturnSnapshot
    {
        $this->assertScope($company, $period, $type);

        return DB::transaction(function () use ($company, $period, $type, $payload, $schemaVersion) {
            Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $previous = GstReturnSnapshot::query()->where('period', $period)->where('type', $type)->orderByDesc('version')->lockForUpdate()->get();
            $version = ($previous->first()?->version ?? 0) + 1;
            foreach ($previous->whereIn('status', ['draft', 'final']) as $snapshot) {
                $snapshot->update(['status' => 'superseded']);
            }
            $payload = self::canonicalize($payload);

            return GstReturnSnapshot::create(['period' => $period, 'type' => $type, 'schema_version' => $schemaVersion, 'version' => $version,
                'payload' => $payload, 'sha256' => self::hashPayload($payload), 'generated_by' => Auth::id(), 'generated_at' => now(), 'status' => 'draft']);
        });
    }

    public static function hashPayload(array $payload): string
    {
        $canonical = self::canonicalize($payload);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonicalize($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    public function finalize(int $snapshotId): GstReturnSnapshot
    {
        return DB::transaction(function () use ($snapshotId) {
            $snapshot = GstReturnSnapshot::query()->whereKey($snapshotId)->lockForUpdate()->firstOrFail();
            if ($snapshot->status !== 'draft') {
                throw new DomainException('Only a draft return snapshot can be finalized.');
            }
            if (($snapshot->payload['validation']['valid'] ?? true) === false) {
                throw new DomainException('Return snapshot has blocking validation errors.');
            }
            $snapshot->update(['status' => 'final']);

            return $snapshot->fresh();
        });
    }

    private function assertScope(Company $company, string $period, string $type): void
    {
        if ((int) $company->id !== app(TenantContext::class)->id()) {
            throw new DomainException('Return company must match the active tenant.');
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new DomainException('Return period must use YYYY-MM.');
        }
        if (! in_array($type, ['gstr1', 'gstr3b'], true)) {
            throw new DomainException('Unsupported return type.');
        }
    }
}
