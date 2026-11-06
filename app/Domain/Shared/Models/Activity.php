<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::addGlobalScope('company', fn (Builder $q) => $q->where($q->qualifyColumn('company_id'), app(TenantContext::class)->id()));
        static::creating(function (self $activity): void {
            $activity->company_id ??= app(TenantContext::class)->id();
            if ((int) $activity->company_id !== app(TenantContext::class)->id()) {
                throw new LogicException('Cross-company activity is forbidden.');
            }
        });
        static::updating(fn () => throw new LogicException('Audit entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit entries are immutable.'));
    }
}
