<?php
declare(strict_types=1);
namespace App\Domain\Purchase\Models;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Shared\Casts\MoneyCast;
class PurchaseBill extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'supplier_bill_date' => 'immutable_date', 'reverse_charge' => 'boolean',
        'supplier_snapshot' => 'array', 'taxable_amount' => MoneyCast::class, 'cgst' => MoneyCast::class,
        'sgst' => MoneyCast::class, 'utgst' => MoneyCast::class, 'igst' => MoneyCast::class,
        'cess' => MoneyCast::class, 'total' => MoneyCast::class];
    public function items() { return $this->hasMany(PurchaseBillItem::class)->orderBy('position'); }
    public function party() { return $this->belongsTo(\App\Domain\Masters\Models\Party::class); }
}
