<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class Item extends TenantModel
{
    protected $casts = ['sale_price' => MoneyCast::class, 'tracks_stock' => 'boolean'];
}
