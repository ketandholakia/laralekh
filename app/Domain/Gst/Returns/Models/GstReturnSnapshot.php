<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns\Models;

use App\Domain\Shared\Models\TenantModel;
use App\Models\User;

final class GstReturnSnapshot extends TenantModel
{
    protected $table = 'gst_return_snapshots';

    protected $casts = ['payload' => 'array', 'generated_at' => 'immutable_datetime'];

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
