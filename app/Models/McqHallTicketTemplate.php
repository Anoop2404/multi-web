<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McqHallTicketTemplate extends Model
{
    protected $fillable = ['tenant_id', 'title', 'design_json', 'is_default', 'is_active'];

    protected $casts = [
        'design_json' => 'array',
        'is_default'  => 'boolean',
        'is_active'   => 'boolean',
    ];
}
