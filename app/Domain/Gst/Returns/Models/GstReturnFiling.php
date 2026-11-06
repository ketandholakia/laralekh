<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns\Models;

use App\Domain\Shared\Models\TenantModel;
use App\Models\User;

final class GstReturnFiling extends TenantModel
{
    protected $table='gst_return_filings';
    protected $casts=['filed_on'=>'immutable_date'];
    public function snapshot(){return $this->belongsTo(GstReturnSnapshot::class,'snapshot_id');}
    public function marker(){return $this->belongsTo(User::class,'marked_by');}
}
