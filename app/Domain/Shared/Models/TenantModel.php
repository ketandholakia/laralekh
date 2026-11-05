<?php
declare(strict_types=1);
namespace App\Domain\Shared\Models;

use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TenantModel extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('company_id'), app(TenantContext::class)->id());
        });
        static::saving(function (self $model): void {
            $company = app(TenantContext::class)->id();
            $model->company_id ??= $company;
            if ((int) $model->company_id !== $company ||
                ($model->exists && (int) $model->getOriginal('company_id') !== $company)) {
                throw new LogicException('Cross-company writes are forbidden.');
            }
            if ($model->exists && $model->isDirty('company_id')) {
                throw new LogicException('Company ownership is immutable.');
            }
        });
        static::deleting(function (self $model): void {
            if ((int) $model->company_id !== app(TenantContext::class)->id()) {
                throw new LogicException('Cross-company deletion is forbidden.');
            }
        });
    }
}

