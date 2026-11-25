<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\Party;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class Receipt extends TenantModel
{
    protected $casts = [
        'date' => 'immutable_date', 'amount' => MoneyCast::class,
        'party_snapshot' => 'array', 'allocations_snapshot' => 'array',
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function allocations()
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function reversals()
    {
        return $this->hasMany(ReceiptReversal::class, 'original_receipt_id');
    }

    public function advanceReceiptVouchers()
    {
        return $this->hasMany(AdvanceReceiptVoucher::class);
    }

    public function label(): string
    {
        return $this->number !== null ? $this->series.'/'.$this->number : $this->series.'/draft';
    }
}
