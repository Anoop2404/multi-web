<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestSportsAgeGroupConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'group_key',
        'label',
        'under_age',
        'sort_order',
        'default_fee',
        'is_active',
    ];

    protected $casts = [
        'under_age'    => 'integer',
        'sort_order'   => 'integer',
        'default_fee'  => 'float',
        'is_active'    => 'boolean',
    ];
}
