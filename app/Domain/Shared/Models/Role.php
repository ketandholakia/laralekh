<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected static function booted(): void
    {
        static::addGlobalScope('company', fn (Builder $query) => $query->where($query->qualifyColumn('company_id'), app(TenantContext::class)->id()));
        static::saving(function (self $role): void {
            $role->company_id ??= app(TenantContext::class)->id();
            if ((int) $role->company_id !== app(TenantContext::class)->id() || ($role->exists && $role->isDirty('company_id'))) {
                throw new LogicException('Cross-company roles are forbidden.');
            }
        });
        static::deleting(function (self $role): void {
            if ((int) $role->company_id !== app(TenantContext::class)->id()) {
                throw new LogicException('Cross-company role deletion is forbidden.');
            }
        });
    }

    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
    }
}
