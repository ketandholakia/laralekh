<?php
namespace App\Domain\Masters\Models;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Accounting\Models\Ledger;
class Party extends TenantModel
{
    public function ledger() { return $this->belongsTo(Ledger::class); }
}

