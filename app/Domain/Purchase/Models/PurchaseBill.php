<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Models;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\Party;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class PurchaseBill extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'supplier_bill_date' => 'immutable_date', 'cancelled_at' => 'immutable_datetime', 'reverse_charge' => 'boolean',
        'supplier_snapshot' => 'array', 'taxable_amount' => MoneyCast::class, 'cgst' => MoneyCast::class,
        'sgst' => MoneyCast::class, 'utgst' => MoneyCast::class, 'igst' => MoneyCast::class,
        'cess' => MoneyCast::class, 'total' => MoneyCast::class];

    public function items()
    {
        return $this->hasMany(PurchaseBillItem::class)->orderBy('position');
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function debitNotes()
    {
        return $this->hasMany(PurchaseDebitNote::class)->orderBy('date');
    }
}
