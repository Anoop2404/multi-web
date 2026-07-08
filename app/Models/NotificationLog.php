<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'template_key', 'notifiable_type', 'notifiable_id',
        'to', 'subject', 'status', 'error', 'attempts', 'sent_at',
    ];

    protected $casts = [
        'sent_at'  => 'datetime',
        'attempts' => 'integer',
    ];
}
