<?php

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\Item;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class InvoiceItem extends TenantModel
{
    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => MoneyCast::class, 'discount' => MoneyCast::class,
        'taxable_amount' => MoneyCast::class, 'cgst' => MoneyCast::class, 'sgst' => MoneyCast::class, 'cess' => MoneyCast::class,
        'utgst' => MoneyCast::class, 'igst' => MoneyCast::class, 'total' => MoneyCast::class, 'recipient_tax' => MoneyCast::class,
        'recipient_cess' => MoneyCast::class, 'rate' => 'decimal:3'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
