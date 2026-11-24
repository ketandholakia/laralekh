<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRefundReversal extends TenantModel
{
    protected $casts = [
        'date' => 'immutable_date',
        'original_snapshot' => 'array',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(CustomerRefund::class, 'original_customer_refund_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
