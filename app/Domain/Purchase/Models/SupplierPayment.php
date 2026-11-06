<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class SupplierPayment extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'amount' => MoneyCast::class, 'supplier_snapshot' => 'array'];

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'payment_id');
    }
}
