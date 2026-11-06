<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Shared\Models\TenantModel;

class ReceiptReversal extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'original_snapshot' => 'array', 'undone_allocations' => 'array'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function originalReceipt()
    {
        return $this->belongsTo(Receipt::class, 'original_receipt_id');
    }
}
