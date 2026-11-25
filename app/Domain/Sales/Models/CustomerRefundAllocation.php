<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRefundAllocation extends TenantModel
{
    protected $casts = [
        'amount' => MoneyCast::class,
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(CustomerRefund::class, 'customer_refund_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
