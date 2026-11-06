<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

// Insert-only link between a posted receipt and a customer invoice. Allocations
// never create accounting entries; undo records are separate immutable rows.
class ReceiptAllocation extends TenantModel
{
    protected $casts = ['amount' => MoneyCast::class];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function undoneBy()
    {
        return $this->hasOne(ReceiptAllocationReversal::class, 'allocation_id');
    }
}
