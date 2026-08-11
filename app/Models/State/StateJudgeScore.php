<?php

namespace App\Models\State;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateJudgeScore extends StateModel
{
    protected $table = 'state_judge_scores';

    protected $fillable = [
        'state_event_id', 'item_id', 'item_code', 'participant_id', 'judge_user_id',
        'score', 'grade', 'notes',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function stateEvent(): BelongsTo
    {
        return $this->belongsTo(StateFestEvent::class, 'state_event_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(StateFestParticipant::class, 'participant_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_user_id');
    }
}
