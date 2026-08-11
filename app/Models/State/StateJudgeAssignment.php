<?php

namespace App\Models\State;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateJudgeAssignment extends StateModel
{
    protected $table = 'state_judge_assignments';

    protected $fillable = ['state_event_id', 'item_id', 'item_code', 'user_id'];

    public function stateEvent(): BelongsTo
    {
        return $this->belongsTo(StateFestEvent::class, 'state_event_id');
    }

    /**
     * User accounts live on the central connection, not the state connection — this
     * relation crosses connections (fine for a simple belongsTo lookup by id; Eloquent
     * doesn't require both sides to share a connection).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
