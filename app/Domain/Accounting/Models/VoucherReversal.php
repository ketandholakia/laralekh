<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Domain\Shared\Models\TenantModel;

class VoucherReversal extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'original_snapshot' => 'array', 'entries_snapshot' => 'array'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function originalVoucher()
    {
        return $this->belongsTo(Voucher::class, 'original_voucher_id');
    }
}
