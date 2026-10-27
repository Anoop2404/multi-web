<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McqAdmitCardExport extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'registration_ids' => 'array',
        'total_count' => 'integer',
        'completed_at' => 'datetime',
    ];
}
