<?php

namespace App\Domain\Gst\Models;

use App\Domain\Shared\Models\TenantModel;

class TaxRate extends TenantModel
{
    protected $casts = ['rate' => 'decimal:3', 'cess_rate' => 'decimal:3', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date', 'is_verified' => 'boolean'];
}
