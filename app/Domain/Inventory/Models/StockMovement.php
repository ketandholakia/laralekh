<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class StockMovement extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'quantity' => 'decimal:3', 'unit_cost' => MoneyCast::class];
}
