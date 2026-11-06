<?php

namespace App\Domain\Masters\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'uses_utgst' => 'boolean'];
}
