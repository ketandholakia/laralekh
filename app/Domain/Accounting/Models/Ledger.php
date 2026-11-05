<?php
namespace App\Domain\Accounting\Models;
use App\Domain\Shared\Models\TenantModel;
class Ledger extends TenantModel
{
    public function group() { return $this->belongsTo(LedgerGroup::class, 'ledger_group_id'); }
}
