<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataChangeLog extends Model
{
    protected $fillable = [
        'school_id', 'log_name', 'action', 'description',
        'subject_type', 'subject_id', 'causer_user_id',
        'changes', 'properties', 'ip_address',
    ];

    protected $casts = [
        'changes'    => 'array',
        'properties' => 'array',
    ];

    public function subject() { return $this->morphTo(); }
}
