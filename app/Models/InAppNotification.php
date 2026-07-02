<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InAppNotification extends Model
{
    protected $fillable = ["user_id", "title", "body", "action_url", "read_at"];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
