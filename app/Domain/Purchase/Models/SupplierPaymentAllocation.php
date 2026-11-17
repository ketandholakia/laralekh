<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class SupplierPaymentAllocation extends TenantModel
{
    protected $casts = ['amount' => MoneyCast::class];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'payment_id');
    }

    public function purchaseBill()
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }
}
