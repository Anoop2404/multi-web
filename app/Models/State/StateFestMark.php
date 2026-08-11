<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wraps the state_fest_marks table (created in migration
 * 2026_07_20_000001_state_fest_tables.php, but nothing modeled/wrote to it until State Event
 * Conduct Phase 4 — docs/STATE_EVENT_CONDUCT_PLAN.md). One row per (registration, participant)
 * — item context comes via registration->item_code, there's no item_id column here directly.
 */
class StateFestMark extends StateModel
{
    protected $table = 'state_fest_marks';

    protected $fillable = [
        'state_event_id', 'registration_id', 'participant_id',
        'score', 'grade', 'position', 'points', 'status', 'entered_by',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function stateEvent(): BelongsTo
    {
        return $this->belongsTo(StateFestEvent::class, 'state_event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(StateFestRegistration::class, 'registration_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(StateFestParticipant::class, 'participant_id');
    }
}
