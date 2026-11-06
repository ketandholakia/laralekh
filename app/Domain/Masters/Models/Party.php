<?php

namespace App\Domain\Masters\Models;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Shared\Models\TenantModel;

class Party extends TenantModel
{
    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }
}
