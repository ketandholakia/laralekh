<?php
namespace App\Domain\Accounting\Models;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Shared\Casts\MoneyCast;
class VoucherLine extends TenantModel
{
    protected $casts = ['debit' => MoneyCast::class, 'credit' => MoneyCast::class];
    public function voucher() { return $this->belongsTo(Voucher::class); }
    public function ledger() { return $this->belongsTo(Ledger::class); }
}

