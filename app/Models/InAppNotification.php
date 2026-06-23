<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InAppNotification extends Model
{
    protected $fillable = ["user_id", "title", "body", "action_url", "read_at"];

    protected $casts = [
        'is_active' => 'boolean',
        'is_cascaded' => 'boolean',
        'results_published' => 'boolean',
        'registration_open' => 'date',
        'registration_close' => 'date',
        'event_start' => 'date',
        'event_end' => 'date',
        'submitted_at' => 'datetime',
        'marked_at' => 'datetime',
        'corrected_at' => 'datetime',
        'locked_at' => 'datetime',
        'published_at' => 'datetime',
        'promoted_at' => 'datetime',
        'generated_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'read_at' => 'datetime',
        'fee_amount' => 'decimal:2',
        'score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'config_json' => 'array',
        'dynamic_fields_json' => 'array',
        'ref_data_json' => 'array',
        'answers_json' => 'array',
        'settings_json' => 'array',
        'channels_json' => 'array',
    ];
}
