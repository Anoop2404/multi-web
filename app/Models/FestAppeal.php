<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestAppeal extends Model
{
    protected $fillable = [
        'event_id', 'participant_id', 'reason', 'fee_amount', 'fee_paid_at', 'status',
        'submitted_by_user_id', 'resolved_by_user_id',
        'resolution_note', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'fee_paid_at' => 'datetime',
        'fee_amount'  => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }
}
