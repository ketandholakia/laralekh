<?php

declare(strict_types=1);

namespace App\Domain\Gst\Returns\Models;

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Gstr2bImport extends TenantModel
{
    protected $table = 'gstr2b_imports';

    protected $casts = [
        'summary' => 'array',
        'records' => 'array',
        'imported_at' => 'immutable_datetime',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
