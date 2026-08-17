<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqProctorEvent extends Model
{
    /** Allowlisted violation types the client is permitted to report. */
    public const TYPES = ['tab_hidden', 'window_blur', 'fullscreen_exit'];

    /** Hard cap on stored events per registration -- a flaky client should never be able to spam the DB. */
    public const MAX_PER_REGISTRATION = 50;

    protected $fillable = [
        'registration_id', 'event_type', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(McqRegistration::class, 'registration_id');
    }
}
