<?php
declare(strict_types=1);
namespace App\Domain\Purchase\Models;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Shared\Casts\MoneyCast;
class PurchaseBillItem extends TenantModel
{
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:3', 'unit_price' => MoneyCast::class,
        'discount' => MoneyCast::class, 'taxable_amount' => MoneyCast::class, 'cgst' => MoneyCast::class,
        'sgst' => MoneyCast::class, 'utgst' => MoneyCast::class, 'igst' => MoneyCast::class,
        'cess' => MoneyCast::class, 'total' => MoneyCast::class];
    public function bill() { return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id'); }
}
