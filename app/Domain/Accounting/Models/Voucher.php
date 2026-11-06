<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Shared\Models\TenantModel;

class Voucher extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'posted_at' => 'immutable_datetime'];

    public function lines()
    {
        return $this->hasMany(VoucherLine::class);
    }
}
