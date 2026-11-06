<?php

namespace App\Domain\Masters\Models;

use App\Domain\Shared\Models\TenantModel;

class FinancialYear extends TenantModel
{
    protected $casts = ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'is_closed' => 'boolean'];
}
