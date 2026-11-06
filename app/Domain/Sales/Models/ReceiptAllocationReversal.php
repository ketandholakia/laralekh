<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Shared\Models\TenantModel;

// Insert-only undo record: its existence deactivates exactly one allocation.
class ReceiptAllocationReversal extends TenantModel
{
    public function allocation()
    {
        return $this->belongsTo(ReceiptAllocation::class, 'allocation_id');
    }
}
