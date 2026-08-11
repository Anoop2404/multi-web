<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedEmailLog extends Model
{
    protected $fillable = [
        'sahodaya_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'mail_type',
        'mail_view',
        'payload',
        'error_message',
        'status',
        'attempts',
        'last_attempted_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
