<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Support\Facades\DB;

class Item extends TenantModel
{
    protected $casts = [
        'sale_price' => MoneyCast::class,
        'tracks_stock' => 'boolean',
    ];

    public function isUsed(): bool
    {
        return DB::table('invoice_items')->where('item_id', $this->id)->exists()
            || DB::table('purchase_bill_items')->where('item_id', $this->id)->exists()
            || DB::table('stock_movements')->where('item_id', $this->id)->exists();
    }
}
